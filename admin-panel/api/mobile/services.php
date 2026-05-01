<?php
/**
 * Mobile API - Services & Categories
 * الخدمات والفئات
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/service_areas.php';
require_once __DIR__ . '/../../includes/special_services.php';
require_once __DIR__ . '/../../includes/inspection_pricing.php';

ensureSpecialServicesSchema();
ensureServicesMultilingualSchemaForApi();
serviceAreaEnsureServiceLinksSchema();
inspectionPricingEnsureSchema();

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getCategories();
        break;
    case 'detail':
        getCategoryDetail();
        break;
    case 'problem_types':
        getProblemTypes();
        break;
    case 'featured':
        getFeaturedServices();
        break;
    default:
        sendError('Invalid action', 400);
}

function servicesCoverage(): array
{
    static $coverage = null;
    if ($coverage !== null) {
        return $coverage;
    }

    $country = serviceAreaNormalizeCountryCode($_GET['country_code'] ?? '');
    $lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : null;
    $lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : null;
    $hasCoordinates = $lat !== null && $lng !== null;

    if ($hasCoordinates && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
        sendError('إحداثيات الموقع غير صحيحة', 422);
    }

    $coverage = serviceAreaEvaluateCoverage($country, $lat, $lng);
    return $coverage;
}

function guardServiceCoverageList(): void
{
    $allowOutside = in_array(
        strtolower(trim((string) ($_GET['allow_outside'] ?? $_GET['guest'] ?? ''))),
        ['1', 'true', 'yes', 'on'],
        true
    );
    if ($allowOutside) {
        return;
    }
    $coverage = servicesCoverage();
    if (!($coverage['is_supported'] ?? true)) {
        $message = trim((string) ($coverage['message_ar'] ?? ''));
        if ($message === '') {
            $message = 'أنت خارج نطاق تقديم الخدمة';
        }
        sendSuccess([], $message);
    }
}

function guardServiceCoverageDetail(): void
{
    $allowOutside = in_array(
        strtolower(trim((string) ($_GET['allow_outside'] ?? $_GET['guest'] ?? ''))),
        ['1', 'true', 'yes', 'on'],
        true
    );
    if ($allowOutside) {
        return;
    }
    $coverage = servicesCoverage();
    if (!($coverage['is_supported'] ?? true)) {
        $message = trim((string) ($coverage['message_ar'] ?? ''));
        if ($message === '') {
            $message = 'أنت خارج نطاق تقديم الخدمة';
        }
        sendError($message, 403);
    }
}

function servicesCoverageAreaIds(): array
{
    $coverage = servicesCoverage();
    $matchedArea = $coverage['matched_area'] ?? null;
    if (!is_array($matchedArea)) {
        return [];
    }

    $matchedAreaId = (int) ($matchedArea['id'] ?? 0);
    if ($matchedAreaId <= 0) {
        return [];
    }

    return [$matchedAreaId];
}

function servicesBuildAreaVisibilityFragment(string $serviceAlias, array $areaIds): array
{
    $areaIds = array_values(array_unique(array_filter(array_map('intval', $areaIds), static fn($id) => $id > 0)));
    if (empty($areaIds)) {
        return ['sql' => '1=1', 'types' => '', 'params' => []];
    }

    if (!tableExists('service_area_services')) {
        return ['sql' => '1=1', 'types' => '', 'params' => []];
    }

    $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $serviceAlias);
    if ($safeAlias === '') {
        $safeAlias = 's';
    }

    $placeholders = implode(',', array_fill(0, count($areaIds), '?'));
    $sql = "(NOT EXISTS (
                SELECT 1
                FROM service_area_services sas_any
                WHERE sas_any.service_id = {$safeAlias}.id
            ) OR EXISTS (
                SELECT 1
                FROM service_area_services sas_match
                WHERE sas_match.service_id = {$safeAlias}.id
                  AND sas_match.service_area_id IN ({$placeholders})
            ))";

    return [
        'sql' => $sql,
        'types' => str_repeat('i', count($areaIds)),
        'params' => $areaIds,
    ];
}

function servicesResolveVisibleCategoryIds(array $areaIds): array
{
    global $conn;

    if (!tableExists('services')) {
        return [];
    }

    $visibility = servicesBuildAreaVisibilityFragment('s', $areaIds);
    $sql = "SELECT DISTINCT s.category_id
            FROM services s
            WHERE s.is_active = 1
              AND s.category_id IS NOT NULL
              AND {$visibility['sql']}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($visibility['types'] !== '') {
        $stmt->bind_param($visibility['types'], ...$visibility['params']);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $categoryId = (int) ($row['category_id'] ?? 0);
        if ($categoryId > 0) {
            $ids[$categoryId] = $categoryId;
        }
    }

    return array_values($ids);
}

function ensureServicesMultilingualSchemaForApi(): void
{
    global $conn;
    static $done = false;

    if ($done) {
        return;
    }
    $done = true;

    if (!tableExists('services')) {
        return;
    }

    if (!tableColumnExists('services', 'name_ur')) {
        $conn->query("ALTER TABLE `services` ADD COLUMN `name_ur` VARCHAR(100) NULL AFTER `name_en`");
    }
    if (!tableColumnExists('services', 'description_ur')) {
        $conn->query("ALTER TABLE `services` ADD COLUMN `description_ur` TEXT NULL AFTER `description_en`");
    }
}

/**
 * Get all service categories (main categories + nested sub-categories).
 */
function getCategories()
{
    global $conn;
    guardServiceCoverageList();
    $areaIds = servicesCoverageAreaIds();
    $useAreaFilter = !empty($areaIds);
    $visibleCategoryIds = $useAreaFilter ? servicesResolveVisibleCategoryIds($areaIds) : [];
    $visibleCategorySet = [];
    foreach ($visibleCategoryIds as $visibleCategoryId) {
        $visibleCategorySet[(int) $visibleCategoryId] = true;
    }

    $hasHierarchy = serviceCategoriesHasParentColumn();
    if ($hasHierarchy) {
        $sql = "SELECT * FROM service_categories
                WHERE is_active = 1
                ORDER BY COALESCE(parent_id, id) ASC,
                         CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC,
                         sort_order ASC,
                         id ASC";
    } else {
        $sql = "SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendError('Failed to prepare categories query', 500);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$hasHierarchy) {
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $item = mapCategory($row);
            if ($useAreaFilter && empty($visibleCategorySet[$item['id']])) {
                continue;
            }
            $item['sub_categories'] = [];
            $categories[] = $item;
        }
        $categories = array_merge($categories, getSpecialServiceCategories());
        $categories = deduplicateServiceCategoriesForApi($categories);
        sendSuccess($categories);
    }

    $mainCategories = [];
    $childrenByParent = [];
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if ($useAreaFilter && !empty($rows)) {
        $parentMap = [];
        foreach ($rows as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }
            $parentId = 0;
            if (array_key_exists('parent_id', $row) && $row['parent_id'] !== null && $row['parent_id'] !== '') {
                $parentId = (int) $row['parent_id'];
            }
            $parentMap[$rowId] = $parentId;
        }

        $keepSet = $visibleCategorySet;
        foreach (array_keys($visibleCategorySet) as $categoryId) {
            $cursor = $categoryId;
            $guard = 0;
            while (isset($parentMap[$cursor]) && $parentMap[$cursor] > 0 && $guard < 10) {
                $parentId = (int) $parentMap[$cursor];
                if ($parentId <= 0) {
                    break;
                }
                $keepSet[$parentId] = true;
                $cursor = $parentId;
                $guard++;
            }
        }

        $visibleCategorySet = $keepSet;
    }

    foreach ($rows as $row) {
        $item = mapCategory($row);
        if ($useAreaFilter && empty($visibleCategorySet[$item['id']])) {
            continue;
        }
        $item['sub_categories'] = [];

        $parentId = $item['parent_id'];
        if ($parentId) {
            if (!isset($childrenByParent[$parentId])) {
                $childrenByParent[$parentId] = [];
            }
            $childrenByParent[$parentId][] = $item;
            continue;
        }

        $mainCategories[] = $item;
    }

    foreach ($mainCategories as &$category) {
        $category['sub_categories'] = $childrenByParent[$category['id']] ?? [];
    }
    unset($category);

    $mainCategories = array_merge($mainCategories, getSpecialServiceCategories());
    $mainCategories = deduplicateServiceCategoriesForApi($mainCategories);

    sendSuccess($mainCategories);
}

/**
 * Get category detail with services + nested sub-categories.
 */
function getCategoryDetail()
{
    global $conn;
    guardServiceCoverageDetail();
    $areaIds = servicesCoverageAreaIds();
    $visibility = servicesBuildAreaVisibilityFragment('s', $areaIds);

    $id = (int) ($_GET['id'] ?? 0);
    if ($id === -101 || $id === -102) {
        getSpecialCategoryDetail($id);
    }

    if ($id <= 0) {
        sendError('Category id is required', 422);
    }

    $stmt = $conn->prepare("SELECT * FROM service_categories WHERE id = ? AND is_active = 1");
    if (!$stmt) {
        sendError('Failed to prepare category query', 500);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();

    if (!$category) {
        sendError('Category not found', 404);
    }

    $subCategories = [];
    if (serviceCategoriesHasParentColumn()) {
        $subStmt = $conn->prepare("SELECT * FROM service_categories
                                   WHERE parent_id = ? AND is_active = 1
                                   ORDER BY sort_order ASC, id ASC");
        if ($subStmt) {
            $subStmt->bind_param('i', $id);
            $subStmt->execute();
            $subResult = $subStmt->get_result();
            while ($subRow = $subResult->fetch_assoc()) {
                $item = mapCategory($subRow);
                $item['sub_categories'] = [];
                $subCategories[] = $item;
            }
        }
    }

    $subServices = [];
    if (tableExists('services')) {
        $servicesSql = "SELECT s.id, s.category_id, s.name_ar, s.name_en, s.name_ur, s.description_ar, s.description_en, s.description_ur, s.price, s.image,
                               s.inspection_pricing_mode, s.inspection_fee, s.inspection_details_ar, s.inspection_details_en, s.inspection_details_ur
                        FROM services s
                        WHERE s.category_id = ? AND s.is_active = 1 AND {$visibility['sql']}
                        ORDER BY s.id ASC";
        $servicesStmt = $conn->prepare($servicesSql);
        if ($servicesStmt) {
            $bindTypes = 'i' . $visibility['types'];
            $bindParams = array_merge([$id], $visibility['params']);
            $servicesStmt->bind_param($bindTypes, ...$bindParams);
            $servicesStmt->execute();
            $servicesResult = $servicesStmt->get_result();
            while ($service = $servicesResult->fetch_assoc()) {
                $subServices[] = [
                    'id' => (int) $service['id'],
                    'category_id' => (int) ($service['category_id'] ?? $id),
                    'name_ar' => $service['name_ar'],
                    'name_en' => $service['name_en'],
                    'name_ur' => $service['name_ur'] ?? ($service['name_en'] ?? ($service['name_ar'] ?? '')),
                    'description_ar' => $service['description_ar'],
                    'description_en' => $service['description_en'],
                    'description_ur' => $service['description_ur'] ?? ($service['description_en'] ?? ($service['description_ar'] ?? '')),
                    'description' => !empty($service['description_ar'])
                        ? $service['description_ar']
                        : (!empty($service['description_ur']) ? $service['description_ur'] : ($service['description_en'] ?? '')),
	                    'price' => (float) ($service['price'] ?? 0),
	                    'image' => $service['image'] ?? null,
	                    'inspection_pricing_mode' => inspectionPricingNormalizeMode($service['inspection_pricing_mode'] ?? 'inherit', true),
	                    'inspection_fee' => inspectionPricingNormalizeFee($service['inspection_fee'] ?? 0),
	                    'inspection_details_ar' => $service['inspection_details_ar'] ?? '',
	                    'inspection_details_en' => $service['inspection_details_en'] ?? '',
	                    'inspection_details_ur' => $service['inspection_details_ur'] ?? ''
	                ];
            }
        }
    }

    $response = mapCategory($category);
    $response['sub_categories'] = $subCategories;
    $response['sub_services'] = $subServices;

    sendSuccess($response);
}

/**
 * Get problem detail options for a category and optional selected service types.
 */
function getProblemTypes()
{
    global $conn;
    guardServiceCoverageList();

    $categoryId = (int) ($_GET['category_id'] ?? 0);
    if ($categoryId < 0) {
        sendSuccess([]);
    }
    if ($categoryId <= 0) {
        sendError('category_id is required', 422);
    }

    if (!tableExists('problem_detail_options')) {
        sendSuccess([]);
    }

    $serviceIds = parseIds($_GET['service_ids'] ?? '');

    $sql = "SELECT p.id, p.title_ar, p.title_en, p.title_ur, p.category_id, p.service_id,
                   s.name_ar AS service_name
            FROM problem_detail_options p
            LEFT JOIN services s ON p.service_id = s.id
            WHERE p.is_active = 1 AND p.category_id = ?";

    $params = [$categoryId];
    $types = 'i';

    if (!empty($serviceIds)) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $sql .= " AND (p.service_id IS NULL OR p.service_id IN ($placeholders))";
        foreach ($serviceIds as $serviceId) {
            $types .= 'i';
            $params[] = $serviceId;
        }
    }

    $sql .= " ORDER BY COALESCE(p.sort_order, 0) ASC, p.id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendError('Failed to prepare query', 500);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'title_ar' => $row['title_ar'],
            'title_en' => $row['title_en'],
            'title_ur' => $row['title_ur'] ?? ($row['title_en'] ?? ($row['title_ar'] ?? '')),
            'category_id' => (int) $row['category_id'],
            'service_id' => !empty($row['service_id']) ? (int) $row['service_id'] : null,
            'service_name' => $row['service_name'] ?? null,
        ];
    }

    sendSuccess($rows);
}

/**
 * Get featured services (admin controlled for "Most Requested")
 */
function getFeaturedServices()
{
    global $conn;
    guardServiceCoverageList();
    $areaIds = servicesCoverageAreaIds();
    $visibility = servicesBuildAreaVisibilityFragment('s', $areaIds);

    if (!tableExists('services')) {
        sendSuccess([]);
    }

    $limit = (int) ($_GET['limit'] ?? 0);
    $useLimit = $limit > 0;
    if ($limit > 100) {
        $limit = 100;
    }

    $featuredDemand = buildFeaturedServicesDemandFragments();
    $featuredFilter = tableColumnExists('services', 'is_featured') ? ' AND s.is_featured = 1' : '';
    $ratingExpr = tableColumnExists('services', 'rating') ? "COALESCE(s.rating, 0)" : "0";
    $categoryNameUrExpr = tableColumnExists('service_categories', 'name_ur')
        ? 'c.name_ur AS category_name_ur'
        : 'c.name_en AS category_name_ur';

	    $sql = "SELECT s.id, s.category_id, s.name_ar, s.name_en, s.name_ur, s.description_ar, s.description_en, s.description_ur,
	                   s.image, s.price, s.inspection_pricing_mode, s.inspection_fee, s.inspection_details_ar, s.inspection_details_en, s.inspection_details_ur,
	                   {$featuredDemand['requests_expr']} AS requests_count,
                   {$ratingExpr} AS rating,
	                   c.name_ar AS category_name_ar, c.name_en AS category_name_en, {$categoryNameUrExpr},
	                   c.inspection_pricing_mode AS category_inspection_pricing_mode,
	                   c.inspection_fee AS category_inspection_fee,
	                   c.inspection_details_ar AS category_inspection_details_ar,
	                   c.inspection_details_en AS category_inspection_details_en,
	                   c.inspection_details_ur AS category_inspection_details_ur
            FROM services s
            LEFT JOIN service_categories c ON s.category_id = c.id
            {$featuredDemand['joins_sql']}
            WHERE s.is_active = 1{$featuredFilter}
              AND {$visibility['sql']}
            ORDER BY requests_count DESC, rating DESC, s.id DESC";

    if ($useLimit) {
        $sql .= " LIMIT ?";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendError('Failed to prepare query', 500);
    }

    $bindTypes = $visibility['types'];
    $bindParams = $visibility['params'];
    if ($useLimit) {
        $bindTypes .= 'i';
        $bindParams[] = $limit;
    }
    if ($bindTypes !== '') {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $categoryInfo = getServicesCategoryDisplayInfo(
            (int) ($row['category_id'] ?? 0),
            $row['category_name_ar'] ?? '',
            $row['category_name_en'] ?? '',
            $row['category_name_ur'] ?? ''
        );
        $rows[] = [
            'id' => (int) $row['id'],
            'category_id' => (int) ($row['category_id'] ?? 0),
            'name_ar' => $row['name_ar'] ?? '',
            'name_en' => $row['name_en'] ?? '',
            'name_ur' => $row['name_ur'] ?? ($row['name_en'] ?? ($row['name_ar'] ?? '')),
            'description_ar' => $row['description_ar'] ?? '',
            'description_en' => $row['description_en'] ?? '',
            'description_ur' => $row['description_ur'] ?? ($row['description_en'] ?? ($row['description_ar'] ?? '')),
	            'category_name_ar' => $categoryInfo['name_ar'],
	            'category_name_en' => $categoryInfo['name_en'],
	            'category_name_ur' => $categoryInfo['name_ur'],
	            'category_inspection_pricing_mode' => inspectionPricingNormalizeMode($row['category_inspection_pricing_mode'] ?? 'free', false),
	            'category_inspection_fee' => inspectionPricingNormalizeFee($row['category_inspection_fee'] ?? 0),
	            'category_inspection_details_ar' => $row['category_inspection_details_ar'] ?? '',
	            'category_inspection_details_en' => $row['category_inspection_details_en'] ?? '',
	            'category_inspection_details_ur' => $row['category_inspection_details_ur'] ?? '',
	            'image' => isset($row['image']) ? imageUrl($row['image']) : null,
	            'price' => (float) ($row['price'] ?? 0),
	            'inspection_pricing_mode' => inspectionPricingNormalizeMode($row['inspection_pricing_mode'] ?? 'inherit', true),
	            'inspection_fee' => inspectionPricingNormalizeFee($row['inspection_fee'] ?? 0),
	            'inspection_details_ar' => $row['inspection_details_ar'] ?? '',
	            'inspection_details_en' => $row['inspection_details_en'] ?? '',
	            'inspection_details_ur' => $row['inspection_details_ur'] ?? '',
	            'requests_count' => (int) ($row['requests_count'] ?? 0),
            'rating' => (float) ($row['rating'] ?? 0),
        ];
    }

    sendSuccess($rows);
}

function mapCategory(array $row): array
{
    $parentId = null;
    if (array_key_exists('parent_id', $row) && $row['parent_id'] !== null && $row['parent_id'] !== '') {
        $parentId = (int) $row['parent_id'];
    }
    $nameAr = $row['name_ar'] ?? '';
    $nameEn = $row['name_en'] ?? '';

    return [
        'id' => (int) ($row['id'] ?? 0),
        'parent_id' => $parentId,
        'name_ar' => $nameAr,
        'name_en' => $nameEn,
        'name_ur' => $row['name_ur'] ?? (($nameEn ?? '') !== '' ? $nameEn : $nameAr),
        'icon' => serviceCategoryIconForApi($row['icon'] ?? null, $nameAr, $nameEn),
        'image' => serviceCategoryImageForApi($row['image'] ?? null),
	        'special_module' => $row['special_module'] ?? null,
	        'warranty_days' => isset($row['warranty_days']) ? (int) $row['warranty_days'] : 14,
	        'inspection_pricing_mode' => inspectionPricingNormalizeMode($row['inspection_pricing_mode'] ?? 'free', false),
	        'inspection_fee' => inspectionPricingNormalizeFee($row['inspection_fee'] ?? 0),
	        'inspection_details_ar' => $row['inspection_details_ar'] ?? '',
	        'inspection_details_en' => $row['inspection_details_en'] ?? '',
	        'inspection_details_ur' => $row['inspection_details_ur'] ?? '',
	        'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
    ];
}

function getSpecialServiceCategories(): array
{
    $categories = [];

    if (tableExists('furniture_services')) {
        $furnitureCount = (int) db()->count('furniture_services', 'is_active = 1');
        if ($furnitureCount > 0) {
            $meta = specialServiceCategoryDisplayMeta('furniture');
            $categories[] = [
                'id' => $meta['id'] ?? -101,
                'parent_id' => null,
                'name_ar' => $meta['name_ar'] ?? 'نقل العفش',
                'name_en' => $meta['name_en'] ?? 'Furniture Moving',
                'name_ur' => $meta['name_ur'] ?? 'فرنیچر کی منتقلی',
                'icon' => $meta['icon'] ?? '🚚',
                'image' => $meta['image'] ?? null,
                'special_module' => $meta['special_module'] ?? 'furniture_moving',
                'warranty_days' => $meta['warranty_days'] ?? 0,
                'sort_order' => $meta['sort_order'] ?? 9001,
                'sub_categories' => [],
            ];
        }
    }

    if (tableExists('container_services')) {
        $containerCount = (int) db()->count('container_services', 'is_active = 1');
        if ($containerCount > 0) {
            $meta = specialServiceCategoryDisplayMeta('container');
            $categories[] = [
                'id' => $meta['id'] ?? -102,
                'parent_id' => null,
                'name_ar' => $meta['name_ar'] ?? 'الحاويات',
                'name_en' => $meta['name_en'] ?? 'Containers',
                'name_ur' => $meta['name_ur'] ?? 'کنٹینرز',
                'icon' => $meta['icon'] ?? '📦',
                'image' => $meta['image'] ?? null,
                'special_module' => $meta['special_module'] ?? 'container_rental',
                'warranty_days' => $meta['warranty_days'] ?? 0,
                'sort_order' => $meta['sort_order'] ?? 9002,
                'sub_categories' => [],
            ];
        }
    }

    return $categories;
}

function getSpecialCategoryDetail(int $id): void
{
    if ($id === -101) {
        $meta = specialServiceCategoryDisplayMeta('furniture');
        $subServices = [];
        if (tableExists('furniture_services')) {
            $rows = db()->fetchAll(
                "SELECT id, name_ar, name_en, name_ur, description_ar, description_en, description_ur, base_price, price_per_kg, price_per_meter, minimum_charge, image, estimated_duration_hours, price_note
                 FROM furniture_services
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC"
            );
            foreach ($rows as $row) {
                $subServices[] = [
                    'id' => (int) $row['id'],
                    'category_id' => -101,
                    'name_ar' => $row['name_ar'] ?? '',
                    'name_en' => $row['name_en'] ?? '',
                    'name_ur' => $row['name_ur'] ?? (($row['name_en'] ?? '') !== '' ? $row['name_en'] : ($row['name_ar'] ?? '')),
                    'description_ar' => $row['description_ar'] ?? '',
                    'description_en' => $row['description_en'] ?? '',
                    'description_ur' => $row['description_ur'] ?? (($row['description_en'] ?? '') !== '' ? $row['description_en'] : ($row['description_ar'] ?? '')),
                    'description' => !empty($row['description_ar'])
                        ? $row['description_ar']
                        : (!empty($row['description_ur']) ? $row['description_ur'] : ($row['description_en'] ?? '')),
                    'price' => (float) ($row['base_price'] ?? 0),
                    'base_price' => (float) ($row['base_price'] ?? 0),
                    'price_per_kg' => (float) ($row['price_per_kg'] ?? 0),
                    'price_per_meter' => (float) ($row['price_per_meter'] ?? 0),
                    'minimum_charge' => (float) ($row['minimum_charge'] ?? 0),
                    'image' => !empty($row['image']) ? imageUrl($row['image']) : null,
                    'estimated_duration_hours' => (float) ($row['estimated_duration_hours'] ?? 0),
                    'price_note' => $row['price_note'] ?? null,
                ];
            }
        }

        sendSuccess([
            'id' => $meta['id'] ?? -101,
            'parent_id' => null,
            'name_ar' => $meta['name_ar'] ?? 'نقل العفش',
            'name_en' => $meta['name_en'] ?? 'Furniture Moving',
            'name_ur' => $meta['name_ur'] ?? 'فرنیچر کی منتقلی',
            'icon' => $meta['icon'] ?? '🚚',
            'image' => $meta['image'] ?? null,
            'special_module' => $meta['special_module'] ?? 'furniture_moving',
            'warranty_days' => $meta['warranty_days'] ?? 0,
            'sort_order' => $meta['sort_order'] ?? 9001,
            'sub_categories' => [],
            'sub_services' => $subServices,
            'request_flow' => [
                'requires_area' => true,
                'requires_custom_fields' => true,
            ],
        ]);
    }

    if ($id === -102) {
        $meta = specialServiceCategoryDisplayMeta('container');
        $subServices = [];
        if (tableExists('container_services')) {
            $rows = db()->fetchAll(
                "SELECT cs.id, cs.name_ar, cs.name_en, cs.name_ur, cs.description_ar, cs.description_en, cs.description_ur,
                        cs.container_size, cs.capacity_ton, cs.store_id,
                        cs.daily_price, cs.weekly_price, cs.monthly_price, cs.delivery_fee, cs.price_per_kg, cs.price_per_meter,
                        cs.minimum_charge, cs.image, cst.name_ar AS store_name_ar
                 FROM container_services cs
                 LEFT JOIN container_stores cst ON cst.id = cs.store_id
                 WHERE cs.is_active = 1
                 ORDER BY cs.sort_order ASC, cs.id ASC"
            );
            foreach ($rows as $row) {
                $size = trim((string) ($row['container_size'] ?? ''));
                $capacity = (float) ($row['capacity_ton'] ?? 0);
                $descAr = trim((string) ($row['description_ar'] ?? ''));
                if ($descAr === '' && $size !== '') {
                    $descAr = 'المقاس: ' . $size . ($capacity > 0 ? ' - السعة: ' . $capacity . ' طن' : '');
                }
                $descUr = trim((string) ($row['description_ur'] ?? ''));
                $subServices[] = [
                    'id' => (int) $row['id'],
                    'category_id' => -102,
                    'name_ar' => $row['name_ar'] ?? '',
                    'name_en' => $row['name_en'] ?? '',
                    'name_ur' => $row['name_ur'] ?? (($row['name_en'] ?? '') !== '' ? $row['name_en'] : ($row['name_ar'] ?? '')),
                    'description_ar' => $descAr,
                    'description_en' => $row['description_en'] ?? '',
                    'description_ur' => $descUr !== '' ? $descUr : (($row['description_en'] ?? '') !== '' ? $row['description_en'] : $descAr),
                    'description' => $descAr !== '' ? $descAr : ($descUr !== '' ? $descUr : ($row['description_en'] ?? '')),
                    'price' => (float) ($row['daily_price'] ?? 0),
                    'daily_price' => (float) ($row['daily_price'] ?? 0),
                    'weekly_price' => (float) ($row['weekly_price'] ?? 0),
                    'monthly_price' => (float) ($row['monthly_price'] ?? 0),
                    'delivery_fee' => (float) ($row['delivery_fee'] ?? 0),
                    'price_per_kg' => (float) ($row['price_per_kg'] ?? 0),
                    'price_per_meter' => (float) ($row['price_per_meter'] ?? 0),
                    'minimum_charge' => (float) ($row['minimum_charge'] ?? 0),
                    'image' => !empty($row['image']) ? imageUrl($row['image']) : null,
                    'container_size' => $size,
                    'capacity_ton' => $capacity,
                    'container_store_id' => (int) ($row['store_id'] ?? 0),
                    'container_store_name' => trim((string) ($row['store_name_ar'] ?? '')),
                ];
            }
        }

        sendSuccess([
            'id' => $meta['id'] ?? -102,
            'parent_id' => null,
            'name_ar' => $meta['name_ar'] ?? 'الحاويات',
            'name_en' => $meta['name_en'] ?? 'Containers',
            'name_ur' => $meta['name_ur'] ?? 'کنٹینرز',
            'icon' => $meta['icon'] ?? '📦',
            'image' => $meta['image'] ?? null,
            'special_module' => $meta['special_module'] ?? 'container_rental',
            'warranty_days' => $meta['warranty_days'] ?? 0,
            'sort_order' => $meta['sort_order'] ?? 9002,
            'sub_categories' => [],
            'sub_services' => $subServices,
            'request_flow' => [
                'requires_area' => false,
                'requires_custom_fields' => false,
            ],
        ]);
    }

    sendError('Category not found', 404);
}

function parseIds($value)
{
    $items = explode(',', (string) $value);
    $ids = [];
    foreach ($items as $item) {
        $id = (int) trim($item);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function tableExists($table)
{
    global $conn;

    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }

    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function tableColumnExists($table, $column)
{
    global $conn;

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function buildFeaturedServicesDemandFragments()
{
    $joins = [];
    $requestSources = [];

    $hasOrders = tableExists('orders');
    $hasStatusColumn = $hasOrders && tableColumnExists('orders', 'status');
    $ordersStatusFilter = $hasStatusColumn ? " AND o.status NOT IN ('cancelled', 'rejected')" : '';
    $ordersStatusFilterNoAlias = $hasStatusColumn ? " AND status NOT IN ('cancelled', 'rejected')" : '';

    if (
        $hasOrders
        && tableExists('order_services')
        && tableColumnExists('order_services', 'service_id')
        && tableColumnExists('order_services', 'order_id')
    ) {
        $joins[] = "LEFT JOIN (
                        SELECT os.service_id, COUNT(DISTINCT os.order_id) AS requests_count
                        FROM order_services os
                        INNER JOIN orders o ON o.id = os.order_id
                        WHERE os.service_id IS NOT NULL{$ordersStatusFilter}
                        GROUP BY os.service_id
                    ) service_order_counts ON service_order_counts.service_id = s.id";
        $requestSources[] = 'service_order_counts.requests_count';
    }

    if ($hasOrders && tableColumnExists('orders', 'service_id')) {
        $joins[] = "LEFT JOIN (
                        SELECT o.service_id, COUNT(DISTINCT o.id) AS requests_count
                        FROM orders o
                        WHERE o.service_id IS NOT NULL{$ordersStatusFilter}
                        GROUP BY o.service_id
                    ) direct_service_order_counts ON direct_service_order_counts.service_id = s.id";
        $requestSources[] = 'direct_service_order_counts.requests_count';
    }

    if ($hasOrders && tableColumnExists('orders', 'category_id')) {
        $joins[] = "LEFT JOIN (
                        SELECT category_id, COUNT(*) AS requests_count
                        FROM orders
                        WHERE category_id IS NOT NULL{$ordersStatusFilterNoAlias}
                        GROUP BY category_id
                    ) category_order_counts ON category_order_counts.category_id = s.category_id";
        $requestSources[] = 'category_order_counts.requests_count';
    }

    $requestsExpr = empty($requestSources)
        ? '0'
        : 'COALESCE(' . implode(', ', $requestSources) . ', 0)';

    return [
        'joins_sql' => implode("\n", $joins),
        'requests_expr' => $requestsExpr,
    ];
}

function serviceCategoriesHasParentColumn(): bool
{
    static $hasParentColumn = null;

    if ($hasParentColumn !== null) {
        return $hasParentColumn;
    }

    global $conn;

    $result = $conn->query("SHOW COLUMNS FROM service_categories LIKE 'parent_id'");
    $hasParentColumn = $result && $result->num_rows > 0;

    return $hasParentColumn;
}

function getServicesCategoryDisplayInfo($categoryId, $fallbackAr = '', $fallbackEn = '', $fallbackUr = '')
{
    $resolvedFallbackUr = $fallbackUr !== '' ? $fallbackUr : ($fallbackEn !== '' ? $fallbackEn : $fallbackAr);

    if ($categoryId <= 0) {
        return [
            'name_ar' => $fallbackAr,
            'name_en' => $fallbackEn,
            'name_ur' => $resolvedFallbackUr,
        ];
    }

    $map = getServicesCategoryDisplayMap();
    if (isset($map[$categoryId])) {
        return $map[$categoryId];
    }

    return [
        'name_ar' => $fallbackAr,
        'name_en' => $fallbackEn,
        'name_ur' => $resolvedFallbackUr,
    ];
}

function getServicesCategoryDisplayMap()
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    global $conn;
    $map = [];

    $hasCategoryUr = tableColumnExists('service_categories', 'name_ur');
    $categoryUrSelect = $hasCategoryUr ? 'c.name_ur' : 'c.name_en AS name_ur';
    $parentUrSelect = $hasCategoryUr ? 'p.name_ur AS parent_name_ur' : 'p.name_en AS parent_name_ur';

    if (serviceCategoriesHasParentColumn()) {
        $query = "SELECT c.id, c.name_ar, c.name_en, {$categoryUrSelect}, p.name_ar AS parent_name_ar, p.name_en AS parent_name_en, {$parentUrSelect}
                  FROM service_categories c
                  LEFT JOIN service_categories p ON p.id = c.parent_id";
    } else {
        $query = "SELECT c.id, c.name_ar, c.name_en, {$categoryUrSelect}, NULL AS parent_name_ar, NULL AS parent_name_en, NULL AS parent_name_ur
                  FROM service_categories c";
    }

    $result = $conn->query($query);
    if (!$result) {
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $nameAr = $row['name_ar'] ?? '';
        $nameEn = $row['name_en'] ?? '';
        $nameUr = $row['name_ur'] ?? ($nameEn !== '' ? $nameEn : $nameAr);
        if (!empty($row['parent_name_ar'])) {
            $nameAr = $row['parent_name_ar'] . ' > ' . $nameAr;
        }
        if (!empty($row['parent_name_en']) && $nameEn !== '') {
            $nameEn = $row['parent_name_en'] . ' > ' . $nameEn;
        }
        if (!empty($row['parent_name_ur']) && $nameUr !== '') {
            $nameUr = $row['parent_name_ur'] . ' > ' . $nameUr;
        }
        $map[(int) $row['id']] = [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'name_ur' => $nameUr !== '' ? $nameUr : ($nameEn !== '' ? $nameEn : $nameAr),
        ];
    }

    return $map;
}
