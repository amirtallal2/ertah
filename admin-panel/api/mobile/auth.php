<?php
/**
 * Mobile API - Auth Endpoints
 * نقاط نهاية المصادقة للموبايل
 */

// Prevent HTML/Text output from warnings
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);


header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

// OTP + SMS Gateway (4jawaly)
define('OTP_LENGTH', 4);
define('OTP_DELIVERY_CHANNELS_LIMIT', 4);
define('FOURJAWALY_API_URL_DEFAULT', 'https://api-sms.4jawaly.com/api/v1/account/area/sms/v2/send');
define('FOURJAWALY_API_URL', getenv('FOURJAWALY_API_URL') ?: FOURJAWALY_API_URL_DEFAULT);

// Disable warnings/notices to ensure clean JSON
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// Fetch App Settings
$appSettings = getAppSettings($conn);

try {
    switch ($action) {
        case 'send-otp':
            sendOTP($input, $appSettings);
            break;
        case 'verify-otp':
            verifyOTP($input, $appSettings);
            break;
        case 'register':
            registerUser($input);
            break;
        case 'login':
            loginUser($input);
            break;
        case 'refresh-token':
            refreshToken($input);
            break;
        default:
            sendError('Invalid action', 400);
    }
} catch (Throwable $e) {
    // Log internal error details but return safe API response.
    error_log('Auth API error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    sendError('حدث خطأ في الخادم', 500);
}

/**
 * Get App Settings
 */
function getAppSettings($conn)
{
    if (!$conn)
        return [];

    $settings = [];
    $query = "SELECT setting_key, setting_value FROM app_settings";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings; // Returns ['sms_enabled' => '0', 'fixed_otp' => '1234', ...]
}

function normalizeArabicDigitsToEnglish($value)
{
    return strtr((string) $value, [
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

function extractDigits($value)
{
    $normalized = normalizeArabicDigitsToEnglish((string) $value);
    return preg_replace('/\D+/', '', $normalized) ?? '';
}

function normalizePhoneInput($value)
{
    $normalized = normalizeArabicDigitsToEnglish((string) $value);
    $normalized = preg_replace('/\s+/', '', $normalized) ?? '';
    return trim($normalized);
}

function firstNonEmptyValue(array $values, $fallback = '')
{
    foreach ($values as $value) {
        $text = trim((string) $value);
        if ($text !== '') {
            return $text;
        }
    }

    return $fallback;
}

function resolveSmsAppName(array $settings): string
{
    return firstNonEmptyValue([
        $settings['app_name_ar'] ?? '',
        $settings['app_name'] ?? '',
        $settings['app_name_en'] ?? '',
        $settings['app_name_ur'] ?? '',
    ], 'Darfix');
}

function resolveSmsConfig(array $settings): array
{
    $apiUrl = firstNonEmptyValue([
        $settings['sms_api_url'] ?? '',
        getenv('FOURJAWALY_API_URL') ?: '',
    ], FOURJAWALY_API_URL);

    $apiKey = firstNonEmptyValue([
        $settings['sms_api_key'] ?? '',
        $settings['whatsapp_api_key'] ?? '',
        getenv('FOURJAWALY_API_KEY') ?: '',
    ]);

    $apiSecret = firstNonEmptyValue([
        $settings['sms_api_secret'] ?? '',
        $settings['whatsapp_api_secret'] ?? '',
        getenv('FOURJAWALY_API_SECRET') ?: '',
    ]);

    $senderId = firstNonEmptyValue([
        $settings['sms_sender_id'] ?? '',
        $settings['whatsapp_sender'] ?? '',
        getenv('FOURJAWALY_SENDER_ID') ?: '',
    ]);

    return [
        'api_url' => $apiUrl,
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'sender_id' => $senderId,
        'app_name' => resolveSmsAppName($settings),
    ];
}

/**
 * Resolve requested account type from request payload.
 * Defaults to user for backward compatibility with client app.
 */
function resolveAccountType($input)
{
    $raw = strtolower(trim((string) (
        $input['account_type']
            ?? $input['role']
            ?? $input['login_as']
            ?? $input['app_type']
            ?? 'user'
    )));

    if (in_array($raw, ['provider', 'service_provider', 'technician'], true)) {
        return 'provider';
    }

    return 'user';
}

function providerColumnExists($column)
{
    global $conn;
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `providers` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function ensureProviderAuthSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'providers'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    if (!providerColumnExists('otp_code')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `otp_code` VARCHAR(10) NULL");
    }
    if (!providerColumnExists('is_verified')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!providerColumnExists('last_login')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `last_login` DATETIME NULL");
    }
    if (!providerColumnExists('profile_completed')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `profile_completed` TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!providerColumnExists('city')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `city` VARCHAR(100) NULL");
    }
    if (!providerColumnExists('country')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `country` VARCHAR(100) NULL");
    }
    if (!providerColumnExists('district')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `district` VARCHAR(100) NULL");
    }
    if (!providerColumnExists('bio')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `bio` TEXT NULL");
    }
    if (!providerColumnExists('experience_years')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `experience_years` INT NOT NULL DEFAULT 0");
    }
    if (!providerColumnExists('whatsapp_number')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `whatsapp_number` VARCHAR(32) NULL");
    }
    if (!providerColumnExists('residency_document_path')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `residency_document_path` VARCHAR(255) NULL");
    }
    if (!providerColumnExists('categories_locked')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `categories_locked` TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!providerColumnExists('approved_at')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `approved_at` DATETIME NULL");
    }
    if (!providerColumnExists('approved_by')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `approved_by` INT NULL");
    }
    if (!providerColumnExists('location_address')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `location_address` VARCHAR(255) NULL");
    }
    if (!providerColumnExists('current_lat')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `current_lat` DECIMAL(10,8) NULL");
    }
    if (!providerColumnExists('current_lng')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `current_lng` DECIMAL(11,8) NULL");
    }
    if (!providerColumnExists('location_updated_at')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `location_updated_at` DATETIME NULL");
    }
}

function getProviderStatusMessage($status)
{
    $map = [
        'pending' => 'حسابك قيد المراجعة من الإدارة',
        'rejected' => 'تم رفض طلب الانضمام، راجع الإدارة',
        'suspended' => 'تم إيقاف حساب مقدم الخدمة مؤقتًا',
    ];
    return $map[$status] ?? 'حالة حساب مقدم الخدمة لا تسمح بتسجيل الدخول';
}

function providerStatusIsBlocked($status)
{
    return in_array(strtolower(trim((string) $status)), ['rejected', 'suspended'], true);
}

function providerAuthTableExists($tableName)
{
    global $conn;
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
    if ($safe === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function ensureProviderServicesSchema()
{
    global $conn;

    if (!providerAuthTableExists('provider_services')) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS `provider_services` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `provider_id` INT NOT NULL,
                `category_id` INT NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_provider_category` (`provider_id`, `category_id`),
                KEY `idx_provider_id` (`provider_id`),
                KEY `idx_category_id` (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function getProviderByPhone($phone)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM providers WHERE phone = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getProviderByIdForAuth($providerId)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM providers WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function createProviderSkeleton($phone)
{
    global $conn;

    $defaultName = 'مقدم خدمة جديد';
    $stmt = $conn->prepare(
        "INSERT INTO providers (full_name, phone, status, is_available, is_verified, profile_completed)
         VALUES (?, ?, 'pending', 0, 1, 0)"
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("ss", $defaultName, $phone);
    if (!$stmt->execute()) {
        return 0;
    }
    return (int) $conn->insert_id;
}

function getProviderCategoryIdsForAuth($providerId)
{
    global $conn;

    $providerId = (int) $providerId;
    if ($providerId <= 0) {
        return [];
    }

    ensureProviderServicesSchema();
    if (!providerAuthTableExists('provider_services')) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT category_id FROM provider_services WHERE provider_id = ? ORDER BY category_id ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $categoryId = (int) ($row['category_id'] ?? 0);
        if ($categoryId !== 0) {
            $ids[] = $categoryId;
        }
    }
    return array_values(array_unique($ids));
}

function isMissingAvatarValue($avatarValue, $defaultFile)
{
    $avatar = trim((string) $avatarValue);
    if ($avatar === '') {
        return true;
    }

    $lowered = strtolower($avatar);
    if (in_array($lowered, ['null', 'undefined', 'nan'], true)) {
        return true;
    }

    $normalized = trim(str_replace('\\', '/', $lowered), '/');
    $default = strtolower(trim((string) $defaultFile));
    if ($default === '') {
        return false;
    }

    if ($normalized === $default) {
        return true;
    }

    $suffix = '/' . $default;
    if (strlen($normalized) >= strlen($suffix) && substr($normalized, -strlen($suffix)) === $suffix) {
        return true;
    }

    return false;
}

function computeProviderProfileCompletion($provider, $categoryIds = null)
{
    if (!$provider || !is_array($provider)) {
        return false;
    }

    $fullName = trim((string) ($provider['full_name'] ?? ''));
    $avatar = trim((string) ($provider['avatar'] ?? ''));
    $residencyDocument = trim((string) ($provider['residency_document_path'] ?? ''));
    $providerCategoryIds = is_array($categoryIds) ? $categoryIds : getProviderCategoryIdsForAuth((int) ($provider['id'] ?? 0));

    if ($fullName === '' || $fullName === 'مقدم خدمة جديد') {
        return false;
    }
    if (isMissingAvatarValue($avatar, 'default-provider.png')) {
        return false;
    }
    if ($residencyDocument === '' || in_array(strtolower($residencyDocument), ['null', 'undefined', 'nan'], true)) {
        return false;
    }
    if (empty($providerCategoryIds)) {
        return false;
    }

    return true;
}

function syncProviderProfileCompletion($providerId)
{
    global $conn;

    $provider = getProviderByIdForAuth((int) $providerId);
    if (!$provider) {
        return false;
    }

    $categoryIds = getProviderCategoryIdsForAuth((int) $provider['id']);
    $isComplete = computeProviderProfileCompletion($provider, $categoryIds);

    if (providerColumnExists('profile_completed')) {
        $profileCompleted = $isComplete ? 1 : 0;
        $stmt = $conn->prepare("UPDATE providers SET profile_completed = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $profileCompleted, $provider['id']);
            $stmt->execute();
        }
    }

    return $isComplete;
}

function formatProviderForAuth($provider)
{
    $providerId = (int) ($provider['id'] ?? 0);
    $categoryIds = getProviderCategoryIdsForAuth($providerId);
    $profileCompleted = computeProviderProfileCompletion($provider, $categoryIds);

    return [
        'id' => $providerId,
        'full_name' => $provider['full_name'] ?? '',
        'phone' => $provider['phone'] ?? '',
        'whatsapp_number' => $provider['whatsapp_number'] ?? ($provider['phone'] ?? ''),
        'email' => $provider['email'] ?? null,
        'avatar' => $provider['avatar'] ?? null,
        'residency_document_path' => $provider['residency_document_path'] ?? null,
        'residency_document' => $provider['residency_document_path'] ?? null,
        'country' => $provider['country'] ?? null,
        'city' => $provider['city'] ?? null,
        'district' => $provider['district'] ?? null,
        'location_address' => $provider['location_address'] ?? null,
        'current_lat' => isset($provider['current_lat']) && $provider['current_lat'] !== null ? (float) $provider['current_lat'] : null,
        'current_lng' => isset($provider['current_lng']) && $provider['current_lng'] !== null ? (float) $provider['current_lng'] : null,
        'location_updated_at' => $provider['location_updated_at'] ?? null,
        'bio' => $provider['bio'] ?? null,
        'experience_years' => (int) ($provider['experience_years'] ?? 0),
        'is_available' => isset($provider['is_available']) ? ((int) $provider['is_available'] === 1) : false,
        'category_ids' => $categoryIds,
        'categories_locked' => isset($provider['categories_locked']) ? ((int) ($provider['categories_locked']) === 1) : !empty($categoryIds),
        'wallet_balance' => isset($provider['wallet_balance']) ? (float) $provider['wallet_balance'] : 0.0,
        'points' => 0,
        'membership_level' => 'provider',
        'referral_code' => null,
        'referred_by' => null,
        'is_active' => ($provider['status'] ?? '') === 'approved',
        'is_verified' => isset($provider['is_verified']) ? ((int) $provider['is_verified'] === 1) : true,
        'device_token' => null,
        'last_login' => $provider['last_login'] ?? null,
        'created_at' => $provider['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $provider['updated_at'] ?? ($provider['created_at'] ?? date('Y-m-d H:i:s')),
        'account_type' => 'provider',
        'provider_status' => $provider['status'] ?? null,
        'is_approved' => strtolower((string) ($provider['status'] ?? 'pending')) === 'approved',
        'profile_completed' => $profileCompleted,
    ];
}

/**
 * Resolve if SMS should be sent via 4jawaly.
 */
function isSmsEnabled($settings, $smsConfig = null)
{
    $raw = strtolower(trim((string) ($settings['sms_enabled'] ?? '0')));
    $enabledFromSettings = in_array($raw, ['1', 'true', 'yes', 'on'], true);
    $config = is_array($smsConfig) ? $smsConfig : resolveSmsConfig($settings);
    $hasCredentials =
        !empty($config['api_key']) &&
        !empty($config['api_secret']) &&
        !empty($config['sender_id']);

    return $enabledFromSettings && $hasCredentials;
}

/**
 * Return fixed OTP as exactly 4 numeric digits.
 */
function resolveFixedOtp($settings)
{
    $digits = extractDigits((string) ($settings['fixed_otp'] ?? '1234'));

    if (strlen($digits) !== OTP_LENGTH) {
        return '1234';
    }

    return $digits;
}

/**
 * Normalize phone number for 4jawaly API (digits only with country code).
 */
function normalizePhoneFor4Jawaly($phone)
{
    $digits = extractDigits($phone);

    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    if (strlen($digits) === 10 && strpos($digits, '05') === 0) {
        $digits = '966' . substr($digits, 1);
    } elseif (strlen($digits) === 9 && strpos($digits, '5') === 0) {
        $digits = '966' . $digits;
    }

    return $digits;
}

/**
 * Build multiple phone formats to maximize provider compatibility.
 */
function build4JawalyNumberCandidates($phone)
{
    $normalized = normalizePhoneFor4Jawaly($phone);
    if ($normalized === '') {
        return [];
    }

    $candidates = [$normalized];

    // Some accounts/routes may require international prefix variants.
    $candidates[] = '+' . $normalized;
    $candidates[] = '00' . $normalized;

    // Local fallback for common country-code patterns.
    if (strlen($normalized) > 3) {
        if (strpos($normalized, '966') === 0) {
            $candidates[] = '0' . substr($normalized, 3);
        } elseif (strpos($normalized, '20') === 0) {
            $candidates[] = '0' . substr($normalized, 2);
        }
    }

    // Keep parity with client flow: retry over at most 4 delivery channels.
    $uniqueCandidates = array_values(array_unique(array_filter($candidates)));
    return array_slice($uniqueCandidates, 0, OTP_DELIVERY_CHANNELS_LIMIT);
}

/**
 * Send OTP to phone number
 */
function sendOTP($input, $settings)
{
    global $conn;

    $phone = normalizePhoneInput($input['phone'] ?? '');
    $accountType = resolveAccountType($input);
    $purpose = strtolower(trim((string) ($input['purpose'] ?? '')));
    $isNewProvider = false;

    if (empty($phone)) {
        sendError('رقم الهاتف مطلوب', 422);
    }

    if ($accountType === 'provider') {
        ensureProviderAuthSchema();
        $provider = getProviderByPhone($phone);

        if (!$provider) {
            if ($purpose === 'delete') {
                sendError('مقدم الخدمة غير موجود', 404);
            }
            $newProviderId = createProviderSkeleton($phone);
            if ($newProviderId <= 0) {
                sendError('تعذر إنشاء حساب مقدم الخدمة', 500);
            }
            $provider = getProviderByIdForAuth($newProviderId);
            $isNewProvider = true;
        }

        if ($purpose !== 'delete') {
            $providerStatus = strtolower(trim((string) ($provider['status'] ?? '')));
            if (providerStatusIsBlocked($providerStatus)) {
                sendError(getProviderStatusMessage($providerStatus), 403);
            }
        }
    }

    if ($accountType !== 'provider' && $purpose === 'delete') {
        $checkUser = $conn->prepare("SELECT id, is_active FROM users WHERE phone = ? LIMIT 1");
        $checkUser->bind_param("s", $phone);
        $checkUser->execute();
        $res = $checkUser->get_result();
        $userRow = $res ? $res->fetch_assoc() : null;
        if (!$userRow) {
            sendError('المستخدم غير موجود', 404);
        }
        if (isset($userRow['is_active']) && (int) $userRow['is_active'] !== 1) {
            sendError('تم حذف الحساب أو تعطيله', 403);
        }
    }

    $smsConfig = resolveSmsConfig($settings);
    $smsEnabled = isSmsEnabled($settings, $smsConfig);
    $fixedOtp = resolveFixedOtp($settings);

    $smsToggleRaw = strtolower(trim((string) ($settings['sms_enabled'] ?? '0')));
    $smsGatewayRequested = in_array($smsToggleRaw, ['1', 'true', 'yes', 'on'], true);
    if ($smsGatewayRequested && !$smsEnabled) {
        sendError('بيانات بوابة الرسائل غير مكتملة. تأكد من API Key و API Secret و Sender ID.', 422);
    }

    if (!$smsEnabled) {
        // Free/Simulation Mode
        $otp = $fixedOtp;

        // Return success with fixed OTP
        sendSuccess([
            'message' => 'تم إرسال رمز التحقق (وضع التجربة)',
            'otp' => $otp,
            'expires_in' => 300,
            'is_simulation' => true,
            'account_type' => $accountType,
            'is_new_provider' => $isNewProvider,
        ]);
        return;
    }

    // Real SMS Mode
    $otp = str_pad((string) random_int(0, (10 ** OTP_LENGTH) - 1), OTP_LENGTH, '0', STR_PAD_LEFT);

    if ($accountType === 'provider') {
        // Provider app uses the same OTP API but validates against providers table.
        $stmt = $conn->prepare("UPDATE providers SET otp_code = ? WHERE phone = ?");
        $stmt->bind_param("ss", $otp, $phone);
        $stmt->execute();
    } else {
        // Store OTP in users table for user app flows.
        $stmt = $conn->prepare("UPDATE users SET otp_code = ? WHERE phone = ?");
        $stmt->bind_param("ss", $otp, $phone);
        $stmt->execute();

        if ($purpose !== 'delete') {
            // Create lightweight unverified user row for new numbers.
            $checkUser = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            $checkUser->bind_param("s", $phone);
            $checkUser->execute();
            $res = $checkUser->get_result();
            if ($res->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO users (phone, otp_code, is_verified) VALUES (?, ?, 0)");
                $stmt->bind_param("ss", $phone, $otp);
                $stmt->execute();
            }
        }
    }

    // Send SMS via 4jawaly
    $result = send4JawalySMS($phone, $otp, $smsConfig);

    if ($result['success']) {
        sendSuccess([
            'message' => 'تم إرسال رمز التحقق SMS',
            'expires_in' => 300,
            'sms_sent' => true,
            'account_type' => $accountType,
            'is_new_provider' => $isNewProvider,
        ]);
    } else {
        // Return detailed error
        sendError('فشل إرسال SMS: ' . $result['error'], 500);
    }
}

function send4JawalySMS($to, $otp, array $smsConfig)
{
    $apiKey = trim((string) ($smsConfig['api_key'] ?? ''));
    $apiSecret = trim((string) ($smsConfig['api_secret'] ?? ''));
    $senderId = trim((string) ($smsConfig['sender_id'] ?? ''));
    $apiUrl = trim((string) ($smsConfig['api_url'] ?? ''));
    if ($apiUrl === '') {
        $apiUrl = FOURJAWALY_API_URL;
    }
    $appName = trim((string) ($smsConfig['app_name'] ?? 'Darfix'));
    if ($appName === '') {
        $appName = 'Darfix';
    }

    if ($apiKey === '' || $apiSecret === '' || $senderId === '') {
        return ['success' => false, 'error' => '4jawaly credentials are missing'];
    }

    $numbers = build4JawalyNumberCandidates($to);
    if (empty($numbers)) {
        return ['success' => false, 'error' => 'Invalid phone number'];
    }

    $headers = [
        'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret),
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $lastError = 'تعذر إرسال الرسالة النصية';
    foreach ($numbers as $candidate) {
        $payload = [
            'from_site' => true,
            'app' => '4jawaly',
            'ver' => '17.0',
            'messages' => [
                [
                    'text' => "<#> رمز التحقق الخاص بك في تطبيق {$appName} هو: $otp",
                    'numbers' => [$candidate],
                    'sender' => $senderId,
                ],
            ],
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $rawResponse = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        unset($ch);

        if ($curlError) {
            $lastError = 'Curl Error: ' . $curlError;
            continue;
        }

        $json = json_decode((string) $rawResponse, true);
        $success = $httpCode >= 200 && $httpCode < 300;

        if ($success) {
            return ['success' => true, 'error' => ''];
        }

        $errorMsg = '';
        if (is_array($json)) {
            $errorMsg = trim((string) ($json['message'] ?? ($json['msg'] ?? ($json['error'] ?? ''))));
        }
        if ($errorMsg === '') {
            $errorMsg = '4jawaly HTTP ' . $httpCode;
        }

        // Keep trying other formats only for phone-validation failures.
        $lastError = $errorMsg;
        if (
            stripos($errorMsg, 'Invalid sender') !== false ||
            stripos($errorMsg, 'Invalid senders') !== false
        ) {
            $lastError = 'معرف المرسل غير معتمد في 4Jawaly. تأكد من تفعيل Sender ID.';
            break;
        }
        if (stripos($errorMsg, 'token') !== false && stripos($errorMsg, 'required') !== false) {
            $lastError = 'بيانات 4Jawaly غير صحيحة أو التوكن مفقود.';
            break;
        }
        if (stripos($errorMsg, 'No valid numbers found') === false) {
            break;
        }
    }

    if (stripos($lastError, 'No valid numbers found') !== false) {
        $lastError = 'رقم الهاتف غير مدعوم لدى مزود الرسائل الحالي. استخدم رقمًا سعوديًا أو فعّل الإرسال الدولي في 4jawaly.';
    }

    return ['success' => false, 'error' => $lastError];
}

/**
 * Verify OTP and login/register user
 */
function verifyOTP($input, $settings)
{
    global $conn;

    $phone = normalizePhoneInput($input['phone'] ?? '');
    $otp = extractDigits($input['otp'] ?? '');
    $accountType = resolveAccountType($input);
    $purpose = strtolower(trim((string) ($input['purpose'] ?? '')));

    if (empty($phone) || empty($otp)) {
        sendError('رقم الهاتف ورمز التحقق مطلوبان', 422);
    }

    if (strlen($otp) !== OTP_LENGTH) {
        sendError('رمز التحقق غير صحيح', 422);
    }

    $smsEnabled = isSmsEnabled($settings);
    $fixedOtp = resolveFixedOtp($settings);

    if ($accountType === 'provider') {
        ensureProviderAuthSchema();
        $isNewProvider = false;
        $provider = getProviderByPhone($phone);

        if (!$provider) {
            if ($purpose === 'delete') {
                sendError('مقدم الخدمة غير موجود', 404);
            }
            $newProviderId = createProviderSkeleton($phone);
            if ($newProviderId <= 0) {
                sendError('تعذر إنشاء حساب مقدم الخدمة', 500);
            }
            $provider = getProviderByIdForAuth($newProviderId);
            $isNewProvider = true;
        }

        if ($purpose !== 'delete') {
            $providerStatus = strtolower(trim((string) ($provider['status'] ?? '')));
            if (providerStatusIsBlocked($providerStatus)) {
                sendError(getProviderStatusMessage($providerStatus), 403);
            }
        }

        if (!$smsEnabled) {
            if ($otp !== $fixedOtp) {
                sendError('رمز التحقق غير صحيح', 422);
            }
        } else {
            $storedOtp = extractDigits($provider['otp_code'] ?? '');
            if ($storedOtp === '') {
                sendError('انتهت صلاحية رمز التحقق، اطلب رمزاً جديداً', 422);
            }
            if (!hash_equals($storedOtp, $otp)) {
                sendError('رمز التحقق غير صحيح', 422);
            }
        }

        if ($smsEnabled && providerColumnExists('otp_code')) {
            $clearStmt = $conn->prepare("UPDATE providers SET otp_code = NULL WHERE id = ?");
            $clearStmt->bind_param("i", $provider['id']);
            $clearStmt->execute();
        }

        $providerUpdates = [];
        $providerUpdateTypes = '';
        $providerUpdateValues = [];
        if (providerColumnExists('last_login')) {
            $providerUpdates[] = "last_login = NOW()";
        }
        if (providerColumnExists('is_verified')) {
            $providerUpdates[] = "is_verified = 1";
        }
        if (!empty($providerUpdates)) {
            $sql = "UPDATE providers SET " . implode(', ', $providerUpdates) . " WHERE id = ?";
            $updateStmt = $conn->prepare($sql);
            if ($updateStmt) {
                $providerUpdateTypes .= 'i';
                $providerUpdateValues[] = (int) $provider['id'];
                $updateStmt->bind_param($providerUpdateTypes, ...$providerUpdateValues);
                $updateStmt->execute();
            }
        }

        $profileCompleted = syncProviderProfileCompletion((int) $provider['id']);
        $provider = getProviderByIdForAuth((int) $provider['id']) ?: $provider;

        $token = generateJWT($provider['id'], 'provider');
        sendSuccess([
            'token' => $token,
            'user' => formatProviderForAuth($provider),
            'is_new_user' => $isNewProvider,
            'needs_profile_completion' => !$profileCompleted,
            'account_type' => 'provider',
        ]);
    }

    if (!$smsEnabled) {
        // Validate against fixed OTP
        if ($otp !== $fixedOtp) {
            sendError('رمز التحقق غير صحيح', 422);
        }
    } else {
        // Validate against generated OTP from DB (strict validation).
        $stmt = $conn->prepare("SELECT otp_code FROM users WHERE phone = ? LIMIT 1");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $res = $stmt->get_result();

        $u = $res ? $res->fetch_assoc() : null;
        if (!$u) {
            sendError('يرجى طلب رمز تحقق جديد', 422);
        }

        $storedOtp = extractDigits($u['otp_code'] ?? '');
        if ($storedOtp === '') {
            sendError('انتهت صلاحية رمز التحقق، اطلب رمزاً جديداً', 422);
        }

        if (!hash_equals($storedOtp, $otp)) {
            sendError('رمز التحقق غير صحيح', 422);
        }
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && isset($user['is_active']) && (int) $user['is_active'] !== 1) {
        sendError('تم حذف الحساب أو تعطيله. الرجاء إنشاء حساب جديد.', 403);
    }

    $isNewUser = false;

    if (!$user) {
        if ($purpose === 'delete') {
            sendError('المستخدم غير موجود', 404);
        }
        // Check if registration is allowed
        $allowRegistration = isset($settings['allow_registration']) ? $settings['allow_registration'] == '1' : true;

        if (!$allowRegistration) {
            sendError('التسجيل الجديد مغلق حالياً', 403);
        }

        // Create new user
        $referralCode = generateReferralCode();
        $stmt = $conn->prepare("INSERT INTO users (phone, referral_code, is_verified) VALUES (?, ?, 1)");
        $stmt->bind_param("ss", $phone, $referralCode);
        $stmt->execute();

        $userId = $conn->insert_id;
        $isNewUser = true;

        // Fetch the new user
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    }

    // Ensure verified users always have is_verified = 1 and a referral code.
    if ($user) {
        $needsVerifyFlag = (int) ($user['is_verified'] ?? 0) !== 1;
        $needsReferralCode = empty($user['referral_code']);

        if ($needsVerifyFlag || $needsReferralCode) {
            if ($needsVerifyFlag && $needsReferralCode) {
                $referralCode = generateReferralCode();
                $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, referral_code = ? WHERE id = ?");
                $updateStmt->bind_param("si", $referralCode, $user['id']);
                $updateStmt->execute();
            } elseif ($needsVerifyFlag) {
                $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $user['id']);
                $updateStmt->execute();
            } else {
                $referralCode = generateReferralCode();
                $updateStmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
                $updateStmt->bind_param("si", $referralCode, $user['id']);
                $updateStmt->execute();
            }

            // Refresh user after patching required profile fields.
            $refreshStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $refreshStmt->bind_param("i", $user['id']);
            $refreshStmt->execute();
            $refreshResult = $refreshStmt->get_result();
            $user = $refreshResult->fetch_assoc() ?: $user;
        }
    }

    // Clear OTP after successful usage
    if ($smsEnabled && !$isNewUser) {
        $updateStmt = $conn->prepare("UPDATE users SET otp_code = NULL WHERE id = ?");
        $updateStmt->bind_param("i", $user['id']);
        $updateStmt->execute();
    }

    // Update last login
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();

    // Generate JWT token
    $token = generateJWT($user['id'], 'user');

    $profileCompleted = computeUserProfileCompletion($user);

    sendSuccess([
        'token' => $token,
        'user' => formatUser($user),
        'is_new_user' => $isNewUser,
        'needs_profile_completion' => !$profileCompleted,
        'account_type' => 'user',
    ]);
}

/**
 * Register user with details
 */
function registerUser($input)
{
    global $conn;

    $accountType = resolveAccountType($input);
    $phone = trim((string) ($input['phone'] ?? ''));
    $fullName = trim((string) ($input['full_name'] ?? ''));
    $emailRaw = trim((string) ($input['email'] ?? ''));
    $email = $emailRaw !== '' ? $emailRaw : null;

    if (empty($phone) || empty($fullName)) {
        sendError('الاسم ورقم الهاتف مطلوبان', 422);
    }

    if ($accountType === 'provider') {
        ensureProviderAuthSchema();

        $provider = getProviderByPhone($phone);
        $isNewProvider = false;

        if (!$provider) {
            $providerId = createProviderSkeleton($phone);
            if ($providerId <= 0) {
                sendError('تعذر إنشاء حساب مقدم الخدمة', 500);
            }
            $provider = getProviderByIdForAuth($providerId);
            $isNewProvider = true;
        }

        if (!$provider) {
            sendError('تعذر تحميل بيانات مقدم الخدمة', 500);
        }

        $providerStatus = strtolower(trim((string) ($provider['status'] ?? '')));
        if (providerStatusIsBlocked($providerStatus)) {
            sendError(getProviderStatusMessage($providerStatus), 403);
        }

        $providerId = (int) ($provider['id'] ?? 0);
        if ($providerId <= 0) {
            sendError('بيانات مقدم الخدمة غير صالحة', 500);
        }

        $stmt = $conn->prepare("UPDATE providers SET full_name = ?, email = ?, is_verified = 1, last_login = NOW() WHERE id = ?");
        if (!$stmt) {
            sendError('تعذر تحديث بيانات مقدم الخدمة', 500);
        }
        $stmt->bind_param("ssi", $fullName, $email, $providerId);
        $stmt->execute();

        $profileCompleted = syncProviderProfileCompletion($providerId);
        $provider = getProviderByIdForAuth($providerId);
        if (!$provider) {
            sendError('تعذر تحميل بيانات مقدم الخدمة', 500);
        }

        $token = generateJWT($providerId, 'provider');

        sendSuccess([
            'token' => $token,
            'user' => formatProviderForAuth($provider),
            'is_new_user' => $isNewProvider,
            'needs_profile_completion' => !$profileCompleted,
            'account_type' => 'provider',
        ]);
        return;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Update existing user
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $fullName, $email, $user['id']);
        $stmt->execute();
        $userId = $user['id'];
    } else {
        // Create new user
        $referralCode = generateReferralCode();
        $stmt = $conn->prepare("INSERT INTO users (phone, full_name, email, referral_code, is_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $phone, $fullName, $email, $referralCode);
        $stmt->execute();
        $userId = $conn->insert_id;
    }

    // Fetch user
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Generate token
    $token = generateJWT($userId, 'user');

    $profileCompleted = computeUserProfileCompletion($user);

    sendSuccess([
        'token' => $token,
        'user' => formatUser($user),
        'needs_profile_completion' => !$profileCompleted,
        'account_type' => 'user',
    ]);
}

/**
 * Login with phone (for returning users)
 */
function loginUser($input)
{
    global $conn;

    $accountType = resolveAccountType($input);
    $phone = trim((string) ($input['phone'] ?? ''));

    if (empty($phone)) {
        sendError('رقم الهاتف مطلوب', 422);
    }

    if ($accountType === 'provider') {
        ensureProviderAuthSchema();
        $provider = getProviderByPhone($phone);

        if (!$provider) {
            sendError('مقدم الخدمة غير موجود', 404);
        }

        $providerStatus = strtolower(trim((string) ($provider['status'] ?? '')));
        if (providerStatusIsBlocked($providerStatus)) {
            sendError(getProviderStatusMessage($providerStatus), 403);
        }

        if (providerColumnExists('last_login')) {
            $stmt = $conn->prepare("UPDATE providers SET last_login = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $provider['id']);
                $stmt->execute();
            }
        }

        $profileCompleted = syncProviderProfileCompletion((int) $provider['id']);
        $provider = getProviderByIdForAuth((int) $provider['id']) ?: $provider;

        $token = generateJWT($provider['id'], 'provider');

        sendSuccess([
            'token' => $token,
            'user' => formatProviderForAuth($provider),
            'needs_profile_completion' => !$profileCompleted,
            'account_type' => 'provider',
        ]);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ? AND is_active = 1");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        sendError('المستخدم غير موجود', 404);
    }

    // Update last login
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();

    $token = generateJWT($user['id'], 'user');

    $profileCompleted = computeUserProfileCompletion($user);

    sendSuccess([
        'token' => $token,
        'user' => formatUser($user),
        'needs_profile_completion' => !$profileCompleted,
        'account_type' => 'user',
    ]);
}

/**
 * Refresh token
 */
function refreshToken($input)
{
    $token = $input['token'] ?? '';

    if (empty($token)) {
        sendError('Token required', 422);
    }

    $payload = verifyJWT($token);
    if (!$payload) {
        sendError('Invalid token', 401);
    }

    $newToken = generateJWT($payload['user_id'], $payload['type']);

    sendSuccess([
        'token' => $newToken
    ]);
}

/**
 * Generate unique referral code
 */
function generateReferralCode()
{
    global $conn;

    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxAttempts = 20;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $candidate = 'ERT' . $code;

        if (!$conn) {
            return $candidate;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
        if (!$stmt) {
            return $candidate;
        }

        $stmt->bind_param("s", $candidate);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            return $candidate;
        }
    }

    // Ultra-rare fallback.
    try {
        return 'ERT' . strtoupper(bin2hex(random_bytes(4)));
    } catch (Throwable $e) {
        // Last deterministic fallback if random_bytes is unavailable.
        return 'ERT' . strtoupper(substr(md5((string) microtime(true)), 0, 8));
    }
}

/**
 * Format user data for response
 */
function formatUser($user)
{
    $profileCompleted = computeUserProfileCompletion($user);

    return [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'] ?? '',
        'phone' => $user['phone'],
        'email' => $user['email'],
        'avatar' => $user['avatar'],
        'wallet_balance' => (float) ($user['wallet_balance'] ?? 0),
        'points' => (int) ($user['points'] ?? 0),
        'membership_level' => $user['membership_level'] ?? 'silver',
        'referral_code' => $user['referral_code'],
        'is_active' => (bool) $user['is_active'],
        'is_verified' => (bool) $user['is_verified'],
        'created_at' => $user['created_at'],
        'profile_completed' => $profileCompleted,
        'needs_profile_completion' => !$profileCompleted,
    ];
}

function computeUserProfileCompletion($user)
{
    if (!$user || !is_array($user)) {
        return false;
    }

    $fullName = trim((string) ($user['full_name'] ?? ''));
    $avatar = trim((string) ($user['avatar'] ?? ''));

    if ($fullName === '') {
        return false;
    }
    if (isMissingAvatarValue($avatar, 'default-user.png')) {
        return false;
    }

    return true;
}
