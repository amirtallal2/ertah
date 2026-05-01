<?php
/**
 * صفحة إدارة الطلبات
 * Orders Management Page
 */

require_once '../init.php';
requireLogin();
require_once '../includes/provider_finance.php';
require_once '../includes/special_services.php';

$pageTitle = 'الطلبات';
$pageSubtitle = 'إدارة طلبات الخدمات';

$action = get('action', 'list');
$id = (int)get('id');

function orderHasColumn($column, $reload = false)
{
    static $columns = null;
    if ($reload || $columns === null) {
        $columns = [];
        $rows = db()->fetchAll("SHOW COLUMNS FROM orders");
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    }
    return !empty($columns[$column]);
}

function userHasColumn($column)
{
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $rows = db()->fetchAll("SHOW COLUMNS FROM users");
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    }
    return !empty($columns[$column]);
}

function categoryIconLooksLikeImage($icon)
{
    $icon = trim((string) $icon);
    if ($icon === '') {
        return false;
    }

    if (filter_var($icon, FILTER_VALIDATE_URL)) {
        return true;
    }

    if (strpos($icon, '/') !== false) {
        return true;
    }

    return (bool) preg_match('/\.(png|jpe?g|gif|webp|svg|avif)$/i', $icon);
}

function renderCategoryIcon($icon, $size = 24)
{
    $icon = trim((string) $icon);
    $size = max(12, (int) $size);

    if ($icon === '') {
        return '<i class="fas fa-tools" style="font-size:' . $size . 'px; color:#64748b;"></i>';
    }

    if (categoryIconLooksLikeImage($icon)) {
        $src = htmlspecialchars(imageUrl($icon), ENT_QUOTES, 'UTF-8');
        return '<img src="' . $src . '" alt="" style="width:' . $size . 'px;height:' . $size . 'px;object-fit:cover;border-radius:6px;">';
    }

    return htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
}

function renderCategoryIconText($icon)
{
    $icon = trim((string) $icon);
    if ($icon === '' || categoryIconLooksLikeImage($icon)) {
        return '🛠️';
    }
    return $icon;
}

function orderAdminFirstDisplayChar($value, $fallback = 'م')
{
    $text = trim((string) $value);
    if ($text === '') {
        $text = $fallback;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 1);
    }

    return substr($text, 0, 1);
}

function resolveOrderCustomerName(array $order): string
{
    $name = trim((string) ($order['user_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $phone = trim((string) ($order['user_phone'] ?? ''));
    if ($phone !== '') {
        $userByPhone = db()->fetch(
            "SELECT full_name FROM users WHERE phone = ? AND full_name IS NOT NULL AND TRIM(full_name) <> '' ORDER BY id DESC LIMIT 1",
            [$phone]
        );
        $name = trim((string) ($userByPhone['full_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $providerByPhone = db()->fetch(
            "SELECT full_name FROM providers WHERE phone = ? AND full_name IS NOT NULL AND TRIM(full_name) <> '' ORDER BY id DESC LIMIT 1",
            [$phone]
        );
        $name = trim((string) ($providerByPhone['full_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    $customerUserId = (int) ($order['user_id'] ?? 0);
    if ($customerUserId > 0) {
        return 'عميل #' . $customerUserId;
    }

    return 'عميل غير معروف';
}

function tableExistsByName($table)
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($safeTable === '') {
        return false;
    }

    $quotedTable = db()->getConnection()->quote($safeTable);
    $row = db()->fetch("SHOW TABLES LIKE {$quotedTable}");
    return !empty($row);
}

function tableColumnExistsByName($table, $column)
{
    static $cache = [];

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $cacheKey = $safeTable . ':' . $safeColumn;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!tableExistsByName($safeTable)) {
        $cache[$cacheKey] = false;
        return false;
    }

    $quotedColumn = db()->getConnection()->quote($safeColumn);
    $row = db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
    $cache[$cacheKey] = !empty($row);
    return $cache[$cacheKey];
}

function normalizeOrderProblemDetailsForAdmin($problemDetailsRaw): array
{
    if (is_array($problemDetailsRaw)) {
        return $problemDetailsRaw;
    }

    if (is_string($problemDetailsRaw) && trim($problemDetailsRaw) !== '') {
        $decoded = json_decode($problemDetailsRaw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

function normalizeAdminIntegerIds($value): array
{
    $ids = [];
    $push = static function ($raw) use (&$ids) {
        $id = (int) $raw;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    };

    if (is_object($value)) {
        $value = (array) $value;
    }

    if (is_array($value)) {
        foreach ($value as $item) {
            $push($item);
        }
        return array_values($ids);
    }

    if (is_numeric($value)) {
        $push($value);
        return array_values($ids);
    }

    if (!is_string($value)) {
        return [];
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $item) {
            $push($item);
        }
        return array_values($ids);
    }

    foreach (explode(',', $trimmed) as $item) {
        $push(trim($item));
    }

    return array_values($ids);
}

function normalizeProblemTypeIdsForAdmin(array $details, $fallbackId = null): array
{
    $ids = array_merge(
        normalizeAdminIntegerIds($details['problem_type_ids'] ?? []),
        normalizeAdminIntegerIds($details['type_option_ids'] ?? [])
    );

    if (!empty($details['type_option_id'])) {
        $ids = array_merge($ids, normalizeAdminIntegerIds($details['type_option_id']));
    }

    if ($fallbackId !== null && $fallbackId !== '') {
        $ids = array_merge($ids, normalizeAdminIntegerIds($fallbackId));
    }

    return array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
}

function fetchProblemTypeLabelsByIdsForAdmin(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (empty($ids) || !tableExistsByName('problem_detail_options')) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = db()->fetchAll(
        "SELECT id, title_ar, title_en, title_ur
         FROM problem_detail_options
         WHERE id IN ($placeholders)",
        $ids
    );

    $labelsById = [];
    foreach ($rows as $row) {
        $label = trim((string) ($row['title_ar'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['title_en'] ?? ''));
        }
        if ($label === '') {
            $label = trim((string) ($row['title_ur'] ?? ''));
        }
        if ($label !== '') {
            $labelsById[(int) $row['id']] = $label;
        }
    }

    $labels = [];
    foreach ($ids as $id) {
        if (!empty($labelsById[$id]) && !in_array($labelsById[$id], $labels, true)) {
            $labels[] = $labelsById[$id];
        }
    }

    return $labels;
}

function detectSpecialOrderModuleForAdmin($problemDetailsRaw): string
{
    $details = normalizeOrderProblemDetailsForAdmin($problemDetailsRaw);
    if (empty($details)) {
        return '';
    }

    $module = strtolower(trim((string) ($details['module'] ?? '')));
    $type = strtolower(trim((string) ($details['type'] ?? '')));

    if (
        isset($details['container_request'])
        || strpos($module, 'container') !== false
        || strpos($type, 'container') !== false
    ) {
        return 'container';
    }

    if (
        isset($details['furniture_request'])
        || strpos($module, 'furniture') !== false
        || strpos($type, 'furniture') !== false
    ) {
        return 'furniture';
    }

    return '';
}

function getSpecialOrderDisplayOverride($problemDetailsRaw): array
{
    $module = detectSpecialOrderModuleForAdmin($problemDetailsRaw);
    $meta = $module !== '' && function_exists('specialServiceCategoryDisplayMeta')
        ? specialServiceCategoryDisplayMeta($module)
        : [];

    if (!empty($meta)) {
        return [
            'name' => $meta['name_ar'] ?? null,
            'icon' => $meta['icon'] ?? ($meta['image'] ?? ($meta['fallback_icon'] ?? null)),
        ];
    }

    return [
        'name' => null,
        'icon' => null,
    ];
}

function resolveSpecialOrderServiceNamesForAdmin($problemDetailsRaw): array
{
    $details = normalizeOrderProblemDetailsForAdmin($problemDetailsRaw);
    $module = detectSpecialOrderModuleForAdmin($details);
    if ($module === '') {
        return [];
    }

    $names = [];
    $appendName = static function ($value) use (&$names): void {
        $name = trim((string) $value);
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
    };

    if ($module === 'container') {
        $container = $details['container_request'] ?? [];
        if ($container instanceof stdClass) {
            $container = (array) $container;
        }
        if (!is_array($container)) {
            $container = [];
        }

        $appendName($container['container_service_name'] ?? '');

        $selectedServices = $container['selected_services'] ?? ($details['selected_services'] ?? []);
        if ($selectedServices instanceof stdClass) {
            $selectedServices = (array) $selectedServices;
        }
        if (is_array($selectedServices)) {
            foreach ($selectedServices as $selectedService) {
                if ($selectedService instanceof stdClass) {
                    $selectedService = (array) $selectedService;
                }
                if (!is_array($selectedService)) {
                    continue;
                }
                $appendName(
                    $selectedService['name_ar']
                    ?? $selectedService['name']
                    ?? $selectedService['name_en']
                    ?? ''
                );
            }
        }

        if (empty($names)) {
            $size = trim((string) ($container['container_size'] ?? ''));
            $appendName($size !== '' ? 'طلب خدمة الحاويات - ' . $size : 'طلب خدمة الحاويات');
        }

        return $names;
    }

    if ($module === 'furniture') {
        $furniture = $details['furniture_request'] ?? [];
        if ($furniture instanceof stdClass) {
            $furniture = (array) $furniture;
        }
        if (is_array($furniture)) {
            $appendName($furniture['service_name'] ?? '');
            $appendName($furniture['area_name'] ?? '');
        }
        if (empty($names)) {
            $appendName('طلب نقل العفش');
        }
    }

    return $names;
}

function appendSpecialOrdersExclusionForAdmin(string &$where, array &$params, string $alias = 'o'): void
{
    $clauses = [];

    if (orderHasColumn('problem_details')) {
        $column = $alias . '.problem_details';
        $clauses[] = "(
            {$column} IS NULL
            OR {$column} = ''
            OR (
                {$column} NOT LIKE ?
                AND {$column} NOT LIKE ?
                AND {$column} NOT LIKE ?
                AND {$column} NOT LIKE ?
                AND {$column} NOT LIKE ?
                AND {$column} NOT LIKE ?
            )
        )";
        $params[] = '%"module":"container_rental"%';
        $params[] = '%"type":"container_rental"%';
        $params[] = '%"container_request"%';
        $params[] = '%"module":"furniture_moving"%';
        $params[] = '%"type":"furniture_moving"%';
        $params[] = '%"furniture_request"%';
    }

    if (tableExistsByName('container_requests') && tableColumnExistsByName('container_requests', 'source_order_id')) {
        $clauses[] = "{$alias}.id NOT IN (
            SELECT cr.source_order_id
            FROM container_requests cr
            WHERE cr.source_order_id IS NOT NULL
        )";
    }

    if (tableExistsByName('furniture_requests') && tableColumnExistsByName('furniture_requests', 'source_order_id')) {
        $clauses[] = "{$alias}.id NOT IN (
            SELECT fr.source_order_id
            FROM furniture_requests fr
            WHERE fr.source_order_id IS NOT NULL
        )";
    }

    if (!empty($clauses)) {
        $where .= ' AND ' . implode(' AND ', $clauses);
    }
}

function normalizePhoneForWhatsApp($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    if (strpos($raw, '+') === 0) {
        return '+' . preg_replace('/\D+/', '', substr($raw, 1));
    }

    return preg_replace('/\D+/', '', $raw);
}

function adminOrderDistanceKm($lat1, $lng1, $lat2, $lng2)
{
    $lat1 = (float) $lat1;
    $lng1 = (float) $lng1;
    $lat2 = (float) $lat2;
    $lng2 = (float) $lng2;

    $earthRadiusKm = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadiusKm * $c;
}

function providerOrderCoordinates(array $provider)
{
    $latCandidates = [
        $provider['current_lat'] ?? null,
        $provider['lat'] ?? null,
    ];
    $lngCandidates = [
        $provider['current_lng'] ?? null,
        $provider['lng'] ?? null,
    ];

    $lat = null;
    $lng = null;

    foreach ($latCandidates as $candidate) {
        if ($candidate === null || $candidate === '') {
            continue;
        }
        $value = (float) $candidate;
        if ($value >= -90 && $value <= 90) {
            $lat = $value;
            break;
        }
    }

    foreach ($lngCandidates as $candidate) {
        if ($candidate === null || $candidate === '') {
            continue;
        }
        $value = (float) $candidate;
        if ($value >= -180 && $value <= 180) {
            $lng = $value;
            break;
        }
    }

    if ($lat === null || $lng === null) {
        return null;
    }

    return ['lat' => $lat, 'lng' => $lng];
}

function decorateProvidersWithDistance(array $providers, $orderLat, $orderLng)
{
    $hasOrderCoordinates = is_numeric($orderLat)
        && is_numeric($orderLng)
        && (float) $orderLat >= -90
        && (float) $orderLat <= 90
        && (float) $orderLng >= -180
        && (float) $orderLng <= 180;

    $normalized = [];
    foreach ($providers as $provider) {
        $providerRow = $provider;
        $providerRow['distance_km'] = null;

        if ($hasOrderCoordinates) {
            $providerCoords = providerOrderCoordinates($providerRow);
            if ($providerCoords !== null) {
                $providerRow['distance_km'] = round(
                    adminOrderDistanceKm(
                        (float) $orderLat,
                        (float) $orderLng,
                        (float) $providerCoords['lat'],
                        (float) $providerCoords['lng']
                    ),
                    2
                );
            }
        }

        $normalized[] = $providerRow;
    }

    usort($normalized, function ($a, $b) {
        $aDistance = $a['distance_km'];
        $bDistance = $b['distance_km'];

        if ($aDistance === null && $bDistance !== null) {
            return 1;
        }
        if ($aDistance !== null && $bDistance === null) {
            return -1;
        }
        if ($aDistance !== null && $bDistance !== null && $aDistance !== $bDistance) {
            return $aDistance <=> $bDistance;
        }

        $aRating = isset($a['rating']) ? (float) $a['rating'] : 0.0;
        $bRating = isset($b['rating']) ? (float) $b['rating'] : 0.0;
        if ($aRating !== $bRating) {
            return $bRating <=> $aRating;
        }

        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    });

    return $normalized;
}

function createOrderAdminUserNotification($userId, $title, $body, $type = 'order', array $data = [])
{
    $userId = (int) $userId;
    if ($userId <= 0 || !tableExistsByName('notifications')) {
        return;
    }

    $allowedTypes = ['order', 'promotion', 'system', 'wallet', 'review'];
    $normalizedType = in_array($type, $allowedTypes, true) ? $type : 'order';
    $payloadJson = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    if ($payloadJson !== null && tableColumnExistsByName('notifications', 'data')) {
        db()->query(
            "INSERT INTO notifications (user_id, title, body, type, data, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$userId, $title, $body, $normalizedType, $payloadJson]
        );
        return;
    }

    db()->query(
        "INSERT INTO notifications (user_id, title, body, type, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())",
        [$userId, $title, $body, $normalizedType]
    );
}

function createOrderAdminProviderNotification($providerId, $title, $body, $type = 'order', array $data = [])
{
    $providerId = (int) $providerId;
    if ($providerId <= 0 || !tableExistsByName('notifications') || !tableColumnExistsByName('notifications', 'provider_id')) {
        return;
    }

    $allowedTypes = ['order', 'promotion', 'system', 'wallet', 'review'];
    $normalizedType = in_array($type, $allowedTypes, true) ? $type : 'order';
    $payloadJson = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    if ($payloadJson !== null && tableColumnExistsByName('notifications', 'data')) {
        db()->query(
            "INSERT INTO notifications (provider_id, title, body, type, data, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$providerId, $title, $body, $normalizedType, $payloadJson]
        );
        return;
    }

    db()->query(
        "INSERT INTO notifications (provider_id, title, body, type, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())",
        [$providerId, $title, $body, $normalizedType]
    );
}

function orderAdminOneSignalAppId()
{
    $settingsValue = trim((string) getAdminAppSetting('onesignal_app_id', ''));
    if ($settingsValue !== '') {
        return $settingsValue;
    }

    $legacySettingsValue = trim((string) getAdminAppSetting('one_signal_app_id', ''));
    if ($legacySettingsValue !== '') {
        return $legacySettingsValue;
    }

    $envValue = trim((string) (getenv('ONESIGNAL_APP_ID') ?: getenv('ONE_SIGNAL_APP_ID') ?: ''));
    if ($envValue !== '') {
        return $envValue;
    }

    return trim((string) (defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : ''));
}

function orderAdminOneSignalRestApiKey()
{
    $settingsValue = trim((string) getAdminAppSetting('onesignal_rest_api_key', ''));
    if ($settingsValue !== '') {
        return orderAdminNormalizeOneSignalRestApiKey($settingsValue);
    }

    $legacySettingsValue = trim((string) getAdminAppSetting('one_signal_rest_api_key', ''));
    if ($legacySettingsValue !== '') {
        return orderAdminNormalizeOneSignalRestApiKey($legacySettingsValue);
    }

    $envValue = trim((string) (getenv('ONESIGNAL_REST_API_KEY') ?: getenv('ONE_SIGNAL_REST_API_KEY') ?: ''));
    if ($envValue !== '') {
        return orderAdminNormalizeOneSignalRestApiKey($envValue);
    }

    return orderAdminNormalizeOneSignalRestApiKey(defined('ONESIGNAL_REST_API_KEY') ? ONESIGNAL_REST_API_KEY : '');
}

function orderAdminNormalizeOneSignalRestApiKey($raw)
{
    $value = trim((string) $raw);
    if ($value === '') {
        return '';
    }

    $value = trim($value, " \t\n\r\0\x0B\"'");
    $value = preg_replace('/^authorization\s*:\s*/i', '', $value);
    $value = preg_replace('/^(key|basic|bearer)\s+/i', '', trim((string) $value));
    return trim((string) $value, " \t\n\r\0\x0B\"'");
}

function orderAdminAppendOneSignalCandidate(array &$candidates, $value, $source, $isRestKey = false)
{
    $value = trim((string) $value);
    if ($isRestKey) {
        $value = orderAdminNormalizeOneSignalRestApiKey($value);
    }
    if ($value === '') {
        return;
    }

    foreach ($candidates as $candidate) {
        if (($candidate['value'] ?? '') === $value) {
            return;
        }
    }

    $candidates[] = [
        'value' => $value,
        'source' => (string) $source,
    ];
}

function orderAdminOneSignalAppIdCandidates()
{
    $candidates = [];

    orderAdminAppendOneSignalCandidate($candidates, getAdminAppSetting('onesignal_app_id', ''), 'app_settings:onesignal_app_id');
    orderAdminAppendOneSignalCandidate($candidates, getAdminAppSetting('one_signal_app_id', ''), 'app_settings:one_signal_app_id');
    orderAdminAppendOneSignalCandidate($candidates, getenv('ONESIGNAL_APP_ID') ?: '', 'ENV:ONESIGNAL_APP_ID');
    orderAdminAppendOneSignalCandidate($candidates, getenv('ONE_SIGNAL_APP_ID') ?: '', 'ENV:ONE_SIGNAL_APP_ID');
    orderAdminAppendOneSignalCandidate(
        $candidates,
        defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : '',
        'config:ONESIGNAL_APP_ID'
    );

    return $candidates;
}

function orderAdminOneSignalRestApiKeyCandidates()
{
    $candidates = [];

    orderAdminAppendOneSignalCandidate($candidates, getAdminAppSetting('onesignal_rest_api_key', ''), 'app_settings:onesignal_rest_api_key', true);
    orderAdminAppendOneSignalCandidate($candidates, getAdminAppSetting('one_signal_rest_api_key', ''), 'app_settings:one_signal_rest_api_key', true);
    orderAdminAppendOneSignalCandidate($candidates, getenv('ONESIGNAL_REST_API_KEY') ?: '', 'ENV:ONESIGNAL_REST_API_KEY', true);
    orderAdminAppendOneSignalCandidate($candidates, getenv('ONE_SIGNAL_REST_API_KEY') ?: '', 'ENV:ONE_SIGNAL_REST_API_KEY', true);
    orderAdminAppendOneSignalCandidate(
        $candidates,
        defined('ONESIGNAL_REST_API_KEY') ? ONESIGNAL_REST_API_KEY : '',
        'config:ONESIGNAL_REST_API_KEY',
        true
    );

    return $candidates;
}

function orderAdminOneSignalCredentialCandidates()
{
    $credentials = [];
    $seen = [];

    $appendCredential = static function ($appId, $apiKey, $source) use (&$credentials, &$seen) {
        $appId = trim((string) $appId);
        $apiKey = orderAdminNormalizeOneSignalRestApiKey($apiKey);
        if ($appId === '' || $apiKey === '') {
            return;
        }

        $fingerprint = $appId . '|' . $apiKey;
        if (isset($seen[$fingerprint])) {
            return;
        }
        $seen[$fingerprint] = true;

        $credentials[] = [
            'app_id' => $appId,
            'api_key' => $apiKey,
            'source' => (string) $source,
        ];
    };

    $dbAppId = getAdminAppSetting('onesignal_app_id', '');
    $dbLegacyAppId = getAdminAppSetting('one_signal_app_id', '');
    $dbRestKey = getAdminAppSetting('onesignal_rest_api_key', '');
    $dbLegacyRestKey = getAdminAppSetting('one_signal_rest_api_key', '');
    $envAppId = getenv('ONESIGNAL_APP_ID') ?: '';
    $envLegacyAppId = getenv('ONE_SIGNAL_APP_ID') ?: '';
    $envRestKey = getenv('ONESIGNAL_REST_API_KEY') ?: '';
    $envLegacyRestKey = getenv('ONE_SIGNAL_REST_API_KEY') ?: '';
    $configAppId = defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : '';
    $configRestKey = defined('ONESIGNAL_REST_API_KEY') ? ONESIGNAL_REST_API_KEY : '';

    $appendCredential($dbAppId, $dbRestKey, 'app_settings:onesignal_app_id + app_settings:onesignal_rest_api_key');
    $appendCredential($dbLegacyAppId, $dbLegacyRestKey, 'app_settings:one_signal_app_id + app_settings:one_signal_rest_api_key');
    $appendCredential($dbAppId, $dbLegacyRestKey, 'app_settings:onesignal_app_id + app_settings:one_signal_rest_api_key');
    $appendCredential($dbLegacyAppId, $dbRestKey, 'app_settings:one_signal_app_id + app_settings:onesignal_rest_api_key');
    $appendCredential($envAppId, $envRestKey, 'ENV:ONESIGNAL_APP_ID + ENV:ONESIGNAL_REST_API_KEY');
    $appendCredential($envLegacyAppId, $envLegacyRestKey, 'ENV:ONE_SIGNAL_APP_ID + ENV:ONE_SIGNAL_REST_API_KEY');
    $appendCredential($configAppId, $configRestKey, 'config:ONESIGNAL_APP_ID + config:ONESIGNAL_REST_API_KEY');

    foreach (orderAdminOneSignalAppIdCandidates() as $appCandidate) {
        foreach (orderAdminOneSignalRestApiKeyCandidates() as $keyCandidate) {
            $appendCredential(
                $appCandidate['value'] ?? '',
                $keyCandidate['value'] ?? '',
                ($appCandidate['source'] ?? 'App ID') . ' + ' . ($keyCandidate['source'] ?? 'REST API Key')
            );
        }
    }

    return $credentials;
}

function orderAdminOneSignalCredentialError($statusCode, $responseText)
{
    $statusCode = (int) $statusCode;
    if ($statusCode === 401 || $statusCode === 403) {
        return true;
    }

    $lower = strtolower((string) $responseText);
    return strpos($lower, 'access denied') !== false
        || strpos($lower, 'authorization') !== false
        || strpos($lower, 'valid api key') !== false
        || strpos($lower, 'failed to parse app_id') !== false
        || strpos($lower, 'app_id is present but malformed') !== false
        || (strpos($lower, 'app id') !== false && strpos($lower, 'not found') !== false)
        || (strpos($lower, 'app_id') !== false && strpos($lower, 'not found') !== false);
}

function orderAdminOneSignalRecipientsFromResponse($responseText)
{
    $decoded = json_decode((string) $responseText, true);
    if (!is_array($decoded) || !array_key_exists('recipients', $decoded)) {
        return null;
    }

    return max(0, (int) $decoded['recipients']);
}

function orderAdminPostOneSignalPayload(array $payload)
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'recipients' => null,
            'error' => 'cURL is not available',
        ];
    }

    $credentials = orderAdminOneSignalCredentialCandidates();
    if (empty($credentials)) {
        return [
            'ok' => false,
            'recipients' => null,
            'error' => 'OneSignal credentials are missing',
        ];
    }

    $credentialErrors = [];
    foreach ($credentials as $credential) {
        $candidatePayload = $payload;
        $candidatePayload['app_id'] = (string) ($credential['app_id'] ?? '');
        $jsonPayload = json_encode($candidatePayload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            return [
                'ok' => false,
                'recipients' => null,
                'error' => 'JSON encode failed',
            ];
        }

        $ch = curl_init('https://api.onesignal.com/notifications');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Key ' . orderAdminNormalizeOneSignalRestApiKey($credential['api_key'] ?? ''),
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
            return [
                'ok' => true,
                'recipients' => orderAdminOneSignalRecipientsFromResponse($response),
                'error' => '',
            ];
        }

        $details = $curlError !== '' ? $curlError : trim((string) $response);
        if ($details === '') {
            $details = 'HTTP ' . $statusCode;
        }

        if (orderAdminOneSignalCredentialError($statusCode, $details)) {
            $credentialErrors[] = (string) ($credential['source'] ?? 'unknown') . ': ' . $details;
            continue;
        }

        return [
            'ok' => false,
            'recipients' => null,
            'error' => $details,
        ];
    }

    return [
        'ok' => false,
        'recipients' => null,
        'error' => !empty($credentialErrors) ? implode(' | ', $credentialErrors) : 'No valid OneSignal credentials accepted',
    ];
}

function orderAdminOneSignalTargetDelivered(array $result)
{
    if (empty($result['ok'])) {
        return false;
    }

    return !array_key_exists('recipients', $result)
        || $result['recipients'] === null
        || (int) $result['recipients'] > 0;
}

function orderAdminOneSignalDeviceTokens($table, $id)
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if (!in_array($safeTable, ['users', 'providers'], true) || (int) $id <= 0) {
        return [];
    }

    if (!tableExistsByName($safeTable) || !tableColumnExistsByName($safeTable, 'device_token')) {
        return [];
    }

    $row = db()->fetch("SELECT device_token FROM `{$safeTable}` WHERE id = ? LIMIT 1", [(int) $id]);
    $token = trim((string) ($row['device_token'] ?? ''));

    return strlen($token) >= 8 ? [$token] : [];
}

function orderAdminOneSignalExternalIdAllowed($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    $restricted = [
        'na', 'null', '0', '1', '-1', 'unqualified', 'all', 'nan',
        '00000000-0000-0000-0000-000000000000', '-', 'none', 'ok',
        '123abc', 'unknown', 'invalid_user', 'undefined', 'not set',
    ];

    return !in_array(strtolower($value), $restricted, true);
}

function orderAdminOneSignalAliasMapForTarget($externalUserId)
{
    $external = trim((string) $externalUserId);
    if ($external === '') {
        return [];
    }

    $aliases = [];
    $externalIds = [];
    if (orderAdminOneSignalExternalIdAllowed($external)) {
        $externalIds[] = $external;
    }

    if (preg_match('/^provider_(\d+)$/', $external, $providerMatch)) {
        $aliases['darfix_provider_id'] = [$providerMatch[1]];
    } elseif (preg_match('/^user_(\d+)$/', $external, $userMatch)) {
        if (orderAdminOneSignalExternalIdAllowed($userMatch[1])) {
            $externalIds[] = $userMatch[1];
        }
        $aliases['darfix_user_id'] = [$userMatch[1]];
    } elseif (ctype_digit($external)) {
        $externalIds[] = 'user_' . $external;
        $aliases['darfix_user_id'] = [$external];
    }

    if (!empty($externalIds)) {
        $aliases['external_id'] = $externalIds;
    }

    foreach ($aliases as $key => $values) {
        $aliases[$key] = array_values(array_unique(array_filter(array_map(static function ($value) {
            return trim((string) $value);
        }, $values), static function ($value) {
            return $value !== '';
        })));
        if (empty($aliases[$key])) {
            unset($aliases[$key]);
        }
    }

    return $aliases;
}

function orderAdminOneSignalTagFiltersForTarget($externalUserId)
{
    $external = trim((string) $externalUserId);
    if ($external === '') {
        return [];
    }

    $accountType = '';
    $tagKey = '';
    $tagValue = '';

    if (preg_match('/^provider_(\d+)$/', $external, $providerMatch)) {
        $accountType = 'provider';
        $tagKey = 'darfix_provider_id';
        $tagValue = $providerMatch[1];
    } elseif (preg_match('/^user_(\d+)$/', $external, $userMatch)) {
        $accountType = 'user';
        $tagKey = 'darfix_user_id';
        $tagValue = $userMatch[1];
    } elseif (ctype_digit($external)) {
        $accountType = 'user';
        $tagKey = 'darfix_user_id';
        $tagValue = $external;
    }

    if ($accountType === '' || $tagKey === '' || $tagValue === '') {
        return [];
    }

    return [
        ['field' => 'tag', 'key' => 'darfix_account_type', 'relation' => '=', 'value' => $accountType],
        ['operator' => 'AND'],
        ['field' => 'tag', 'key' => $tagKey, 'relation' => '=', 'value' => $tagValue],
    ];
}

function sendOrderAdminOneSignalToExternalUser($externalUserId, $title, $body, array $data = [], array $subscriptionIds = [])
{
    $external = trim((string) $externalUserId);
    if ($external === '') {
        return false;
    }

    $basePayload = [
        'target_channel' => 'push',
        'headings' => [
            'ar' => $title,
            'en' => $title,
        ],
        'contents' => [
            'ar' => $body,
            'en' => $body,
        ],
        'data' => $data,
    ];

    $aliasPayload = $basePayload;
    $aliasPayload['include_aliases'] = orderAdminOneSignalAliasMapForTarget($external);

    $aliasResult = orderAdminPostOneSignalPayload($aliasPayload);
    if (orderAdminOneSignalTargetDelivered($aliasResult)) {
        return true;
    }

    $tagFilters = orderAdminOneSignalTagFiltersForTarget($external);
    $tagResult = ['error' => ''];
    if (!empty($tagFilters)) {
        $tagPayload = $basePayload;
        $tagPayload['filters'] = $tagFilters;

        $tagResult = orderAdminPostOneSignalPayload($tagPayload);
        if (orderAdminOneSignalTargetDelivered($tagResult)) {
            return true;
        }
    }

    $subscriptionIds = array_values(array_unique(array_filter(array_map(static function ($value) {
        return trim((string) $value);
    }, $subscriptionIds), static function ($value) {
        return strlen($value) >= 8;
    })));

    foreach (array_chunk($subscriptionIds, 200) as $chunk) {
        $subscriptionPayload = $basePayload;
        $subscriptionPayload['include_subscription_ids'] = array_values($chunk);
        $subscriptionResult = orderAdminPostOneSignalPayload($subscriptionPayload);
        if (orderAdminOneSignalTargetDelivered($subscriptionResult)) {
            return true;
        }

        $legacyPayload = $basePayload;
        $legacyPayload['include_player_ids'] = array_values($chunk);
        $legacyResult = orderAdminPostOneSignalPayload($legacyPayload);
        if (orderAdminOneSignalTargetDelivered($legacyResult)) {
            return true;
        }
    }

    error_log(
        'Admin order OneSignal push failed for target ' . $external
        . '; alias_error=' . (string) ($aliasResult['error'] ?? '')
        . '; tag_error=' . (string) ($tagResult['error'] ?? '')
    );

    return false;
}

function notifyOrderCustomerFromAdmin($orderId, $title, $body, array $extraData = [])
{
    $orderId = (int) $orderId;
    if ($orderId <= 0) {
        return;
    }

    $row = db()->fetch("SELECT user_id FROM orders WHERE id = ? LIMIT 1", [$orderId]);
    $userId = (int) ($row['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    $payload = array_merge([
        'event' => 'order_update',
        'order_id' => $orderId,
        'deep_link' => 'order:' . $orderId,
    ], $extraData);

    createOrderAdminUserNotification($userId, $title, $body, 'order', $payload);
    sendOrderAdminOneSignalToExternalUser(
        (string) $userId,
        $title,
        $body,
        $payload,
        orderAdminOneSignalDeviceTokens('users', $userId)
    );
}

function notifyOrderProvidersFromAdmin($orderId, array $providerIds, $title, $body, array $extraData = [])
{
    $orderId = (int) $orderId;
    if ($orderId <= 0) {
        return;
    }

    $ids = normalizeIntegerList($providerIds);
    if (empty($ids)) {
        return;
    }

    $payload = array_merge([
        'event' => 'order_update',
        'order_id' => $orderId,
        'deep_link' => 'order:' . $orderId,
        'target' => 'provider',
    ], $extraData);

    foreach ($ids as $providerId) {
        createOrderAdminProviderNotification((int) $providerId, $title, $body, 'order', $payload);
        sendOrderAdminOneSignalToExternalUser(
            'provider_' . (int) $providerId,
            $title,
            $body,
            $payload,
            orderAdminOneSignalDeviceTokens('providers', (int) $providerId)
        );
    }
}

function ensureOrderLiveLocationTableForAdmin()
{
    db()->query("CREATE TABLE IF NOT EXISTS `order_live_locations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `provider_id` INT NOT NULL,
        `lat` DECIMAL(10,8) NOT NULL,
        `lng` DECIMAL(11,8) NOT NULL,
        `accuracy` DECIMAL(10,2) NULL,
        `speed` DECIMAL(10,2) NULL,
        `heading` DECIMAL(10,2) NULL,
        `created_at` DATETIME NOT NULL,
        INDEX `idx_oll_order` (`order_id`),
        INDEX `idx_oll_provider` (`provider_id`),
        INDEX `idx_oll_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function fetchLatestOrderLiveLocationForAdmin($orderId, $providerId = 0)
{
    if ($orderId <= 0) {
        return null;
    }

    ensureOrderLiveLocationTableForAdmin();
    if (!tableExistsByName('order_live_locations')) {
        return null;
    }

    $params = [$orderId];
    $sql = "SELECT oll.*,
                   p.full_name AS provider_name,
                   p.phone AS provider_phone
            FROM order_live_locations oll
            LEFT JOIN providers p ON p.id = oll.provider_id
            WHERE oll.order_id = ?";

    if ((int) $providerId > 0) {
        $sql .= " AND oll.provider_id = ?";
        $params[] = (int) $providerId;
    }

    $sql .= " ORDER BY oll.created_at DESC, oll.id DESC LIMIT 1";
    $row = db()->fetch($sql, $params);
    if (!$row) {
        return null;
    }

    return [
        'order_id' => (int) ($row['order_id'] ?? 0),
        'provider_id' => (int) ($row['provider_id'] ?? 0),
        'provider_name' => $row['provider_name'] ?? null,
        'provider_phone' => $row['provider_phone'] ?? null,
        'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
        'lng' => isset($row['lng']) ? (float) $row['lng'] : null,
        'accuracy' => isset($row['accuracy']) ? (float) $row['accuracy'] : null,
        'speed' => isset($row['speed']) ? (float) $row['speed'] : null,
        'heading' => isset($row['heading']) ? (float) $row['heading'] : null,
        'captured_at' => $row['created_at'] ?? null,
    ];
}

function isValidAdminMapCoordinatePair($lat, $lng)
{
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }

    $latValue = (float) $lat;
    $lngValue = (float) $lng;
    return $latValue >= -90 && $latValue <= 90 && $lngValue >= -180 && $lngValue <= 180;
}

function buildAdminLiveTrackingFeedPayload($orderId)
{
    $orderId = (int) $orderId;
    if ($orderId <= 0) {
        return null;
    }

    $order = db()->fetch(
        "SELECT o.id, o.order_number, o.status, o.provider_id, o.lat, o.lng, o.address,
                p.full_name AS provider_name
         FROM orders o
         LEFT JOIN providers p ON p.id = o.provider_id
         WHERE o.id = ?
         LIMIT 1",
        [$orderId]
    );
    if (!$order) {
        return null;
    }

    $orderStatus = strtolower(trim((string) ($order['status'] ?? '')));
    $trackingEnabled = $orderStatus === 'on_the_way';

    $customerLat = null;
    $customerLng = null;
    if (isValidAdminMapCoordinatePair($order['lat'] ?? null, $order['lng'] ?? null)) {
        $customerLat = (float) $order['lat'];
        $customerLng = (float) $order['lng'];
    }

    $providerId = (int) ($order['provider_id'] ?? 0);
    $liveLocation = fetchLatestOrderLiveLocationForAdmin($orderId, $providerId);
    $liveLat = null;
    $liveLng = null;
    if (!empty($liveLocation) && isValidAdminMapCoordinatePair($liveLocation['lat'] ?? null, $liveLocation['lng'] ?? null)) {
        $liveLat = (float) $liveLocation['lat'];
        $liveLng = (float) $liveLocation['lng'];
    } else {
        $liveLocation = null;
    }

    $capturedAt = $liveLocation['captured_at'] ?? null;
    $secondsSinceUpdate = null;
    if ($capturedAt) {
        $capturedTs = strtotime((string) $capturedAt);
        if ($capturedTs !== false) {
            $secondsSinceUpdate = max(0, time() - $capturedTs);
        }
    }

    return [
        'order_id' => (int) $order['id'],
        'order_number' => (string) ($order['order_number'] ?? ''),
        'status' => $orderStatus,
        'tracking_enabled' => $trackingEnabled,
        'provider_id' => $providerId > 0 ? $providerId : null,
        'provider_name' => trim((string) ($order['provider_name'] ?? '')),
        'customer' => [
            'lat' => $customerLat,
            'lng' => $customerLng,
            'address' => trim((string) ($order['address'] ?? '')),
        ],
        'live_location' => $liveLocation ? [
            'lat' => $liveLat,
            'lng' => $liveLng,
            'accuracy' => isset($liveLocation['accuracy']) ? (float) $liveLocation['accuracy'] : null,
            'speed' => isset($liveLocation['speed']) ? (float) $liveLocation['speed'] : null,
            'heading' => isset($liveLocation['heading']) ? (float) $liveLocation['heading'] : null,
            'captured_at' => $capturedAt,
            'seconds_since_update' => $secondsSinceUpdate,
            'provider_name' => trim((string) ($liveLocation['provider_name'] ?? '')),
            'provider_phone' => trim((string) ($liveLocation['provider_phone'] ?? '')),
        ] : null,
        'server_time' => date('Y-m-d H:i:s'),
    ];
}

function normalizeIntegerList($value)
{
    $ids = [];
    $push = function ($raw) use (&$ids) {
        $id = (int) $raw;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    };

    if (is_array($value)) {
        foreach ($value as $item) {
            $push($item);
        }
        return array_values($ids);
    }

    if (is_numeric($value)) {
        $push($value);
        return array_values($ids);
    }

    if (!is_string($value)) {
        return [];
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $item) {
            $push($item);
        }
        return array_values($ids);
    }

    foreach (explode(',', $trimmed) as $item) {
        $push(trim($item));
    }

    return array_values($ids);
}

function ensureOrderRelationTables()
{
    db()->query("CREATE TABLE IF NOT EXISTS `order_services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `service_id` INT NULL,
        `service_name` VARCHAR(255) NULL,
        `is_custom` TINYINT(1) NOT NULL DEFAULT 0,
        `notes` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_order_services_order` (`order_id`),
        INDEX `idx_order_services_service` (`service_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `order_providers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `provider_id` INT NOT NULL,
        `assignment_status` VARCHAR(32) NOT NULL DEFAULT 'assigned',
        `assigned_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_order_provider` (`order_id`, `provider_id`),
        INDEX `idx_order_providers_order` (`order_id`),
        INDEX `idx_order_providers_provider` (`provider_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function fetchOrderServiceItemsForAdmin($orderId, $problemDetailsRaw = null)
{
    $items = [];
    $specialServiceNames = resolveSpecialOrderServiceNamesForAdmin($problemDetailsRaw);
    if (!empty($specialServiceNames)) {
        foreach ($specialServiceNames as $serviceName) {
            $items[] = [
                'id' => 0,
                'service_id' => null,
                'service_name' => $serviceName,
                'is_custom' => false,
                'notes' => '',
            ];
        }
        return $items;
    }

    if (tableExistsByName('order_services')) {
        $rows = db()->fetchAll(
            "SELECT os.id, os.service_id, os.service_name, os.is_custom, os.notes, s.name_ar AS service_name_ar
             FROM order_services os
             LEFT JOIN services s ON s.id = os.service_id
             WHERE os.order_id = ?
             ORDER BY os.id ASC",
            [$orderId]
        );

        foreach ($rows as $row) {
            $serviceName = trim((string) ($row['service_name'] ?? ''));
            if ($serviceName === '') {
                $serviceName = trim((string) ($row['service_name_ar'] ?? ''));
            }
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'service_id' => !empty($row['service_id']) ? (int) $row['service_id'] : null,
                'service_name' => $serviceName,
                'is_custom' => (int) ($row['is_custom'] ?? 0) === 1,
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];
        }
    }

    if (!empty($items)) {
        return $items;
    }

    $problemDetails = [];
    if (is_string($problemDetailsRaw)) {
        $decoded = json_decode($problemDetailsRaw, true);
        if (is_array($decoded)) {
            $problemDetails = $decoded;
        }
    } elseif (is_array($problemDetailsRaw)) {
        $problemDetails = $problemDetailsRaw;
    }

    $serviceIds = normalizeIntegerList($problemDetails['service_type_ids'] ?? ($problemDetails['sub_services'] ?? []));
    if (!empty($serviceIds)) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $serviceRows = db()->fetchAll("SELECT id, name_ar FROM services WHERE id IN ($placeholders)", $serviceIds);
        $nameMap = [];
        foreach ($serviceRows as $serviceRow) {
            $nameMap[(int) $serviceRow['id']] = $serviceRow['name_ar'];
        }
        foreach ($serviceIds as $serviceId) {
            $items[] = [
                'id' => 0,
                'service_id' => $serviceId,
                'service_name' => $nameMap[$serviceId] ?? ('خدمة #' . $serviceId),
                'is_custom' => false,
                'notes' => '',
            ];
        }
    }

    $customService = $problemDetails['custom_service'] ?? null;
    if (is_array($customService)) {
        $customTitle = trim((string) ($customService['title'] ?? ''));
        $customDescription = trim((string) ($customService['description'] ?? ''));
        if ($customTitle !== '' || $customDescription !== '') {
            if ($customTitle === '') {
                $customTitle = 'خدمة أخرى';
            }
            $items[] = [
                'id' => 0,
                'service_id' => null,
                'service_name' => $customTitle,
                'is_custom' => true,
                'notes' => $customDescription,
            ];
        }
    }

    return $items;
}

function fetchOrderAssignedProvidersForAdmin($orderId, array $orderRow = [])
{
    $items = [];
    if (tableExistsByName('order_providers')) {
        $rows = db()->fetchAll(
            "SELECT op.provider_id, op.assignment_status, op.assigned_at,
                    p.full_name AS provider_name, p.phone AS provider_phone, p.rating AS provider_rating
             FROM order_providers op
             LEFT JOIN providers p ON p.id = op.provider_id
             WHERE op.order_id = ?
               AND op.assignment_status NOT IN ('cancelled', 'rejected')
             ORDER BY op.id ASC",
            [$orderId]
        );

        foreach ($rows as $row) {
            $providerId = (int) ($row['provider_id'] ?? 0);
            if ($providerId <= 0) {
                continue;
            }
            $items[] = [
                'provider_id' => $providerId,
                'provider_name' => $row['provider_name'] ?? null,
                'provider_phone' => $row['provider_phone'] ?? null,
                'provider_rating' => isset($row['provider_rating']) ? (float) $row['provider_rating'] : null,
                'assignment_status' => $row['assignment_status'] ?? 'assigned',
                'assigned_at' => $row['assigned_at'] ?? null,
                'is_primary' => !empty($orderRow['provider_id']) && (int) $orderRow['provider_id'] === $providerId,
            ];
        }
    }

    if (empty($items) && !empty($orderRow['provider_id'])) {
        $items[] = [
            'provider_id' => (int) $orderRow['provider_id'],
            'provider_name' => $orderRow['provider_name'] ?? null,
            'provider_phone' => $orderRow['provider_phone'] ?? null,
            'provider_rating' => isset($orderRow['provider_rating']) ? (float) $orderRow['provider_rating'] : null,
            'assignment_status' => $orderRow['status'] ?? 'assigned',
            'assigned_at' => $orderRow['created_at'] ?? null,
            'is_primary' => true,
        ];
    }

    return $items;
}

function assignProvidersToOrderForAdmin($orderId, array $providerIds, &$resolvedProviderIds = [], &$syncSummary = [])
{
    $orderId = (int) $orderId;
    $providerIds = normalizeIntegerList($providerIds);
    $resolvedProviderIds = [];
    $syncSummary = [
        'selected_provider_ids' => [],
        'added_provider_ids' => [],
        'removed_provider_ids' => [],
        'blocked_remove_provider_ids' => [],
        'active_provider_ids' => [],
        'primary_provider_id' => null,
    ];

    ensureOrderRelationTables();

    $order = db()->fetch("SELECT id, provider_id, status FROM orders WHERE id = ? LIMIT 1", [$orderId]);
    if (!$order) {
        return 0;
    }

    $validIds = [];
    if (!empty($providerIds)) {
        $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
        $validProviders = db()->fetchAll(
            "SELECT id FROM providers WHERE id IN ($placeholders) AND status = 'approved'",
            $providerIds
        );
        $validLookup = [];
        foreach ($validProviders as $provider) {
            $validLookup[(int) $provider['id']] = true;
        }
        foreach ($providerIds as $providerId) {
            if (!empty($validLookup[$providerId]) && !in_array($providerId, $validIds, true)) {
                $validIds[] = $providerId;
            }
        }
    }
    $currentRows = db()->fetchAll(
        "SELECT provider_id, assignment_status
         FROM order_providers
         WHERE order_id = ?",
        [$orderId]
    );
    $currentStatusByProvider = [];
    foreach ($currentRows as $row) {
        $providerId = (int) ($row['provider_id'] ?? 0);
        if ($providerId > 0) {
            $currentStatusByProvider[$providerId] = strtolower((string) ($row['assignment_status'] ?? 'assigned'));
        }
    }

    foreach ($providerIds as $providerId) {
        if (
            !empty($currentStatusByProvider[$providerId])
            && !in_array($providerId, $validIds, true)
        ) {
            $validIds[] = $providerId;
        }
    }
    $resolvedProviderIds = $validIds;
    $syncSummary['selected_provider_ids'] = $validIds;

    $protectedStatuses = ['accepted', 'in_progress', 'completed', 'on_the_way', 'arrived'];
    $removeIds = [];
    foreach ($currentStatusByProvider as $providerId => $assignmentStatus) {
        if (in_array($providerId, $validIds, true)) {
            continue;
        }
        if (in_array($assignmentStatus, $protectedStatuses, true)) {
            $syncSummary['blocked_remove_provider_ids'][] = $providerId;
            continue;
        }
        $removeIds[] = $providerId;
    }

    if (!empty($removeIds)) {
        $removePlaceholders = implode(',', array_fill(0, count($removeIds), '?'));
        db()->query(
            "DELETE FROM order_providers
             WHERE order_id = ?
               AND provider_id IN ($removePlaceholders)",
            array_merge([$orderId], $removeIds)
        );
        $syncSummary['removed_provider_ids'] = $removeIds;
    }

    $inserted = 0;
    foreach ($validIds as $providerId) {
        $previousStatus = $currentStatusByProvider[$providerId] ?? '';
        db()->query(
            "INSERT INTO order_providers (order_id, provider_id, assignment_status, assigned_at)
             VALUES (?, ?, 'assigned', NOW())
             ON DUPLICATE KEY UPDATE
                assignment_status = CASE
                    WHEN assignment_status IN ('accepted', 'in_progress', 'completed') THEN assignment_status
                    ELSE 'assigned'
                END,
                assigned_at = COALESCE(assigned_at, NOW())",
            [$orderId, $providerId]
        );
        if ($previousStatus === '' || in_array($previousStatus, ['cancelled', 'rejected'], true)) {
            $syncSummary['added_provider_ids'][] = $providerId;
        }
        $inserted++;
    }

    $activeRows = db()->fetchAll(
        "SELECT provider_id, assignment_status
         FROM order_providers
         WHERE order_id = ?
           AND assignment_status NOT IN ('cancelled', 'rejected')
         ORDER BY FIELD(assignment_status, 'in_progress', 'accepted', 'completed', 'assigned'), id ASC",
        [$orderId]
    );
    $activeProviderIds = [];
    foreach ($activeRows as $row) {
        $providerId = (int) ($row['provider_id'] ?? 0);
        if ($providerId > 0 && !in_array($providerId, $activeProviderIds, true)) {
            $activeProviderIds[] = $providerId;
        }
    }
    $syncSummary['active_provider_ids'] = $activeProviderIds;

    $currentPrimaryId = (int) ($order['provider_id'] ?? 0);
    $primaryProviderId = null;
    if ($currentPrimaryId > 0 && in_array($currentPrimaryId, $activeProviderIds, true)) {
        $primaryProviderId = $currentPrimaryId;
    } elseif (!empty($validIds)) {
        $primaryProviderId = $validIds[0];
    } elseif (!empty($activeProviderIds)) {
        $primaryProviderId = $activeProviderIds[0];
    }
    $syncSummary['primary_provider_id'] = $primaryProviderId;

    if ($primaryProviderId !== null && $primaryProviderId > 0) {
        db()->query(
            "UPDATE orders
             SET provider_id = ?,
                 status = CASE WHEN status = 'pending' THEN 'assigned' ELSE status END
             WHERE id = ?",
            [$primaryProviderId, $orderId]
        );
    } else {
        db()->query(
            "UPDATE orders
             SET provider_id = NULL,
                 status = CASE WHEN status = 'assigned' THEN 'pending' ELSE status END
             WHERE id = ?",
            [$orderId]
        );
    }

    return $inserted;
}

function ensureOrderPaymentGatewayColumns()
{
    if (!tableExistsByName('orders')) {
        return;
    }

    $columns = [
        'myfatoorah_invoice_id' => 'VARCHAR(100) NULL',
        'myfatoorah_payment_url' => 'TEXT NULL',
        'myfatoorah_payment_method_id' => 'INT NULL',
        'myfatoorah_payment_id' => 'VARCHAR(150) NULL',
        'myfatoorah_invoice_status' => 'VARCHAR(50) NULL',
        'myfatoorah_last_status_at' => 'DATETIME NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!orderHasColumn($column)) {
            db()->query("ALTER TABLE `orders` ADD COLUMN `{$column}` {$definition}");
        }
    }

    orderHasColumn('', true);
}

function getAdminAppSetting($key, $default = '')
{
    if (!tableExistsByName('app_settings')) {
        return $default;
    }

    $row = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1", [$key]);
    if (!$row) {
        return $default;
    }

    $value = trim((string) ($row['setting_value'] ?? ''));
    return $value !== '' ? $value : $default;
}

function getAdminMyFatoorahConfig()
{
    $enabledRaw = strtolower(getAdminAppSetting('myfatoorah_enabled', '1'));
    $enabled = in_array($enabledRaw, ['1', 'true', 'yes', 'on'], true);

    $baseUrl = getAdminAppSetting(
        'myfatoorah_base_url',
        (string) (defined('MYFATOORAH_BASE_URL') ? MYFATOORAH_BASE_URL : 'https://api-sa.myfatoorah.com')
    );
    $baseUrl = rtrim($baseUrl, '/');

    $dbToken = getAdminAppSetting('myfatoorah_token', '');
    $envToken = trim((string) (defined('MYFATOORAH_TOKEN') ? MYFATOORAH_TOKEN : ''));
    $token = $dbToken !== '' ? $dbToken : $envToken;

    return [
        'enabled' => $enabled,
        'base_url' => $baseUrl !== '' ? $baseUrl : 'https://api-sa.myfatoorah.com',
        'token' => trim($token),
    ];
}

function adminMyFatoorahRequest($endpoint, array $payload)
{
    $config = getAdminMyFatoorahConfig();
    if (!$config['enabled']) {
        return [
            'success' => false,
            'status_code' => 503,
            'message' => 'بوابة الدفع MyFatoorah معطلة من الإعدادات',
            'data' => null,
        ];
    }
    if ($config['token'] === '') {
        return [
            'success' => false,
            'status_code' => 503,
            'message' => 'MyFatoorah Token غير مضبوط في الإعدادات',
            'data' => null,
        ];
    }

    $url = $config['base_url'] . '/' . ltrim($endpoint, '/');
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return [
            'success' => false,
            'status_code' => 500,
            'message' => 'Invalid request payload',
            'data' => null,
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($rawResponse === false) {
        return [
            'success' => false,
            'status_code' => 0,
            'message' => $curlError !== '' ? $curlError : 'Payment gateway connection failed',
            'data' => null,
        ];
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => 'Invalid payment gateway response',
            'data' => null,
        ];
    }

    $isSuccess = (bool) ($decoded['IsSuccess'] ?? false);
    if (!$isSuccess) {
        $message = trim((string) ($decoded['Message'] ?? 'Payment gateway request failed'));
        if ($message === '' && !empty($decoded['ValidationErrors']) && is_array($decoded['ValidationErrors'])) {
            $firstError = $decoded['ValidationErrors'][0] ?? [];
            if (is_array($firstError)) {
                $message = trim((string) ($firstError['Error'] ?? 'Payment validation failed'));
            } else {
                $message = trim((string) $firstError);
            }
        }
        if ($message === '') {
            $message = 'Payment gateway request failed';
        }

        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => $message,
            'data' => $decoded['Data'] ?? null,
        ];
    }

    return [
        'success' => true,
        'status_code' => $statusCode,
        'message' => trim((string) ($decoded['Message'] ?? '')),
        'data' => $decoded['Data'] ?? null,
    ];
}

function adminChooseMyFatoorahMethodId($methods)
{
    if (!is_array($methods) || empty($methods)) {
        return null;
    }

    $preferredCodes = ['md', 'vm', 'ap', 'stcpay', 'knet'];
    foreach ($preferredCodes as $code) {
        foreach ($methods as $method) {
            if (!is_array($method)) {
                continue;
            }
            $methodCode = strtolower(trim((string) ($method['PaymentMethodCode'] ?? '')));
            $methodId = (int) ($method['PaymentMethodId'] ?? 0);
            if ($methodCode === $code && $methodId > 0) {
                return $methodId;
            }
        }
    }

    foreach ($methods as $method) {
        if (!is_array($method)) {
            continue;
        }
        $methodId = (int) ($method['PaymentMethodId'] ?? 0);
        if ($methodId > 0) {
            return $methodId;
        }
    }

    return null;
}

function buildAdminMyFatoorahCallbackUrls($orderId)
{
    $orderId = (int) $orderId;
    $baseAppUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    if ($baseAppUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseAppUrl = $scheme . '://' . $host . '/admin-panel';
    }

    $callbackBase = $baseAppUrl . '/api/mobile/orders.php';
    return [
        'callback_url' => $callbackBase . '?action=myfatoorah_callback&order_id=' . $orderId,
        'error_url' => $callbackBase . '?action=myfatoorah_error&order_id=' . $orderId,
    ];
}

function updateOrderMyFatoorahMetaForAdmin($orderId, array $meta)
{
    $updates = [];

    if (orderHasColumn('myfatoorah_invoice_id') && isset($meta['invoice_id'])) {
        $invoiceId = trim((string) $meta['invoice_id']);
        if ($invoiceId !== '') {
            $updates['myfatoorah_invoice_id'] = $invoiceId;
        }
    }

    if (orderHasColumn('myfatoorah_payment_url') && isset($meta['payment_url'])) {
        $paymentUrl = trim((string) $meta['payment_url']);
        if ($paymentUrl !== '') {
            $updates['myfatoorah_payment_url'] = $paymentUrl;
        }
    }

    if (orderHasColumn('myfatoorah_payment_method_id') && isset($meta['payment_method_id'])) {
        $updates['myfatoorah_payment_method_id'] = (int) $meta['payment_method_id'];
    }

    if (orderHasColumn('myfatoorah_payment_id') && isset($meta['payment_id'])) {
        $paymentId = trim((string) $meta['payment_id']);
        if ($paymentId !== '') {
            $updates['myfatoorah_payment_id'] = $paymentId;
        }
    }

    if (orderHasColumn('myfatoorah_invoice_status') && isset($meta['invoice_status'])) {
        $invoiceStatus = trim((string) $meta['invoice_status']);
        if ($invoiceStatus !== '') {
            $updates['myfatoorah_invoice_status'] = $invoiceStatus;
        }
    }

    if (orderHasColumn('myfatoorah_last_status_at')) {
        $updates['myfatoorah_last_status_at'] = date('Y-m-d H:i:s');
    }

    if (!empty($updates)) {
        db()->update('orders', $updates, 'id = :id', ['id' => $orderId]);
    }
}

function markOrderAsPaidFromMyFatoorahForAdmin($orderId, $amount, $reference)
{
    $amount = (float) $amount;
    if ($amount <= 0) {
        return;
    }

    $order = db()->fetch("SELECT id, user_id, payment_status FROM orders WHERE id = ? LIMIT 1", [$orderId]);
    if (!$order) {
        throw new RuntimeException('الطلب غير موجود');
    }

    $userId = (int) ($order['user_id'] ?? 0);
    $wasPaid = strtolower(trim((string) ($order['payment_status'] ?? ''))) === 'paid';
    $pdo = db()->getConnection();
    $pdo->beginTransaction();

    try {
        if (tableExistsByName('transactions') && $userId > 0) {
            $existingTx = db()->fetch(
                "SELECT id FROM transactions WHERE order_id = ? AND reference_number = ? LIMIT 1",
                [$orderId, $reference]
            );
            if (!$existingTx) {
                db()->insert('transactions', [
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'type' => 'payment',
                    'amount' => $amount,
                    'description' => 'دفع طلب #' . $orderId . ' عبر MyFatoorah',
                    'reference_number' => $reference,
                    'status' => 'completed',
                ]);
            }
        }

        $updateData = [];
        if (orderHasColumn('payment_status')) {
            $updateData['payment_status'] = 'paid';
        }
        if (orderHasColumn('payment_method')) {
            $updateData['payment_method'] = 'card';
        }
        if (orderHasColumn('total_amount')) {
            $updateData['total_amount'] = $amount;
        }
        if (!empty($updateData)) {
            db()->update('orders', $updateData, 'id = :id', ['id' => $orderId]);
        }

        $pdo->commit();
        try {
            providerFinanceSyncOrder((int) $orderId);
        } catch (Throwable $financeError) {
            error_log('Admin order provider finance sync failed: ' . $financeError->getMessage());
        }

        if (!$wasPaid) {
            notifyOrderCustomerFromAdmin(
                (int) $orderId,
                'تم تأكيد الدفع',
                'تم استلام دفعتك بنجاح، وسيتم استكمال إجراءات الطلب.',
                [
                    'event' => 'payment_confirmed',
                    'payment_status' => 'paid',
                    'payment_reference' => (string) $reference,
                ]
            );
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function createOrderMyFatoorahLinkForAdmin($orderId)
{
    $order = db()->fetch(
        "SELECT o.*, u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ? LIMIT 1",
        [$orderId]
    );

    if (!$order) {
        throw new RuntimeException('الطلب غير موجود');
    }

    if (($order['status'] ?? '') === 'cancelled') {
        throw new RuntimeException('لا يمكن إنشاء رابط دفع لطلب ملغي');
    }

    if (($order['payment_status'] ?? '') === 'paid') {
        throw new RuntimeException('هذا الطلب مدفوع بالفعل');
    }

    $amount = (float) ($order['total_amount'] ?? 0);
    if ($amount <= 0) {
        $amount = (float) ($order['inspection_fee'] ?? 0) + (float) ($order['service_fee'] ?? 0) + (float) ($order['parts_fee'] ?? 0) - (float) ($order['discount_amount'] ?? 0);
    }
    if ($amount <= 0) {
        throw new RuntimeException('قيمة الطلب غير صالحة للدفع');
    }

    $customerName = trim((string) ($order['user_name'] ?? 'عميل Darfix'));
    if ($customerName === '') {
        $customerName = 'عميل Darfix';
    }

    $customerEmail = trim((string) ($order['user_email'] ?? 'no-reply@darfix.app'));
    if ($customerEmail === '' || strpos($customerEmail, '@') === false) {
        $customerEmail = 'no-reply@darfix.app';
    }

    $customerPhone = preg_replace('/\D+/', '', (string) ($order['user_phone'] ?? ''));
    if ($customerPhone === '') {
        $customerPhone = '966500000000';
    } elseif (strlen($customerPhone) < 8) {
        $customerPhone = str_pad($customerPhone, 8, '0', STR_PAD_LEFT);
    }

    $initiate = adminMyFatoorahRequest('/v2/InitiatePayment', [
        'InvoiceAmount' => round($amount, 2),
        'CurrencyIso' => 'SAR',
    ]);

    if (!$initiate['success']) {
        throw new RuntimeException($initiate['message'] ?: 'تعذر تهيئة الدفع');
    }

    $methods = [];
    if (is_array($initiate['data'])) {
        $methods = $initiate['data']['PaymentMethods'] ?? [];
    }
    $paymentMethodId = adminChooseMyFatoorahMethodId($methods);
    if ($paymentMethodId === null) {
        throw new RuntimeException('لا توجد طرق دفع متاحة حالياً عبر MyFatoorah');
    }

    $callbackUrls = buildAdminMyFatoorahCallbackUrls($orderId);
    $execute = adminMyFatoorahRequest('/v2/ExecutePayment', [
        'PaymentMethodId' => $paymentMethodId,
        'InvoiceValue' => round($amount, 2),
        'DisplayCurrencyIso' => 'SAR',
        'CustomerName' => $customerName,
        'CustomerEmail' => $customerEmail,
        'CustomerMobile' => $customerPhone,
        'Language' => 'AR',
        'CustomerReference' => 'order-' . $orderId,
        'UserDefinedField' => 'order:' . $orderId,
        'SourceInfo' => 'ERTAH-Admin',
        'CallBackUrl' => $callbackUrls['callback_url'],
        'ErrorUrl' => $callbackUrls['error_url'],
    ]);

    if (!$execute['success']) {
        throw new RuntimeException($execute['message'] ?: 'تعذر إنشاء رابط الدفع');
    }

    $data = is_array($execute['data']) ? $execute['data'] : [];
    $invoiceId = trim((string) ($data['InvoiceId'] ?? ''));
    $paymentUrl = trim((string) ($data['PaymentURL'] ?? $data['InvoiceURL'] ?? ''));
    if ($invoiceId === '' || $paymentUrl === '') {
        throw new RuntimeException('استجابة MyFatoorah غير مكتملة');
    }

    updateOrderMyFatoorahMetaForAdmin($orderId, [
        'invoice_id' => $invoiceId,
        'payment_url' => $paymentUrl,
        'payment_method_id' => $paymentMethodId,
        'invoice_status' => 'Pending',
    ]);

    return [
        'invoice_id' => $invoiceId,
        'payment_url' => $paymentUrl,
        'amount' => round($amount, 2),
    ];
}

function syncOrderMyFatoorahStatusForAdmin($orderId, $invoiceId = '', $paymentId = '')
{
    $order = db()->fetch("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);
    if (!$order) {
        throw new RuntimeException('الطلب غير موجود');
    }

    $invoiceId = trim($invoiceId);
    $paymentId = trim($paymentId);
    if ($invoiceId === '' && orderHasColumn('myfatoorah_invoice_id')) {
        $invoiceId = trim((string) ($order['myfatoorah_invoice_id'] ?? ''));
    }
    if ($paymentId === '' && orderHasColumn('myfatoorah_payment_id')) {
        $paymentId = trim((string) ($order['myfatoorah_payment_id'] ?? ''));
    }

    if ($invoiceId === '' && $paymentId === '') {
        throw new RuntimeException('لا يوجد Invoice ID أو Payment ID للتحقق من الحالة');
    }

    $statusResponse = adminMyFatoorahRequest('/v2/GetPaymentStatus', [
        'Key' => $invoiceId !== '' ? $invoiceId : $paymentId,
        'KeyType' => $invoiceId !== '' ? 'InvoiceId' : 'PaymentId',
    ]);

    if (!$statusResponse['success']) {
        throw new RuntimeException($statusResponse['message'] ?: 'تعذر التحقق من حالة الدفع');
    }

    $data = is_array($statusResponse['data']) ? $statusResponse['data'] : [];
    $invoiceStatus = trim((string) ($data['InvoiceStatus'] ?? ''));
    $isPaid = strtolower($invoiceStatus) === 'paid';
    $invoiceValue = (float) ($data['InvoiceValue'] ?? 0);
    $amount = (float) ($order['total_amount'] ?? 0);
    if ($amount <= 0) {
        $amount = $invoiceValue;
    }

    $resolvedInvoiceId = trim((string) ($data['InvoiceId'] ?? $invoiceId));
    $gatewayPaymentId = trim($paymentId);
    $invoiceTransactions = $data['InvoiceTransactions'] ?? [];
    if (is_array($invoiceTransactions) && !empty($invoiceTransactions)) {
        $firstTx = $invoiceTransactions[0];
        if (is_array($firstTx)) {
            $gatewayPaymentId = trim((string) ($firstTx['PaymentId'] ?? $gatewayPaymentId));
        }
    }
    if ($gatewayPaymentId === '') {
        $gatewayPaymentId = $resolvedInvoiceId !== '' ? ('INV-' . $resolvedInvoiceId) : ('PAY-' . $orderId);
    }

    $metaUpdate = [
        'invoice_id' => $resolvedInvoiceId,
        'payment_id' => $gatewayPaymentId,
        'invoice_status' => $invoiceStatus !== '' ? $invoiceStatus : 'Unknown',
    ];
    $invoiceUrlFromGateway = trim((string) ($data['InvoiceURL'] ?? ''));
    if ($invoiceUrlFromGateway !== '') {
        $metaUpdate['payment_url'] = $invoiceUrlFromGateway;
    }
    updateOrderMyFatoorahMetaForAdmin($orderId, $metaUpdate);

    if ($isPaid && ($order['payment_status'] ?? '') !== 'paid') {
        markOrderAsPaidFromMyFatoorahForAdmin($orderId, $amount, $gatewayPaymentId);
    }

    return [
        'invoice_status' => $invoiceStatus !== '' ? $invoiceStatus : 'Unknown',
        'is_paid' => $isPaid,
        'amount' => $amount,
        'invoice_id' => $resolvedInvoiceId,
        'payment_id' => $gatewayPaymentId,
    ];
}

function getMyFatoorahInvoiceStatusAr($status)
{
    $normalized = strtolower(trim((string) $status));
    $map = [
        'paid' => 'مدفوع',
        'pending' => 'قيد الانتظار',
        'initiated' => 'تم الإنشاء',
        'failed' => 'فشل',
        'expired' => 'منتهية',
        'canceled' => 'ملغاة',
        'cancelled' => 'ملغاة',
    ];
    return $map[$normalized] ?? ($status !== '' ? $status : 'غير معروف');
}

function orderStatusFlowRank($status)
{
    $normalized = strtolower(trim((string) $status));
    $flow = [
        'pending' => 0,
        'accepted' => 1,
        'on_the_way' => 2,
        'arrived' => 3,
        'in_progress' => 4,
        'completed' => 5,
    ];
    return array_key_exists($normalized, $flow) ? $flow[$normalized] : null;
}

function isOrderStatusTransitionAllowed($currentStatus, $newStatus)
{
    $current = strtolower(trim((string) $currentStatus));
    $next = strtolower(trim((string) $newStatus));

    if ($current === '' || $next === '' || $current === $next) {
        return false;
    }

    // Terminal statuses cannot be changed once reached.
    if (in_array($current, ['completed', 'cancelled'], true)) {
        return false;
    }

    // Cancel is allowed only before reaching terminal statuses.
    if ($next === 'cancelled') {
        return true;
    }

    $currentRank = orderStatusFlowRank($current);
    $nextRank = orderStatusFlowRank($next);
    if ($currentRank === null || $nextRank === null) {
        return false;
    }

    // Never allow going back to an earlier stage.
    return $nextRank >= $currentRank;
}

ensureOrderRelationTables();
ensureOrderPaymentGatewayColumns();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action === 'live_tracking_feed') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    $feedPayload = buildAdminLiveTrackingFeedPayload($id);
    if ($feedPayload === null) {
        jsonResponse([
            'success' => false,
            'message' => 'الطلب غير موجود',
            'data' => null,
        ], 404);
    }

    jsonResponse([
        'success' => true,
        'message' => 'OK',
        'data' => $feedPayload,
    ]);
}

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');
    
    if ($postAction === 'update_status') {
        $orderId = (int)post('order_id');
        $newStatus = post('new_status');
        $currentOrder = db()->fetch("SELECT status FROM orders WHERE id = ? LIMIT 1", [$orderId]);
        if (!$currentOrder) {
            setFlashMessage('danger', 'الطلب غير موجود');
            redirect('orders.php');
        }

        $currentStatus = strtolower(trim((string) ($currentOrder['status'] ?? 'pending')));
        
        $validStatuses = ['pending', 'accepted', 'on_the_way', 'arrived', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($newStatus, $validStatuses, true)) {
            setFlashMessage('danger', 'الحالة المطلوبة غير صالحة');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        if ($newStatus === $currentStatus) {
            setFlashMessage('info', 'الحالة الحالية مطبقة بالفعل');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        if (!isOrderStatusTransitionAllowed($currentStatus, $newStatus)) {
            setFlashMessage('danger', 'لا يمكن الرجوع إلى مرحلة سابقة بعد إتمامها');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        if (in_array($newStatus, $validStatuses, true)) {
            $updateData = ['status' => $newStatus];
            
            if ($newStatus === 'completed') {
                if (orderHasColumn('completed_at')) {
                    $updateData['completed_at'] = date('Y-m-d H:i:s');
                }
            } elseif ($newStatus === 'cancelled') {
                if (orderHasColumn('cancelled_at')) {
                    $updateData['cancelled_at'] = date('Y-m-d H:i:s');
                }
                if (orderHasColumn('cancelled_by')) {
                    $updateData['cancelled_by'] = 'admin';
                }
                if (orderHasColumn('cancel_reason')) {
                    $updateData['cancel_reason'] = post('cancel_reason');
                }
            }
            
            db()->update('orders', $updateData, 'id = :id', ['id' => $orderId]);
            if (tableExistsByName('order_providers')) {
                if ($newStatus === 'completed') {
                    $primaryProvider = db()->fetch("SELECT provider_id FROM orders WHERE id = ?", [$orderId]);
                    $primaryProviderId = (int) ($primaryProvider['provider_id'] ?? 0);
                    if ($primaryProviderId > 0) {
                        db()->query(
                            "UPDATE order_providers
                             SET assignment_status = CASE
                                WHEN provider_id = ? THEN 'completed'
                                WHEN assignment_status = 'completed' THEN assignment_status
                                ELSE assignment_status
                             END
                             WHERE order_id = ?",
                            [$primaryProviderId, $orderId]
                        );
                    } else {
                        db()->query(
                            "UPDATE order_providers
                             SET assignment_status = 'completed'
                             WHERE order_id = ?",
                            [$orderId]
                        );
                    }
                } elseif ($newStatus === 'cancelled') {
                    db()->query(
                        "UPDATE order_providers
                         SET assignment_status = 'cancelled'
                         WHERE order_id = ?
                           AND assignment_status <> 'completed'",
                        [$orderId]
                    );
                }
            }

            $customerStatusTitles = [
                'pending' => 'الطلب قيد المراجعة',
                'accepted' => 'تم تأكيد الطلب',
                'on_the_way' => 'الفني في الطريق',
                'arrived' => 'وصل الفني',
                'in_progress' => 'بدأ تنفيذ الخدمة',
                'completed' => 'تم إغلاق الطلب',
                'cancelled' => 'تم إلغاء الطلب',
            ];
            $customerStatusBodies = [
                'pending' => 'طلبك قيد المتابعة من مركز العمليات.',
                'accepted' => 'تم تأكيد الطلب وسيتم تحديثك بمراحل التنفيذ.',
                'on_the_way' => 'مقدم الخدمة في الطريق إليك الآن.',
                'arrived' => 'مقدم الخدمة وصل إلى العنوان المحدد.',
                'in_progress' => 'بدأ مقدم الخدمة تنفيذ طلبك.',
                'completed' => 'تم إنهاء الطلب بنجاح. يرجى تقييم الخدمة.',
                'cancelled' => 'تم إلغاء الطلب من مركز العمليات. سنخدمك في أي وقت.',
            ];
            notifyOrderCustomerFromAdmin(
                $orderId,
                $customerStatusTitles[$newStatus] ?? 'تحديث على طلبك',
                $customerStatusBodies[$newStatus] ?? 'تم تحديث حالة الطلب.',
                ['event' => 'admin_status_updated', 'status' => $newStatus]
            );

            $providerNotifyStatuses = ['cancelled', 'completed', 'on_the_way', 'arrived', 'in_progress'];
            if (in_array($newStatus, $providerNotifyStatuses, true)) {
                $providerIdsForNotify = [];
                if (tableExistsByName('order_providers')) {
                    $providerRows = db()->fetchAll(
                        "SELECT provider_id
                         FROM order_providers
                         WHERE order_id = ?
                           AND assignment_status IN ('assigned', 'accepted', 'in_progress', 'completed')",
                        [$orderId]
                    );
                    foreach ($providerRows as $providerRow) {
                        $providerIdsForNotify[] = (int) ($providerRow['provider_id'] ?? 0);
                    }
                }
                $primaryProviderRow = db()->fetch("SELECT provider_id FROM orders WHERE id = ? LIMIT 1", [$orderId]);
                $primaryProviderId = (int) ($primaryProviderRow['provider_id'] ?? 0);
                if ($primaryProviderId > 0) {
                    $providerIdsForNotify[] = $primaryProviderId;
                }

                notifyOrderProvidersFromAdmin(
                    $orderId,
                    $providerIdsForNotify,
                    'تحديث على الطلب #' . $orderId,
                    'تم تحديث حالة الطلب إلى: ' . getOrderStatusAr($newStatus),
                    ['event' => 'admin_status_updated', 'status' => $newStatus]
                );
            }

            logActivity('update_order_status', 'orders', $orderId);
            setFlashMessage('success', 'تم تحديث حالة الطلب بنجاح');
        }
        redirect('orders.php?action=view&id=' . $orderId);
    }
    
    if ($postAction === 'assign_provider') {
        $orderId = (int) post('order_id');
        $singleProviderId = (int) post('provider_id');
        $providerIdsFromForm = $_POST['provider_ids'] ?? [];
        $providerIds = normalizeIntegerList($providerIdsFromForm);
        if ($singleProviderId > 0) {
            $providerIds[] = $singleProviderId;
        }
        $providerIds = normalizeIntegerList($providerIds);

        if ($orderId <= 0) {
            setFlashMessage('danger', 'الطلب غير موجود');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        $resolvedProviderIds = [];
        $syncSummary = [];
        $assignedCount = assignProvidersToOrderForAdmin($orderId, $providerIds, $resolvedProviderIds, $syncSummary);
        $removedProviderIds = normalizeIntegerList($syncSummary['removed_provider_ids'] ?? []);
        $addedProviderIds = normalizeIntegerList($syncSummary['added_provider_ids'] ?? []);
        $blockedRemoveProviderIds = normalizeIntegerList($syncSummary['blocked_remove_provider_ids'] ?? []);
        $activeProviderIds = normalizeIntegerList($syncSummary['active_provider_ids'] ?? $resolvedProviderIds);

        if (!empty($resolvedProviderIds) || !empty($removedProviderIds) || empty($providerIds)) {
            $selectedCount = count($activeProviderIds);
            if ($selectedCount > 0) {
                $providersLabel = $selectedCount > 1
                    ? ('تم تحديث ترشيح ' . $selectedCount . ' من مقدمي الخدمة')
                    : 'تم تحديث مقدم الخدمة المرشح لطلبك';
            } else {
                $providersLabel = 'تم إزالة مقدمي الخدمة المرشحين مؤقتًا من طلبك';
            }
            notifyOrderCustomerFromAdmin(
                $orderId,
                'تحديث على طلبك',
                $providersLabel . ($selectedCount > 0 ? '، بانتظار قبول الفني.' : '.'),
                ['event' => 'providers_assigned', 'status' => $selectedCount > 0 ? 'assigned' : 'pending', 'providers_count' => $selectedCount]
            );

            if (!empty($addedProviderIds)) {
                notifyOrderProvidersFromAdmin(
                    $orderId,
                    $addedProviderIds,
                    'طلب خدمة جديد',
                    'تم ترشيحك لطلب جديد. راجع التفاصيل وقم بالقبول أو الرفض.',
                    ['event' => 'provider_assigned', 'status' => 'assigned']
                );
            }

            if (!empty($removedProviderIds)) {
                notifyOrderProvidersFromAdmin(
                    $orderId,
                    $removedProviderIds,
                    'إلغاء ترشيح الطلب #' . $orderId,
                    'تم إلغاء ترشيحك لهذا الطلب من لوحة التحكم.',
                    ['event' => 'provider_unassigned', 'status' => 'cancelled']
                );
            }

            try {
                providerFinanceSyncOrder($orderId);
            } catch (Throwable $financeError) {
                error_log('Provider finance sync after assignment failed: ' . $financeError->getMessage());
            }

            logActivity('assign_provider', 'orders', $orderId);
            $message = 'تم تحديث مقدمي الخدمة للطلب بنجاح';
            if (!empty($blockedRemoveProviderIds)) {
                $message .= '، ولم يتم حذف مقدم بدأ/قبل الطلب بالفعل.';
            }
            setFlashMessage('success', $message);
        } else {
            setFlashMessage('warning', 'لم يتم العثور على مقدمي خدمات صالحين للتعيين');
        }
        redirect('orders.php?action=view&id=' . $orderId);
    }

    if ($postAction === 'assign_container_store') {
        $orderId = (int) post('order_id');
        $storeId = (int) post('container_store_id');

        if ($orderId <= 0) {
            setFlashMessage('danger', 'رقم الطلب غير صالح');
            redirect('orders.php');
        }

        if (!tableExistsByName('container_requests') || !tableColumnExistsByName('container_requests', 'source_order_id')) {
            setFlashMessage('danger', 'لا يوجد سجل طلب حاويات مرتبط لهذا الطلب');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        $requestRow = db()->fetch(
            'SELECT id FROM container_requests WHERE source_order_id = ? LIMIT 1',
            [$orderId]
        );
        if (!$requestRow) {
            setFlashMessage('danger', 'لا يوجد سجل طلب حاويات مرتبط لهذا الطلب');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        $updateData = ['container_store_id' => null];
        if ($storeId > 0) {
            $storeRow = db()->fetch('SELECT id FROM container_stores WHERE id = ? LIMIT 1', [$storeId]);
            if (!$storeRow) {
                setFlashMessage('danger', 'متجر الحاويات المختار غير موجود');
                redirect('orders.php?action=view&id=' . $orderId);
            }
            $updateData['container_store_id'] = $storeId;
        }

        $containerRequestId = (int) $requestRow['id'];
        db()->update('container_requests', $updateData, 'id = ?', [$containerRequestId]);
        specialSyncContainerStoreAccountEntryForRequest($containerRequestId, (int) (getCurrentAdmin()['id'] ?? 0));

        if ($storeId > 0) {
            db()->query(
                "UPDATE orders
                 SET status = CASE WHEN status = 'pending' THEN 'assigned' ELSE status END
                 WHERE id = ?",
                [$orderId]
            );
        }

        logActivity('assign_container_store', 'container_requests', (int) $requestRow['id']);
        setFlashMessage('success', 'تم تحديث متجر الحاويات للطلب');
        redirect('orders.php?action=view&id=' . $orderId);
    }
    
    if ($postAction === 'add_note') {
        $orderId = (int)post('order_id');
        $note = post('admin_notes');
        
        db()->update('orders', ['admin_notes' => $note], 'id = :id', ['id' => $orderId]);
        setFlashMessage('success', 'تم حفظ الملاحظة');
        redirect('orders.php?action=view&id=' . $orderId);
    }
    
    if ($postAction === 'set_estimate') {
        $orderId = (int)post('order_id');
        $minEst = (float)post('min_estimate');
        $maxEst = (float)post('max_estimate');
        
        db()->update('orders', 
            ['min_estimate' => $minEst, 'max_estimate' => $maxEst], 
            'id = :id', 
            ['id' => $orderId]
        );
        notifyOrderCustomerFromAdmin(
            $orderId,
            'تم تحديث التقدير المبدئي',
            'تم تحديث التقدير المبدئي لتكلفة طلبك من مركز العمليات.',
            [
                'event' => 'estimate_updated',
                'min_estimate' => $minEst,
                'max_estimate' => $maxEst,
            ]
        );
        logActivity('set_estimate', 'orders', $orderId);
        setFlashMessage('success', 'تم حفظ التقدير المبدئي');
        redirect('orders.php?action=view&id=' . $orderId);
    }

    if ($postAction === 'create_myfatoorah_link') {
        $orderId = (int) post('order_id');
        if ($orderId <= 0) {
            setFlashMessage('danger', 'رقم الطلب غير صالح');
            redirect('orders.php');
        }

        try {
            $result = createOrderMyFatoorahLinkForAdmin($orderId);
            notifyOrderCustomerFromAdmin(
                $orderId,
                'تم إصدار رابط الدفع',
                'تم إصدار رابط دفع لطلبك. يمكنك إكمال الدفع الآن.',
                [
                    'event' => 'payment_link_created',
                    'invoice_id' => (string) ($result['invoice_id'] ?? ''),
                    'payment_required' => true,
                ]
            );
            logActivity('create_myfatoorah_link', 'orders', $orderId);
            setFlashMessage(
                'success',
                'تم إنشاء رابط الدفع بنجاح. Invoice: ' . $result['invoice_id'] . ' - المبلغ: ' . number_format((float) $result['amount'], 2) . ' ⃁'
            );
        } catch (Throwable $e) {
            setFlashMessage('danger', $e->getMessage());
        }
        redirect('orders.php?action=view&id=' . $orderId);
    }

    if ($postAction === 'sync_myfatoorah_status') {
        $orderId = (int) post('order_id');
        $invoiceId = trim((string) post('invoice_id'));
        $paymentId = trim((string) post('payment_id'));
        if ($orderId <= 0) {
            setFlashMessage('danger', 'رقم الطلب غير صالح');
            redirect('orders.php');
        }

        try {
            $status = syncOrderMyFatoorahStatusForAdmin($orderId, $invoiceId, $paymentId);
            $gatewayStatus = strtolower(trim((string) ($status['invoice_status'] ?? '')));
            if (in_array($gatewayStatus, ['failed', 'expired', 'canceled', 'cancelled'], true)) {
                notifyOrderCustomerFromAdmin(
                    $orderId,
                    'تعذر إتمام الدفع',
                    'تعذر إتمام عملية الدفع. يمكنك إعادة المحاولة من صفحة الطلب.',
                    [
                        'event' => 'payment_failed',
                        'payment_status' => $gatewayStatus,
                    ]
                );
            }
            logActivity('sync_myfatoorah_status', 'orders', $orderId);
            $statusText = getMyFatoorahInvoiceStatusAr($status['invoice_status']);
            setFlashMessage('success', 'تم تحديث حالة دفع MyFatoorah: ' . $statusText);
        } catch (Throwable $e) {
            setFlashMessage('danger', $e->getMessage());
        }
        redirect('orders.php?action=view&id=' . $orderId);
    }

    if ($postAction === 'update_confirmation') {
        $orderId = (int)post('order_id');
        $confirmationStatus = post('confirmation_status');
        $confirmationNotes = trim(post('confirmation_notes'));
        $validStatuses = ['pending', 'confirmed', 'unreachable', 'cancelled'];

        if (!$orderId || !in_array($confirmationStatus, $validStatuses, true)) {
            setFlashMessage('danger', 'بيانات تأكيد غير صحيحة');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        if (!orderHasColumn('confirmation_status')) {
            setFlashMessage('warning', 'حقول تأكيد الموعد غير موجودة في قاعدة البيانات');
            redirect('orders.php?action=view&id=' . $orderId);
        }

        $updateData = [
            'confirmation_status' => $confirmationStatus,
        ];

        if (orderHasColumn('confirmation_notes')) {
            $updateData['confirmation_notes'] = $confirmationNotes;
        }

        if (orderHasColumn('confirmation_attempts') && in_array($confirmationStatus, ['pending', 'unreachable'], true)) {
            $attemptRow = db()->fetch("SELECT confirmation_attempts FROM orders WHERE id = ?", [$orderId]);
            $attempts = (int)($attemptRow['confirmation_attempts'] ?? 0);
            $updateData['confirmation_attempts'] = $attempts + 1;
        }

        if (orderHasColumn('confirmed_at') && $confirmationStatus === 'confirmed') {
            $updateData['confirmed_at'] = date('Y-m-d H:i:s');
        }

        if ($confirmationStatus === 'cancelled') {
            $updateData['status'] = 'cancelled';
            if (orderHasColumn('cancelled_at')) {
                $updateData['cancelled_at'] = date('Y-m-d H:i:s');
            }
            if (orderHasColumn('cancelled_by')) {
                $updateData['cancelled_by'] = 'admin';
            }
            if (orderHasColumn('cancel_reason')) {
                $updateData['cancel_reason'] = $confirmationNotes ?: 'إلغاء من العمليات بعد تعذر تأكيد الموعد';
            }
        }

        db()->update('orders', $updateData, 'id = :id', ['id' => $orderId]);
        if ($confirmationStatus === 'cancelled' && tableExistsByName('order_providers')) {
            db()->query(
                "UPDATE order_providers
                 SET assignment_status = 'cancelled'
                 WHERE order_id = ?
                   AND assignment_status <> 'completed'",
                [$orderId]
            );
        }

        $confirmationTitles = [
            'pending' => 'محاولة تأكيد جديدة',
            'confirmed' => 'تم تأكيد موعد الخدمة',
            'unreachable' => 'تعذر التواصل مؤقتًا',
            'cancelled' => 'تم إلغاء الطلب',
        ];
        $confirmationBodies = [
            'pending' => 'لا يزال طلبك بانتظار التأكيد النهائي قبل التنفيذ.',
            'confirmed' => 'تم تأكيد الموعد معك من مركز العمليات.',
            'unreachable' => 'تعذر الوصول إليك، يرجى متابعة هاتفك لتأكيد الموعد.',
            'cancelled' => 'تم إلغاء الطلب بعد تعذر تأكيد الموعد.',
        ];

        notifyOrderCustomerFromAdmin(
            $orderId,
            $confirmationTitles[$confirmationStatus] ?? 'تحديث على تأكيد الموعد',
            $confirmationBodies[$confirmationStatus] ?? 'تم تحديث حالة تأكيد الموعد.',
            ['event' => 'confirmation_updated', 'confirmation_status' => $confirmationStatus]
        );

        if ($confirmationStatus === 'cancelled') {
            $providerIdsForNotify = [];
            if (tableExistsByName('order_providers')) {
                $providerRows = db()->fetchAll(
                    "SELECT provider_id FROM order_providers WHERE order_id = ?",
                    [$orderId]
                );
                foreach ($providerRows as $providerRow) {
                    $providerIdsForNotify[] = (int) ($providerRow['provider_id'] ?? 0);
                }
            }
            $primaryProviderRow = db()->fetch("SELECT provider_id FROM orders WHERE id = ? LIMIT 1", [$orderId]);
            $primaryProviderId = (int) ($primaryProviderRow['provider_id'] ?? 0);
            if ($primaryProviderId > 0) {
                $providerIdsForNotify[] = $primaryProviderId;
            }

            notifyOrderProvidersFromAdmin(
                $orderId,
                $providerIdsForNotify,
                'إلغاء الطلب #' . $orderId,
                'تم إلغاء الطلب من العمليات بعد تعذر تأكيد الموعد.',
                ['event' => 'order_cancelled', 'status' => 'cancelled']
            );
        }

        logActivity('update_order_confirmation', 'orders', $orderId);
        setFlashMessage('success', 'تم تحديث حالة تأكيد الموعد');
        redirect('orders.php?action=view&id=' . $orderId);
    }

    if ($postAction === 'mark_no_show') {
        $orderId = (int)post('order_id');
        $noShowNotes = trim(post('no_show_notes'));
        $orderForNoShow = db()->fetch("SELECT id, user_id FROM orders WHERE id = ?", [$orderId]);

        if (!$orderForNoShow) {
            setFlashMessage('danger', 'الطلب غير موجود');
            redirect('orders.php');
        }

        $orderUpdate = [
            'status' => 'cancelled',
        ];
        if (orderHasColumn('cancelled_at')) {
            $orderUpdate['cancelled_at'] = date('Y-m-d H:i:s');
        }
        if (orderHasColumn('cancelled_by')) {
            $orderUpdate['cancelled_by'] = 'admin';
        }
        if (orderHasColumn('cancel_reason')) {
            $orderUpdate['cancel_reason'] = 'عدم التزام العميل بالموعد' . ($noShowNotes ? (' - ' . $noShowNotes) : '');
        }
        db()->update('orders', $orderUpdate, 'id = :id', ['id' => $orderId]);
        if (tableExistsByName('order_providers')) {
            db()->query(
                "UPDATE order_providers
                 SET assignment_status = 'cancelled'
                 WHERE order_id = ?
                   AND assignment_status <> 'completed'",
                [$orderId]
            );
        }

        if (userHasColumn('no_show_count')) {
            $userId = (int)$orderForNoShow['user_id'];
            $userData = db()->fetch("SELECT no_show_count, is_blacklisted FROM users WHERE id = ?", [$userId]);
            $currentCount = (int)($userData['no_show_count'] ?? 0);
            $newCount = $currentCount + 1;
            $thresholdSetting = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'no_show_blacklist_threshold' LIMIT 1");
            $threshold = (int)($thresholdSetting['setting_value'] ?? 3);
            if ($threshold < 1) {
                $threshold = 3;
            }

            $userUpdate = ['no_show_count' => $newCount];
            if (userHasColumn('is_blacklisted') && userHasColumn('blacklist_reason') && $newCount >= $threshold) {
                $userUpdate['is_blacklisted'] = 1;
                $userUpdate['blacklist_reason'] = 'تجاوز الحد المسموح من حالات عدم الالتزام بالمواعيد';
            }

            db()->update('users', $userUpdate, 'id = :id', ['id' => $userId]);
        }

        notifyOrderCustomerFromAdmin(
            $orderId,
            'تم إلغاء الطلب',
            'تم إلغاء الطلب بسبب عدم الالتزام بالموعد. يمكنك التواصل مع الدعم لإعادة الجدولة.',
            [
                'event' => 'order_cancelled',
                'status' => 'cancelled',
                'cancel_reason' => 'no_show',
            ]
        );

        logActivity('mark_user_no_show', 'orders', $orderId);
        setFlashMessage('success', 'تم تسجيل حالة عدم التزام العميل بالموعد');
        redirect('orders.php?action=view&id=' . $orderId);
    }
}

// البحث والفلترة
$search = get('search');
$status = get('status');
$categoryId = (int)get('category');
$page = max(1, (int)get('page', 1));
$allServiceCategories = getServiceCategoriesHierarchy(false);
$serviceCategoryById = [];
foreach ($allServiceCategories as $serviceCategoryItem) {
    $serviceCategoryById[(int) $serviceCategoryItem['id']] = $serviceCategoryItem;
}
$categoryDisplayMap = getServiceCategoryDisplayMap(false);

$where = '1=1';
$params = [];

if ($search) {
    $where .= " AND (o.order_number LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status) {
    $where .= " AND o.status = ?";
    $params[] = $status;
}

if ($categoryId) {
    $where .= " AND o.category_id = ?";
    $params[] = $categoryId;
}

$totalOrders = db()->fetch("
    SELECT COUNT(*) as count FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE {$where}
", $params)['count'];

$pagination = paginate($totalOrders, $page);

$orders = db()->fetchAll("
    SELECT o.*, 
           u.full_name as user_name, u.phone as user_phone,
           p.full_name as provider_name,
           c.name_ar as category_name, c.icon as category_icon, c.image as category_image
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN providers p ON o.provider_id = p.id
    LEFT JOIN service_categories c ON o.category_id = c.id
    WHERE {$where}
    ORDER BY o.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
", $params);

$orderServiceNamesMap = [];
$orderHasCustomServiceMap = [];
$orderCustomServiceTitleMap = [];
$orderCustomServiceDescriptionMap = [];
$orderProviderNamesMap = [];
$orderIds = [];
foreach ($orders as $orderRow) {
    $orderId = (int) ($orderRow['id'] ?? 0);
    if ($orderId > 0) {
        $orderIds[$orderId] = $orderId;
    }
}
$orderIds = array_values($orderIds);

if (!empty($orderIds)) {
    $orderPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));

    if (tableExistsByName('order_services')) {
        $serviceRows = db()->fetchAll(
            "SELECT os.order_id, os.service_name, os.is_custom, os.notes, s.name_ar AS linked_service_name
             FROM order_services os
             LEFT JOIN services s ON s.id = os.service_id
             WHERE os.order_id IN ($orderPlaceholders)
             ORDER BY os.id ASC",
            $orderIds
        );
        foreach ($serviceRows as $serviceRow) {
            $orderId = (int) ($serviceRow['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            if (!isset($orderServiceNamesMap[$orderId])) {
                $orderServiceNamesMap[$orderId] = [];
            }
            $serviceName = trim((string) ($serviceRow['service_name'] ?? ''));
            if ($serviceName === '') {
                $serviceName = trim((string) ($serviceRow['linked_service_name'] ?? ''));
            }
            if ($serviceName !== '' && !in_array($serviceName, $orderServiceNamesMap[$orderId], true)) {
                $orderServiceNamesMap[$orderId][] = $serviceName;
            }

            if ((int) ($serviceRow['is_custom'] ?? 0) === 1) {
                $orderHasCustomServiceMap[$orderId] = true;
                if ($serviceName !== '' && empty($orderCustomServiceTitleMap[$orderId])) {
                    $orderCustomServiceTitleMap[$orderId] = $serviceName;
                }

                $customNotes = trim((string) ($serviceRow['notes'] ?? ''));
                if ($customNotes !== '' && empty($orderCustomServiceDescriptionMap[$orderId])) {
                    $orderCustomServiceDescriptionMap[$orderId] = $customNotes;
                }
            }
        }
    }

    if (tableExistsByName('order_providers')) {
        $providerRows = db()->fetchAll(
            "SELECT op.order_id, p.full_name AS provider_name
             FROM order_providers op
             LEFT JOIN providers p ON p.id = op.provider_id
             WHERE op.order_id IN ($orderPlaceholders)
               AND op.assignment_status NOT IN ('cancelled', 'rejected')
             ORDER BY op.id ASC",
            $orderIds
        );
        foreach ($providerRows as $providerRow) {
            $orderId = (int) ($providerRow['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            if (!isset($orderProviderNamesMap[$orderId])) {
                $orderProviderNamesMap[$orderId] = [];
            }
            $providerName = trim((string) ($providerRow['provider_name'] ?? ''));
            if ($providerName !== '' && !in_array($providerName, $orderProviderNamesMap[$orderId], true)) {
                $orderProviderNamesMap[$orderId][] = $providerName;
            }
        }
    }
}

foreach ($orders as &$orderRow) {
    $orderId = (int) ($orderRow['id'] ?? 0);
    $problemDetails = [];
    $problemDetailsRaw = $orderRow['problem_details'] ?? null;
    if (is_string($problemDetailsRaw) && trim($problemDetailsRaw) !== '') {
        $decodedProblemDetails = json_decode($problemDetailsRaw, true);
        if (is_array($decodedProblemDetails)) {
            $problemDetails = $decodedProblemDetails;
        }
    } elseif (is_array($problemDetailsRaw)) {
        $problemDetails = $problemDetailsRaw;
    }

    $specialOrderModuleForRow = detectSpecialOrderModuleForAdmin($problemDetails);
    $specialServiceNamesForRow = $specialOrderModuleForRow !== ''
        ? resolveSpecialOrderServiceNamesForAdmin($problemDetails)
        : [];

    $customTitleFromDetails = '';
    $customDescriptionFromDetails = '';
    $customFlagFromDetails = false;
    if (!empty($problemDetails)) {
        $customFlagFromDetails = !empty($problemDetails['is_custom_service']);
        $customServiceData = $problemDetails['custom_service'] ?? null;
        if (is_array($customServiceData)) {
            $customTitleFromDetails = trim((string) ($customServiceData['title'] ?? ''));
            $customDescriptionFromDetails = trim((string) ($customServiceData['description'] ?? ''));
        }

        if ($customDescriptionFromDetails === '') {
            $customDescriptionFromDetails = trim((string) ($problemDetails['user_desc'] ?? ''));
        }

        if ($customTitleFromDetails === '' && $customFlagFromDetails) {
            $customTitleFromDetails = 'خدمة أخرى';
        }

        if (
            !$customFlagFromDetails
            && ($customTitleFromDetails !== '' || $customDescriptionFromDetails !== '')
        ) {
            $customFlagFromDetails = true;
        }
    }

    $serviceNames = !empty($specialServiceNamesForRow)
        ? $specialServiceNamesForRow
        : ($orderServiceNamesMap[$orderId] ?? []);
    $customTitle = trim((string) ($orderCustomServiceTitleMap[$orderId] ?? ''));
    if ($customTitle === '') {
        $customTitle = $customTitleFromDetails;
    }
    if ($specialOrderModuleForRow === '' && $customTitle !== '' && !in_array($customTitle, $serviceNames, true)) {
        $serviceNames[] = $customTitle;
    }

    if (empty($serviceNames)) {
        $fallbackServiceName = trim((string) ($orderRow['category_name'] ?? ''));
        if ($fallbackServiceName !== '') {
            $serviceNames[] = $fallbackServiceName;
        }
    }
    $orderRow['service_names_list'] = $serviceNames;

    $providerNames = $orderProviderNamesMap[$orderId] ?? [];
    $fallbackProviderName = trim((string) ($orderRow['provider_name'] ?? ''));
    if (empty($providerNames) && $fallbackProviderName !== '') {
        $providerNames[] = $fallbackProviderName;
    }
    $orderRow['provider_names_list'] = $providerNames;
    $orderRow['providers_count'] = count($providerNames);

    $customDescription = trim((string) ($orderCustomServiceDescriptionMap[$orderId] ?? ''));
    if ($customDescription === '') {
        $customDescription = $customDescriptionFromDetails;
    }

    $orderRow['special_order_module'] = $specialOrderModuleForRow;
    $orderRow['is_custom_service_request'] = $specialOrderModuleForRow === ''
        && (!empty($orderHasCustomServiceMap[$orderId]) || $customFlagFromDetails);
    $orderRow['custom_service_title'] = $specialOrderModuleForRow === '' ? $customTitle : '';
    $orderRow['custom_service_description'] = $specialOrderModuleForRow === '' ? $customDescription : '';
}
unset($orderRow);

// إحصائيات (مع استبعاد طلبات الخدمات الخاصة المنفصلة)
$statsWhere = '1=1';
$statsParams = [];

$stats = [
    'total' => (int) (db()->fetch(
        "SELECT COUNT(*) AS count FROM orders o WHERE {$statsWhere}",
        $statsParams
    )['count'] ?? 0),
    'pending' => (int) (db()->fetch(
        "SELECT COUNT(*) AS count FROM orders o WHERE {$statsWhere} AND o.status = 'pending'",
        $statsParams
    )['count'] ?? 0),
    'in_progress' => (int) (db()->fetch(
        "SELECT COUNT(*) AS count FROM orders o WHERE {$statsWhere} AND o.status IN ('accepted', 'on_the_way', 'arrived', 'in_progress')",
        $statsParams
    )['count'] ?? 0),
    'completed' => (int) (db()->fetch(
        "SELECT COUNT(*) AS count FROM orders o WHERE {$statsWhere} AND o.status = 'completed'",
        $statsParams
    )['count'] ?? 0),
    'cancelled' => (int) (db()->fetch(
        "SELECT COUNT(*) AS count FROM orders o WHERE {$statsWhere} AND o.status = 'cancelled'",
        $statsParams
    )['count'] ?? 0),
];

// فئات الخدمات للفلترة
$categories = getServiceCategoriesHierarchy(true);

// عرض تفاصيل طلب
if ($action === 'view' && $id) {
    $extraUserSelect = '';
    if (userHasColumn('no_show_count')) {
        $extraUserSelect .= ', u.no_show_count';
    }
    if (userHasColumn('is_blacklisted')) {
        $extraUserSelect .= ', u.is_blacklisted';
    }
    if (userHasColumn('blacklist_reason')) {
        $extraUserSelect .= ', u.blacklist_reason';
    }
    if (userHasColumn('avatar')) {
        $extraUserSelect .= ', u.avatar AS user_avatar';
    }

    $order = db()->fetch("
        SELECT o.*, 
               u.full_name as user_name, u.phone as user_phone, u.email as user_email {$extraUserSelect},
               p.full_name as provider_name, p.phone as provider_phone, p.rating as provider_rating,
               c.name_ar as category_name, c.icon as category_icon, c.image as category_image,
               a.city, a.district, a.street
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN providers p ON o.provider_id = p.id
        LEFT JOIN service_categories c ON o.category_id = c.id
        LEFT JOIN user_addresses a ON o.address_id = a.id
        WHERE o.id = ?
    ", [$id]);
    
    if (!$order) {
        setFlashMessage('danger', 'الطلب غير موجود');
        redirect('orders.php');
    }

    $specialOrderModule = detectSpecialOrderModuleForAdmin($order['problem_details'] ?? null);
    $specialOrderLinkedRequestUrl = '';
    $specialOrderRequestRow = null;
    $specialOrderContainerStores = [];
    if ($specialOrderModule === 'container') {
        $specialOrderLinkedRequestUrl = 'container-requests.php';
        if (tableExistsByName('container_requests') && tableColumnExistsByName('container_requests', 'source_order_id')) {
            $linkedContainerRequest = db()->fetch(
                'SELECT cr.id, COALESCE(cr.container_store_id, cs.store_id) AS container_store_id
                 FROM container_requests cr
                 LEFT JOIN container_services cs ON cs.id = cr.container_service_id
                 WHERE cr.source_order_id = ?
                 LIMIT 1',
                [$id]
            );
            if ($linkedContainerRequest) {
                $specialOrderRequestRow = $linkedContainerRequest;
            }
            if (!empty($linkedContainerRequest['id'])) {
                $specialOrderLinkedRequestUrl = 'container-requests.php?action=edit&id=' . (int) $linkedContainerRequest['id'];
            }
        }
        if (tableExistsByName('container_stores')) {
            $specialOrderContainerStores = db()->fetchAll(
                'SELECT id, name_ar, is_active FROM container_stores ORDER BY is_active DESC, sort_order ASC, id ASC'
            );
        }
    } elseif ($specialOrderModule === 'furniture') {
        $specialOrderLinkedRequestUrl = 'furniture-requests.php';
        if (tableExistsByName('furniture_requests') && tableColumnExistsByName('furniture_requests', 'source_order_id')) {
            $linkedFurnitureRequest = db()->fetch(
                'SELECT id FROM furniture_requests WHERE source_order_id = ? LIMIT 1',
                [$id]
            );
            if ($linkedFurnitureRequest) {
                $specialOrderRequestRow = $linkedFurnitureRequest;
            }
            if (!empty($linkedFurnitureRequest['id'])) {
                $specialOrderLinkedRequestUrl = 'furniture-requests.php?action=edit&id=' . (int) $linkedFurnitureRequest['id'];
            }
        }
    }
    
    $orderServiceItems = fetchOrderServiceItemsForAdmin($id, $order['problem_details'] ?? null);
    $orderAssignedProviders = fetchOrderAssignedProvidersForAdmin($id, $order);
    $orderAssignedProviderIds = [];
    $orderServiceIds = [];
    foreach ($orderAssignedProviders as $assignedProvider) {
        $providerId = (int) ($assignedProvider['provider_id'] ?? 0);
        if ($providerId > 0) {
            $orderAssignedProviderIds[$providerId] = $providerId;
        }
    }
    foreach ($orderServiceItems as $serviceItem) {
        $serviceId = (int) ($serviceItem['service_id'] ?? 0);
        if ($serviceId > 0) {
            $orderServiceIds[$serviceId] = $serviceId;
        }
    }
    $orderAssignedProviderIds = array_values($orderAssignedProviderIds);
    $orderServiceIds = array_values($orderServiceIds);

    // مقدمي خدمات متاحين لهذه الفئة
    $providerCategoryIds = [(int) $order['category_id']];
    $orderCategoryMeta = $serviceCategoryById[(int) $order['category_id']] ?? null;
    if ($orderCategoryMeta && !empty($orderCategoryMeta['parent_id'])) {
        $providerCategoryIds[] = (int) $orderCategoryMeta['parent_id'];
    }
    if ($specialOrderModule === 'furniture') {
        foreach ($serviceCategoryById as $categoryMeta) {
            $nameAr = (string) ($categoryMeta['name_ar'] ?? '');
            $nameEn = strtolower((string) ($categoryMeta['name_en'] ?? ''));
            if (
                strpos($nameAr, 'عفش') !== false
                || strpos($nameAr, 'أثاث') !== false
                || strpos($nameEn, 'furniture') !== false
                || strpos($nameEn, 'moving') !== false
            ) {
                $providerCategoryIds[] = (int) ($categoryMeta['id'] ?? 0);
                if (!empty($categoryMeta['parent_id'])) {
                    $providerCategoryIds[] = (int) $categoryMeta['parent_id'];
                }
            }
        }
    }

    if (!empty($orderServiceIds)) {
        $servicePlaceholders = implode(',', array_fill(0, count($orderServiceIds), '?'));
        $serviceRows = db()->fetchAll("SELECT category_id FROM services WHERE id IN ($servicePlaceholders)", $orderServiceIds);
        foreach ($serviceRows as $serviceRow) {
            $serviceCategoryId = (int) ($serviceRow['category_id'] ?? 0);
            if ($serviceCategoryId > 0) {
                $providerCategoryIds[] = $serviceCategoryId;
                $serviceCategoryMeta = $serviceCategoryById[$serviceCategoryId] ?? null;
                if ($serviceCategoryMeta && !empty($serviceCategoryMeta['parent_id'])) {
                    $providerCategoryIds[] = (int) $serviceCategoryMeta['parent_id'];
                }
            }
        }
    }

    $providerCategoryIds = array_values(array_unique(array_filter($providerCategoryIds)));

    if (!empty($providerCategoryIds) && tableExistsByName('provider_services')) {
        $providerPlaceholders = implode(',', array_fill(0, count($providerCategoryIds), '?'));
        $availableProviders = db()->fetchAll("
            SELECT DISTINCT p.* FROM providers p
            JOIN provider_services ps ON p.id = ps.provider_id
            WHERE ps.category_id IN ($providerPlaceholders) AND p.status = 'approved' AND p.is_available = 1
            ORDER BY p.rating DESC
        ", $providerCategoryIds);
    } else {
        $availableProviders = db()->fetchAll("
            SELECT p.* FROM providers p
            WHERE p.status = 'approved' AND p.is_available = 1
            ORDER BY p.rating DESC
        ");
    }

    if (!empty($orderAssignedProviderIds)) {
        $availableProviderIds = [];
        foreach ($availableProviders as $availableProvider) {
            $providerId = (int) ($availableProvider['id'] ?? 0);
            if ($providerId > 0) {
                $availableProviderIds[$providerId] = true;
            }
        }

        $missingAssignedProviderIds = array_values(array_filter(
            $orderAssignedProviderIds,
            static fn($providerId) => empty($availableProviderIds[(int) $providerId])
        ));

        if (!empty($missingAssignedProviderIds)) {
            $missingPlaceholders = implode(',', array_fill(0, count($missingAssignedProviderIds), '?'));
            $missingProviders = db()->fetchAll(
                "SELECT p.*
                 FROM providers p
                 WHERE p.id IN ($missingPlaceholders)
                 ORDER BY p.rating DESC",
                $missingAssignedProviderIds
            );
            $availableProviders = array_merge($missingProviders, $availableProviders);
        }
    }

    $availableProviders = decorateProvidersWithDistance(
        $availableProviders,
        $order['lat'] ?? null,
        $order['lng'] ?? null
    );
    
    // التقييم إن وجد
    $review = db()->fetch("SELECT * FROM reviews WHERE order_id = ?", [$id]);
    if (!$review && tableExistsByName('container_store_reviews')) {
        $review = db()->fetch(
            "SELECT r.*, cs.name_ar AS container_store_name
             FROM container_store_reviews r
             LEFT JOIN container_stores cs ON cs.id = r.store_id
             WHERE r.order_id = ?
             LIMIT 1",
            [$id]
        );
        if ($review) {
            $review['review_type'] = 'container_store';
        }
    }
    $orderLiveLocation = fetchLatestOrderLiveLocationForAdmin($id, (int) ($order['provider_id'] ?? 0));

    $customerDisplayName = resolveOrderCustomerName($order);

    $customerDisplayPhone = trim((string) ($order['user_phone'] ?? ''));
    if ($customerDisplayPhone === '') {
        $customerDisplayPhone = 'غير متوفر';
    }

    $customerDisplayEmail = trim((string) ($order['user_email'] ?? ''));
    if ($customerDisplayEmail === '' || strpos($customerDisplayEmail, '@') === false) {
        $customerDisplayEmail = 'غير متوفر';
    }

    $customerInitial = orderAdminFirstDisplayChar($customerDisplayName, 'م');
    $customerAvatarRaw = trim((string) ($order['user_avatar'] ?? ''));
    $customerAvatarUrl = $customerAvatarRaw !== ''
        ? imageUrl($customerAvatarRaw, '')
        : '';

    $orderStatusNormalized = strtolower(trim((string) ($order['status'] ?? '')));
    $isTrackingActiveStatus = $orderStatusNormalized === 'on_the_way';
    $hasCustomerTrackingCoords = isValidAdminMapCoordinatePair($order['lat'] ?? null, $order['lng'] ?? null);
    $customerTrackingLat = $hasCustomerTrackingCoords ? (float) $order['lat'] : null;
    $customerTrackingLng = $hasCustomerTrackingCoords ? (float) $order['lng'] : null;
    $hasLiveTrackingCoords = !empty($orderLiveLocation) && isValidAdminMapCoordinatePair(
        $orderLiveLocation['lat'] ?? null,
        $orderLiveLocation['lng'] ?? null
    );
    $liveTrackingLat = $hasLiveTrackingCoords ? (float) $orderLiveLocation['lat'] : null;
    $liveTrackingLng = $hasLiveTrackingCoords ? (float) $orderLiveLocation['lng'] : null;
    $liveTrackingFeedUrl = 'orders.php?action=live_tracking_feed&id=' . (int) $order['id'];
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- إحصائيات -->
<div class="stats-grid" style="margin-bottom: 25px;">
    <a href="?status=" class="stat-card animate-slideUp" style="text-decoration: none;">
        <div class="stat-icon secondary">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['total']); ?></h3>
            <p>إجمالي الطلبات</p>
        </div>
    </a>
    
    <a href="?status=pending" class="stat-card animate-slideUp" style="text-decoration: none; animation-delay: 0.1s;">
        <div class="stat-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['pending']); ?></h3>
            <p>قيد الانتظار</p>
        </div>
    </a>
    
    <a href="?status=in_progress" class="stat-card animate-slideUp" style="text-decoration: none; animation-delay: 0.2s;">
        <div class="stat-icon info">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['in_progress']); ?></h3>
            <p>قيد التنفيذ</p>
        </div>
    </a>
    
    <a href="?status=completed" class="stat-card animate-slideUp" style="text-decoration: none; animation-delay: 0.3s;">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['completed']); ?></h3>
            <p>مكتملة</p>
        </div>
    </a>
</div>

<!-- قائمة الطلبات -->
<div class="card animate-slideUp">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-clipboard-list" style="color: var(--primary-color);"></i>
            الطلبات
        </h3>
    </div>
    
    <div class="card-body">
        <!-- البحث والفلترة -->
        <form method="GET" style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <div class="search-input" style="flex: 1; min-width: 200px;">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="رقم الطلب، اسم العميل، الهاتف..." 
                       value="<?php echo $search; ?>">
            </div>
            
            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">جميع الحالات</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                <option value="accepted" <?php echo $status === 'accepted' ? 'selected' : ''; ?>>مقبول</option>
                <option value="on_the_way" <?php echo $status === 'on_the_way' ? 'selected' : ''; ?>>في الطريق</option>
                <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>مكتمل</option>
                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
            </select>
            
            <select name="category" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">جميع الخدمات</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo renderCategoryIconText($cat['icon'] ?? ''); ?> <?php echo htmlspecialchars(($cat['display_name_ar'] ?? $cat['name_ar']), ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>لا توجد طلبات</h3>
            <p>لم يتم العثور على أي طلبات مطابقة</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>الخدمة</th>
                        <th>مقدم الخدمة</th>
                        <th>المبلغ</th>
                        <th>الدفع</th>
                        <?php if (orderHasColumn('confirmation_status')): ?>
                        <th>تأكيد الموعد</th>
                        <?php endif; ?>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--secondary-color);"><?php echo $order['order_number']; ?></strong>
                        </td>
                        <td>
                            <?php
                                $rowCustomerName = trim((string) ($order['user_name'] ?? 'غير معروف'));
                                if ($rowCustomerName === '') {
                                    $rowCustomerName = 'غير معروف';
                                }
                                $rowCustomerAvatarRaw = trim((string) ($order['user_avatar'] ?? ''));
                                $rowCustomerAvatarUrl = $rowCustomerAvatarRaw !== ''
                                    ? imageUrl($rowCustomerAvatarRaw, '')
                                    : '';
                                $rowCustomerInitial = function_exists('mb_substr')
                                    ? mb_substr($rowCustomerName, 0, 1, 'UTF-8')
                                    : substr($rowCustomerName, 0, 1);
                            ?>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($rowCustomerAvatarUrl !== ''): ?>
                                <img
                                    src="<?php echo htmlspecialchars($rowCustomerAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="صورة العميل"
                                    style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid #e5e7eb;"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: #fff; display: none; align-items: center; justify-content: center; font-weight: 700;">
                                    <?php echo htmlspecialchars((string) $rowCustomerInitial, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php else: ?>
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                    <?php echo htmlspecialchars((string) $rowCustomerInitial, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($rowCustomerName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <div style="font-size: 12px; color: #6b7280;" dir="ltr"><?php echo htmlspecialchars((string) ($order['user_phone'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                                $specialOverride = getSpecialOrderDisplayOverride($order['problem_details'] ?? null);
                                $displayCategoryName = $specialOverride['name'] ?? '';
                                if ($displayCategoryName === '' || $displayCategoryName === null) {
                                    $displayCategoryName = $categoryDisplayMap[(int) ($order['category_id'] ?? 0)]
                                        ?? ($order['category_name'] ?? 'غير محدد');
                                }
                                $displayCategoryIcon = $specialOverride['icon'] ?? '';
                                if ($displayCategoryIcon === '' || $displayCategoryIcon === null) {
                                    $displayCategoryIcon = serviceCategoryPrimaryMediaForApi(
                                        $order['category_icon'] ?? '',
                                        $order['category_image'] ?? '',
                                        $displayCategoryName,
                                        ''
                                    );
                                }
                            ?>
                            <span style="display: inline-flex; align-items: center; gap: 5px; margin-bottom: 6px;">
                                <?php echo renderCategoryIcon($displayCategoryIcon, 20); ?>
                                <?php echo htmlspecialchars($displayCategoryName, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if (!empty($order['service_names_list'])): ?>
                            <?php
                                $isSparePartsOrder = false;
                                foreach ($order['service_names_list'] as $serviceNameCheck) {
                                    $serviceNameCheck = trim((string) $serviceNameCheck);
                                    if ($serviceNameCheck !== '' && strpos($serviceNameCheck, 'قطع غيار') !== false) {
                                        $isSparePartsOrder = true;
                                        break;
                                    }
                                }

                                if (!$isSparePartsOrder) {
                                    $problemDetailsParsed = null;
                                    $problemDetailsRaw = $order['problem_details'] ?? null;
                                    if (is_string($problemDetailsRaw) && trim($problemDetailsRaw) !== '') {
                                        $decodedProblemDetails = json_decode($problemDetailsRaw, true);
                                        if (is_array($decodedProblemDetails)) {
                                            $problemDetailsParsed = $decodedProblemDetails;
                                        }
                                    } elseif (is_array($problemDetailsRaw)) {
                                        $problemDetailsParsed = $problemDetailsRaw;
                                    }

                                    if (is_array($problemDetailsParsed)) {
                                        $problemType = strtolower(trim((string) ($problemDetailsParsed['type'] ?? '')));
                                        $rawSpareParts = $problemDetailsParsed['spare_parts'] ?? [];
                                        $isSparePartsOrder = in_array(
                                            $problemType,
                                            ['spare_parts_with_installation', 'spare_parts_order', 'spare_parts'],
                                            true
                                        ) || (is_array($rawSpareParts) && !empty($rawSpareParts));
                                    }
                                }
                            ?>
                            <div style="font-size: 12px; color: #475569;">
                                <?php
                                    $previewServices = array_slice($order['service_names_list'], 0, 2);
                                    echo htmlspecialchars(implode('، ', $previewServices), ENT_QUOTES, 'UTF-8');
                                    if (count($order['service_names_list']) > 2) {
                                        echo ' +' . (count($order['service_names_list']) - 2);
                                    }
                                ?>
                            </div>
                            <?php if ($isSparePartsOrder): ?>
                            <div style="margin-top: 6px;">
                                <span class="badge badge-info">طلب قطع غيار</span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($order['is_custom_service_request'])): ?>
                            <div style="margin-top: 6px;">
                                <span class="badge badge-warning">خدمة أخرى</span>
                            </div>
                            <?php if (!empty($order['custom_service_title'])): ?>
                            <div style="font-size: 12px; color: #334155; margin-top: 4px;">
                                <?php echo htmlspecialchars($order['custom_service_title'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($order['custom_service_description'])): ?>
                            <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                <?php echo htmlspecialchars($order['custom_service_description'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($order['provider_names_list'])): ?>
                            <?php
                                $previewProviders = array_slice($order['provider_names_list'], 0, 2);
                                echo htmlspecialchars(implode('، ', $previewProviders), ENT_QUOTES, 'UTF-8');
                                if (count($order['provider_names_list']) > 2) {
                                    echo ' +' . (count($order['provider_names_list']) - 2);
                                }
                            ?>
                            <?php else: ?>
                            <span style="color: #f59e0b;">لم يُعين بعد</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($order['total_amount'], 2); ?> ⃁</strong>
                        </td>
                        <td>
                            <?php $paymentStatus = $order['payment_status'] ?? 'pending'; ?>
                            <span class="badge <?php echo $paymentStatus === 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo getPaymentStatusAr($paymentStatus); ?>
                            </span>
                            <?php
                                $rowGatewayStatus = orderHasColumn('myfatoorah_invoice_status')
                                    ? trim((string) ($order['myfatoorah_invoice_status'] ?? ''))
                                    : '';
                            ?>
                            <?php if ($rowGatewayStatus !== ''): ?>
                            <div style="margin-top: 6px; font-size: 11px; color: #475569;">
                                MyFatoorah: <?php echo htmlspecialchars(getMyFatoorahInvoiceStatusAr($rowGatewayStatus), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php if (orderHasColumn('confirmation_status')): ?>
                        <td>
                            <?php
                                $confStatus = $order['confirmation_status'] ?? 'pending';
                                $confClass = 'badge-warning';
                                $confText = 'قيد التأكيد';
                                if ($confStatus === 'confirmed') {
                                    $confClass = 'badge-success';
                                    $confText = 'تم التأكيد';
                                } elseif ($confStatus === 'unreachable') {
                                    $confClass = 'badge-danger';
                                    $confText = 'لا يرد';
                                } elseif ($confStatus === 'cancelled') {
                                    $confClass = 'badge-dark';
                                    $confText = 'ملغي';
                                }
                            ?>
                            <span class="badge <?php echo $confClass; ?>"><?php echo $confText; ?></span>
                        </td>
                        <?php endif; ?>
                        <td>
                            <span class="badge <?php echo getOrderStatusColor($order['status']); ?>">
                                <?php echo getOrderStatusAr($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo timeAgo($order['created_at']); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- التصفح -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination" style="margin-top: 20px;">
            <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&category=<?php echo $categoryId; ?>&search=<?php echo $search; ?>" 
               class="page-link <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'view' && isset($order)): ?>
<!-- تفاصيل الطلب -->
<div style="margin-bottom: 20px;">
    <a href="orders.php" class="btn btn-outline">
        <i class="fas fa-arrow-right"></i>
        العودة للقائمة
    </a>
</div>

<?php if ($specialOrderModule === 'container' || $specialOrderModule === 'furniture'): ?>
<div class="alert alert-info" style="margin-bottom: 18px;">
    <strong>
        <?php echo $specialOrderModule === 'container' ? 'طلب حاويات' : 'طلب نقل عفش'; ?>
    </strong>
    يتم عرضه الآن داخل الطلبات العادية للمتابعة وإنهاء الحالة.
    <?php if (!empty($specialOrderLinkedRequestUrl)): ?>
        <a href="<?php echo htmlspecialchars($specialOrderLinkedRequestUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline" style="margin-right: 8px;">
            فتح صفحة الطلب المتخصصة
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- تحديث الحالة -->
<div class="card animate-slideUp" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-tasks" style="color: var(--info-color);"></i>
            تحديث الحالة
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php 
                $statuses = ['pending', 'accepted', 'on_the_way', 'arrived', 'in_progress', 'completed', 'cancelled'];
                foreach ($statuses as $s): 
                    $isCurrentStatus = ($order['status'] === $s);
                    $canTransitionStatus = isOrderStatusTransitionAllowed((string) ($order['status'] ?? ''), $s);
                    $isStatusDisabled = $isCurrentStatus || !$canTransitionStatus;
                ?>
                <button type="submit" name="new_status" value="<?php echo $s; ?>" 
                        class="btn <?php echo $isCurrentStatus ? 'btn-primary' : 'btn-outline'; ?>"
                        <?php echo $isStatusDisabled ? 'disabled' : ''; ?>>
                    <?php echo getOrderStatusAr($s); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    <div>
        <!-- معلومات الطلب -->
        <div class="card animate-slideUp" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clipboard-list" style="color: var(--primary-color);"></i>
                    طلب #<?php echo $order['order_number']; ?>
                </h3>
                <span class="badge <?php echo getOrderStatusColor($order['status']); ?>" style="font-size: 14px;">
                    <?php echo getOrderStatusAr($order['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <!-- الخدمة -->
                <div style="display: flex; align-items: center; gap: 15px; padding: 20px; background: var(--gray-50); border-radius: 12px; margin-bottom: 20px;">
                    <?php
                        $detailOverride = getSpecialOrderDisplayOverride($order['problem_details'] ?? null);
                        $detailCategoryName = $detailOverride['name'] ?? '';
                        if ($detailCategoryName === '' || $detailCategoryName === null) {
                            $detailCategoryName = $categoryDisplayMap[(int) ($order['category_id'] ?? 0)]
                                ?? ($order['category_name'] ?? 'غير محدد');
                        }
                        $detailCategoryIcon = $detailOverride['icon'] ?? '';
                        if ($detailCategoryIcon === '' || $detailCategoryIcon === null) {
                            $detailCategoryIcon = serviceCategoryPrimaryMediaForApi(
                                $order['category_icon'] ?? '',
                                $order['category_image'] ?? '',
                                $detailCategoryName,
                                ''
                            );
                        }
                    ?>
                    <div style="width: 60px; height: 60px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <?php echo renderCategoryIcon($detailCategoryIcon, 40); ?>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($detailCategoryName, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p style="color: #6b7280; font-size: 14px; margin: 0;">
                            تاريخ الطلب: <?php echo formatDateTime($order['created_at']); ?>
                        </p>
                        <?php if (!empty($orderServiceItems)): ?>
                        <p style="color: #334155; font-size: 13px; margin: 6px 0 0;">
                            الخدمات المطلوبة:
                            <?php
                                $serviceNamesForHeader = [];
                                foreach ($orderServiceItems as $serviceItem) {
                                    $serviceName = trim((string) ($serviceItem['service_name'] ?? ''));
                                    if ($serviceName !== '' && !in_array($serviceName, $serviceNamesForHeader, true)) {
                                        $serviceNamesForHeader[] = $serviceName;
                                    }
                                }
                                echo htmlspecialchars(implode('، ', $serviceNamesForHeader), ENT_QUOTES, 'UTF-8');
                            ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- تفاصيل المشكلة -->
                <?php
                    $problemDetails = json_decode($order['problem_details'] ?? '', true);
                    if (!is_array($problemDetails)) {
                        $problemDetails = [];
                    }

                    $attachments = json_decode($order['attachments'] ?? '', true);
                    if (!is_array($attachments)) {
                        $attachments = [];
                    }

                    $legacyProblemImages = [];
                    if (!empty($order['problem_images'])) {
                        $decodedLegacy = json_decode($order['problem_images'], true);
                        if (is_array($decodedLegacy)) {
                            $legacyProblemImages = $decodedLegacy;
                        } elseif (is_string($order['problem_images']) && trim($order['problem_images']) !== '') {
                            $legacyProblemImages = [trim($order['problem_images'])];
                        }
                    }

                    $allMedia = [];
                    foreach (array_merge($attachments, $legacyProblemImages) as $mediaItem) {
                        $mediaPath = trim((string) $mediaItem);
                        if ($mediaPath !== '' && !in_array($mediaPath, $allMedia, true)) {
                            $allMedia[] = $mediaPath;
                        }
                    }

                    $problemDescription = trim((string) ($problemDetails['user_desc'] ?? ''));
                    if ($problemDescription === '') {
                        $problemDescription = trim((string) ($order['notes'] ?? ''));
                    }
                    if ($problemDescription === '' && !empty($order['problem_description'])) {
                        $problemDescription = trim((string) $order['problem_description']);
                    }

                    $problemTypes = [];
                    $appendProblemTypes = static function ($value) use (&$problemTypes, &$appendProblemTypes) {
                        if ($value instanceof stdClass) {
                            $value = (array) $value;
                        }

                        if (is_string($value)) {
                            $trimmed = trim($value);
                            if ($trimmed === '') {
                                return;
                            }
                            $decoded = json_decode($trimmed, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $appendProblemTypes($decoded);
                                return;
                            }
                            if (!in_array($trimmed, $problemTypes, true)) {
                                $problemTypes[] = $trimmed;
                            }
                            return;
                        }

                        if (!is_array($value)) {
                            return;
                        }

                        foreach ($value as $item) {
                            if ($item instanceof stdClass) {
                                $item = (array) $item;
                            }
                            if (is_array($item)) {
                                $nestedItem = $item;
                                $item = $item['title_ar']
                                    ?? $item['name_ar']
                                    ?? $item['title']
                                    ?? $item['name']
                                    ?? $item['type']
                                    ?? $item['title_en']
                                    ?? $item['name_en']
                                    ?? '';
                                if ($item === '') {
                                    $appendProblemTypes($nestedItem);
                                    continue;
                                }
                            }
                            $label = trim((string) $item);
                            if ($label !== '' && !in_array($label, $problemTypes, true)) {
                                $problemTypes[] = $label;
                            }
                        }
                    };
                    $appendProblemTypes($problemDetails['problem_type_labels'] ?? []);
                    $appendProblemTypes($problemDetails['problem_types'] ?? []);
                    $appendProblemTypes($problemDetails['types'] ?? []);
                    $appendProblemTypes($problemDetails['problem_type_titles'] ?? []);
                    $appendProblemTypes($problemDetails['selected_problem_types'] ?? []);
                    $appendProblemTypes(fetchProblemTypeLabelsByIdsForAdmin(
                        normalizeProblemTypeIdsForAdmin($problemDetails, $order['type_option_id'] ?? null)
                    ));
                    if (empty($problemTypes)) {
                        $appendProblemTypes($problemDetails['type'] ?? '');
                    }
                    $problemType = !empty($problemTypes) ? implode('، ', $problemTypes) : 'غير محدد';
                    $inspectionPolicy = [];
                    if (isset($problemDetails['inspection_policy'])) {
                        $rawInspectionPolicy = $problemDetails['inspection_policy'];
                        if ($rawInspectionPolicy instanceof stdClass) {
                            $rawInspectionPolicy = (array) $rawInspectionPolicy;
                        }
                        if (is_array($rawInspectionPolicy)) {
                            $inspectionPolicy = $rawInspectionPolicy;
                        }
                    }
                    $inspectionPolicyDetails = trim((string) ($inspectionPolicy['details_ar'] ?? ''));
                    $inspectionPolicySource = trim((string) ($inspectionPolicy['source_name'] ?? ''));

                    $serviceTypeNames = [];
                    $customServiceNames = [];
                    foreach ($orderServiceItems as $serviceItem) {
                        $serviceName = trim((string) ($serviceItem['service_name'] ?? ''));
                        if ($serviceName === '') {
                            continue;
                        }
                        if (!empty($serviceItem['is_custom'])) {
                            if (!in_array($serviceName, $customServiceNames, true)) {
                                $customServiceNames[] = $serviceName;
                            }
                        } else {
                            if (!in_array($serviceName, $serviceTypeNames, true)) {
                                $serviceTypeNames[] = $serviceName;
                            }
                        }
                    }
                ?>

                <div style="margin-bottom: 25px; padding: 20px; border: 1px solid var(--gray-200); border-radius: 12px;">
                    <h4 style="margin-bottom: 15px; border-bottom: 1px solid var(--gray-200); padding-bottom: 10px;">
                        <i class="fas fa-stethoscope" style="color: var(--info-color);"></i> تفاصيل المشكلة من العميل
                    </h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                        <div>
                            <strong>نوع المشكلة:</strong>
                            <p><?php echo htmlspecialchars($problemType, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div>
                            <strong>نوع الخدمة:</strong>
                            <p><?php echo !empty($serviceTypeNames) ? implode('، ', $serviceTypeNames) : 'غير محدد'; ?></p>
                        </div>
                    </div>

                    <?php if ($inspectionPolicyDetails !== '' || $inspectionPolicySource !== ''): ?>
                    <div style="margin-bottom: 15px; padding: 12px; background: #f8fafc; border-radius: 10px;">
                        <strong>تفاصيل المعاينة:</strong>
                        <?php if ($inspectionPolicyDetails !== ''): ?>
                            <p style="margin: 6px 0 0;"><?php echo htmlspecialchars($inspectionPolicyDetails, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php if ($inspectionPolicySource !== ''): ?>
                            <p style="margin: 6px 0 0; color: #64748b; font-size: 13px;">تم احتسابها حسب: <?php echo htmlspecialchars($inspectionPolicySource, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($customServiceNames)): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>خدمات أخرى مطلوبة:</strong>
                        <p style="margin-top: 8px;"><?php echo htmlspecialchars(implode('، ', $customServiceNames), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php
                        $requestedSpareParts = [];
                        $rawRequestedSpares = $problemDetails['spare_parts'] ?? [];
                        if (is_array($rawRequestedSpares)) {
                            foreach ($rawRequestedSpares as $spareItem) {
                                if ($spareItem instanceof stdClass) {
                                    $spareItem = (array) $spareItem;
                                }
                                if (!is_array($spareItem)) {
                                    continue;
                                }

                                $spareName = trim((string) ($spareItem['name'] ?? ''));
                                $spareQty = max(1, (int) ($spareItem['quantity'] ?? 1));
                                $spareMode = trim((string) ($spareItem['pricing_mode'] ?? 'with_installation'));
                                $spareUnitPrice = (float) ($spareItem['unit_price'] ?? 0);

                                if ($spareName === '' && !empty($spareItem['spare_part_id'])) {
                                    $spareName = 'قطعة #' . (int) $spareItem['spare_part_id'];
                                }
                                if ($spareName === '') {
                                    continue;
                                }

                                $requestedSpareParts[] = [
                                    'name' => $spareName,
                                    'quantity' => $spareQty,
                                    'pricing_mode' => $spareMode,
                                    'unit_price' => $spareUnitPrice,
                                ];
                            }
                        }
                    ?>

                    <?php if (!empty($requestedSpareParts)): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>قطع الغيار المطلوبة:</strong>
                        <div style="margin-top: 10px; border: 1px solid var(--gray-200); border-radius: 10px; overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr style="background: var(--gray-50);">
                                        <th style="text-align: right; padding: 8px 10px; border-bottom: 1px solid var(--gray-200);">القطعة</th>
                                        <th style="text-align: center; padding: 8px 10px; border-bottom: 1px solid var(--gray-200);">الكمية</th>
                                        <th style="text-align: center; padding: 8px 10px; border-bottom: 1px solid var(--gray-200);">النوع</th>
                                        <th style="text-align: center; padding: 8px 10px; border-bottom: 1px solid var(--gray-200);">سعر الوحدة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requestedSpareParts as $spareRow): ?>
                                    <tr>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid var(--gray-100);">
                                            <?php echo htmlspecialchars($spareRow['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td style="padding: 8px 10px; text-align: center; border-bottom: 1px solid var(--gray-100);">
                                            <?php echo (int) $spareRow['quantity']; ?>
                                        </td>
                                        <td style="padding: 8px 10px; text-align: center; border-bottom: 1px solid var(--gray-100);">
                                            <?php echo $spareRow['pricing_mode'] === 'without_installation' ? 'بدون تركيب' : 'مع التركيب'; ?>
                                        </td>
                                        <td style="padding: 8px 10px; text-align: center; border-bottom: 1px solid var(--gray-100);">
                                            <?php echo number_format((float) $spareRow['unit_price'], 2); ?> ⃁
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 15px;">
                        <strong>وصف المشكلة:</strong>
                        <p style="margin-top: 8px;"><?php echo $problemDescription !== '' ? nl2br($problemDescription) : 'لا يوجد'; ?></p>
                    </div>

                    <?php if (!empty($allMedia)): ?>
                    <div style="margin-top: 20px;">
                        <strong>صور / مرفقات المشكلة:</strong>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                            <?php foreach ($allMedia as $media): ?>
                                <?php
                                    $mediaUrl = imageUrl($media);
                                    $pathForExt = parse_url($media, PHP_URL_PATH);
                                    if (!$pathForExt) {
                                        $pathForExt = $media;
                                    }
                                    $ext = strtolower(pathinfo($pathForExt, PATHINFO_EXTENSION));
                                    $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'm4v'], true);
                                ?>
                                <?php if ($isVideo): ?>
                                <a href="<?php echo $mediaUrl; ?>" target="_blank"
                                   style="width: 110px; height: 110px; border-radius: 8px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; background: #f8fafc; text-decoration: none; color: #0f172a;">
                                    <span><i class="fas fa-play-circle"></i> فيديو</span>
                                </a>
                                <?php else: ?>
                                <a href="<?php echo $mediaUrl; ?>" target="_blank">
                                    <img src="<?php echo $mediaUrl; ?>" alt="صورة مشكلة"
                                         style="width: 110px; height: 110px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                        $inspectionImages = [];
                        if (!empty($order['inspection_images'])) {
                            $decodedInspection = json_decode((string) $order['inspection_images'], true);
                            if (is_array($decodedInspection)) {
                                foreach ($decodedInspection as $inspectionImageItem) {
                                    $inspectionPath = trim((string) $inspectionImageItem);
                                    if ($inspectionPath !== '' && !in_array($inspectionPath, $inspectionImages, true)) {
                                        $inspectionImages[] = $inspectionPath;
                                    }
                                }
                            } else {
                                $singleInspectionPath = trim((string) $order['inspection_images']);
                                if ($singleInspectionPath !== '') {
                                    $inspectionImages[] = $singleInspectionPath;
                                }
                            }
                        }
                    ?>
                    <?php if (!empty($inspectionImages)): ?>
                    <div style="margin-top: 20px;">
                        <strong>صور دليل التنفيذ المرفوعة من الفني:</strong>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                            <?php foreach ($inspectionImages as $inspectionImage): ?>
                            <a href="<?php echo imageUrl($inspectionImage); ?>" target="_blank">
                                <img src="<?php echo imageUrl($inspectionImage); ?>" alt="صورة معاينة"
                                     style="width: 110px; height: 110px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- العنوان -->
                <?php if ($order['city'] || $order['district'] || $order['address']): ?>
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px;"><i class="fas fa-map-marker-alt" style="color: var(--danger-color);"></i> العنوان</h4>
                    <p style="color: #374151;">
                        <?php echo $order['address']; ?> <br>
                        <?php echo $order['city']; ?>، <?php echo $order['district']; ?>
                        <?php if ($order['street']): ?> - <?php echo $order['street']; ?><?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- ملاحظات الإدارة -->
                <div>
                    <h4 style="margin-bottom: 10px;"><i class="fas fa-sticky-note" style="color: var(--warning-color);"></i> ملاحظات الإدارة</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_note">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="أضف ملاحظة..."><?php echo $order['admin_notes']; ?></textarea>
                        <button type="submit" class="btn btn-outline btn-sm" style="margin-top: 10px;">حفظ الملاحظة</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- عمليات التشغيل (Operations) -->
        <div class="card animate-slideUp" style="margin-bottom: 25px; border: 2px solid var(--primary-color);">
            <div class="card-header" style="background-color: var(--primary-light);">
                <h3 class="card-title">
                    <i class="fas fa-cogs" style="color: var(--primary-dark);"></i>
                    إدارة العمليات والتقديرات
                </h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="set_estimate">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>الحد الأدنى للتقدير (⃁)</label>
                            <input type="number" step="0.01" name="min_estimate" class="form-control" 
                                   value="<?php echo $order['min_estimate']; ?>" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>الحد الأقصى للتقدير (⃁)</label>
                            <input type="number" step="0.01" name="max_estimate" class="form-control" 
                                   value="<?php echo $order['max_estimate']; ?>" placeholder="0.00">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                        حفظ التقدير المبدئي
                    </button>
                </form>
                <small class="text-muted" style="display: block; margin-top: 10px;">
                    * هذا التقدير سيظهر للفني وموظفي العمليات للمساعدة في التجهيز.
                </small>
            </div>
        </div>

        <?php if (orderHasColumn('confirmation_status')): ?>
        <!-- تأكيد الموعد قبل التنفيذ -->
        <div class="card animate-slideUp" style="margin-bottom: 25px; border: 2px solid #0ea5e9;">
            <div class="card-header" style="background: #f0f9ff;">
                <h3 class="card-title">
                    <i class="fas fa-phone-volume" style="color: #0284c7;"></i>
                    تأكيد الموعد قبل التنفيذ
                </h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 12px;">
                    <strong>الموعد المحدد:</strong>
                    <span><?php echo ($order['scheduled_date'] ?: '-') . ' ' . ($order['scheduled_time'] ?: ''); ?></span>
                </div>
                <?php if (orderHasColumn('confirmation_due_at')): ?>
                <div style="margin-bottom: 12px;">
                    <strong>موعد اتصال التأكيد المقترح:</strong>
                    <span><?php echo $order['confirmation_due_at'] ?: 'غير محدد'; ?></span>
                </div>
                <?php endif; ?>
                <?php if (orderHasColumn('confirmation_attempts')): ?>
                <div style="margin-bottom: 12px;">
                    <strong>عدد محاولات التواصل:</strong>
                    <span><?php echo (int)($order['confirmation_attempts'] ?? 0); ?></span>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="update_confirmation">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <input type="hidden" name="confirmation_status" id="confirmation-status-input" value="pending">
                    <div class="form-group">
                        <label>ملاحظات الاتصال</label>
                        <textarea name="confirmation_notes" class="form-control" rows="2" placeholder="ملاحظات فريق العمليات..."><?php echo $order['confirmation_notes'] ?? ''; ?></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-success" onclick="document.getElementById('confirmation-status-input').value='confirmed'">
                            تم تأكيد الموعد
                        </button>
                        <button type="submit" class="btn btn-warning" onclick="document.getElementById('confirmation-status-input').value='unreachable'">
                            العميل لا يرد
                        </button>
                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('confirmation-status-input').value='cancelled'">
                            إلغاء بسبب عدم التأكيد
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- مراجعة الفاتورة النهائية -->
        <?php if ($order['invoice_status'] != 'none'): ?>
        <div class="card animate-slideUp" style="margin-bottom: 25px; border: 1px solid <?php echo $order['invoice_status'] == 'approved' ? 'green' : 'orange'; ?>;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    الفاتورة المقدمة من الفني
                </h3>
                <span class="badge"><?php echo $order['invoice_status']; ?></span>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>تكلفة اليد العاملة</th>
                        <td><?php echo number_format($order['labor_cost'], 2); ?> ⃁</td>
                    </tr>
                    <tr>
                        <th>قطع الغيار</th>
                        <td><?php echo number_format($order['parts_cost'], 2); ?> ⃁</td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <th><strong>الإجمالي</strong></th>
                        <td><strong><?php echo number_format($order['labor_cost'] + $order['parts_cost'], 2); ?> ⃁</strong></td>
                    </tr>
                </table>
                <?php if ($order['invoice_status'] == 'pending'): ?>
                    <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 5px;">
                        بانتظار موافقة العميل
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- تعيين مقدم الخدمة / المتجر -->
        <?php if (!in_array($order['status'], ['completed', 'cancelled'], true)): ?>
            <?php if ($specialOrderModule === 'container'): ?>
                <div class="card animate-slideUp" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-store" style="color: var(--secondary-color);"></i>
                            تعيين متجر الحاويات
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($specialOrderRequestRow['id'])): ?>
                            <p style="color: #6b7280;">لا يوجد طلب حاويات مرتبط لهذا الطلب.</p>
                        <?php elseif (empty($specialOrderContainerStores)): ?>
                            <p style="color: #6b7280;">لا توجد متاجر حاويات مضافة بعد.</p>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_container_store">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <div class="form-group">
                                    <label style="display: block; margin-bottom: 8px;">اختر متجر الحاويات</label>
                                    <select name="container_store_id" class="form-control">
                                        <option value="0">بدون تعيين</option>
                                        <?php foreach ($specialOrderContainerStores as $storeItem): ?>
                                            <?php
                                                $storeId = (int) ($storeItem['id'] ?? 0);
                                                $selected = !empty($specialOrderRequestRow['container_store_id'])
                                                    && (int) $specialOrderRequestRow['container_store_id'] === $storeId;
                                                $storeLabel = trim((string) ($storeItem['name_ar'] ?? ''));
                                                if ($storeLabel === '') {
                                                    $storeLabel = 'متجر #' . $storeId;
                                                }
                                                if (isset($storeItem['is_active']) && (int) $storeItem['is_active'] === 0) {
                                                    $storeLabel .= ' (غير نشط)';
                                                }
                                            ?>
                                            <option value="<?php echo $storeId; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($storeLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-save"></i>
                                    حفظ التعيين
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($specialOrderLinkedRequestUrl)): ?>
                            <div style="margin-top: 10px;">
                                <a href="<?php echo htmlspecialchars($specialOrderLinkedRequestUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline btn-sm">
                                    فتح طلب الحاويات
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($specialOrderModule === 'furniture'): ?>
                <div class="card animate-slideUp" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-dolly" style="color: var(--secondary-color);"></i>
                            طلب نقل عفش
                        </h3>
                    </div>
                    <div class="card-body">
                        <p style="color: #6b7280; margin-bottom: 10px;">
                            بيانات نقل العفش التفصيلية تتم من الصفحة المخصصة، وإسناد مقدم الخدمة يتم من هنا حتى يظهر الطلب في تطبيق مقدم الخدمة.
                        </p>
                        <?php if (!empty($specialOrderLinkedRequestUrl)): ?>
                            <a href="<?php echo htmlspecialchars($specialOrderLinkedRequestUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline btn-sm">
                                فتح طلب نقل العفش
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div id="provider-assignment" class="card animate-slideUp" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-plus" style="color: var(--secondary-color);"></i>
                            تعيين مقدمي الخدمة
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($orderAssignedProviders)): ?>
                        <div style="margin-bottom: 14px;">
                            <strong style="display: block; margin-bottom: 6px;">المقدمون المعينون حاليًا:</strong>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php foreach ($orderAssignedProviders as $assignedProvider): ?>
                                    <span class="badge badge-info" style="padding: 8px 12px;">
                                        <?php echo htmlspecialchars((string) ($assignedProvider['provider_name'] ?? ('مقدم #' . $assignedProvider['provider_id'])), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($assignedProvider['is_primary'])): ?>
                                            (أساسي)
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (empty($availableProviders)): ?>
                        <p style="color: #6b7280;">لا يوجد مقدمي خدمات متاحين لهذه الفئة</p>
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="assign_provider">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 8px;">اختر مقدم خدمة أو أكثر</label>
                                <small style="display: block; margin-bottom: 8px; color: #64748b;">
                                    القائمة مرتبة تلقائيًا حسب الأقرب لموقع العميل ثم الأعلى تقييمًا.
                                </small>
                                <div style="max-height: 220px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px;">
                                    <?php foreach ($availableProviders as $prov): ?>
                                    <?php $isAlreadyAssigned = in_array((int) $prov['id'], $orderAssignedProviderIds, true); ?>
                                    <?php
                                        $distanceKm = isset($prov['distance_km']) && $prov['distance_km'] !== null
                                            ? (float) $prov['distance_km']
                                            : null;
                                    ?>
                                    <label style="display: flex; gap: 10px; align-items: center; padding: 8px 6px; border-bottom: 1px solid #f1f5f9;">
                                        <input
                                            type="checkbox"
                                            name="provider_ids[]"
                                            value="<?php echo (int) $prov['id']; ?>"
                                            <?php echo $isAlreadyAssigned ? 'checked' : ''; ?>
                                        >
                                        <span style="font-size: 13px;">
                                            <?php echo htmlspecialchars((string) ($prov['full_name'] ?? 'غير معروف'), ENT_QUOTES, 'UTF-8'); ?>
                                            -
                                            ⭐ <?php echo number_format((float) ($prov['rating'] ?? 0), 1); ?>
                                            -
                                            <?php echo (int) ($prov['completed_orders'] ?? 0); ?> طلب مكتمل
                                            <?php if ($distanceKm !== null): ?>
                                                -
                                                <?php echo number_format($distanceKm, $distanceKm < 10 ? 2 : 1); ?> كم
                                            <?php endif; ?>
                                            <?php if ($isAlreadyAssigned): ?>
                                                <span style="color: #2563eb;">(معين)</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-user-check"></i>
                                حفظ التعيين
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
    </div>
    
    <div>
        <!-- معلومات العميل -->
        <div class="card animate-slideUp" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user" style="color: var(--primary-color);"></i>
                    العميل
                </h3>
            </div>
            <div class="card-body" style="text-align: center;">
                <?php if ($customerAvatarUrl !== ''): ?>
                <img
                    src="<?php echo htmlspecialchars($customerAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="صورة العميل"
                    style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%; margin: 0 auto 15px; border: 2px solid #e5e7eb; display: block;"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border-radius: 50%; align-items: center; justify-content: center; margin: 0 auto 15px; color: white; font-size: 28px; display: none;">
                    <?php echo htmlspecialchars($customerInitial, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php else: ?>
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: white; font-size: 28px;">
                    <?php echo htmlspecialchars($customerInitial, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                <h4><?php echo htmlspecialchars($customerDisplayName, ENT_QUOTES, 'UTF-8'); ?></h4>
                <p style="color: #6b7280; direction: ltr;"><?php echo htmlspecialchars($customerDisplayPhone, ENT_QUOTES, 'UTF-8'); ?></p>
                <p style="color: #6b7280; font-size: 13px;"><?php echo htmlspecialchars($customerDisplayEmail, ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if (userHasColumn('no_show_count')): ?>
                <div style="margin-top: 10px; padding: 10px; border-radius: 10px; background: #fff7ed; border: 1px solid #fed7aa;">
                    <strong>سجل الالتزام:</strong>
                    <div style="margin-top: 6px;">حالات عدم الالتزام بالمواعيد: <strong><?php echo (int)($order['no_show_count'] ?? 0); ?></strong></div>
                    <?php if (userHasColumn('is_blacklisted') && !empty($order['is_blacklisted'])): ?>
                    <div style="margin-top: 6px; color: #b91c1c;">
                        <strong>الحالة:</strong> محظور
                        <?php if (!empty($order['blacklist_reason'])): ?>
                        <div style="font-size: 12px;"><?php echo $order['blacklist_reason']; ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ((int) ($order['user_id'] ?? 0) > 0): ?>
                <a href="users.php?action=view&id=<?php echo (int) $order['user_id']; ?>" class="btn btn-outline btn-sm" style="margin-top: 10px;">
                    عرض الملف
                </a>
                <?php endif; ?>

                <?php if (userHasColumn('no_show_count') && $order['status'] !== 'completed'): ?>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="action" value="mark_no_show">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <textarea name="no_show_notes" class="form-control" rows="2" placeholder="تفاصيل عدم الالتزام (اختياري)"></textarea>
                    <button type="submit" class="btn btn-danger btn-sm" style="margin-top: 8px;">
                        تسجيل عدم التزام بالموعد
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- مقدمو الخدمة -->
        <?php if (!empty($orderAssignedProviders)): ?>
        <div class="card animate-slideUp" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-tie" style="color: var(--secondary-color);"></i>
                    مقدمو الخدمة
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($orderAssignedProviders as $assignedProvider): ?>
                    <div style="padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                            <div>
                                <strong><?php echo htmlspecialchars((string) ($assignedProvider['provider_name'] ?? ('مقدم #' . $assignedProvider['provider_id'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($assignedProvider['provider_phone'])): ?>
                                <div style="color: #64748b; font-size: 12px;" dir="ltr"><?php echo htmlspecialchars((string) $assignedProvider['provider_phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: left;">
                                <div style="font-size: 12px; color: #64748b;">
                                    <?php echo htmlspecialchars((string) ($assignedProvider['assignment_status'] ?? 'assigned'), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php if (isset($assignedProvider['provider_rating']) && $assignedProvider['provider_rating'] !== null): ?>
                                <div style="font-size: 12px;">⭐ <?php echo number_format((float) $assignedProvider['provider_rating'], 1); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if (!empty($assignedProvider['is_primary'])): ?>
                            <span class="badge badge-success">مقدم أساسي</span>
                            <?php endif; ?>
                            <a href="providers.php?action=view&id=<?php echo (int) $assignedProvider['provider_id']; ?>" class="btn btn-outline btn-sm">
                                عرض الملف
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- الموقع الحي لمقدم الخدمة -->
        <div class="card animate-slideUp" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-map-marker-alt" style="color: #2563eb;"></i>
                    التتبع الحي لمقدم الخدمة
                </h3>
            </div>
            <div class="card-body">
                <?php if (!$hasCustomerTrackingCoords): ?>
                    <p style="margin: 0; color: #b45309;">
                        لا يمكن تشغيل التتبع الحي لأن إحداثيات عنوان العميل غير متوفرة في الطلب.
                    </p>
                <?php else: ?>
                    <div id="admin-live-track-note" style="margin-bottom: 10px; color: #334155; font-size: 13px;">
                        <?php if ($isTrackingActiveStatus): ?>
                            التتبع يعمل لحظيًا طالما حالة الطلب "في الطريق"، ويتم تحديث الموقع تلقائيًا.
                        <?php else: ?>
                            التتبع الحي يتفعّل تلقائيًا عندما يغيّر مقدم الخدمة الحالة إلى "في الطريق".
                        <?php endif; ?>
                    </div>

                    <div id="admin-live-track-map" style="height: 320px; border-radius: 12px; overflow: hidden; border: 1px solid #dbeafe; background: #f8fafc;"></div>

                    <div id="admin-live-track-banner" style="margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #eff6ff; color: #1e3a8a; font-size: 13px;">
                        جاري جلب آخر موقع...
                    </div>

                    <div style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                        <div><strong>مقدم الخدمة:</strong> <span id="admin-live-provider-name"><?php echo htmlspecialchars((string) ($orderLiveLocation['provider_name'] ?? $order['provider_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <div><strong>الهاتف:</strong> <span id="admin-live-provider-phone" dir="ltr"><?php echo htmlspecialchars((string) ($orderLiveLocation['provider_phone'] ?? $order['provider_phone'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <div><strong>آخر موقع:</strong> <span id="admin-live-provider-coords" dir="ltr"><?php echo $hasLiveTrackingCoords ? (number_format((float) $liveTrackingLat, 6) . ', ' . number_format((float) $liveTrackingLng, 6)) : '-'; ?></span></div>
                        <div><strong>آخر تحديث:</strong> <span id="admin-live-updated-at"><?php echo htmlspecialchars((string) ($orderLiveLocation['captured_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                    </div>

                    <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                        <a
                            id="admin-live-open-provider-map"
                            href="<?php echo $hasLiveTrackingCoords ? ('https://www.google.com/maps?q=' . $liveTrackingLat . ',' . $liveTrackingLng) : '#'; ?>"
                            target="_blank"
                            class="btn btn-info btn-sm <?php echo $hasLiveTrackingCoords ? '' : 'disabled'; ?>">
                            <i class="fas fa-location-arrow"></i>
                            فتح موقع مقدم الخدمة
                        </a>
                        <a href="<?php echo 'https://www.google.com/maps?q=' . $customerTrackingLat . ',' . $customerTrackingLng; ?>" target="_blank" class="btn btn-outline btn-sm">
                            <i class="fas fa-home"></i>
                            فتح موقع العميل
                        </a>
                        <button type="button" id="admin-live-force-refresh" class="btn btn-outline btn-sm">
                            <i class="fas fa-sync"></i>
                            تحديث الآن
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- تفاصيل الدفع -->
        <div class="card animate-slideUp">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-receipt" style="color: var(--success-color);"></i>
                    تفاصيل الدفع
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>رسوم المعاينة</span>
                    <?php if ((float)($order['inspection_fee'] ?? 0) <= 0): ?>
                    <strong style="color: #16a34a;">مجانية</strong>
                    <?php else: ?>
                    <strong><?php echo number_format($order['inspection_fee'], 2); ?> ⃁</strong>
                    <?php endif; ?>
                </div>
                <?php if ($order['service_fee'] > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>رسوم الخدمة</span>
                    <strong><?php echo number_format($order['service_fee'], 2); ?> ⃁</strong>
                </div>
                <?php endif; ?>
                <?php if ($order['parts_fee'] > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span>قطع الغيار</span>
                    <strong><?php echo number_format($order['parts_fee'], 2); ?> ⃁</strong>
                </div>
                <?php endif; ?>
                <?php if ($order['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: var(--success-color);">
                    <span>الخصم</span>
                    <strong>-<?php echo number_format($order['discount_amount'], 2); ?> ⃁</strong>
                </div>
                <?php endif; ?>
                <hr style="margin: 15px 0;">
                <div style="display: flex; justify-content: space-between; font-size: 18px;">
                    <strong>الإجمالي</strong>
                    <strong style="color: var(--primary-color);"><?php echo number_format($order['total_amount'], 2); ?> ⃁</strong>
                </div>
                
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--gray-200);">
                    <?php
                        $paymentMethod = $order['payment_method'] ?? 'cash';
                        $paymentStatus = $order['payment_status'] ?? 'pending';
                    ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>طريقة الدفع</span>
                        <span><?php echo $paymentMethod === 'cash' ? 'نقداً' : ($paymentMethod === 'wallet' ? 'المحفظة' : 'بطاقة'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>حالة الدفع</span>
                        <span class="badge <?php echo $paymentStatus === 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo getPaymentStatusAr($paymentStatus); ?>
                        </span>
                    </div>
                </div>

                <?php
                    $myFatoorahConfig = getAdminMyFatoorahConfig();
                    $myFatoorahReady = $myFatoorahConfig['enabled'] && $myFatoorahConfig['token'] !== '';
                    $gatewayInvoiceId = orderHasColumn('myfatoorah_invoice_id') ? trim((string) ($order['myfatoorah_invoice_id'] ?? '')) : '';
                    $gatewayPaymentUrl = orderHasColumn('myfatoorah_payment_url') ? trim((string) ($order['myfatoorah_payment_url'] ?? '')) : '';
                    $gatewayPaymentId = orderHasColumn('myfatoorah_payment_id') ? trim((string) ($order['myfatoorah_payment_id'] ?? '')) : '';
                    $gatewayInvoiceStatus = orderHasColumn('myfatoorah_invoice_status') ? trim((string) ($order['myfatoorah_invoice_status'] ?? '')) : '';
                    $gatewayLastStatusAt = orderHasColumn('myfatoorah_last_status_at') ? trim((string) ($order['myfatoorah_last_status_at'] ?? '')) : '';
                    $gatewayStatusClass = 'badge-warning';
                    if (strtolower($gatewayInvoiceStatus) === 'paid') {
                        $gatewayStatusClass = 'badge-success';
                    } elseif (in_array(strtolower($gatewayInvoiceStatus), ['failed', 'expired', 'canceled', 'cancelled'], true)) {
                        $gatewayStatusClass = 'badge-danger';
                    }
                ?>

                <div style="margin-top: 15px; padding: 14px; border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 10px;">
                        <strong><i class="fas fa-credit-card"></i> MyFatoorah</strong>
                        <span class="badge <?php echo $myFatoorahReady ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $myFatoorahReady ? 'مفعلة' : 'غير مفعلة'; ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>Invoice ID</span>
                        <span dir="ltr"><?php echo $gatewayInvoiceId !== '' ? htmlspecialchars($gatewayInvoiceId, ENT_QUOTES, 'UTF-8') : '-'; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>Payment ID</span>
                        <span dir="ltr"><?php echo $gatewayPaymentId !== '' ? htmlspecialchars($gatewayPaymentId, ENT_QUOTES, 'UTF-8') : '-'; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>حالة فاتورة MyFatoorah</span>
                        <span class="badge <?php echo $gatewayStatusClass; ?>">
                            <?php echo getMyFatoorahInvoiceStatusAr($gatewayInvoiceStatus); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <span>آخر تحديث</span>
                        <span><?php echo $gatewayLastStatusAt !== '' ? htmlspecialchars($gatewayLastStatusAt, ENT_QUOTES, 'UTF-8') : '-'; ?></span>
                    </div>

                    <?php if ($gatewayPaymentUrl !== ''): ?>
                    <a href="<?php echo htmlspecialchars($gatewayPaymentUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-bottom: 10px;">
                        <i class="fas fa-external-link-alt"></i>
                        فتح رابط الدفع
                    </a>
                    <?php endif; ?>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <form method="POST">
                            <input type="hidden" name="action" value="sync_myfatoorah_status">
                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                            <input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($gatewayInvoiceId, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($gatewayPaymentId, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-info btn-sm" <?php echo (!$myFatoorahReady || ($gatewayInvoiceId === '' && $gatewayPaymentId === '')) ? 'disabled' : ''; ?>>
                                <i class="fas fa-sync"></i>
                                تحديث حالة الدفع
                            </button>
                        </form>

                        <?php if ($paymentStatus !== 'paid'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_myfatoorah_link">
                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                            <button type="submit" class="btn btn-primary btn-sm" <?php echo !$myFatoorahReady ? 'disabled' : ''; ?>>
                                <i class="fas fa-link"></i>
                                إنشاء رابط دفع
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php if (!$myFatoorahReady): ?>
                    <small class="text-muted" style="display: block; margin-top: 8px;">فعّل MyFatoorah من صفحة إعدادات التطبيق حتى تعمل أزرار الربط.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- التقييم -->
        <?php if ($review): ?>
        <div class="card animate-slideUp" style="margin-top: 25px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-star" style="color: #fbbf24;"></i>
                    التقييم<?php echo ($review['review_type'] ?? '') === 'container_store' ? ' - متجر الحاويات' : ''; ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($review['container_store_name'])): ?>
                    <p style="margin: 0 0 10px; color: #4b5563;">
                        المتجر: <?php echo htmlspecialchars((string) $review['container_store_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>
                <div style="display: flex; gap: 3px; margin-bottom: 10px;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#fbbf24' : '#e5e7eb'; ?>; font-size: 20px;"></i>
                    <?php endfor; ?>
                </div>
                <?php if ($review['comment']): ?>
                <p style="color: #374151; margin: 0;"><?php echo $review['comment']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php if ($action === 'view' && isset($order) && !empty($hasCustomerTrackingCoords)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
    #admin-live-track-map .leaflet-popup-content-wrapper {
        border-radius: 10px;
    }
    .admin-live-provider-icon,
    .admin-live-customer-icon {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 3px solid #ffffff;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.15);
    }
    .admin-live-provider-icon {
        background: #2563eb;
    }
    .admin-live-customer-icon {
        background: #16a34a;
    }
    .admin-live-route-line {
        animation: admin-live-route-dash 1.2s linear infinite;
    }
    @keyframes admin-live-route-dash {
        from { stroke-dashoffset: 20; }
        to { stroke-dashoffset: 0; }
    }
    .btn.disabled {
        opacity: 0.6;
        pointer-events: none;
    }
</style>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (function() {
        const mapEl = document.getElementById('admin-live-track-map');
        if (!mapEl || typeof L === 'undefined') {
            return;
        }

        const feedUrl = <?php echo json_encode($liveTrackingFeedUrl, JSON_UNESCAPED_UNICODE); ?>;
        const customerLat = <?php echo json_encode($customerTrackingLat); ?>;
        const customerLng = <?php echo json_encode($customerTrackingLng); ?>;
        const initialLiveLat = <?php echo json_encode($liveTrackingLat); ?>;
        const initialLiveLng = <?php echo json_encode($liveTrackingLng); ?>;
        const initialTrackingEnabled = <?php echo json_encode($isTrackingActiveStatus); ?>;

        const bannerEl = document.getElementById('admin-live-track-banner');
        const providerNameEl = document.getElementById('admin-live-provider-name');
        const providerPhoneEl = document.getElementById('admin-live-provider-phone');
        const coordsEl = document.getElementById('admin-live-provider-coords');
        const updatedAtEl = document.getElementById('admin-live-updated-at');
        const providerMapLinkEl = document.getElementById('admin-live-open-provider-map');
        const forceRefreshBtn = document.getElementById('admin-live-force-refresh');

        const providerIcon = L.divIcon({
            className: '',
            html: '<div class="admin-live-provider-icon"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });
        const customerIcon = L.divIcon({
            className: '',
            html: '<div class="admin-live-customer-icon"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });

        const map = L.map('admin-live-track-map', {
            center: [customerLat, customerLng],
            zoom: 13,
            zoomControl: true,
        });
        L.tileLayer('../ajax/map_tiles.php?provider=auto&z={z}&x={x}&y={y}', {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO &copy; Esri',
            maxZoom: 19,
        }).addTo(map);

        const customerMarker = L.marker([customerLat, customerLng], { icon: customerIcon })
            .addTo(map)
            .bindPopup('موقع العميل');

        let providerMarker = null;
        let routeLine = null;
        let lastRouteFetchAt = 0;
        let lastProviderLat = null;
        let lastProviderLng = null;
        let didFitBounds = false;
        let pollingTimer = null;
        let isFetchingFeed = false;

        function toFloat(value) {
            const n = Number(value);
            return Number.isFinite(n) ? n : null;
        }

        function isValidLatLng(lat, lng) {
            return lat !== null && lng !== null &&
                lat >= -90 && lat <= 90 &&
                lng >= -180 && lng <= 180;
        }

        function setBanner(message, tone) {
            if (!bannerEl) return;
            bannerEl.textContent = message;
            if (tone === 'warn') {
                bannerEl.style.background = '#fff7ed';
                bannerEl.style.color = '#9a3412';
                return;
            }
            if (tone === 'error') {
                bannerEl.style.background = '#fef2f2';
                bannerEl.style.color = '#991b1b';
                return;
            }
            bannerEl.style.background = '#eff6ff';
            bannerEl.style.color = '#1e3a8a';
        }

        function setProviderMapLink(lat, lng) {
            if (!providerMapLinkEl) return;
            if (!isValidLatLng(lat, lng)) {
                providerMapLinkEl.classList.add('disabled');
                providerMapLinkEl.setAttribute('href', '#');
                return;
            }
            providerMapLinkEl.classList.remove('disabled');
            providerMapLinkEl.setAttribute('href', 'https://www.google.com/maps?q=' + lat + ',' + lng);
        }

        function updateInfo(info) {
            const live = info && info.live_location ? info.live_location : null;
            if (providerNameEl) {
                const providerName = (live && live.provider_name) || info.provider_name || '-';
                providerNameEl.textContent = providerName;
            }
            if (providerPhoneEl) {
                providerPhoneEl.textContent = (live && live.provider_phone) ? live.provider_phone : '-';
            }
            if (coordsEl) {
                if (live && isValidLatLng(toFloat(live.lat), toFloat(live.lng))) {
                    const lat = toFloat(live.lat);
                    const lng = toFloat(live.lng);
                    coordsEl.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
                } else {
                    coordsEl.textContent = '-';
                }
            }
            if (updatedAtEl) {
                updatedAtEl.textContent = (live && live.captured_at) ? live.captured_at : '-';
            }
        }

        function ensureProviderMarker(lat, lng) {
            if (!providerMarker) {
                providerMarker = L.marker([lat, lng], { icon: providerIcon })
                    .addTo(map)
                    .bindPopup('موقع مقدم الخدمة');
                return;
            }
            providerMarker.setLatLng([lat, lng]);
        }

        function fallbackDirectRoute(providerLat, providerLng) {
            const points = [
                [providerLat, providerLng],
                [customerLat, customerLng]
            ];
            if (!routeLine) {
                routeLine = L.polyline(points, {
                    color: '#2563eb',
                    weight: 5,
                    opacity: 0.9,
                    dashArray: '10 10',
                    className: 'admin-live-route-line',
                }).addTo(map);
                return;
            }
            routeLine.setLatLngs(points);
        }

        async function updateRoute(providerLat, providerLng) {
            const now = Date.now();
            const movedEnough = (
                lastProviderLat === null ||
                lastProviderLng === null ||
                Math.abs(providerLat - lastProviderLat) > 0.00015 ||
                Math.abs(providerLng - lastProviderLng) > 0.00015
            );

            if (!movedEnough && now - lastRouteFetchAt < 15000) {
                return;
            }

            lastProviderLat = providerLat;
            lastProviderLng = providerLng;
            lastRouteFetchAt = now;

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 3500);
            try {
                const url = 'https://router.project-osrm.org/route/v1/driving/' +
                    providerLng + ',' + providerLat + ';' + customerLng + ',' + customerLat +
                    '?overview=full&geometries=geojson';
                const response = await fetch(url, {
                    method: 'GET',
                    signal: controller.signal,
                    cache: 'no-store',
                });
                if (!response.ok) {
                    throw new Error('route_http_' + response.status);
                }
                const data = await response.json();
                const route = data && data.routes && data.routes[0] ? data.routes[0] : null;
                if (!route || !route.geometry || !Array.isArray(route.geometry.coordinates)) {
                    throw new Error('route_invalid');
                }

                const latLngs = route.geometry.coordinates
                    .map((pair) => {
                        if (!Array.isArray(pair) || pair.length < 2) return null;
                        const lng = toFloat(pair[0]);
                        const lat = toFloat(pair[1]);
                        return isValidLatLng(lat, lng) ? [lat, lng] : null;
                    })
                    .filter(Boolean);

                if (latLngs.length < 2) {
                    throw new Error('route_short');
                }

                if (!routeLine) {
                    routeLine = L.polyline(latLngs, {
                        color: '#2563eb',
                        weight: 5,
                        opacity: 0.9,
                        dashArray: '10 10',
                        className: 'admin-live-route-line',
                    }).addTo(map);
                } else {
                    routeLine.setLatLngs(latLngs);
                }
            } catch (_) {
                fallbackDirectRoute(providerLat, providerLng);
            } finally {
                clearTimeout(timeout);
            }
        }

        function fitBoundsIfNeeded(providerLat, providerLng) {
            if (didFitBounds) return;
            const bounds = L.latLngBounds([
                [providerLat, providerLng],
                [customerLat, customerLng],
            ]);
            map.fitBounds(bounds.pad(0.25));
            didFitBounds = true;
        }

        function applyFeed(payload) {
            if (!payload) return;
            updateInfo(payload);

            const trackingEnabled = !!payload.tracking_enabled;
            const live = payload.live_location || null;

            if (!trackingEnabled) {
                setBanner('التتبع الحي غير نشط حاليًا. سيتفعّل تلقائيًا عندما تصبح الحالة "في الطريق".', 'warn');
            } else if (!live) {
                setBanner('في انتظار أول تحديث موقع من مقدم الخدمة...', 'info');
            }

            const liveLat = live ? toFloat(live.lat) : null;
            const liveLng = live ? toFloat(live.lng) : null;
            if (!isValidLatLng(liveLat, liveLng)) {
                setProviderMapLink(null, null);
                return;
            }

            ensureProviderMarker(liveLat, liveLng);
            setProviderMapLink(liveLat, liveLng);
            updateRoute(liveLat, liveLng);
            fitBoundsIfNeeded(liveLat, liveLng);

            const seconds = live && live.seconds_since_update != null ? Number(live.seconds_since_update) : null;
            if (trackingEnabled) {
                if (seconds !== null && Number.isFinite(seconds)) {
                    setBanner('آخر تحديث منذ ' + Math.max(0, Math.floor(seconds)) + ' ثانية.', 'info');
                } else {
                    setBanner('جاري تتبع مقدم الخدمة بشكل لحظي.', 'info');
                }
            }
        }

        async function pullFeed() {
            if (isFetchingFeed) {
                return;
            }
            isFetchingFeed = true;
            try {
                const response = await fetch(feedUrl + '&_=' + Date.now(), {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('feed_http_' + response.status);
                }
                const payload = await response.json();
                if (!payload || payload.success !== true) {
                    throw new Error('feed_invalid');
                }
                applyFeed(payload.data || null);
            } catch (_) {
                setBanner('تعذر جلب التحديث الحي الآن. سيتم إعادة المحاولة تلقائيًا.', 'error');
            } finally {
                isFetchingFeed = false;
            }
        }

        if (isValidLatLng(initialLiveLat, initialLiveLng)) {
            applyFeed({
                tracking_enabled: !!initialTrackingEnabled,
                provider_name: providerNameEl ? providerNameEl.textContent : '',
                live_location: {
                    lat: initialLiveLat,
                    lng: initialLiveLng,
                    provider_name: providerNameEl ? providerNameEl.textContent : '',
                    provider_phone: providerPhoneEl ? providerPhoneEl.textContent : '',
                    captured_at: updatedAtEl ? updatedAtEl.textContent : '',
                },
            });
        } else {
            map.setView([customerLat, customerLng], 14);
            customerMarker.openPopup();
            setProviderMapLink(null, null);
        }

        pullFeed();
        pollingTimer = setInterval(pullFeed, 8000);

        if (forceRefreshBtn) {
            forceRefreshBtn.addEventListener('click', function() {
                pullFeed();
            });
        }

        window.addEventListener('beforeunload', function() {
            if (pollingTimer) {
                clearInterval(pollingTimer);
            }
        });
    })();
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
