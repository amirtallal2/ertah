<?php
/**
 * Mobile API - Home Data
 * بيانات الصفحة الرئيسية
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
require_once __DIR__ . '/../helpers/offers_targeting.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/service_areas.php';
require_once __DIR__ . '/../../includes/spare_parts_scope.php';
require_once __DIR__ . '/../../includes/special_services.php';

ensureSpecialServicesSchema();
ensureHomeServicesMultilingualSchema();
ensureHomePromoCodesSchema();
serviceAreaEnsureServiceLinksSchema();
sparePartScopeEnsureSchema();

// Get all home data in one request
getHomeData();

function normalizeBannerHexColor($value, $default = '#FBCC26')
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return strtoupper($default);
    }

    if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $raw)) {
        return strtoupper($default);
    }

    if ($raw[0] !== '#') {
        $raw = '#' . $raw;
    }

    return strtoupper($raw);
}

function ensureHomeServicesMultilingualSchema(): void
{
    global $conn;
    static $done = false;

    if ($done) {
        return;
    }
    $done = true;

    if (!homeTableExists('services')) {
        return;
    }

    if (!homeTableColumnExists('services', 'name_ur')) {
        $conn->query("ALTER TABLE `services` ADD COLUMN `name_ur` VARCHAR(100) NULL AFTER `name_en`");
    }
    if (!homeTableColumnExists('services', 'description_ur')) {
        $conn->query("ALTER TABLE `services` ADD COLUMN `description_ur` TEXT NULL AFTER `description_en`");
    }
}

function ensureHomePromoCodesSchema(): void
{
    global $conn;
    static $done = false;

    if ($done) {
        return;
    }
    $done = true;

    if (!homeTableExists('promo_codes')) {
        return;
    }

    $columns = [
        'image' => "VARCHAR(255) NULL AFTER `description`",
        'title_ar' => "VARCHAR(255) NULL AFTER `code`",
        'title_en' => "VARCHAR(255) NULL AFTER `title_ar`",
        'description_ar' => "TEXT NULL AFTER `title_en`",
        'description_en' => "TEXT NULL AFTER `description_ar`",
    ];

    foreach ($columns as $column => $definition) {
        if (!homeTableColumnExists('promo_codes', $column)) {
            $conn->query("ALTER TABLE `promo_codes` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

function homeNormalizeDigits(string $value): string
{
    return strtr($value, [
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
    ]);
}

function homeClampIntValue($value, int $default, int $min, int $max): int
{
    $normalized = homeNormalizeDigits(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }

    $parsed = (int) preg_replace('/[^\-\d]/', '', $normalized);
    if ($parsed < $min) {
        return $min;
    }
    if ($parsed > $max) {
        return $max;
    }
    return $parsed;
}

function getHomeDisplayLimits(): array
{
    global $conn;

    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $defaults = [
        'how_it_works_steps' => 4,
        'banners' => 5,
        'categories' => 8,
        'stores' => 5,
        'offers' => 5,
        'cities' => 200,
        'most_requested_services' => 4,
        'spare_parts' => 4,
    ];

    $bounds = [
        'how_it_works_steps' => ['min' => 0, 'max' => 10],
        'banners' => ['min' => 0, 'max' => 20],
        'categories' => ['min' => 0, 'max' => 30],
        'stores' => ['min' => 0, 'max' => 30],
        'offers' => ['min' => 0, 'max' => 30],
        'cities' => ['min' => 0, 'max' => 2000],
        'most_requested_services' => ['min' => 0, 'max' => 30],
        'spare_parts' => ['min' => 0, 'max' => 30],
    ];

    $settingMap = [
        'home_limit_how_it_works_steps' => 'how_it_works_steps',
        'home_limit_banners' => 'banners',
        'home_limit_categories' => 'categories',
        'home_limit_stores' => 'stores',
        'home_limit_offers' => 'offers',
        'home_limit_cities' => 'cities',
        'home_limit_most_requested_services' => 'most_requested_services',
        'home_limit_spare_parts' => 'spare_parts',
    ];

    $resolved = $defaults;
    if (!homeTableExists('app_settings')) {
        return $resolved;
    }

    $escapedKeys = array_map(function ($key) use ($conn) {
        return "'" . $conn->real_escape_string($key) . "'";
    }, array_keys($settingMap));

    if (empty($escapedKeys)) {
        return $resolved;
    }

    $sql = "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN (" . implode(',', $escapedKeys) . ")";
    $result = $conn->query($sql);
    if (!$result) {
        return $resolved;
    }

    while ($row = $result->fetch_assoc()) {
        $settingKey = (string) ($row['setting_key'] ?? '');
        if (!isset($settingMap[$settingKey])) {
            continue;
        }

        $targetKey = $settingMap[$settingKey];
        $default = (int) ($defaults[$targetKey] ?? 0);
        $min = (int) ($bounds[$targetKey]['min'] ?? 0);
        $max = (int) ($bounds[$targetKey]['max'] ?? 9999);

        $resolved[$targetKey] = homeClampIntValue(
            (string) ($row['setting_value'] ?? ''),
            $default,
            $min,
            $max
        );
    }

    return $resolved;
}

function homeActiveDateRangeSql(string $tableAlias = ''): string
{
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
    return "({$prefix}start_date IS NULL OR {$prefix}start_date = '' OR {$prefix}start_date = '0000-00-00' OR {$prefix}start_date <= CURDATE())
            AND ({$prefix}end_date IS NULL OR {$prefix}end_date = '' OR {$prefix}end_date = '0000-00-00' OR {$prefix}end_date >= CURDATE())";
}

function homeCoverageAreaIds(array $coverage): array
{
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

function homeBuildAreaVisibilityFragment(string $serviceAlias, array $areaIds): array
{
    $areaIds = array_values(array_unique(array_filter(array_map('intval', $areaIds), static fn($id) => $id > 0)));
    if (empty($areaIds)) {
        return ['sql' => '1=1', 'types' => '', 'params' => []];
    }

    if (!homeTableExists('service_area_services')) {
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

function homeResolveVisibleCategoryIds(array $areaIds): array
{
    global $conn;

    if (!homeTableExists('services')) {
        return [];
    }

    $visibility = homeBuildAreaVisibilityFragment('s', $areaIds);
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

function getHomeData()
{
    global $conn;

    $data = [];
    $homeSectionsConfig = getHomeSectionsConfig();
    $data['how_it_works_steps'] = getHomeHowItWorksSteps();
    $requestedCountry = serviceAreaNormalizeCountryCode($_GET['country_code'] ?? '');
    $lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : null;
    $lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : null;
    $hasCoordinates = $lat !== null && $lng !== null;

    if ($hasCoordinates && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
        sendError('إحداثيات الموقع غير صحيحة', 422);
    }

    $coverage = serviceAreaEvaluateCoverage($requestedCountry, $lat, $lng);
    $isSupportedArea = (bool) ($coverage['is_supported'] ?? true);
    $allowOutside = in_array(
        strtolower(trim((string) ($_GET['allow_outside'] ?? $_GET['guest'] ?? ''))),
        ['1', 'true', 'yes', 'on'],
        true
    );
    $coverageAreaIds = homeCoverageAreaIds($coverage);
    $serviceVisibility = homeBuildAreaVisibilityFragment('s', $coverageAreaIds);
    $homeDisplayLimits = getHomeDisplayLimits();
    $homeHowItWorksLimit = (int) ($homeDisplayLimits['how_it_works_steps'] ?? 4);
    $homeBannersLimit = (int) ($homeDisplayLimits['banners'] ?? 5);
    $homeCategoriesLimit = (int) ($homeDisplayLimits['categories'] ?? 8);
    $homeStoresLimit = (int) ($homeDisplayLimits['stores'] ?? 5);
    $homeOffersLimit = (int) ($homeDisplayLimits['offers'] ?? 5);
    $homeCitiesLimit = (int) ($homeDisplayLimits['cities'] ?? 200);
    $homeMostRequestedLimit = (int) ($homeDisplayLimits['most_requested_services'] ?? 4);
    $homeSparePartsLimit = (int) ($homeDisplayLimits['spare_parts'] ?? 4);

    if ($homeHowItWorksLimit <= 0) {
        $data['how_it_works_steps'] = [];
    } elseif (count($data['how_it_works_steps']) > $homeHowItWorksLimit) {
        $data['how_it_works_steps'] = array_slice($data['how_it_works_steps'], 0, $homeHowItWorksLimit);
    }

    $data['service_availability'] = [
        'requested_country' => $coverage['requested_country'] ?? $requestedCountry,
        'supported_countries' => $coverage['supported_countries'] ?? [],
        'is_supported' => $isSupportedArea,
        'reason' => $coverage['reason'] ?? '',
        'message_ar' => $coverage['message_ar'] ?? ($isSupportedArea ? '' : 'أنت خارج نطاق تقديم الخدمة'),
        'message_en' => $coverage['message_en'] ?? ($isSupportedArea ? '' : 'You are outside the service coverage area.'),
        'matched_area' => $coverage['matched_area'] ?? null,
        'has_active_service_areas' => (bool) ($coverage['has_active_service_areas'] ?? false),
        'active_service_areas_count' => (int) ($coverage['active_service_areas_count'] ?? 0),
    ];

    // Get active banners
    $data['banners'] = [];
    if ($homeBannersLimit > 0) {
        $stmt = $conn->prepare("SELECT * FROM banners WHERE is_active = 1 AND position = 'home_slider' AND " . homeActiveDateRangeSql() . " ORDER BY sort_order ASC LIMIT {$homeBannersLimit}");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $startColor = normalizeBannerHexColor($row['background_color'] ?? '#FBCC26', '#FBCC26');
                $rawEndColor = trim((string) ($row['background_color_end'] ?? ''));
                $endColor = $rawEndColor !== '' ? normalizeBannerHexColor($rawEndColor, $startColor) : null;
                $titleAr = $row['title'] ?? '';
                $titleEn = $row['title_en'] ?? '';
                if ($titleEn === '') {
                    $titleEn = $titleAr;
                }
                $titleUr = $row['title_ur'] ?? '';
                if ($titleUr === '') {
                    $titleUr = $titleEn !== '' ? $titleEn : $titleAr;
                }
                $subtitleAr = $row['subtitle'] ?? '';
                $subtitleEn = $row['subtitle_en'] ?? '';
                if ($subtitleEn === '') {
                    $subtitleEn = $subtitleAr;
                }
                $subtitleUr = $row['subtitle_ur'] ?? '';
                if ($subtitleUr === '') {
                    $subtitleUr = $subtitleEn !== '' ? $subtitleEn : $subtitleAr;
                }

                $data['banners'][] = [
                    'id' => (int) $row['id'],
                    'title' => $titleAr,
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'title_ur' => $titleUr,
                    'subtitle' => $subtitleAr,
                    'subtitle_ar' => $subtitleAr,
                    'subtitle_en' => $subtitleEn,
                    'subtitle_ur' => $subtitleUr,
                    'image' => isset($row['image']) ? imageUrl($row['image']) : null,
                    'link' => $row['link'] ?? '',
                    'link_type' => $row['link_type'] ?? '',
                    'link_id' => isset($row['link_id']) ? (int) $row['link_id'] : null,
                    'background_color' => $startColor,
                    'background_color_end' => $endColor
                ];
            }
        }
    }

    // Get popup banner
    $stmt = $conn->prepare("SELECT * FROM banners WHERE is_active = 1 AND position = 'home_popup' AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY sort_order ASC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $popup = $result->fetch_assoc();
    if ($popup) {
        $popupTitleAr = $popup['title'] ?? '';
        $popupTitleEn = $popup['title_en'] ?? '';
        if ($popupTitleEn === '') {
            $popupTitleEn = $popupTitleAr;
        }
        $popupTitleUr = $popup['title_ur'] ?? '';
        if ($popupTitleUr === '') {
            $popupTitleUr = $popupTitleEn !== '' ? $popupTitleEn : $popupTitleAr;
        }
        $popupSubtitleAr = $popup['subtitle'] ?? '';
        $popupSubtitleEn = $popup['subtitle_en'] ?? '';
        if ($popupSubtitleEn === '') {
            $popupSubtitleEn = $popupSubtitleAr;
        }
        $popupSubtitleUr = $popup['subtitle_ur'] ?? '';
        if ($popupSubtitleUr === '') {
            $popupSubtitleUr = $popupSubtitleEn !== '' ? $popupSubtitleEn : $popupSubtitleAr;
        }
    }
    $data['popup_banner'] = $popup ? [
        'id' => (int) $popup['id'],
        'title' => $popupTitleAr,
        'title_ar' => $popupTitleAr,
        'title_en' => $popupTitleEn,
        'title_ur' => $popupTitleUr,
        'subtitle' => $popupSubtitleAr,
        'subtitle_ar' => $popupSubtitleAr,
        'subtitle_en' => $popupSubtitleEn,
        'subtitle_ur' => $popupSubtitleUr,
        'image' => isset($popup['image']) ? imageUrl($popup['image']) : null,
        'link' => $popup['link'] ?? '',
        'link_type' => $popup['link_type'] ?? '',
        'link_id' => isset($popup['link_id']) ? (int) $popup['link_id'] : null
    ] : null;

    // If area is not supported, return only banners + availability message + section icons.
    if (!$isSupportedArea && !$allowOutside) {
        $data['categories'] = [];
        $data['category_banner'] = null;
        $data['stores'] = [];
        $data['offers'] = [];
        $data['cities'] = [];
        $data['most_requested_services'] = [];
        $data['ad_banner'] = null;
        $data['spare_parts'] = [];
        $data['section_icons'] = getHomeSectionIcons();
        $data['home_sections'] = $homeSectionsConfig;
        sendSuccess($data);
    }

    // Get service categories (main categories only when sub-categories are enabled)
    $categoriesSql = "SELECT * FROM service_categories WHERE is_active = 1";
    $categoriesTypes = '';
    $categoriesParams = [];
    $hasCategoryHierarchy = serviceCategoriesHasParentColumn();
    if ($hasCategoryHierarchy) {
        $categoriesSql .= " AND parent_id IS NULL";
    }

    if (!empty($coverageAreaIds)) {
        if ($hasCategoryHierarchy) {
            $categoriesSql .= " AND (
                EXISTS (
                    SELECT 1
                    FROM services s
                    WHERE s.is_active = 1
                      AND s.category_id = service_categories.id
                      AND {$serviceVisibility['sql']}
                )
                OR EXISTS (
                    SELECT 1
                    FROM service_categories ch
                    JOIN services s ON s.category_id = ch.id AND s.is_active = 1
                    WHERE ch.parent_id = service_categories.id
                      AND {$serviceVisibility['sql']}
                )
            )";
            $categoriesTypes .= $serviceVisibility['types'];
            $categoriesParams = array_merge($categoriesParams, $serviceVisibility['params']);
            $categoriesTypes .= $serviceVisibility['types'];
            $categoriesParams = array_merge($categoriesParams, $serviceVisibility['params']);
        } else {
            $categoriesSql .= " AND EXISTS (
                SELECT 1
                FROM services s
                WHERE s.is_active = 1
                  AND s.category_id = service_categories.id
                  AND {$serviceVisibility['sql']}
            )";
            $categoriesTypes .= $serviceVisibility['types'];
            $categoriesParams = array_merge($categoriesParams, $serviceVisibility['params']);
        }
    }
    $data['categories'] = [];
    if ($homeCategoriesLimit > 0) {
        $specialCategories = getHomeSpecialCategories();
        $regularLimit = max(0, $homeCategoriesLimit - count($specialCategories));

        if ($regularLimit > 0) {
            $categoriesSql .= " ORDER BY sort_order ASC, id ASC LIMIT {$regularLimit}";

            $stmt = $conn->prepare($categoriesSql);
            if (!$stmt) {
                sendError('Failed to prepare categories query', 500);
            }
            if ($categoriesTypes !== '') {
                $stmt->bind_param($categoriesTypes, ...$categoriesParams);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['categories'][] = [
                    'id' => (int) $row['id'],
                    'parent_id' => isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'],
                    'name_ur' => $row['name_ur'] ?? (($row['name_en'] ?? '') !== '' ? $row['name_en'] : $row['name_ar']),
                    'icon' => serviceCategoryIconForApi($row['icon'] ?? null, $row['name_ar'] ?? '', $row['name_en'] ?? ''),
                    'image' => serviceCategoryImageForApi($row['image'] ?? null),
                    'special_module' => null,
                    'warranty_days' => isset($row['warranty_days']) ? (int) $row['warranty_days'] : 14,
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
                    'sub_categories' => [],
                ];
            }
        }

        if ($hasCategoryHierarchy && !empty($data['categories'])) {
            $rootIds = array_values(array_unique(array_map(
                static fn($item) => (int) ($item['id'] ?? 0),
                $data['categories']
            )));
            $rootIds = array_values(array_filter($rootIds, static fn($id) => $id > 0));

            if (!empty($rootIds)) {
                $placeholders = implode(',', array_fill(0, count($rootIds), '?'));
                $subSql = "SELECT *
                           FROM service_categories
                           WHERE is_active = 1
                             AND parent_id IN ({$placeholders})
                           ORDER BY sort_order ASC, id ASC";
                $subStmt = $conn->prepare($subSql);
                if ($subStmt) {
                    $subStmt->bind_param(str_repeat('i', count($rootIds)), ...$rootIds);
                    $subStmt->execute();
                    $subResult = $subStmt->get_result();

                    $childrenByParent = [];
                    while ($subRow = $subResult->fetch_assoc()) {
                        $parentId = (int) ($subRow['parent_id'] ?? 0);
                        if ($parentId <= 0) {
                            continue;
                        }

                        $childrenByParent[$parentId][] = [
                            'id' => (int) $subRow['id'],
                            'parent_id' => $parentId,
                            'name_ar' => $subRow['name_ar'],
                            'name_en' => $subRow['name_en'],
                            'name_ur' => $subRow['name_ur'] ?? (($subRow['name_en'] ?? '') !== '' ? $subRow['name_en'] : $subRow['name_ar']),
                            'icon' => serviceCategoryIconForApi($subRow['icon'] ?? null, $subRow['name_ar'] ?? '', $subRow['name_en'] ?? ''),
                            'image' => serviceCategoryImageForApi($subRow['image'] ?? null),
                            'special_module' => null,
                            'warranty_days' => isset($subRow['warranty_days']) ? (int) $subRow['warranty_days'] : 14,
                            'sort_order' => isset($subRow['sort_order']) ? (int) $subRow['sort_order'] : 0,
                            'sub_categories' => [],
                        ];
                    }

                    foreach ($data['categories'] as &$categoryItem) {
                        $categoryId = (int) ($categoryItem['id'] ?? 0);
                        $categoryItem['sub_categories'] = $childrenByParent[$categoryId] ?? [];
                    }
                    unset($categoryItem);
                }
            }
        }

        $data['categories'] = deduplicateServiceCategoriesForApi(array_merge($data['categories'], $specialCategories));
        if (count($data['categories']) > $homeCategoriesLimit) {
            $data['categories'] = array_slice($data['categories'], 0, $homeCategoriesLimit);
        }
    }

    // Get category banner
    $stmt = $conn->prepare("SELECT * FROM banners WHERE is_active = 1 AND position = 'category' ORDER BY RAND() LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $catBanner = $result->fetch_assoc();
        if ($catBanner) {
            $catTitleAr = $catBanner['title'] ?? '';
            $catTitleEn = $catBanner['title_en'] ?? '';
            if ($catTitleEn === '') {
                $catTitleEn = $catTitleAr;
            }
            $catTitleUr = $catBanner['title_ur'] ?? '';
            if ($catTitleUr === '') {
                $catTitleUr = $catTitleEn !== '' ? $catTitleEn : $catTitleAr;
            }
            $catSubtitleAr = $catBanner['subtitle'] ?? '';
            $catSubtitleEn = $catBanner['subtitle_en'] ?? '';
            if ($catSubtitleEn === '') {
                $catSubtitleEn = $catSubtitleAr;
            }
            $catSubtitleUr = $catBanner['subtitle_ur'] ?? '';
            if ($catSubtitleUr === '') {
                $catSubtitleUr = $catSubtitleEn !== '' ? $catSubtitleEn : $catSubtitleAr;
            }
        }
        $data['category_banner'] = $catBanner ? [
            'id' => (int) $catBanner['id'],
            'title' => $catTitleAr,
            'title_ar' => $catTitleAr,
            'title_en' => $catTitleEn,
            'title_ur' => $catTitleUr,
            'subtitle' => $catSubtitleAr,
            'subtitle_ar' => $catSubtitleAr,
            'subtitle_en' => $catSubtitleEn,
            'subtitle_ur' => $catSubtitleUr,
            'image' => isset($catBanner['image']) ? imageUrl($catBanner['image']) : null,
            'link' => $catBanner['link'] ?? '',
            'link_type' => $catBanner['link_type'] ?? '',
            'link_id' => isset($catBanner['link_id']) ? (int) $catBanner['link_id'] : null
        ] : null;
    }

    // Get featured stores
    $data['stores'] = [];
    if ($homeStoresLimit > 0) {
        $stmt = $conn->prepare("SELECT * FROM stores WHERE is_active = 1 ORDER BY id DESC LIMIT {$homeStoresLimit}");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['stores'][] = [
                    'id' => (int) $row['id'],
                    'name_ar' => $row['name_ar'],
                    'image' => isset($row['image']) ? imageUrl($row['image']) : null
                ];
            }
        }
    }

    // Get active promo codes as offers
    $data['offers'] = [];
    if ($homeOffersLimit > 0) {
        $stmt = $conn->prepare("SELECT * FROM promo_codes
                                WHERE is_active = 1
                                  AND " . homeActiveDateRangeSql() . "
                                ORDER BY created_at DESC
                                LIMIT {$homeOffersLimit}");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $fallbackTitleAr = $code !== '' ? ('كود خصم ' . $code) : 'كود خصم';
                $fallbackTitleEn = $code !== '' ? ('Promo Code ' . $code) : 'Promo Code';

                $titleAr = trim((string) ($row['title_ar'] ?? ''));
                if ($titleAr === '') {
                    $titleAr = $fallbackTitleAr;
                }
                $titleEn = trim((string) ($row['title_en'] ?? ''));
                if ($titleEn === '') {
                    $titleEn = $fallbackTitleEn;
                }

                $descriptionAr = trim((string) ($row['description_ar'] ?? ''));
                if ($descriptionAr === '') {
                    $descriptionAr = trim((string) ($row['description'] ?? ''));
                }
                $descriptionEn = trim((string) ($row['description_en'] ?? ''));
                if ($descriptionEn === '') {
                    $descriptionEn = $descriptionAr;
                }

                $data['offers'][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'description_ar' => $descriptionAr,
                    'description_en' => $descriptionEn,
                    'image' => isset($row['image']) ? imageUrl($row['image']) : null,
                    'discount_type' => $row['discount_type'] ?? 'fixed',
                    'discount_value' => (float) ($row['discount_value'] ?? 0),
                    'min_order_amount' => (float) ($row['min_order_amount'] ?? 0),
                    'max_discount_amount' => $row['max_discount_amount'] !== null ? (float) $row['max_discount_amount'] : null,
                    'category_id' => null,
                    'category_name_ar' => '',
                    'category_name_en' => '',
                    'link_type' => '',
                    'link_id' => null,
                    'link' => null,
                    'target_audience' => 'all',
                    'end_date' => $row['end_date'] ?? null,
                    'code' => $code,
                    'promo_code' => $code,
                    'source' => 'promo_codes',
                ];
            }
        }

        if (empty($data['offers']) && homeTableExists('offers')) {
            $legacyResult = $conn->query("SELECT * FROM offers
                                          WHERE is_active = 1
                                            AND " . homeActiveDateRangeSql() . "
                                          ORDER BY created_at DESC
                                          LIMIT {$homeOffersLimit}");
            if ($legacyResult) {
                while ($row = $legacyResult->fetch_assoc()) {
                    $titleAr = trim((string) ($row['title_ar'] ?? ''));
                    $titleEn = trim((string) ($row['title_en'] ?? ''));
                    $descriptionAr = trim((string) ($row['description_ar'] ?? ''));
                    $descriptionEn = trim((string) ($row['description_en'] ?? ''));

                    if ($titleAr === '' && $titleEn !== '') {
                        $titleAr = $titleEn;
                    }
                    if ($titleEn === '' && $titleAr !== '') {
                        $titleEn = $titleAr;
                    }
                    if ($descriptionAr === '' && $descriptionEn !== '') {
                        $descriptionAr = $descriptionEn;
                    }
                    if ($descriptionEn === '' && $descriptionAr !== '') {
                        $descriptionEn = $descriptionAr;
                    }

                    $data['offers'][] = [
                        'id' => (int) ($row['id'] ?? 0),
                        'title_ar' => $titleAr !== '' ? $titleAr : 'عرض خاص',
                        'title_en' => $titleEn !== '' ? $titleEn : ($titleAr !== '' ? $titleAr : 'Special Offer'),
                        'description_ar' => $descriptionAr,
                        'description_en' => $descriptionEn !== '' ? $descriptionEn : $descriptionAr,
                        'image' => isset($row['image']) ? imageUrl($row['image']) : null,
                        'discount_type' => $row['discount_type'] ?? 'fixed',
                        'discount_value' => (float) ($row['discount_value'] ?? 0),
                        'min_order_amount' => (float) ($row['min_order_amount'] ?? 0),
                        'max_discount_amount' => $row['max_discount_amount'] !== null ? (float) $row['max_discount_amount'] : null,
                        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                        'category_name_ar' => '',
                        'category_name_en' => '',
                        'link_type' => $row['link_type'] ?? '',
                        'link_id' => isset($row['link_id']) ? (int) $row['link_id'] : null,
                        'link' => $row['link'] ?? null,
                        'target_audience' => $row['target_audience'] ?? 'all',
                        'end_date' => $row['end_date'] ?? null,
                        'code' => '',
                        'promo_code' => '',
                        'source' => 'offers',
                    ];
                }
            }
        }
    }

    // Get cities
    $data['cities'] = [];
    if ($homeCitiesLimit > 0) {
        $stmt = $conn->prepare("SELECT * FROM cities WHERE is_active = 1 ORDER BY name_ar ASC LIMIT {$homeCitiesLimit}");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['cities'][] = [
                    'id' => (int) $row['id'],
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en']
                ];
            }
        }
    }

    // Get Most Requested Services (controlled by admin via is_featured).
    // requests_count is calculated from real orders data, not static service counters.
    $featuredDemand = buildFeaturedServicesDemandFragmentsForHome();
    $featuredFilter = homeTableColumnExists('services', 'is_featured') ? ' AND s.is_featured = 1' : '';
    $ratingExpr = homeTableColumnExists('services', 'rating') ? "COALESCE(s.rating, 0)" : "0";
    $categoryNameUrExpr = homeTableColumnExists('service_categories', 'name_ur')
        ? 'c.name_ur AS category_name_ur'
        : 'c.name_en AS category_name_ur';
    $data['most_requested_services'] = [];
    if ($homeMostRequestedLimit > 0) {
        $stmt = $conn->prepare("SELECT s.id, s.category_id, s.name_ar, s.name_en, s.name_ur, s.description_ar, s.description_en, s.description_ur,
                                       c.name_ar AS category_name_ar, c.name_en AS category_name_en, {$categoryNameUrExpr},
                                       s.image, s.price,
                                       {$featuredDemand['requests_expr']} AS requests_count,
                                       {$ratingExpr} AS rating
                                FROM services s
                                LEFT JOIN service_categories c ON s.category_id = c.id
                                {$featuredDemand['joins_sql']}
                                WHERE s.is_active = 1{$featuredFilter}
                                  AND {$serviceVisibility['sql']}
                                ORDER BY requests_count DESC, rating DESC, s.id DESC
                                LIMIT {$homeMostRequestedLimit}");
        if ($stmt) {
            if ($serviceVisibility['types'] !== '') {
                $stmt->bind_param($serviceVisibility['types'], ...$serviceVisibility['params']);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $rank = 1;
            while ($row = $result->fetch_assoc()) {
                $categoryInfo = getHomeCategoryDisplayInfo(
                    (int) ($row['category_id'] ?? 0),
                    $row['category_name_ar'] ?? '',
                    $row['category_name_en'] ?? '',
                    $row['category_name_ur'] ?? ''
                );
                $data['most_requested_services'][] = [
                    'id' => (int) $row['id'],
                    'category_id' => (int) ($row['category_id'] ?? 0),
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'] ?? '',
                    'name_ur' => $row['name_ur'] ?? (($row['name_en'] ?? '') !== '' ? $row['name_en'] : ($row['name_ar'] ?? '')),
                    'description_ar' => $row['description_ar'] ?? '',
                    'description_en' => $row['description_en'] ?? '',
                    'description_ur' => $row['description_ur'] ?? (($row['description_en'] ?? '') !== '' ? $row['description_en'] : ($row['description_ar'] ?? '')),
                    'category_name_ar' => $categoryInfo['name_ar'],
                    'category_name_en' => $categoryInfo['name_en'],
                    'category_name_ur' => $categoryInfo['name_ur'],
                    'image' => isset($row['image']) ? imageUrl($row['image']) : null,
                    'price' => (float) ($row['price'] ?? 0),
                    'requests_count' => (int) ($row['requests_count'] ?? 0),
                    'rating' => (float) ($row['rating'] ?? 5.0),
                    'rank' => $rank++
                ];
            }
        }
    }

    // Get Banner before Spare Parts (Advertising)
    $stmt = $conn->prepare("SELECT * FROM banners WHERE is_active = 1 AND position IN ('home_middle', 'home_mid') ORDER BY RAND() LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $ad = $result->fetch_assoc();
        if ($ad) {
            $adTitleAr = $ad['title'] ?? '';
            $adTitleEn = $ad['title_en'] ?? '';
            if ($adTitleEn === '') {
                $adTitleEn = $adTitleAr;
            }
            $adTitleUr = $ad['title_ur'] ?? '';
            if ($adTitleUr === '') {
                $adTitleUr = $adTitleEn !== '' ? $adTitleEn : $adTitleAr;
            }
            $adSubtitleAr = $ad['subtitle'] ?? '';
            $adSubtitleEn = $ad['subtitle_en'] ?? '';
            if ($adSubtitleEn === '') {
                $adSubtitleEn = $adSubtitleAr;
            }
            $adSubtitleUr = $ad['subtitle_ur'] ?? '';
            if ($adSubtitleUr === '') {
                $adSubtitleUr = $adSubtitleEn !== '' ? $adSubtitleEn : $adSubtitleAr;
            }
        }
        $data['ad_banner'] = $ad ? [
            'id' => (int) $ad['id'],
            'title' => $adTitleAr,
            'title_ar' => $adTitleAr,
            'title_en' => $adTitleEn,
            'title_ur' => $adTitleUr,
            'subtitle' => $adSubtitleAr,
            'subtitle_ar' => $adSubtitleAr,
            'subtitle_en' => $adSubtitleEn,
            'subtitle_ur' => $adSubtitleUr,
            'image' => isset($ad['image']) ? imageUrl($ad['image']) : null,
            'link' => $ad['link'] ?? '',
            'link_type' => $ad['link_type'] ?? '',
            'link_id' => isset($ad['link_id']) ? (int) $ad['link_id'] : null
        ] : null;
    }

    // Get Spare Parts (respect area availability if mappings are set)
    $sparePartsVisibility = sparePartScopeBuildVisibilityFragment('sp', $coverageAreaIds, []);
    $data['spare_parts'] = [];
    if ($homeSparePartsLimit > 0) {
        $sparePartsSql = "SELECT sp.*
                          FROM spare_parts sp
                          WHERE sp.is_active = 1
                            AND {$sparePartsVisibility['sql']}
                          ORDER BY sp.sort_order ASC
                          LIMIT {$homeSparePartsLimit}";
        $stmt = $conn->prepare($sparePartsSql);
        if ($stmt) {
            if ($sparePartsVisibility['types'] !== '') {
                $stmt->bind_param($sparePartsVisibility['types'], ...$sparePartsVisibility['params']);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $priceWithInstallation = isset($row['price_with_installation']) && (float) $row['price_with_installation'] > 0
                    ? (float) $row['price_with_installation']
                    : (float) ($row['price'] ?? 0);
                $priceWithoutInstallation = isset($row['price_without_installation']) && (float) $row['price_without_installation'] > 0
                    ? (float) $row['price_without_installation']
                    : $priceWithInstallation;
                $oldPriceWithInstallation = isset($row['old_price_with_installation']) && (float) $row['old_price_with_installation'] > 0
                    ? (float) $row['old_price_with_installation']
                    : (isset($row['old_price']) && (float) $row['old_price'] > 0 ? (float) $row['old_price'] : null);
                $oldPriceWithoutInstallation = isset($row['old_price_without_installation']) && (float) $row['old_price_without_installation'] > 0
                    ? (float) $row['old_price_without_installation']
                    : $oldPriceWithInstallation;
                $warrantyDuration = trim((string) ($row['warranty_duration'] ?? ''));
                if ($warrantyDuration === '') {
                    $warrantyDuration = 'سنة';
                }
                $warrantyTerms = trim((string) ($row['warranty_terms'] ?? ''));

                $data['spare_parts'][] = [
                    'id' => (int) $row['id'],
                    'name_ar' => $row['name_ar'],
                    'description_ar' => $row['description_ar'] ?? '',
                    'image' => isset($row['image']) ? imageUrl($row['image']) : null,
                    'price' => $priceWithInstallation,
                    'old_price' => $oldPriceWithInstallation,
                    'price_with_installation' => $priceWithInstallation,
                    'price_without_installation' => $priceWithoutInstallation,
                    'old_price_with_installation' => $oldPriceWithInstallation,
                    'old_price_without_installation' => $oldPriceWithoutInstallation,
                    'warranty' => $warrantyDuration,
                    'warranty_duration' => $warrantyDuration,
                    'warranty_terms' => $warrantyTerms
                ];
            }
        }
    }

    // Home section header icons (managed from admin settings)
    $data['section_icons'] = getHomeSectionIcons();
    $data['home_sections'] = $homeSectionsConfig;

    sendSuccess($data);
}

function getHomeSectionsConfig()
{
    global $conn;

    $defaults = [
        'how_it_works' => ['visible' => true, 'order' => 1],
        'services' => ['visible' => true, 'order' => 2],
        'most_requested_services' => ['visible' => true, 'order' => 3],
        'ad_banner' => ['visible' => true, 'order' => 4],
        'spare_parts' => ['visible' => true, 'order' => 5],
        'stores' => ['visible' => true, 'order' => 6],
        'offers' => ['visible' => true, 'order' => 7],
    ];

    $tableCheck = $conn->query("SHOW TABLES LIKE 'app_settings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return $defaults;
    }

    $settingMap = [
        'home_section_visible_how_it_works' => ['section' => 'how_it_works', 'field' => 'visible'],
        'home_section_visible_services' => ['section' => 'services', 'field' => 'visible'],
        'home_section_visible_most_requested_services' => ['section' => 'most_requested_services', 'field' => 'visible'],
        'home_section_visible_ad_banner' => ['section' => 'ad_banner', 'field' => 'visible'],
        'home_section_visible_spare_parts' => ['section' => 'spare_parts', 'field' => 'visible'],
        'home_section_visible_stores' => ['section' => 'stores', 'field' => 'visible'],
        'home_section_visible_offers' => ['section' => 'offers', 'field' => 'visible'],
        'home_section_order_how_it_works' => ['section' => 'how_it_works', 'field' => 'order'],
        'home_section_order_services' => ['section' => 'services', 'field' => 'order'],
        'home_section_order_most_requested_services' => ['section' => 'most_requested_services', 'field' => 'order'],
        'home_section_order_ad_banner' => ['section' => 'ad_banner', 'field' => 'order'],
        'home_section_order_spare_parts' => ['section' => 'spare_parts', 'field' => 'order'],
        'home_section_order_stores' => ['section' => 'stores', 'field' => 'order'],
        'home_section_order_offers' => ['section' => 'offers', 'field' => 'order'],
    ];

    $escapedKeys = array_map(function ($key) use ($conn) {
        return "'" . $conn->real_escape_string($key) . "'";
    }, array_keys($settingMap));

    if (empty($escapedKeys)) {
        return $defaults;
    }

    $sql = "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN (" . implode(',', $escapedKeys) . ")";
    $result = $conn->query($sql);
    if (!$result) {
        return $defaults;
    }

    while ($row = $result->fetch_assoc()) {
        $settingKey = $row['setting_key'] ?? '';
        if (!isset($settingMap[$settingKey])) {
            continue;
        }

        $mapping = $settingMap[$settingKey];
        $section = $mapping['section'];
        $field = $mapping['field'];
        $value = trim((string) ($row['setting_value'] ?? ''));

        if (!isset($defaults[$section])) {
            continue;
        }

        if ($field === 'visible') {
            if ($value === '') {
                continue;
            }

            $normalized = strtolower($value);
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                $defaults[$section]['visible'] = true;
            } elseif (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                $defaults[$section]['visible'] = false;
            }
            continue;
        }

        $order = (int) $value;
        if ($field === 'order' && $order > 0) {
            $defaults[$section]['order'] = $order;
        }
    }

    return $defaults;
}

function getHomeSpecialCategories(): array
{
    $categories = [];

    if (specialServiceTableExists('furniture_services')) {
        $count = (int) db()->count('furniture_services', 'is_active = 1');
        if ($count > 0) {
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
            ];
        }
    }

    if (specialServiceTableExists('container_services')) {
        $count = (int) db()->count('container_services', 'is_active = 1');
        if ($count > 0) {
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
            ];
        }
    }

    return $categories;
}

function getHomeHowItWorksDefaultSteps()
{
    return [
        1 => [
            'id' => 1,
            'title_ar' => 'احجز الخدمة',
            'title_en' => 'Book Service',
            'title_ur' => 'سروس بک کریں',
            'subtitle_ar' => 'التي تحتاجها',
            'subtitle_en' => 'That you need',
            'subtitle_ur' => 'جس کی آپ کو ضرورت ہے',
            'image' => 'https://iili.io/fxvIxea.png',
        ],
        2 => [
            'id' => 2,
            'title_ar' => 'استقبل العروض',
            'title_en' => 'Receive Offers',
            'title_ur' => 'پیشکشیں وصول کریں',
            'subtitle_ar' => 'من مقدمي الخدمات',
            'subtitle_en' => 'From service providers',
            'subtitle_ur' => 'سروس فراہم کنندگان سے',
            'image' => 'https://iili.io/fxvg2TP.png',
        ],
        3 => [
            'id' => 3,
            'title_ar' => 'اختر الأفضل',
            'title_en' => 'Choose Best',
            'title_ur' => 'بہترین کا انتخاب کریں',
            'subtitle_ar' => 'السعر والتقييم',
            'subtitle_en' => 'Price and Rating',
            'subtitle_ur' => 'قیمت اور درجہ بندی',
            'image' => 'https://iili.io/fxv4PvR.png',
        ],
        4 => [
            'id' => 4,
            'title_ar' => 'تنفيذ الخدمة',
            'title_en' => 'Execute Service',
            'title_ur' => 'سروس انجام دیں',
            'subtitle_ar' => 'بجودة عالية',
            'subtitle_en' => 'High Quality',
            'subtitle_ur' => 'اعلی معیار',
            'image' => 'https://iili.io/fxWOm92.png',
        ],
    ];
}

function getHomeHowItWorksSteps()
{
    global $conn;

    $steps = getHomeHowItWorksDefaultSteps();

    $tableCheck = $conn->query("SHOW TABLES LIKE 'app_settings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        foreach ($steps as &$step) {
            $step['image'] = imageUrl($step['image']);
        }
        unset($step);
        return array_values($steps);
    }

    $keyMap = [];
    foreach ($steps as $stepNumber => $step) {
        $keyMap["home_how_it_works_step_{$stepNumber}_title_ar"] = ['step' => $stepNumber, 'field' => 'title_ar'];
        $keyMap["home_how_it_works_step_{$stepNumber}_title_en"] = ['step' => $stepNumber, 'field' => 'title_en'];
        $keyMap["home_how_it_works_step_{$stepNumber}_title_ur"] = ['step' => $stepNumber, 'field' => 'title_ur'];
        $keyMap["home_how_it_works_step_{$stepNumber}_subtitle_ar"] = ['step' => $stepNumber, 'field' => 'subtitle_ar'];
        $keyMap["home_how_it_works_step_{$stepNumber}_subtitle_en"] = ['step' => $stepNumber, 'field' => 'subtitle_en'];
        $keyMap["home_how_it_works_step_{$stepNumber}_subtitle_ur"] = ['step' => $stepNumber, 'field' => 'subtitle_ur'];
        $keyMap["home_how_it_works_step_{$stepNumber}_image"] = ['step' => $stepNumber, 'field' => 'image'];
    }

    if (empty($keyMap)) {
        return array_values($steps);
    }

    $escapedKeys = array_map(function ($key) use ($conn) {
        return "'" . $conn->real_escape_string($key) . "'";
    }, array_keys($keyMap));

    $sql = "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN (" . implode(',', $escapedKeys) . ")";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settingKey = (string) ($row['setting_key'] ?? '');
            $settingValue = trim((string) ($row['setting_value'] ?? ''));
            if ($settingValue === '' || !isset($keyMap[$settingKey])) {
                continue;
            }

            $mapping = $keyMap[$settingKey];
            $stepNumber = (int) $mapping['step'];
            $field = (string) $mapping['field'];

            if (!isset($steps[$stepNumber])) {
                continue;
            }

            $steps[$stepNumber][$field] = $settingValue;
        }
    }

    foreach ($steps as &$step) {
        $step['image'] = imageUrl($step['image'] ?? '');
    }
    unset($step);

    return array_values($steps);
}

function getHomeSectionIcons()
{
    global $conn;

    $icons = [
        'most_requested' => null,
        'services' => null,
        'spare_parts' => null,
        'latest_offers' => null,
    ];

    $tableCheck = $conn->query("SHOW TABLES LIKE 'app_settings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return $icons;
    }

    $keyMap = [
        'home_section_icon_most_requested' => 'most_requested',
        'home_section_icon_services' => 'services',
        'home_section_icon_spare_parts' => 'spare_parts',
        'home_section_icon_latest_offers' => 'latest_offers',
    ];

    $sql = "SELECT setting_key, setting_value 
            FROM app_settings 
            WHERE setting_key IN (
                'home_section_icon_most_requested',
                'home_section_icon_services',
                'home_section_icon_spare_parts',
                'home_section_icon_latest_offers'
            )";

    $result = $conn->query($sql);
    if (!$result) {
        return $icons;
    }

    while ($row = $result->fetch_assoc()) {
        $settingKey = $row['setting_key'] ?? '';
        $settingValue = $row['setting_value'] ?? '';
        $sectionKey = $keyMap[$settingKey] ?? null;

        if ($sectionKey !== null && $settingValue !== '') {
            $icons[$sectionKey] = imageUrl($settingValue);
        }
    }

    return $icons;
}

function serviceCategoriesHasParentColumn()
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

function homeTableExists($table)
{
    global $conn;

    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($safe === '') {
        return false;
    }

    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function homeTableColumnExists($table, $column)
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

function buildFeaturedServicesDemandFragmentsForHome()
{
    $joins = [];
    $requestSources = [];

    $hasOrders = homeTableExists('orders');
    $hasStatusColumn = $hasOrders && homeTableColumnExists('orders', 'status');
    $ordersStatusFilter = $hasStatusColumn ? " AND o.status NOT IN ('cancelled', 'rejected')" : '';
    $ordersStatusFilterNoAlias = $hasStatusColumn ? " AND status NOT IN ('cancelled', 'rejected')" : '';

    if (
        $hasOrders
        && homeTableExists('order_services')
        && homeTableColumnExists('order_services', 'service_id')
        && homeTableColumnExists('order_services', 'order_id')
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

    if ($hasOrders && homeTableColumnExists('orders', 'service_id')) {
        $joins[] = "LEFT JOIN (
                        SELECT o.service_id, COUNT(DISTINCT o.id) AS requests_count
                        FROM orders o
                        WHERE o.service_id IS NOT NULL{$ordersStatusFilter}
                        GROUP BY o.service_id
                    ) direct_service_order_counts ON direct_service_order_counts.service_id = s.id";
        $requestSources[] = 'direct_service_order_counts.requests_count';
    }

    if ($hasOrders && homeTableColumnExists('orders', 'category_id')) {
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

function getHomeCategoryDisplayInfo($categoryId, $fallbackAr = '', $fallbackEn = '', $fallbackUr = '')
{
    $resolvedFallbackUr = $fallbackUr !== '' ? $fallbackUr : ($fallbackEn !== '' ? $fallbackEn : $fallbackAr);

    if ($categoryId <= 0) {
        return [
            'name_ar' => $fallbackAr,
            'name_en' => $fallbackEn,
            'name_ur' => $resolvedFallbackUr,
        ];
    }

    $map = getHomeCategoryDisplayMap();
    if (isset($map[$categoryId])) {
        return $map[$categoryId];
    }

    return [
        'name_ar' => $fallbackAr,
        'name_en' => $fallbackEn,
        'name_ur' => $resolvedFallbackUr,
    ];
}

function getHomeCategoryDisplayMap()
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    global $conn;
    $map = [];

    $hasParent = serviceCategoriesHasParentColumn();
    $hasCategoryUr = homeTableColumnExists('service_categories', 'name_ur');
    $categoryUrSelect = $hasCategoryUr ? 'c.name_ur' : 'c.name_en AS name_ur';
    $parentUrSelect = $hasCategoryUr ? 'p.name_ur AS parent_name_ur' : 'p.name_en AS parent_name_ur';
    if ($hasParent) {
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

function normalizeCountryCode($value)
{
    $normalized = strtoupper(trim((string) $value));
    if ($normalized === 'NULL' || $normalized === '-') {
        return '';
    }
    return $normalized;
}

function getSupportedServiceCountries()
{
    global $conn;

    $defaultCountries = ['SA'];
    $tableCheck = $conn->query("SHOW TABLES LIKE 'app_settings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return $defaultCountries;
    }

    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'supported_countries' LIMIT 1");
    if (!$stmt) {
        return $defaultCountries;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $raw = $row['setting_value'] ?? '';

    if (trim((string) $raw) === '') {
        return $defaultCountries;
    }

    $countries = array_filter(array_map(
        function ($item) {
            return normalizeCountryCode($item);
        },
        explode(',', $raw)
    ));

    return !empty($countries) ? array_values(array_unique($countries)) : $defaultCountries;
}

function isServiceAvailableForCountry($countryCode, $supportedCountries)
{
    $country = normalizeCountryCode($countryCode);
    if ($country === '') {
        return true;
    }

    if (empty($supportedCountries)) {
        return true;
    }

    return in_array($country, $supportedCountries, true);
}
