<?php
/**
 * Mobile API - Spare Parts
 * جميع قطع الغيار
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
require_once __DIR__ . '/../../includes/service_areas.php';
require_once __DIR__ . '/../../includes/spare_parts_scope.php';

serviceAreaEnsureServiceLinksSchema();
sparePartScopeEnsureSchema();

getSpareParts();

function sparePartsAllowOutsideBrowsing(): bool
{
    return in_array(
        strtolower(trim((string) ($_GET['allow_outside'] ?? $_GET['guest'] ?? ''))),
        ['1', 'true', 'yes', 'on'],
        true
    );
}

function getSpareParts()
{
    global $conn;

    ensureSparePartsPricingSchema();
    ensureSparePartsCategorySchema();
    ensureSparePartsWarrantySchema();
    ensureStoresGeoSchema();

    $requestedCategoryId = (int) ($_GET['category_id'] ?? $_GET['service_category_id'] ?? 0);
    $resolvedCategoryIds = resolveSparePartCategoryScope($requestedCategoryId);
    if ($requestedCategoryId > 0 && empty($resolvedCategoryIds)) {
        sendSuccess([]);
    }

    $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
    $lng = isset($_GET['lng']) ? (float) $_GET['lng'] : null;
    $hasLat = isset($_GET['lat']) && $_GET['lat'] !== '';
    $hasLng = isset($_GET['lng']) && $_GET['lng'] !== '';
    $hasCoordinates = $hasLat && $hasLng;

    if (($hasLat xor $hasLng) === true) {
        sendError('lat و lng يجب إرسالهما معًا', 422);
    }

    if ($hasCoordinates && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
        sendError('إحداثيات الموقع غير صحيحة', 422);
    }

    $countryCode = serviceAreaNormalizeCountryCode($_GET['country_code'] ?? '');
    $coverage = serviceAreaEvaluateCoverage($countryCode, $lat, $lng);
    $allowOutside = sparePartsAllowOutsideBrowsing();
    if (!$allowOutside && !($coverage['is_supported'] ?? true)) {
        $message = trim((string) ($coverage['message_ar'] ?? ''));
        if ($message === '') {
            $message = 'أنت خارج نطاق تقديم الخدمة';
        }
        sendSuccess([], $message);
    }

    $coverageAreaIds = $allowOutside ? [] : sparePartScopeExtractCoverageAreaIds($coverage);
    $requestedServiceIds = normalizeRequestedSparePartServiceIds();
    $visibility = sparePartScopeBuildVisibilityFragment('sp', $coverageAreaIds, $requestedServiceIds);

    $distanceSelect = '';
    $orderBy = 'sp.sort_order ASC, sp.id DESC';
    $types = '';
    $params = [];

    if ($hasCoordinates) {
        // Haversine distance in KM to return nearest stores first.
        $distanceSelect = ",
            CASE
                WHEN s.lat IS NOT NULL AND s.lng IS NOT NULL THEN (
                    6371 * ACOS(
                        LEAST(
                            1,
                            GREATEST(
                                -1,
                                COS(RADIANS(?)) * COS(RADIANS(s.lat)) * COS(RADIANS(s.lng) - RADIANS(?))
                                + SIN(RADIANS(?)) * SIN(RADIANS(s.lat))
                            )
                        )
                    )
                )
                ELSE NULL
            END AS distance_km";
        $orderBy = "CASE WHEN s.lat IS NULL OR s.lng IS NULL THEN 1 ELSE 0 END ASC,
                    distance_km ASC,
                    sp.sort_order ASC,
                    sp.id DESC";
        $types = 'ddd';
        $params = [$lat, $lng, $lat];
    }

    $sql = "SELECT sp.*, s.name_ar AS store_name, s.lat AS store_lat, s.lng AS store_lng,
                   c.name_ar AS category_name_ar, c.name_en AS category_name_en {$distanceSelect}
            FROM spare_parts sp
            LEFT JOIN stores s ON sp.store_id = s.id
            LEFT JOIN service_categories c ON sp.category_id = c.id
            WHERE sp.is_active = 1
    ";
    if (!empty($resolvedCategoryIds)) {
        $placeholders = implode(',', array_fill(0, count($resolvedCategoryIds), '?'));
        $sql .= " AND sp.category_id IN ({$placeholders})";
        $types .= str_repeat('i', count($resolvedCategoryIds));
        foreach ($resolvedCategoryIds as $categoryId) {
            $params[] = (int) $categoryId;
        }
    }
    if ($visibility['sql'] !== '1=1') {
        $sql .= " AND {$visibility['sql']}";
        $types .= $visibility['types'];
        $params = array_merge($params, $visibility['params']);
    }
    $sql .= " ORDER BY {$orderBy}";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendError('Failed to load spare parts', 500);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $partIds = [];
    foreach ($rows as $row) {
        $partId = (int) ($row['id'] ?? 0);
        if ($partId > 0) {
            $partIds[] = $partId;
        }
    }
    $linkedServicesByPart = loadSparePartLinkedServices($partIds);

    $parts = [];
    foreach ($rows as $row) {
        $partId = (int) ($row['id'] ?? 0);
        $linkedServices = $linkedServicesByPart[$partId] ?? [];
        $serviceIds = [];
        $serviceNamesAr = [];
        $serviceNamesEn = [];
        foreach ($linkedServices as $service) {
            $serviceId = (int) ($service['id'] ?? 0);
            if ($serviceId > 0) {
                $serviceIds[] = $serviceId;
            }
            $serviceNameAr = trim((string) ($service['name_ar'] ?? ''));
            if ($serviceNameAr !== '') {
                $serviceNamesAr[] = $serviceNameAr;
            }
            $serviceNameEn = trim((string) ($service['name_en'] ?? ''));
            if ($serviceNameEn !== '') {
                $serviceNamesEn[] = $serviceNameEn;
            }
        }

        $stockQuantity = isset($row['stock_quantity']) ? (int) $row['stock_quantity'] : 0;
        $priceWithInstallation = isset($row['price_with_installation']) && (float) $row['price_with_installation'] > 0
            ? (float) $row['price_with_installation']
            : (float) ($row['price'] ?? 0);
        $priceWithoutInstallation = isset($row['price_without_installation']) && (float) $row['price_without_installation'] > 0
            ? (float) $row['price_without_installation']
            : $priceWithInstallation;
        $oldPriceWithInstallation = isset($row['old_price_with_installation']) && (float) $row['old_price_with_installation'] > 0
            ? (float) $row['old_price_with_installation']
            : ((isset($row['old_price']) && (float) $row['old_price'] > 0) ? (float) $row['old_price'] : null);
        $oldPriceWithoutInstallation = isset($row['old_price_without_installation']) && (float) $row['old_price_without_installation'] > 0
            ? (float) $row['old_price_without_installation']
            : $oldPriceWithInstallation;

        // Keep legacy price fields mapped to "with installation".
        $price = $priceWithInstallation;
        $oldPrice = $oldPriceWithInstallation;

        // Calculate discount if old_price exists
        $discount = 0;
        if ($oldPrice && $oldPrice > $price) {
            $discount = round((($oldPrice - $price) / $oldPrice) * 100);
        }

        $rawCategoryName = trim((string) ($row['category_name_ar'] ?? ''));
        if ($rawCategoryName === '') {
            $rawCategoryName = trim((string) ($row['category_name_en'] ?? ''));
        }
        $legacyCategory = $rawCategoryName !== '' ? guessCategory($rawCategoryName) : guessCategory($row['name_ar'] ?? '');
        $warrantyDuration = trim((string) ($row['warranty_duration'] ?? ''));
        if ($warrantyDuration === '') {
            $warrantyDuration = 'سنة';
        }
        $warrantyTerms = trim((string) ($row['warranty_terms'] ?? ''));

        $parts[] = [
            'id' => $partId,
            'store_id' => !empty($row['store_id']) ? (int) $row['store_id'] : null,
            'store_name' => $row['store_name'] ?? null,
            'category_id' => !empty($row['category_id']) ? (int) $row['category_id'] : null,
            'category_name_ar' => $row['category_name_ar'] ?? '',
            'category_name_en' => $row['category_name_en'] ?? '',
            'linked_services' => $linkedServices,
            'service_ids' => $serviceIds,
            'service_names_ar' => $serviceNamesAr,
            'service_names_en' => $serviceNamesEn,
            'store_lat' => isset($row['store_lat']) && $row['store_lat'] !== null ? (float) $row['store_lat'] : null,
            'store_lng' => isset($row['store_lng']) && $row['store_lng'] !== null ? (float) $row['store_lng'] : null,
            'distance_km' => isset($row['distance_km']) && $row['distance_km'] !== null
                ? round((float) $row['distance_km'], 2)
                : null,
            'name' => $row['name_ar'],
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'] ?? '',
            'description' => $row['description_ar'] ?? '',
            'price' => $price,
            'unit_price' => $price,
            'price_with_installation' => $priceWithInstallation,
            'price_without_installation' => $priceWithoutInstallation,
            'priceWithInstallation' => $priceWithInstallation,
            'priceWithoutInstallation' => $priceWithoutInstallation,
            'oldPrice' => $oldPrice,
            'old_price' => $oldPrice,
            'old_price_with_installation' => $oldPriceWithInstallation,
            'old_price_without_installation' => $oldPriceWithoutInstallation,
            'rating' => 4.8,
            'reviews' => rand(50, 300),
            'warranty' => $warrantyDuration,
            'warranty_duration' => $warrantyDuration,
            'warranty_terms' => $warrantyTerms,
            'image' => $row['image'] ?? '',
            'stock_quantity' => $stockQuantity,
            'inStock' => $stockQuantity > 0,
            'discount' => $discount,
            'category' => $legacyCategory
        ];
    }

    sendSuccess($parts);
}

function normalizeRequestedSparePartServiceIds(): array
{
    $rawCandidates = [];
    $queryKeys = ['service_id', 'service_ids', 'service_type_id', 'service_type_ids', 'sub_service_id', 'sub_services'];
    foreach ($queryKeys as $key) {
        if (!isset($_GET[$key])) {
            continue;
        }
        $rawCandidates[] = $_GET[$key];
    }

    $serviceIds = [];
    foreach ($rawCandidates as $rawValue) {
        foreach (sparePartScopeNormalizeIds($rawValue) as $serviceId) {
            $serviceIds[$serviceId] = $serviceId;
        }
    }

    return array_values($serviceIds);
}

function loadSparePartLinkedServices(array $partIds): array
{
    $normalizedPartIds = [];
    foreach ($partIds as $partId) {
        $normalized = (int) $partId;
        if ($normalized > 0) {
            $normalizedPartIds[$normalized] = $normalized;
        }
    }
    $normalizedPartIds = array_values($normalizedPartIds);
    if (empty($normalizedPartIds)) {
        return [];
    }

    if (!sparePartScopeTableExists('spare_part_services') || !sparePartScopeTableExists('services')) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedPartIds), '?'));
    $rows = db()->fetchAll(
        "SELECT sps.spare_part_id, sps.service_id, srv.name_ar, srv.name_en
         FROM spare_part_services sps
         JOIN services srv ON srv.id = sps.service_id
         WHERE sps.spare_part_id IN ({$placeholders})
         ORDER BY sps.spare_part_id ASC, sps.service_id ASC",
        $normalizedPartIds
    );

    $result = [];
    foreach ($rows as $row) {
        $partId = (int) ($row['spare_part_id'] ?? 0);
        $serviceId = (int) ($row['service_id'] ?? 0);
        if ($partId <= 0 || $serviceId <= 0) {
            continue;
        }
        if (!isset($result[$partId])) {
            $result[$partId] = [];
        }
        $result[$partId][] = [
            'id' => $serviceId,
            'name_ar' => trim((string) ($row['name_ar'] ?? '')),
            'name_en' => trim((string) ($row['name_en'] ?? '')),
        ];
    }

    return $result;
}

function guessCategory($name)
{
    $name = (string) $name;

    if (strpos($name, 'مكيف') !== false || strpos($name, 'تكييف') !== false)
        return 'تكييف';
    if (strpos($name, 'سباكة') !== false || strpos($name, 'حنفية') !== false || strpos($name, 'خلاط') !== false)
        return 'سباكة';
    if (strpos($name, 'كهرباء') !== false || strpos($name, 'لمبة') !== false || strpos($name, 'مفتاح') !== false)
        return 'كهرباء';
    return 'أجهزة';
}

function serviceCategoriesTableColumnExists($column)
{
    global $conn;
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `service_categories` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function sparePartsTableColumnExists($column)
{
    global $conn;
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `spare_parts` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function ensureSparePartsPricingSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'spare_parts'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    if (!sparePartsTableColumnExists('price_with_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `price_with_installation` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    if (!sparePartsTableColumnExists('price_without_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `price_without_installation` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    if (!sparePartsTableColumnExists('old_price_with_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `old_price_with_installation` DECIMAL(10,2) NULL");
    }
    if (!sparePartsTableColumnExists('old_price_without_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `old_price_without_installation` DECIMAL(10,2) NULL");
    }

    if (sparePartsTableColumnExists('price')) {
        $conn->query(
            "UPDATE `spare_parts`
             SET `price_with_installation` = `price`
             WHERE `price_with_installation` <= 0 OR `price_with_installation` IS NULL"
        );
        $conn->query(
            "UPDATE `spare_parts`
             SET `price_without_installation` = `price`
             WHERE `price_without_installation` <= 0 OR `price_without_installation` IS NULL"
        );
    }

    if (sparePartsTableColumnExists('old_price')) {
        $conn->query(
            "UPDATE `spare_parts`
             SET `old_price_with_installation` = `old_price`
             WHERE `old_price_with_installation` IS NULL AND `old_price` IS NOT NULL"
        );
        $conn->query(
            "UPDATE `spare_parts`
             SET `old_price_without_installation` = `old_price`
             WHERE `old_price_without_installation` IS NULL AND `old_price` IS NOT NULL"
        );
    }
}

function ensureSparePartsWarrantySchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'spare_parts'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    if (!sparePartsTableColumnExists('warranty_duration')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `warranty_duration` VARCHAR(150) NULL");
    }
    if (!sparePartsTableColumnExists('warranty_terms')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `warranty_terms` TEXT NULL");
    }
}

function sparePartsTableIndexExists($indexName)
{
    global $conn;
    $safeIndex = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM `spare_parts` WHERE Key_name = '{$safeIndex}'");
    return $result && $result->num_rows > 0;
}

function ensureSparePartsCategorySchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'spare_parts'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    if (!sparePartsTableColumnExists('category_id')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `category_id` INT NULL AFTER `store_id`");
    }
    if (!sparePartsTableIndexExists('idx_spare_parts_category_id')) {
        $conn->query("ALTER TABLE `spare_parts` ADD INDEX `idx_spare_parts_category_id` (`category_id`)");
    }
}

function resolveSparePartCategoryScope($categoryId)
{
    global $conn;

    $categoryId = (int) $categoryId;
    if ($categoryId <= 0) {
        return [];
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'service_categories'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return [$categoryId];
    }

    $ids = [$categoryId => $categoryId];
    $hasParentColumn = serviceCategoriesTableColumnExists('parent_id');

    if ($hasParentColumn) {
        $stmt = $conn->prepare("SELECT parent_id FROM service_categories WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $categoryId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                return [];
            }

            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId > 0) {
                $ids[$parentId] = $parentId;
            }
        }

        $childStmt = $conn->prepare("SELECT id FROM service_categories WHERE parent_id = ?");
        if ($childStmt) {
            $childStmt->bind_param('i', $categoryId);
            $childStmt->execute();
            $result = $childStmt->get_result();
            while ($child = $result->fetch_assoc()) {
                $childId = (int) ($child['id'] ?? 0);
                if ($childId > 0) {
                    $ids[$childId] = $childId;
                }
            }
        }
    } else {
        $stmt = $conn->prepare("SELECT id FROM service_categories WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $categoryId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                return [];
            }
        }
    }

    return array_values($ids);
}

function storeTableColumnExists($column)
{
    global $conn;
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `stores` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function ensureStoresGeoSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'stores'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    if (!storeTableColumnExists('lat')) {
        $conn->query("ALTER TABLE `stores` ADD COLUMN `lat` DECIMAL(10,8) NULL");
    }
    if (!storeTableColumnExists('lng')) {
        $conn->query("ALTER TABLE `stores` ADD COLUMN `lng` DECIMAL(11,8) NULL");
    }
}
