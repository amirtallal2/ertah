<?php
/**
 * صفحة إدارة الإشعارات
 * Notifications Management Page
 */

require_once '../init.php';
requireLogin();

$admin = getCurrentAdmin();
if (!hasPermission('notifications') && ($admin['role'] ?? '') !== 'super_admin') {
    die('صلاحيات غير كافية للوصول إلى صفحة الإشعارات.');
}

$pageTitle = 'إدارة الإشعارات';
$pageSubtitle = 'التحكم في إعدادات الإشعارات وإرسالها للمستخدمين';

function notificationsTableExistsByName(string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $row = db()->fetch("SHOW TABLES LIKE ?", [$table]);
        $cache[$table] = !empty($row);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function notificationsColumnExists(string $table, string $column, bool $forceRefresh = false): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!$forceRefresh && isset($cache[$key])) {
        return $cache[$key];
    }

    if (!notificationsTableExistsByName($table)) {
        $cache[$key] = false;
        return false;
    }

    try {
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $row = db()->fetch("SHOW COLUMNS FROM `{$table}` LIKE '{$safeColumn}'");
        $cache[$key] = !empty($row);
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function notificationsIndexExists(string $table, string $indexName, bool $forceRefresh = false): bool
{
    static $cache = [];
    $key = $table . '.' . $indexName;
    if (!$forceRefresh && isset($cache[$key])) {
        return $cache[$key];
    }

    if (!notificationsTableExistsByName($table)) {
        $cache[$key] = false;
        return false;
    }

    try {
        $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
        $row = db()->fetch("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$safeIndex}'");
        $cache[$key] = !empty($row);
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function notificationsEnsureColumn(string $table, string $column, string $definition): void
{
    if (!notificationsTableExistsByName($table)) {
        return;
    }

    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeColumn === '') {
        return;
    }

    try {
        if (!notificationsColumnExists($table, $safeColumn, true)) {
            db()->query("ALTER TABLE `{$table}` ADD COLUMN `{$safeColumn}` {$definition}");
        } else {
            // Best-effort: relax column to a safe definition (allow NULL + defaults)
            db()->query("ALTER TABLE `{$table}` MODIFY COLUMN `{$safeColumn}` {$definition}");
        }
    } catch (Throwable $e) {
        // Ignore schema hardening issues to avoid blocking the page.
    }
}

function notificationsEnsureDeliverySchema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!notificationsTableExistsByName('notifications')) {
        try {
            db()->query("CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NULL,
                `provider_id` INT NULL,
                `title` VARCHAR(200) NOT NULL,
                `body` TEXT NOT NULL,
                `type` VARCHAR(40) DEFAULT 'system',
                `data` LONGTEXT NULL,
                `is_read` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_notifications_user` (`user_id`),
                INDEX `idx_notifications_provider` (`provider_id`),
                INDEX `idx_notifications_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            return;
        }
    }

    try {
        notificationsEnsureColumn('notifications', 'user_id', 'INT NULL');
        notificationsEnsureColumn('notifications', 'provider_id', 'INT NULL');
        notificationsEnsureColumn('notifications', 'title', 'VARCHAR(200) NULL');
        notificationsEnsureColumn('notifications', 'body', 'TEXT NULL');
        notificationsEnsureColumn('notifications', 'type', "VARCHAR(40) NULL DEFAULT 'system'");
        notificationsEnsureColumn('notifications', 'data', 'LONGTEXT NULL');
        notificationsEnsureColumn('notifications', 'is_read', 'TINYINT(1) NULL DEFAULT 0');
        notificationsEnsureColumn('notifications', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');

        if (!notificationsColumnExists('notifications', 'provider_id', true)) {
            db()->query("ALTER TABLE `notifications` ADD COLUMN `provider_id` INT NULL");
        }
        if (!notificationsColumnExists('notifications', 'data', true)) {
            db()->query("ALTER TABLE `notifications` ADD COLUMN `data` LONGTEXT NULL");
        }
        if (
            notificationsColumnExists('notifications', 'provider_id', true)
            && !notificationsIndexExists('notifications', 'idx_notifications_provider', true)
        ) {
            db()->query("ALTER TABLE `notifications` ADD INDEX `idx_notifications_provider` (`provider_id`)");
        }
    } catch (Throwable $e) {
        // Ignore schema hardening issues to avoid blocking the page.
    }
}

function notificationsGetSettingFromDb(string $key): string
{
    if (!notificationsTableExistsByName('app_settings')) {
        return '';
    }

    $row = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1", [$key]);
    return trim((string) ($row['setting_value'] ?? ''));
}

function notificationsGetSettingWithSource(string $key, string $default = ''): array
{
    $dbValue = notificationsGetSettingFromDb($key);
    if ($dbValue !== '') {
        return [$dbValue, 'قاعدة البيانات (app_settings)'];
    }

    if ($key === 'onesignal_app_id') {
        $envValue = trim((string) (getenv('ONESIGNAL_APP_ID') ?: getenv('ONE_SIGNAL_APP_ID') ?: ''));
        if ($envValue !== '') {
            return [$envValue, 'متغيرات البيئة (ENV)'];
        }

        $configValue = trim((string) (defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : ''));
        if ($configValue !== '') {
            return [$configValue, 'ملف الإعدادات (config.php)'];
        }
    }

    if ($key === 'onesignal_rest_api_key') {
        $envValue = trim((string) (getenv('ONESIGNAL_REST_API_KEY') ?: getenv('ONE_SIGNAL_REST_API_KEY') ?: ''));
        if ($envValue !== '') {
            return [$envValue, 'متغيرات البيئة (ENV)'];
        }

        $configValue = trim((string) (defined('ONESIGNAL_REST_API_KEY') ? ONESIGNAL_REST_API_KEY : ''));
        if ($configValue !== '') {
            return [$configValue, 'ملف الإعدادات (config.php)'];
        }
    }

    if ($key === 'notifications_logo_url') {
        return [rtrim(APP_URL, '/') . '/assets/images/default.png', 'افتراضي'];
    }

    if ($key === 'notifications_enabled') {
        return ['1', 'افتراضي'];
    }

    if ($key === 'notifications_small_icon') {
        return ['ic_stat_onesignal_default', 'افتراضي'];
    }

    return [$default, 'افتراضي'];
}

function notificationsGetSettingValue(string $key, string $default = ''): string
{
    [$value, $source] = notificationsGetSettingWithSource($key, $default);
    unset($source);
    return trim((string) $value);
}

function notificationsUpsertSetting(string $key, string $value, string $description = ''): bool
{
    if (!notificationsTableExistsByName('app_settings')) {
        return false;
    }

    $exists = db()->fetch("SELECT setting_key FROM app_settings WHERE setting_key = ? LIMIT 1", [$key]);
    if ($exists) {
        db()->query(
            "UPDATE app_settings SET setting_value = ? WHERE setting_key = ?",
            [$value, $key]
        );
        return true;
    }

    db()->insert('app_settings', [
        'setting_key' => $key,
        'setting_value' => $value,
        'description' => $description,
    ]);
    return true;
}

function notificationsMaskKey(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return 'غير مُعد';
    }

    $length = strlen($trimmed);
    if ($length <= 8) {
        return str_repeat('*', $length);
    }

    return substr($trimmed, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($trimmed, -4);
}

function notificationsNormalizeEnabled(string $raw): string
{
    $normalized = strtolower(trim($raw));
    return in_array($normalized, ['0', 'false', 'no', 'off'], true) ? '0' : '1';
}

function notificationsNormalizeMediaUrl(string $raw, string $default = ''): string
{
    $value = trim($raw);
    if ($value === '') {
        return $default;
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return $value;
    }

    $value = ltrim($value, '/');
    if (strpos($value, 'admin-panel/') === 0) {
        $rootUrl = preg_replace('#/admin-panel$#', '', rtrim(APP_URL, '/'));
        return rtrim((string) $rootUrl, '/') . '/' . $value;
    }

    if (strpos($value, 'uploads/') === 0 || strpos($value, 'assets/') === 0) {
        return rtrim(APP_URL, '/') . '/' . $value;
    }

    return rtrim(APP_URL, '/') . '/uploads/' . $value;
}

function notificationsNormalizeLogoUrl(string $raw): string
{
    $fallbackLogo = rtrim(APP_URL, '/') . '/assets/images/default.png';
    return notificationsNormalizeMediaUrl($raw, $fallbackLogo);
}

function notificationsNormalizeSmallIconName(string $raw): string
{
    $value = strtolower(trim($raw));
    if ($value === '') {
        return 'ic_stat_onesignal_default';
    }

    if (strpos($value, '@') === 0) {
        $value = substr($value, 1);
    }

    if (strpos($value, 'drawable/') === 0) {
        $value = substr($value, strlen('drawable/'));
    } elseif (strpos($value, 'mipmap/') === 0) {
        $value = substr($value, strlen('mipmap/'));
    }

    $value = preg_replace('/[^a-z0-9_]/', '', $value);
    if ($value === '') {
        return 'ic_stat_onesignal_default';
    }

    return $value;
}

function notificationsIsPrivateHost(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return (bool) filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    return false;
}

function notificationsValidateRichMediaUrl(string $url): string
{
    $value = trim($url);
    if ($value === '') {
        return '';
    }

    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return 'رابط الصورة غير صالح.';
    }

    $parts = parse_url($value);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($scheme !== 'https') {
        return 'رابط الصورة يجب أن يبدأ بـ https://';
    }

    if ($host === '' || notificationsIsPrivateHost($host)) {
        return 'رابط الصورة يجب أن يكون عامًا (ليس localhost أو IP داخلي).';
    }

    return '';
}

function notificationsApplyMediaToPayload(
    array &$payload,
    string $logoUrl,
    string $imageUrl,
    string $smallIcon
): void {
    if ($smallIcon !== '') {
        $payload['small_icon'] = $smallIcon;
    }

    if ($logoUrl !== '') {
        $payload['large_icon'] = $logoUrl;
    }

    if ($imageUrl !== '') {
        $payload['android_notification_layout'] = 'big_picture';
        $payload['big_picture'] = $imageUrl;
        $payload['huawei_big_picture'] = $imageUrl;
        $payload['chrome_web_image'] = $imageUrl;
        $payload['mutable_content'] = true;
        $payload['content_available'] = true;
        $payload['ios_attachments'] = [
            'image' => $imageUrl,
        ];
    } elseif ($logoUrl !== '') {
        $payload['big_picture'] = $logoUrl;
    }
}

function notificationsBuildInsertPayload(?int $userId, ?int $providerId, string $title, string $body, array $data = []): array
{
    if (!notificationsTableExistsByName('notifications')) {
        return [];
    }

    $payload = [];
    if ($userId !== null && $userId > 0 && notificationsColumnExists('notifications', 'user_id')) {
        $payload['user_id'] = $userId;
    }
    if ($providerId !== null && $providerId > 0 && notificationsColumnExists('notifications', 'provider_id')) {
        $payload['provider_id'] = $providerId;
    }

    if (notificationsColumnExists('notifications', 'title')) {
        $payload['title'] = $title;
    }

    if (notificationsColumnExists('notifications', 'body')) {
        $payload['body'] = $body;
    }
    if (notificationsColumnExists('notifications', 'message')) {
        $payload['message'] = $body;
    }

    if (notificationsColumnExists('notifications', 'type')) {
        $payload['type'] = 'system';
    }
    if (notificationsColumnExists('notifications', 'is_read')) {
        $payload['is_read'] = 0;
    }
    if (notificationsColumnExists('notifications', 'created_at')) {
        $payload['created_at'] = date('Y-m-d H:i:s');
    }

    if (notificationsColumnExists('notifications', 'data') && !empty($data)) {
        $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($payloadJson !== false) {
            $payload['data'] = $payloadJson;
        }
    }

    return $payload;
}

function notificationsInsertForUser(int $userId, string $title, string $body, array $data = []): bool
{
    if ($userId <= 0) {
        return false;
    }

    $payload = notificationsBuildInsertPayload($userId, null, $title, $body, $data);
    if (empty($payload)) {
        return false;
    }

    db()->insert('notifications', $payload);
    return true;
}

function notificationsInsertForAllUsers(string $title, string $body, array $data = []): int
{
    if (!notificationsTableExistsByName('notifications') || !notificationsTableExistsByName('users')) {
        return 0;
    }

    $usersWhere = notificationsColumnExists('users', 'is_active') ? " WHERE is_active = 1" : '';
    $targetUsers = db()->fetchAll("SELECT id FROM users{$usersWhere} ORDER BY id ASC");
    if (empty($targetUsers)) {
        return 0;
    }

    $inserted = 0;
    foreach ($targetUsers as $targetUser) {
        $targetUserId = (int) ($targetUser['id'] ?? 0);
        if ($targetUserId <= 0) {
            continue;
        }
        if (notificationsInsertForUser($targetUserId, $title, $body, $data)) {
            $inserted++;
        }
    }

    return $inserted;
}

function notificationsInsertForProvider(int $providerId, string $title, string $body, array $data = []): bool
{
    if ($providerId <= 0 || !notificationsColumnExists('notifications', 'provider_id')) {
        return false;
    }

    $payload = notificationsBuildInsertPayload(null, $providerId, $title, $body, $data);
    if (empty($payload)) {
        return false;
    }

    db()->insert('notifications', $payload);
    return true;
}

function notificationsInsertForAllProviders(string $title, string $body, array $data = []): int
{
    if (
        !notificationsTableExistsByName('notifications')
        || !notificationsTableExistsByName('providers')
        || !notificationsColumnExists('notifications', 'provider_id')
    ) {
        return 0;
    }

    $providersWhere = notificationsColumnExists('providers', 'status')
        ? " WHERE status = 'approved'"
        : '';
    $targetProviders = db()->fetchAll("SELECT id FROM providers{$providersWhere} ORDER BY id ASC");
    if (empty($targetProviders)) {
        return 0;
    }

    $inserted = 0;
    foreach ($targetProviders as $targetProvider) {
        $targetProviderId = (int) ($targetProvider['id'] ?? 0);
        if ($targetProviderId <= 0) {
            continue;
        }
        if (notificationsInsertForProvider($targetProviderId, $title, $body, $data)) {
            $inserted++;
        }
    }

    return $inserted;
}

function notificationsInsertGeneral(string $title, string $body, array $data = []): bool
{
    $payload = notificationsBuildInsertPayload(null, null, $title, $body, $data);
    if (empty($payload)) {
        return false;
    }

    db()->insert('notifications', $payload);
    return true;
}

function notificationsSendPushToExternalIds(
    array $externalIds,
    string $title,
    string $body,
    array $data = [],
    string $imageUrl = ''
): array
{
    $result = [
        'attempted' => 0,
        'successful' => 0,
        'failed' => 0,
        'skipped' => false,
        'skip_reason' => '',
        'errors' => [],
    ];

    $externalIds = array_values(array_filter(array_map(static function ($id) {
        return trim((string) $id);
    }, $externalIds), static function ($id) {
        return $id !== '';
    }));

    if (empty($externalIds)) {
        $result['skipped'] = true;
        $result['skip_reason'] = 'لا يوجد مستلمون صالحون للإرسال.';
        return $result;
    }

    $enabled = notificationsGetSettingValue('notifications_enabled', '1') === '1';
    if (!$enabled) {
        $result['skipped'] = true;
        $result['skip_reason'] = 'الإشعارات المعتمدة عبر OneSignal معطلة من الإعدادات.';
        return $result;
    }

    $appId = notificationsGetSettingValue('onesignal_app_id', '');
    $restApiKey = notificationsGetSettingValue('onesignal_rest_api_key', '');
    $logoUrl = notificationsNormalizeLogoUrl(notificationsGetSettingValue('notifications_logo_url', ''));
    $smallIcon = notificationsNormalizeSmallIconName(
        notificationsGetSettingValue('notifications_small_icon', 'ic_stat_onesignal_default')
    );
    $resolvedImageUrl = notificationsNormalizeMediaUrl($imageUrl, '');

    if ($appId === '' || $restApiKey === '') {
        $result['skipped'] = true;
        $result['skip_reason'] = 'OneSignal App ID أو REST API Key غير مضبوط.';
        return $result;
    }

    if (!function_exists('curl_init')) {
        $result['skipped'] = true;
        $result['skip_reason'] = 'دالة cURL غير متاحة على السيرفر.';
        return $result;
    }

    foreach (array_chunk($externalIds, 200) as $chunk) {
        $result['attempted'] += count($chunk);

        $payload = [
            'app_id' => $appId,
            'target_channel' => 'push',
            'include_aliases' => [
                'external_id' => array_values($chunk),
            ],
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

        notificationsApplyMediaToPayload($payload, $logoUrl, $resolvedImageUrl, $smallIcon);

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            $result['failed'] += count($chunk);
            $result['errors'][] = 'فشل ترميز JSON لدفعة من المستلمين.';
            continue;
        }

        $ch = curl_init('https://api.onesignal.com/notifications');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Key ' . $restApiKey,
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            $result['failed'] += count($chunk);
            $errorDetails = $curlError !== '' ? $curlError : trim((string) $response);
            if ($errorDetails === '') {
                $errorDetails = 'HTTP ' . $statusCode;
            }
            $result['errors'][] = 'دفعة فشلت (' . count($chunk) . '): ' . $errorDetails;
            continue;
        }

        $result['successful'] += count($chunk);
    }

    return $result;
}

function notificationsSendPushToAllInstallations(
    string $title,
    string $body,
    array $data = [],
    string $imageUrl = ''
): array
{
    $result = [
        'attempted' => 1,
        'successful' => 0,
        'failed' => 0,
        'skipped' => false,
        'skip_reason' => '',
        'errors' => [],
    ];

    $enabled = notificationsGetSettingValue('notifications_enabled', '1') === '1';
    if (!$enabled) {
        $result['skipped'] = true;
        $result['skip_reason'] = 'الإشعارات المعتمدة عبر OneSignal معطلة من الإعدادات.';
        return $result;
    }

    $appId = notificationsGetSettingValue('onesignal_app_id', '');
    $restApiKey = notificationsGetSettingValue('onesignal_rest_api_key', '');
    $logoUrl = notificationsNormalizeLogoUrl(notificationsGetSettingValue('notifications_logo_url', ''));
    $smallIcon = notificationsNormalizeSmallIconName(
        notificationsGetSettingValue('notifications_small_icon', 'ic_stat_onesignal_default')
    );
    $resolvedImageUrl = notificationsNormalizeMediaUrl($imageUrl, '');

    if ($appId === '' || $restApiKey === '') {
        $result['skipped'] = true;
        $result['skip_reason'] = 'OneSignal App ID أو REST API Key غير مضبوط.';
        return $result;
    }

    if (!function_exists('curl_init')) {
        $result['skipped'] = true;
        $result['skip_reason'] = 'دالة cURL غير متاحة على السيرفر.';
        return $result;
    }

    $payload = [
        'app_id' => $appId,
        'target_channel' => 'push',
        'included_segments' => ['All'],
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

    notificationsApplyMediaToPayload($payload, $logoUrl, $resolvedImageUrl, $smallIcon);

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($jsonPayload === false) {
        $result['failed'] = 1;
        $result['errors'][] = 'فشل ترميز JSON للإرسال العام.';
        return $result;
    }

    $ch = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Key ' . $restApiKey,
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $statusCode < 200 || $statusCode >= 300) {
        $result['failed'] = 1;
        $errorDetails = $curlError !== '' ? $curlError : trim((string) $response);
        if ($errorDetails === '') {
            $errorDetails = 'HTTP ' . $statusCode;
        }
        $result['errors'][] = 'فشل الإرسال العام: ' . $errorDetails;
        return $result;
    }

    $result['successful'] = 1;
    return $result;
}

$action = '';
$sendTitle = '';
$sendBody = '';
$sendImageUrl = '';
$sendTargetGroup = 'all_users';
$sendUserId = 0;
$sendProviderId = 0;
$sendDestinationType = 'none';
$sendDestinationId = 0;

notificationsEnsureDeliverySchema();
if (!notificationsTableExistsByName('notifications')) {
    setFlashMessage('danger', 'تعذر إنشاء جدول الإشعارات. تأكد من صلاحيات قاعدة البيانات (CREATE/ALTER).');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) post('action'));

    if ($action === 'save_settings') {
        if (!notificationsTableExistsByName('app_settings')) {
            setFlashMessage('danger', 'جدول app_settings غير موجود، لا يمكن حفظ إعدادات الإشعارات.');
            redirect('notifications.php');
        }

        $settingsInput = $_POST['settings'] ?? [];
        $enabled = notificationsNormalizeEnabled((string) ($settingsInput['notifications_enabled'] ?? '1'));
        $appId = trim((string) ($settingsInput['onesignal_app_id'] ?? ''));
        $restApiKeyInput = trim((string) ($settingsInput['onesignal_rest_api_key'] ?? ''));
        $logoUrl = notificationsNormalizeLogoUrl((string) ($settingsInput['notifications_logo_url'] ?? ''));
        $smallIcon = notificationsNormalizeSmallIconName(
            (string) ($settingsInput['notifications_small_icon'] ?? 'ic_stat_onesignal_default')
        );

        $existingDbRestApiKey = notificationsGetSettingFromDb('onesignal_rest_api_key');
        $restApiKeyToSave = $restApiKeyInput !== '' ? $restApiKeyInput : $existingDbRestApiKey;

        notificationsUpsertSetting('notifications_enabled', $enabled, 'تفعيل أو تعطيل الإشعارات الفورية');
        notificationsUpsertSetting('onesignal_app_id', $appId, 'OneSignal App ID');
        notificationsUpsertSetting('onesignal_rest_api_key', $restApiKeyToSave, 'OneSignal REST API Key');
        notificationsUpsertSetting('notifications_logo_url', $logoUrl, 'رابط لوجو الإشعار');
        notificationsUpsertSetting('notifications_small_icon', $smallIcon, 'اسم أيقونة الإشعار الصغيرة في أندرويد');

        logActivity('update_settings', 'notifications', 0);
        setFlashMessage('success', 'تم حفظ إعدادات الإشعارات بنجاح.');
        redirect('notifications.php');
    }

    if ($action === 'send_notification') {
        $sendTitle = trim((string) post('title'));
        $sendBody = trim((string) post('body'));
        $sendImageUrl = trim((string) ($_POST['image_url'] ?? ''));
        $sendTargetGroup = trim((string) post('target_group', 'all_users'));
        $sendUserId = (int) post('user_id');
        $sendProviderId = (int) post('provider_id');
        $sendDestinationType = trim((string) post('destination_type', 'none'));
        $sendDestinationId = (int) post('destination_id');

        if ($sendTitle === '' || $sendBody === '') {
            setFlashMessage('danger', 'عنوان الإشعار ونص الرسالة مطلوبان.');
            redirect('notifications.php');
        }

        if (!in_array($sendTargetGroup, ['all_users', 'specific_user', 'all_providers', 'specific_provider'], true)) {
            $sendTargetGroup = 'all_users';
        }
        if (!in_array($sendDestinationType, ['none', 'offer', 'category', 'spare_part'], true)) {
            $sendDestinationType = 'none';
        }
        if ($sendDestinationType !== 'none' && $sendDestinationId <= 0) {
            setFlashMessage('danger', 'يرجى اختيار وجهة صالحة للإشعار.');
            redirect('notifications.php');
        }

        $resolvedImageUrl = notificationsNormalizeMediaUrl($sendImageUrl, '');
        if (isset($_FILES['image_file']) && is_array($_FILES['image_file'])) {
            $fileError = (int) ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fileError !== UPLOAD_ERR_NO_FILE) {
                if ($fileError !== UPLOAD_ERR_OK) {
                    setFlashMessage('danger', 'حدث خطأ أثناء رفع صورة الإشعار.');
                    redirect('notifications.php');
                }

                $upload = uploadFile($_FILES['image_file'], 'notifications', ALLOWED_IMAGE_TYPES);
                if (empty($upload['success'])) {
                    $errorMessage = trim((string) ($upload['message'] ?? ''));
                    if ($errorMessage === '') {
                        $errorMessage = 'تعذر حفظ صورة الإشعار.';
                    }
                    setFlashMessage('danger', 'فشل رفع صورة الإشعار: ' . $errorMessage);
                    redirect('notifications.php');
                }

                $resolvedImageUrl = imageUrl((string) ($upload['path'] ?? ''));
            }
        }

        $mediaValidationError = notificationsValidateRichMediaUrl($resolvedImageUrl);
        if ($mediaValidationError !== '') {
            setFlashMessage(
                'danger',
                'تعذر إرسال الصورة مع الإشعار: ' . $mediaValidationError .
                ' استخدم رابطًا عامًا HTTPS أو أرسل من لوحة الإنتاج بدل localhost.'
            );
            redirect('notifications.php');
        }

        $externalIds = [];
        $dbInsertCount = 0;
        $targetCount = 0;
        $batchId = uniqid('admin_', true);
        $payloadData = [
            'type' => 'admin_announcement',
            'source' => 'admin_panel',
            'batch_id' => $batchId,
        ];
        if ($resolvedImageUrl !== '') {
            $payloadData['image_url'] = $resolvedImageUrl;
        }
        if ($sendDestinationType !== 'none' && $sendDestinationId > 0) {
            $payloadData['target'] = [
                'type' => $sendDestinationType,
                'id' => $sendDestinationId,
            ];
        }

        if ($sendTargetGroup === 'specific_user') {
            if (!notificationsTableExistsByName('users')) {
                setFlashMessage('danger', 'جدول المستخدمين غير متاح حاليًا.');
                redirect('notifications.php');
            }

            if ($sendUserId <= 0) {
                setFlashMessage('danger', 'يرجى اختيار مستخدم صالح.');
                redirect('notifications.php');
            }

            $targetUser = db()->fetch("SELECT id, full_name, is_active FROM users WHERE id = ? LIMIT 1", [$sendUserId]);
            if (!$targetUser) {
                setFlashMessage('danger', 'المستخدم المحدد غير موجود.');
                redirect('notifications.php');
            }

            $targetCount = 1;
            $externalIds[] = (string) $sendUserId;
            if (notificationsInsertForUser($sendUserId, $sendTitle, $sendBody, $payloadData)) {
                $dbInsertCount = 1;
            }
        } elseif ($sendTargetGroup === 'specific_provider') {
            if (!notificationsTableExistsByName('providers')) {
                setFlashMessage('danger', 'جدول مقدمي الخدمة غير متاح حاليًا.');
                redirect('notifications.php');
            }

            if ($sendProviderId <= 0) {
                setFlashMessage('danger', 'يرجى اختيار مقدم خدمة صالح.');
                redirect('notifications.php');
            }

            $targetProvider = db()->fetch("SELECT id, full_name FROM providers WHERE id = ? LIMIT 1", [$sendProviderId]);
            if (!$targetProvider) {
                setFlashMessage('danger', 'مقدم الخدمة المحدد غير موجود.');
                redirect('notifications.php');
            }

            $targetCount = 1;
            $externalIds[] = 'provider_' . $sendProviderId;
            if (notificationsInsertForProvider($sendProviderId, $sendTitle, $sendBody, $payloadData)) {
                $dbInsertCount = 1;
            }
        } elseif ($sendTargetGroup === 'all_providers') {
            if (notificationsTableExistsByName('providers')) {
                $providerWhere = notificationsColumnExists('providers', 'status')
                    ? "WHERE status = 'approved'"
                    : '';
                $approvedProviders = db()->fetchAll("SELECT id FROM providers {$providerWhere} ORDER BY id ASC");
                foreach ($approvedProviders as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $externalIds[] = 'provider_' . $id;
                }
            }

            $targetCount = count($externalIds);
            if ($targetCount > 0) {
                $dbInsertCount = notificationsInsertForAllProviders($sendTitle, $sendBody, $payloadData);
            } elseif (notificationsInsertGeneral($sendTitle, $sendBody, $payloadData)) {
                $dbInsertCount = 1;
            }
        } else {
            // For "all app devices", do not block sending when users table is empty.
            // Push delivery is handled by OneSignal segment "All" (registered + guest).
            if (notificationsTableExistsByName('users')) {
                $activeUsers = db()->fetchAll("SELECT id FROM users WHERE is_active = 1 ORDER BY id ASC");
                foreach ($activeUsers as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $externalIds[] = (string) $id;
                }
            }

            $targetCount = count($externalIds);
            if ($targetCount > 0) {
                $dbInsertCount = notificationsInsertForAllUsers($sendTitle, $sendBody, $payloadData);
            } else {
                // Keep a visible admin log entry even when all recipients are guests.
                if (notificationsInsertGeneral($sendTitle, $sendBody, $payloadData)) {
                    $dbInsertCount = 1;
                }
            }
        }

        if ($sendTargetGroup === 'all_users') {
            // Send push to all app installations, including guests (not logged in).
            $pushResult = notificationsSendPushToAllInstallations($sendTitle, $sendBody, $payloadData, $resolvedImageUrl);
        } else {
            $pushResult = notificationsSendPushToExternalIds(
                $externalIds,
                $sendTitle,
                $sendBody,
                $payloadData,
                $resolvedImageUrl
            );
        }

        $messageParts = [];
        if ($sendTargetGroup === 'all_users') {
            $messageParts[] = 'تم تجهيز الإرسال إلى كل أجهزة التطبيق.';
            $messageParts[] = 'عدد المستخدمين المسجلين المستهدفين داخل السجل: ' . $targetCount . '.';
        } elseif ($sendTargetGroup === 'all_providers') {
            $messageParts[] = 'تم تجهيز الإرسال إلى جميع مقدمي الخدمة المعتمدين.';
            $messageParts[] = 'عدد مقدمي الخدمة المستهدفين: ' . $targetCount . '.';
        } elseif ($sendTargetGroup === 'specific_provider') {
            $messageParts[] = 'تم تجهيز الإرسال إلى مقدم خدمة واحد.';
        } else {
            $messageParts[] = 'تم تجهيز الإرسال إلى ' . $targetCount . ' مستخدم.';
        }
        $messageParts[] = 'تم حفظ ' . $dbInsertCount . ' إشعار داخل قاعدة البيانات.';
        if ($sendTargetGroup === 'all_users') {
            $messageParts[] = 'تم إرسال Push عام لكل أجهزة التطبيق (يشمل الضيوف وغير المسجلين).';
        } elseif ($sendTargetGroup === 'all_providers') {
            $messageParts[] = 'تم إرسال Push جماعي لكل مقدمي الخدمة المستهدفين.';
        }
        if ($resolvedImageUrl !== '') {
            $messageParts[] = 'تم إرفاق صورة مع الإشعار.';
        }

        if ($pushResult['skipped']) {
            $messageParts[] = 'الإرسال الفعلي عبر OneSignal متوقف: ' . $pushResult['skip_reason'];
            setFlashMessage('warning', implode(' ', $messageParts));
        } else {
            $messageParts[] = 'نجح الإرسال الفوري إلى ' . $pushResult['successful'] . ' مستخدم وفشل لـ ' . $pushResult['failed'] . '.';
            if (!empty($pushResult['errors'])) {
                $messageParts[] = 'تفاصيل: ' . implode(' | ', array_slice($pushResult['errors'], 0, 2));
            }

            if ($pushResult['failed'] > 0) {
                setFlashMessage('warning', implode(' ', $messageParts));
            } else {
                setFlashMessage('success', implode(' ', $messageParts));
            }
        }

        logActivity('send_notification', 'notifications', 0, null, json_encode([
            'target_group' => $sendTargetGroup,
            'target_count' => $targetCount,
            'target_user_id' => $sendUserId > 0 ? $sendUserId : null,
            'target_provider_id' => $sendProviderId > 0 ? $sendProviderId : null,
            'destination_type' => $sendDestinationType !== 'none' ? $sendDestinationType : null,
            'destination_id' => $sendDestinationId > 0 ? $sendDestinationId : null,
            'db_inserted' => $dbInsertCount,
            'push_successful' => $pushResult['successful'],
            'push_failed' => $pushResult['failed'],
            'image_url' => $resolvedImageUrl,
        ], JSON_UNESCAPED_UNICODE));

        redirect('notifications.php');
    }
}

[$savedAppId, $appIdSource] = notificationsGetSettingWithSource('onesignal_app_id', '');
[$savedRestApiKey, $restKeySource] = notificationsGetSettingWithSource('onesignal_rest_api_key', '');
[$savedEnabledRaw, $enabledSource] = notificationsGetSettingWithSource('notifications_enabled', '1');
[$savedLogoUrlRaw, $logoSource] = notificationsGetSettingWithSource('notifications_logo_url', '');
[$savedSmallIconRaw, $smallIconSource] = notificationsGetSettingWithSource(
    'notifications_small_icon',
    'ic_stat_onesignal_default'
);

$notificationsEnabled = notificationsNormalizeEnabled($savedEnabledRaw) === '1';
$savedLogoUrl = notificationsNormalizeLogoUrl($savedLogoUrlRaw);
$savedSmallIcon = notificationsNormalizeSmallIconName($savedSmallIconRaw);
$restApiKeyMasked = notificationsMaskKey($savedRestApiKey);

$usersList = [];
if (notificationsTableExistsByName('users')) {
    $hasIsActiveColumn = notificationsColumnExists('users', 'is_active');
    $hasCreatedAtColumn = notificationsColumnExists('users', 'created_at');
    $userNameField = notificationsColumnExists('users', 'full_name')
        ? 'full_name'
        : (notificationsColumnExists('users', 'name') ? 'name AS full_name' : "CONCAT('مستخدم #', id) AS full_name");
    $userPhoneField = notificationsColumnExists('users', 'phone')
        ? 'phone'
        : (notificationsColumnExists('users', 'mobile') ? 'mobile AS phone' : "'' AS phone");

    $usersSelectFields = 'id, ' . $userNameField . ', ' . $userPhoneField;
    if ($hasIsActiveColumn) {
        $usersSelectFields .= ', is_active';
    }

    $usersOrderBy = $hasCreatedAtColumn ? 'created_at DESC' : 'id DESC';
    try {
        $usersList = db()->fetchAll("
            SELECT {$usersSelectFields}
            FROM users
            ORDER BY {$usersOrderBy}
            LIMIT 1000
        ");
    } catch (Throwable $e) {
        $usersList = [];
    }

    if (empty($usersList)) {
        try {
            $rawUsers = db()->fetchAll("SELECT * FROM users ORDER BY id DESC LIMIT 1000");
            foreach ($rawUsers as $rawUser) {
                $rawUserId = (int) ($rawUser['id'] ?? 0);
                if ($rawUserId <= 0) {
                    continue;
                }
                $resolvedName = '';
                foreach (['full_name', 'name', 'username', 'email', 'phone', 'mobile'] as $nameKey) {
                    $candidate = trim((string) ($rawUser[$nameKey] ?? ''));
                    if ($candidate !== '') {
                        $resolvedName = $candidate;
                        break;
                    }
                }
                if ($resolvedName === '') {
                    $resolvedName = 'مستخدم #' . $rawUserId;
                }
                $resolvedPhone = '';
                foreach (['phone', 'mobile', 'mobile_number', 'phone_number', 'whatsapp', 'email'] as $phoneKey) {
                    $candidate = trim((string) ($rawUser[$phoneKey] ?? ''));
                    if ($candidate !== '') {
                        $resolvedPhone = $candidate;
                        break;
                    }
                }
                $usersList[] = [
                    'id' => $rawUserId,
                    'full_name' => $resolvedName,
                    'phone' => $resolvedPhone,
                    'is_active' => isset($rawUser['is_active']) ? (int) $rawUser['is_active'] : null,
                ];
            }
        } catch (Throwable $e) {
            $usersList = [];
        }
    }
}

$providersList = [];
if (notificationsTableExistsByName('providers')) {
    $hasProviderStatusColumn = notificationsColumnExists('providers', 'status');
    $hasProviderCreatedAtColumn = notificationsColumnExists('providers', 'created_at');
    $hasProviderAvailabilityColumn = notificationsColumnExists('providers', 'is_available');
    $providerNameField = notificationsColumnExists('providers', 'full_name')
        ? 'full_name'
        : (notificationsColumnExists('providers', 'name') ? 'name AS full_name' : "CONCAT('مقدم خدمة #', id) AS full_name");
    $providerPhoneField = notificationsColumnExists('providers', 'phone')
        ? 'phone'
        : (notificationsColumnExists('providers', 'mobile') ? 'mobile AS phone' : "'' AS phone");

    $providersSelectFields = 'id, ' . $providerNameField . ', ' . $providerPhoneField;
    if ($hasProviderStatusColumn) {
        $providersSelectFields .= ', status';
    }
    if ($hasProviderAvailabilityColumn) {
        $providersSelectFields .= ', is_available';
    }

    $providersOrderBy = $hasProviderCreatedAtColumn ? 'created_at DESC' : 'id DESC';
    try {
        $providersList = db()->fetchAll("
            SELECT {$providersSelectFields}
            FROM providers
            ORDER BY {$providersOrderBy}
            LIMIT 1000
        ");
    } catch (Throwable $e) {
        $providersList = [];
    }

    if (empty($providersList)) {
        try {
            $rawProviders = db()->fetchAll("SELECT * FROM providers ORDER BY id DESC LIMIT 1000");
            foreach ($rawProviders as $rawProvider) {
                $rawProviderId = (int) ($rawProvider['id'] ?? 0);
                if ($rawProviderId <= 0) {
                    continue;
                }
                $resolvedName = '';
                foreach (['full_name', 'name', 'username', 'email', 'phone', 'mobile'] as $nameKey) {
                    $candidate = trim((string) ($rawProvider[$nameKey] ?? ''));
                    if ($candidate !== '') {
                        $resolvedName = $candidate;
                        break;
                    }
                }
                if ($resolvedName === '') {
                    $resolvedName = 'مقدم خدمة #' . $rawProviderId;
                }
                $resolvedPhone = '';
                foreach (['phone', 'mobile', 'mobile_number', 'phone_number', 'whatsapp', 'email'] as $phoneKey) {
                    $candidate = trim((string) ($rawProvider[$phoneKey] ?? ''));
                    if ($candidate !== '') {
                        $resolvedPhone = $candidate;
                        break;
                    }
                }
                $providersList[] = [
                    'id' => $rawProviderId,
                    'full_name' => $resolvedName,
                    'phone' => $resolvedPhone,
                    'status' => $rawProvider['status'] ?? null,
                    'is_available' => isset($rawProvider['is_available']) ? (int) $rawProvider['is_available'] : null,
                ];
            }
        } catch (Throwable $e) {
            $providersList = [];
        }
    }
}

$offerTargets = notificationsTableExistsByName('promo_codes')
    ? db()->fetchAll("
        SELECT id,
               COALESCE(NULLIF(title_ar, ''), NULLIF(title_en, ''), code) AS label
        FROM promo_codes
        ORDER BY id DESC
        LIMIT 500
    ")
    : [];

$categoryTargets = notificationsTableExistsByName('service_categories')
    ? db()->fetchAll("
        SELECT id, COALESCE(NULLIF(name_ar, ''), NULLIF(name_en, ''), CONCAT('قسم #', id)) AS label
        FROM service_categories
        ORDER BY sort_order ASC, id ASC
        LIMIT 500
    ")
    : [];

$sparePartTargets = notificationsTableExistsByName('spare_parts')
    ? db()->fetchAll("
        SELECT id, COALESCE(NULLIF(name_ar, ''), NULLIF(name_en, ''), CONCAT('قطعة #', id)) AS label
        FROM spare_parts
        ORDER BY id DESC
        LIMIT 500
    ")
    : [];

$userNotificationsCount = 0;
$providerNotificationsCount = 0;
$unreadNotificationsCount = 0;
$notificationsHistory = [];

if (notificationsTableExistsByName('notifications')) {
    $hasNotificationUserId = notificationsColumnExists('notifications', 'user_id');
    $hasNotificationProviderId = notificationsColumnExists('notifications', 'provider_id');
    $hasNotificationIsRead = notificationsColumnExists('notifications', 'is_read');
    $hasNotificationData = notificationsColumnExists('notifications', 'data');
    $hasNotificationType = notificationsColumnExists('notifications', 'type');
    $hasNotificationCreatedAt = notificationsColumnExists('notifications', 'created_at');
    $hasNotificationTitle = notificationsColumnExists('notifications', 'title');
    $hasNotificationBody = notificationsColumnExists('notifications', 'body');
    $hasNotificationMessage = notificationsColumnExists('notifications', 'message');

    $userIdExpr = $hasNotificationUserId ? 'user_id' : 'NULL';
    $providerIdExpr = $hasNotificationProviderId ? 'provider_id' : 'NULL';
    $isReadExpr = $hasNotificationIsRead ? 'is_read' : '1';

    $statsRow = db()->fetch("
        SELECT
            SUM(CASE WHEN {$userIdExpr} IS NOT NULL THEN 1 ELSE 0 END) AS user_notifications,
            SUM(CASE WHEN {$providerIdExpr} IS NOT NULL THEN 1 ELSE 0 END) AS provider_notifications,
            SUM(CASE WHEN {$isReadExpr} = 0 THEN 1 ELSE 0 END) AS unread_notifications
        FROM notifications
    ");

    $userNotificationsCount = (int) ($statsRow['user_notifications'] ?? 0);
    $providerNotificationsCount = (int) ($statsRow['provider_notifications'] ?? 0);
    $unreadNotificationsCount = (int) ($statsRow['unread_notifications'] ?? 0);

    $canJoinUsers = notificationsTableExistsByName('users') && $hasNotificationUserId;
    $canJoinProviders = notificationsTableExistsByName('providers') && $hasNotificationProviderId;
    $userHistoryNameField = $canJoinUsers
        ? (notificationsColumnExists('users', 'full_name')
            ? 'u.full_name AS user_name'
            : (notificationsColumnExists('users', 'name') ? 'u.name AS user_name' : "'' AS user_name"))
        : "'' AS user_name";
    $userHistoryPhoneField = $canJoinUsers
        ? (notificationsColumnExists('users', 'phone')
            ? 'u.phone AS user_phone'
            : (notificationsColumnExists('users', 'mobile') ? 'u.mobile AS user_phone' : "'' AS user_phone"))
        : "'' AS user_phone";
    $providerHistoryNameField = $canJoinProviders
        ? (notificationsColumnExists('providers', 'full_name')
            ? 'p.full_name AS provider_name'
            : (notificationsColumnExists('providers', 'name') ? 'p.name AS provider_name' : "'' AS provider_name"))
        : "'' AS provider_name";
    $providerHistoryPhoneField = $canJoinProviders
        ? (notificationsColumnExists('providers', 'phone')
            ? 'p.phone AS provider_phone'
            : (notificationsColumnExists('providers', 'mobile') ? 'p.mobile AS provider_phone' : "'' AS provider_phone"))
        : "'' AS provider_phone";

    $historyTitleExpr = $hasNotificationTitle
        ? 'n.title'
        : ($hasNotificationMessage
            ? 'n.message AS title'
            : ($hasNotificationBody ? 'n.body AS title' : "'' AS title"));
    $historyBodyExpr = $hasNotificationBody
        ? 'n.body'
        : ($hasNotificationMessage
            ? 'n.message AS body'
            : ($hasNotificationTitle ? 'n.title AS body' : "'' AS body"));

    $historySelectParts = [
        'n.id',
        $hasNotificationUserId ? 'n.user_id' : 'NULL AS user_id',
        $hasNotificationProviderId ? 'n.provider_id' : 'NULL AS provider_id',
        $historyTitleExpr,
        $historyBodyExpr,
        $hasNotificationType ? 'n.type' : "'system' AS type",
        $hasNotificationCreatedAt ? 'n.created_at' : 'NOW() AS created_at',
        $hasNotificationData ? 'n.data' : 'NULL AS data',
        $userHistoryNameField,
        $userHistoryPhoneField,
        $providerHistoryNameField,
        $providerHistoryPhoneField,
    ];

    $notificationsHistory = db()->fetchAll("
        SELECT " . implode(', ', $historySelectParts) . "
        FROM notifications n
        " . ($canJoinUsers ? "LEFT JOIN users u ON u.id = n.user_id" : '') . "
        " . ($canJoinProviders ? "LEFT JOIN providers p ON p.id = n.provider_id" : '') . "
        ORDER BY " . ($hasNotificationCreatedAt ? 'n.created_at' : 'n.id') . " DESC
        LIMIT 40
    ");
}

include '../includes/header.php';
?>

<div style="display: grid; grid-template-columns: minmax(320px, 1fr) minmax(420px, 1.2fr); gap: 24px;">
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-sliders-h" style="color: var(--primary-color);"></i>
                إعدادات الإشعارات
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="save_settings">

                <div class="form-group">
                    <label class="form-label">تفعيل الإشعارات الفورية</label>
                    <select name="settings[notifications_enabled]" class="form-control">
                        <option value="1" <?php echo $notificationsEnabled ? 'selected' : ''; ?>>مفعل</option>
                        <option value="0" <?php echo !$notificationsEnabled ? 'selected' : ''; ?>>معطل</option>
                    </select>
                    <small class="text-muted">المصدر الحالي: <?php echo $enabledSource; ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label">OneSignal App ID</label>
                    <input
                        type="text"
                        name="settings[onesignal_app_id]"
                        class="form-control"
                        dir="ltr"
                        style="text-align: left;"
                        value="<?php echo htmlspecialchars($savedAppId); ?>"
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    >
                    <small class="text-muted">المصدر الحالي: <?php echo $appIdSource; ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label">OneSignal REST API Key</label>
                    <input
                        type="password"
                        name="settings[onesignal_rest_api_key]"
                        class="form-control"
                        dir="ltr"
                        style="text-align: left;"
                        placeholder="اتركه فارغًا للإبقاء على القيمة المحفوظة"
                    >
                    <small class="text-muted">
                        القيمة الحالية: <?php echo htmlspecialchars($restApiKeyMasked); ?> |
                        المصدر: <?php echo $restKeySource; ?>
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">رابط لوجو الإشعار</label>
                    <input
                        type="text"
                        name="settings[notifications_logo_url]"
                        class="form-control"
                        dir="ltr"
                        style="text-align: left;"
                        value="<?php echo htmlspecialchars($savedLogoUrl); ?>"
                        placeholder="https://darfix.org/admin-panel/assets/images/default.png"
                    >
                    <small class="text-muted">المصدر الحالي: <?php echo $logoSource; ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label">اسم أيقونة الإشعار الصغيرة (Android)</label>
                    <input
                        type="text"
                        name="settings[notifications_small_icon]"
                        class="form-control"
                        dir="ltr"
                        style="text-align: left;"
                        value="<?php echo htmlspecialchars($savedSmallIcon); ?>"
                        placeholder="ic_stat_onesignal_default"
                    >
                    <small class="text-muted">
                        القيمة الحالية: <?php echo htmlspecialchars($savedSmallIcon); ?> |
                        المصدر: <?php echo $smallIconSource; ?>
                    </small>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i>
                    حفظ إعدادات الإشعارات
                </button>
            </form>

            <hr style="margin: 20px 0;">

            <div style="display: grid; gap: 10px;">
                <div class="alert alert-info" style="margin: 0;">
                    <strong>إجمالي إشعارات المستخدمين:</strong>
                    <?php echo number_format($userNotificationsCount); ?>
                </div>
                <div class="alert alert-secondary" style="margin: 0;">
                    <strong>إجمالي إشعارات مقدمي الخدمة:</strong>
                    <?php echo number_format($providerNotificationsCount); ?>
                </div>
                <div class="alert alert-warning" style="margin: 0;">
                    <strong>الإشعارات غير المقروءة:</strong>
                    <?php echo number_format($unreadNotificationsCount); ?>
                </div>
                <div class="alert <?php echo $notificationsEnabled ? 'alert-success' : 'alert-danger'; ?>" style="margin: 0;">
                    <strong>حالة الإرسال الفوري:</strong>
                    <?php echo $notificationsEnabled ? 'مفعل' : 'معطل'; ?>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img
                        src="<?php echo htmlspecialchars($savedLogoUrl); ?>"
                        alt="Notification Logo"
                        style="width: 34px; height: 34px; border-radius: 8px; border: 1px solid #e5e7eb;"
                        onerror="this.style.display='none';"
                    >
                    <small class="text-muted" dir="ltr" style="text-align: left;">
                        <?php echo htmlspecialchars($savedLogoUrl); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; gap: 24px;">
        <div class="card animate-slideUp">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paper-plane" style="color: var(--secondary-color);"></i>
                    إرسال إشعار
                </h3>
            </div>
            <div class="card-body">
                <?php
                    $showSpecificUser = $sendTargetGroup === 'specific_user';
                    $showSpecificProvider = $sendTargetGroup === 'specific_provider';
                ?>
                <form method="POST" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="send_notification">

                    <div class="form-group">
                        <label class="form-label">العنوان</label>
                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            required
                            maxlength="180"
                            value="<?php echo htmlspecialchars($sendTitle); ?>"
                            placeholder="مثال: عرض خاص لفترة محدودة"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">الرسالة</label>
                        <textarea
                            name="body"
                            class="form-control"
                            rows="4"
                            maxlength="1000"
                            required
                            placeholder="اكتب محتوى الإشعار..."
                        ><?php echo htmlspecialchars($sendBody); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">رابط صورة الإشعار (اختياري)</label>
                        <input
                            type="text"
                            name="image_url"
                            class="form-control"
                            dir="ltr"
                            style="text-align: left;"
                            value="<?php echo htmlspecialchars($sendImageUrl); ?>"
                            placeholder="https://example.com/offer.jpg"
                        >
                        <small class="text-muted">يجب أن يكون الرابط عامًا ويبدأ بـ https://</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">أو رفع صورة من الجهاز (اختياري)</label>
                        <input
                            type="file"
                            name="image_file"
                            class="form-control"
                            accept="image/*"
                        >
                        <small class="text-muted">إذا كنت على localhost فلن تظهر الصورة على موبايل خارجي، استخدم رابط HTTPS عام.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الفئة المستهدفة</label>
                        <select name="target_group" id="targetGroup" class="form-control" onchange="toggleUserSelection()">
                            <option value="all_users" <?php echo $sendTargetGroup === 'all_users' ? 'selected' : ''; ?>>كل أجهزة التطبيق (مسجل + ضيف)</option>
                            <option value="specific_user" <?php echo $sendTargetGroup === 'specific_user' ? 'selected' : ''; ?>>مستخدم محدد</option>
                            <option value="all_providers" <?php echo $sendTargetGroup === 'all_providers' ? 'selected' : ''; ?>>كل مقدمي الخدمة</option>
                            <option value="specific_provider" <?php echo $sendTargetGroup === 'specific_provider' ? 'selected' : ''; ?>>مقدم خدمة محدد</option>
                        </select>
                    </div>

                    <div class="form-group" id="specificUserGroup" style="<?php echo $showSpecificUser ? '' : 'display: none;'; ?>">
                        <label class="form-label">ابحث عن مستخدم</label>
                        <input
                            type="text"
                            id="userSearchInput"
                            class="form-control"
                            placeholder="اكتب اسم المستخدم أو رقم الجوال..."
                            autocomplete="off"
                            <?php echo $showSpecificUser ? '' : 'disabled'; ?>
                        >
                        <small class="text-muted" id="userSearchStatus" style="display:block; margin-top:6px;">
                            ابدأ بكتابة حرفين للبحث.
                        </small>
                        <label class="form-label" style="margin-top:12px;">اختيار مستخدم</label>
                        <select name="user_id" id="userSelect" class="form-control" <?php echo $showSpecificUser ? '' : 'disabled'; ?>>
                            <option value="">-- اختر مستخدم --</option>
                            <?php foreach ($usersList as $user): ?>
                                <option value="<?php echo (int) $user['id']; ?>" <?php echo $sendUserId === (int) $user['id'] ? 'selected' : ''; ?>>
                                    #<?php echo (int) $user['id']; ?> -
                                    <?php echo htmlspecialchars((string) ($user['full_name'] ?? 'بدون اسم')); ?>
                                    (<?php echo htmlspecialchars((string) ($user['phone'] ?? '-')); ?>)
                                    <?php if (array_key_exists('is_active', $user)): ?>
                                        - <?php echo ((int) ($user['is_active'] ?? 0) === 1) ? 'نشط' : 'غير نشط'; ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">يمكنك البحث بالاسم أو الجوال لعرض النتائج.</small>
                    </div>

                    <div class="form-group" id="specificProviderGroup" style="<?php echo $showSpecificProvider ? '' : 'display: none;'; ?>">
                        <label class="form-label">ابحث عن مقدم خدمة</label>
                        <input
                            type="text"
                            id="providerSearchInput"
                            class="form-control"
                            placeholder="اكتب اسم مقدم الخدمة أو رقم الجوال..."
                            autocomplete="off"
                            <?php echo $showSpecificProvider ? '' : 'disabled'; ?>
                        >
                        <small class="text-muted" id="providerSearchStatus" style="display:block; margin-top:6px;">
                            ابدأ بكتابة حرفين للبحث.
                        </small>
                        <label class="form-label" style="margin-top:12px;">اختيار مقدم خدمة</label>
                        <select name="provider_id" id="providerSelect" class="form-control" <?php echo $showSpecificProvider ? '' : 'disabled'; ?>>
                            <option value="">-- اختر مقدم خدمة --</option>
                            <?php foreach ($providersList as $provider): ?>
                                <?php
                                $providerStatus = trim((string) ($provider['status'] ?? ''));
                                $providerAvailability = array_key_exists('is_available', $provider)
                                    ? ((int) ($provider['is_available'] ?? 0) === 1 ? 'متاح' : 'غير متاح')
                                    : '';
                                ?>
                                <option value="<?php echo (int) $provider['id']; ?>" <?php echo $sendProviderId === (int) $provider['id'] ? 'selected' : ''; ?>>
                                    #<?php echo (int) $provider['id']; ?> -
                                    <?php echo htmlspecialchars((string) ($provider['full_name'] ?? 'بدون اسم')); ?>
                                    (<?php echo htmlspecialchars((string) ($provider['phone'] ?? '-')); ?>)
                                    <?php if ($providerStatus !== ''): ?>
                                        - <?php echo htmlspecialchars($providerStatus); ?>
                                    <?php endif; ?>
                                    <?php if ($providerAvailability !== ''): ?>
                                        - <?php echo htmlspecialchars($providerAvailability); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">يمكنك البحث بالاسم أو الجوال لعرض النتائج.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">وجهة الإشعار داخل التطبيق (اختياري)</label>
                        <select name="destination_type" id="destinationType" class="form-control" onchange="toggleDestinationSelection()">
                            <option value="none" <?php echo $sendDestinationType === 'none' ? 'selected' : ''; ?>>بدون وجهة</option>
                            <option value="offer" <?php echo $sendDestinationType === 'offer' ? 'selected' : ''; ?>>عرض</option>
                            <option value="category" <?php echo $sendDestinationType === 'category' ? 'selected' : ''; ?>>قسم</option>
                            <option value="spare_part" <?php echo $sendDestinationType === 'spare_part' ? 'selected' : ''; ?>>قطعة غيار</option>
                        </select>
                        <input type="hidden" name="destination_id" id="destinationIdInput" value="<?php echo (int) $sendDestinationId; ?>">
                    </div>

                    <div class="form-group" id="destinationOfferGroup" style="display: none;">
                        <label class="form-label">اختيار العرض</label>
                        <select id="destinationOfferSelect" class="form-control" onchange="syncDestinationId()">
                            <option value="">-- اختر عرضًا --</option>
                            <?php foreach ($offerTargets as $target): ?>
                                <option value="<?php echo (int) ($target['id'] ?? 0); ?>" <?php echo $sendDestinationType === 'offer' && $sendDestinationId === (int) ($target['id'] ?? 0) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($target['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="destinationCategoryGroup" style="display: none;">
                        <label class="form-label">اختيار القسم</label>
                        <select id="destinationCategorySelect" class="form-control" onchange="syncDestinationId()">
                            <option value="">-- اختر قسمًا --</option>
                            <?php foreach ($categoryTargets as $target): ?>
                                <option value="<?php echo (int) ($target['id'] ?? 0); ?>" <?php echo $sendDestinationType === 'category' && $sendDestinationId === (int) ($target['id'] ?? 0) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($target['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="destinationSparePartGroup" style="display: none;">
                        <label class="form-label">اختيار قطعة الغيار</label>
                        <select id="destinationSparePartSelect" class="form-control" onchange="syncDestinationId()">
                            <option value="">-- اختر قطعة غيار --</option>
                            <?php foreach ($sparePartTargets as $target): ?>
                                <option value="<?php echo (int) ($target['id'] ?? 0); ?>" <?php echo $sendDestinationType === 'spare_part' && $sendDestinationId === (int) ($target['id'] ?? 0) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($target['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i>
                        إرسال الإشعار الآن
                    </button>
                </form>
            </div>
        </div>

        <div class="card animate-slideUp">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history" style="color: #6b7280;"></i>
                    آخر الإشعارات المحفوظة
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($notificationsHistory)): ?>
                    <div class="empty-state" style="padding: 20px;">
                        <p>لا يوجد سجل إشعارات حتى الآن.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>العنوان</th>
                                    <th>المستخدم</th>
                                    <th>النوع</th>
                                    <th>الوقت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notificationsHistory as $item): ?>
                                    <?php
                                    $displayUserName = trim((string) ($item['user_name'] ?? ''));
                                    if ($displayUserName === '') {
                                        $displayUserId = (int) ($item['user_id'] ?? 0);
                                        $displayProviderName = trim((string) ($item['provider_name'] ?? ''));
                                        $displayProviderId = (int) ($item['provider_id'] ?? 0);

                                        if ($displayProviderName !== '' || $displayProviderId > 0) {
                                            $displayUserName = $displayProviderName !== ''
                                                ? ('مقدم خدمة: ' . $displayProviderName)
                                                : ('مقدم خدمة #' . $displayProviderId);
                                        } elseif ($displayUserId > 0) {
                                            $displayUserName = 'مستخدم #' . $displayUserId;
                                        } else {
                                            $displayUserName = 'عام (كل الأجهزة)';
                                        }
                                    }
                                    $displayUserPhone = trim((string) ($item['user_phone'] ?? ''));
                                    if ($displayUserPhone === '') {
                                        $displayUserPhone = trim((string) ($item['provider_phone'] ?? ''));
                                    }
                                    $notificationData = json_decode((string) ($item['data'] ?? ''), true);
                                    $targetBadge = '';
                                    if (is_array($notificationData) && !empty($notificationData['target']['type']) && !empty($notificationData['target']['id'])) {
                                        $targetType = (string) ($notificationData['target']['type'] ?? '');
                                        $targetId = (int) ($notificationData['target']['id'] ?? 0);
                                        $targetTypeLabel = match ($targetType) {
                                            'offer' => 'عرض',
                                            'category' => 'قسم',
                                            'spare_part' => 'قطعة',
                                            default => '',
                                        };
                                        if ($targetTypeLabel !== '' && $targetId > 0) {
                                            $targetBadge = $targetTypeLabel . ' #' . $targetId;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars((string) $item['title']); ?></strong>
                                            <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                                <?php echo htmlspecialchars(truncate((string) $item['body'], 90)); ?>
                                            </div>
                                            <?php if ($targetBadge !== ''): ?>
                                                <div style="font-size: 11px; color: #2563eb; margin-top: 4px;">
                                                    الوجهة: <?php echo htmlspecialchars($targetBadge, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;">
                                                <?php echo htmlspecialchars($displayUserName); ?>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($displayUserPhone); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars((string) $item['type']); ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo formatDateTime((string) $item['created_at']); ?></div>
                                            <small class="text-muted"><?php echo timeAgo((string) $item['created_at']); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleUserSelection() {
        var target = document.getElementById('targetGroup');
        var userWrapper = document.getElementById('specificUserGroup');
        var providerWrapper = document.getElementById('specificProviderGroup');
        var userSelect = userWrapper ? userWrapper.querySelector('select[name="user_id"]') : null;
        var providerSelect = providerWrapper ? providerWrapper.querySelector('select[name="provider_id"]') : null;
        var userSearchInput = document.getElementById('userSearchInput');
        var providerSearchInput = document.getElementById('providerSearchInput');
        if (!target || !userWrapper || !providerWrapper) {
            return;
        }
        userWrapper.style.display = target.value === 'specific_user' ? 'block' : 'none';
        providerWrapper.style.display = target.value === 'specific_provider' ? 'block' : 'none';
        if (userSelect) userSelect.disabled = target.value !== 'specific_user';
        if (providerSelect) providerSelect.disabled = target.value !== 'specific_provider';
        if (userSearchInput) userSearchInput.disabled = target.value !== 'specific_user';
        if (providerSearchInput) providerSearchInput.disabled = target.value !== 'specific_provider';
    }

    function toggleDestinationSelection() {
        var target = document.getElementById('destinationType');
        var offerGroup = document.getElementById('destinationOfferGroup');
        var categoryGroup = document.getElementById('destinationCategoryGroup');
        var sparePartGroup = document.getElementById('destinationSparePartGroup');
        var offerSelect = document.getElementById('destinationOfferSelect');
        var categorySelect = document.getElementById('destinationCategorySelect');
        var sparePartSelect = document.getElementById('destinationSparePartSelect');
        if (!target || !offerGroup || !categoryGroup || !sparePartGroup) {
            return;
        }
        offerGroup.style.display = target.value === 'offer' ? 'block' : 'none';
        categoryGroup.style.display = target.value === 'category' ? 'block' : 'none';
        sparePartGroup.style.display = target.value === 'spare_part' ? 'block' : 'none';
        if (offerSelect) offerSelect.disabled = target.value !== 'offer';
        if (categorySelect) categorySelect.disabled = target.value !== 'category';
        if (sparePartSelect) sparePartSelect.disabled = target.value !== 'spare_part';
        syncDestinationId();
    }

    function syncDestinationId() {
        var target = document.getElementById('destinationType');
        var hidden = document.getElementById('destinationIdInput');
        var offerSelect = document.getElementById('destinationOfferSelect');
        var categorySelect = document.getElementById('destinationCategorySelect');
        var sparePartSelect = document.getElementById('destinationSparePartSelect');
        if (!target || !hidden) {
            return;
        }
        if (target.value === 'offer' && offerSelect) {
            hidden.value = offerSelect.value || '';
            return;
        }
        if (target.value === 'category' && categorySelect) {
            hidden.value = categorySelect.value || '';
            return;
        }
        if (target.value === 'spare_part' && sparePartSelect) {
            hidden.value = sparePartSelect.value || '';
            return;
        }
        hidden.value = '';
    }

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            if (timer) clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    function updateSearchSelect(selectEl, items, placeholderText) {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = placeholderText || '-- اختر --';
        selectEl.appendChild(placeholder);
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = '#' + item.id + ' - ' + (item.name || '') + ' (' + (item.phone || '-') + ')';
            selectEl.appendChild(opt);
        });
    }

    function attachSearch(inputId, selectId, statusId, endpoint, placeholderText) {
        var input = document.getElementById(inputId);
        var selectEl = document.getElementById(selectId);
        var statusEl = document.getElementById(statusId);
        if (!input || !selectEl) return;

        var runSearch = debounce(function () {
            var term = input.value.trim();
            if (term.length < 2) {
                if (statusEl) statusEl.textContent = 'اكتب حرفين على الأقل للبحث.';
                return;
            }
            if (statusEl) statusEl.textContent = 'جاري البحث...';
            fetch(endpoint + '?q=' + encodeURIComponent(term))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        if (statusEl) statusEl.textContent = 'تعذر جلب النتائج.';
                        return;
                    }
                    var items = Array.isArray(data.items) ? data.items : [];
                    updateSearchSelect(selectEl, items, placeholderText);
                    if (statusEl) {
                        statusEl.textContent = items.length
                            ? ('تم العثور على ' + items.length + ' نتيجة')
                            : 'لا توجد نتائج مطابقة.';
                    }
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'تعذر الاتصال بالخادم.';
                });
        }, 300);

        input.addEventListener('input', runSearch);
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleUserSelection();
        toggleDestinationSelection();
        attachSearch('userSearchInput', 'userSelect', 'userSearchStatus', '../ajax/search_users.php', '-- اختر مستخدم --');
        attachSearch('providerSearchInput', 'providerSelect', 'providerSearchStatus', '../ajax/search_providers.php', '-- اختر مقدم خدمة --');
    });
</script>

<?php include '../includes/footer.php'; ?>
