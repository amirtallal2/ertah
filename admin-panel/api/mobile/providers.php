<?php
/**
 * Mobile API - Providers
 * إدارة بيانات مقدمي الخدمات
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

$action = $_GET['action'] ?? 'register';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'register':
        registerProvider($input);
        break;
    case 'status':
        checkProviderStatus();
        break;
    case 'profile':
        if ($method === 'GET') {
            getProviderProfile();
        } else {
            updateProviderProfile($input);
        }
        break;
    case 'availability':
        updateAvailability($input);
        break;
    case 'categories':
        getAvailableCategories();
        break;
    default:
        sendError('Invalid action', 400);
}

function providerColumnExists($column)
{
    global $conn;
    $safeColumn = $conn->real_escape_string((string) $column);
    $result = $conn->query("SHOW COLUMNS FROM `providers` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function providerApiTableExists($tableName)
{
    global $conn;
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
    if ($safeTable === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result && $result->num_rows > 0;
}

function ensureProviderProfileSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!providerApiTableExists('providers')) {
        sendError('جدول مقدمي الخدمات غير موجود', 500);
    }

    if (!providerColumnExists('is_verified')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!providerColumnExists('last_login')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `last_login` DATETIME NULL");
    }
    if (!providerColumnExists('otp_code')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `otp_code` VARCHAR(10) NULL");
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
    if (!providerColumnExists('ajeer_certificate_path')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `ajeer_certificate_path` VARCHAR(255) NULL");
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

function boolFromMixed($value)
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function parseCategoryIds($raw)
{
    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = explode(',', $raw);
            }
        } else {
            $raw = explode(',', $raw);
        }
    }

    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $item) {
        $id = (int) $item;
        if ($id !== 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function providerSpecialCategories()
{
    global $conn;

    $rows = [];

    if (providerApiTableExists('furniture_services')) {
        $result = $conn->query("SELECT COUNT(*) AS total FROM furniture_services WHERE is_active = 1");
        $count = $result ? (int) (($result->fetch_assoc()['total'] ?? 0)) : 0;
        if ($count > 0) {
            $rows[] = [
                'id' => -101,
                'parent_id' => null,
                'name_ar' => 'نقل العفش',
                'name_en' => 'Furniture Moving',
                'icon' => '🚚',
                'sort_order' => 9001,
                'special_module' => 'furniture_moving',
            ];
        }
    }

    if (providerApiTableExists('container_services')) {
        $result = $conn->query("SELECT COUNT(*) AS total FROM container_services WHERE is_active = 1");
        $count = $result ? (int) (($result->fetch_assoc()['total'] ?? 0)) : 0;
        if ($count > 0) {
            $rows[] = [
                'id' => -102,
                'parent_id' => null,
                'name_ar' => 'الحاويات',
                'name_en' => 'Containers',
                'icon' => '📦',
                'sort_order' => 9002,
                'special_module' => 'container_rental',
            ];
        }
    }

    return $rows;
}

function providerSpecialCategoryIds()
{
    $ids = [];
    foreach (providerSpecialCategories() as $category) {
        $id = (int) ($category['id'] ?? 0);
        if ($id !== 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
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

function getProviderById($providerId)
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

function getProviderCategoryIds($providerId)
{
    global $conn;

    $providerId = (int) $providerId;
    if ($providerId <= 0 || !providerApiTableExists('provider_services')) {
        return [];
    }

    $stmt = $conn->prepare("SELECT category_id FROM provider_services WHERE provider_id = ?");
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

function keepOnlyExistingCategoryIds($categoryIds)
{
    global $conn;

    if (empty($categoryIds)) {
        return [];
    }

    $uniqueIds = array_values(array_unique(array_map('intval', $categoryIds)));
    $dbCategoryIds = [];
    $specialCategoryIds = [];

    foreach ($uniqueIds as $id) {
        if ($id > 0) {
            $dbCategoryIds[] = $id;
        } elseif ($id < 0) {
            $specialCategoryIds[] = $id;
        }
    }

    $valid = [];

    if (!empty($dbCategoryIds) && providerApiTableExists('service_categories')) {
        $placeholders = implode(',', array_fill(0, count($dbCategoryIds), '?'));
        $types = str_repeat('i', count($dbCategoryIds));
        $sql = "SELECT id FROM service_categories WHERE is_active = 1 AND id IN ($placeholders)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$dbCategoryIds);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $valid[] = $id;
                }
            }
        }
    }

    if (!empty($specialCategoryIds)) {
        $allowedSpecialMap = [];
        foreach (providerSpecialCategoryIds() as $specialId) {
            $allowedSpecialMap[(int) $specialId] = true;
        }
        foreach ($specialCategoryIds as $specialId) {
            if (!empty($allowedSpecialMap[(int) $specialId])) {
                $valid[] = (int) $specialId;
            }
        }
    }

    return array_values(array_unique($valid));
}

function replaceProviderCategories($providerId, $categoryIds)
{
    global $conn;

    $providerId = (int) $providerId;
    if ($providerId <= 0) {
        return [];
    }

    $validCategoryIds = keepOnlyExistingCategoryIds($categoryIds);

    $deleteStmt = $conn->prepare("DELETE FROM provider_services WHERE provider_id = ?");
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $providerId);
        $deleteStmt->execute();
    }

    if (empty($validCategoryIds)) {
        return [];
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO provider_services (provider_id, category_id) VALUES (?, ?)"
    );
    if (!$insertStmt) {
        return [];
    }

    foreach ($validCategoryIds as $categoryId) {
        $insertStmt->bind_param("ii", $providerId, $categoryId);
        $insertStmt->execute();
    }

    return $validCategoryIds;
}

function computeProviderProfileCompleted($provider, $categoryIds = null)
{
    if (!$provider || !is_array($provider)) {
        return false;
    }

    $fullName = trim((string) ($provider['full_name'] ?? ''));
    $avatar = trim((string) ($provider['avatar'] ?? ''));
    $residencyDocument = trim((string) ($provider['residency_document_path'] ?? ''));
    $providerCategoryIds = is_array($categoryIds) ? $categoryIds : getProviderCategoryIds((int) ($provider['id'] ?? 0));

    if ($fullName === '' || $fullName === 'مقدم خدمة جديد') {
        return false;
    }
    if (isProviderAvatarMissing($avatar)) {
        return false;
    }
    if (isProviderDocumentMissing($residencyDocument)) {
        return false;
    }
    if (empty($providerCategoryIds)) {
        return false;
    }

    return true;
}

function isProviderAvatarMissing($avatarValue)
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
    $defaultFile = 'default-provider.png';
    if ($normalized === $defaultFile) {
        return true;
    }

    $suffix = '/' . $defaultFile;
    if (strlen($normalized) >= strlen($suffix) && substr($normalized, -strlen($suffix)) === $suffix) {
        return true;
    }

    return false;
}

function isProviderDocumentMissing($documentValue)
{
    $document = trim((string) $documentValue);
    if ($document === '') {
        return true;
    }

    $lowered = strtolower($document);
    if (in_array($lowered, ['null', 'undefined', 'nan'], true)) {
        return true;
    }

    return false;
}

function syncProfileCompletion($providerId)
{
    global $conn;

    $provider = getProviderById((int) $providerId);
    if (!$provider) {
        return false;
    }
    $categoryIds = getProviderCategoryIds((int) $provider['id']);
    $isCompleted = computeProviderProfileCompleted($provider, $categoryIds);

    $flag = $isCompleted ? 1 : 0;
    $stmt = $conn->prepare("UPDATE providers SET profile_completed = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $flag, $provider['id']);
        $stmt->execute();
    }

    return $isCompleted;
}

function normalizeProvider($provider)
{
    $providerId = (int) ($provider['id'] ?? 0);
    $categoryIds = getProviderCategoryIds($providerId);
    $isCompleted = computeProviderProfileCompleted($provider, $categoryIds);

    return [
        'id' => $providerId,
        'full_name' => $provider['full_name'] ?? '',
        'phone' => $provider['phone'] ?? '',
        'whatsapp_number' => $provider['whatsapp_number'] ?? ($provider['phone'] ?? ''),
        'email' => $provider['email'] ?? null,
        'avatar' => $provider['avatar'] ?? null,
        'residency_document_path' => $provider['residency_document_path'] ?? null,
        'residency_document' => $provider['residency_document_path'] ?? null,
        'ajeer_certificate_path' => $provider['ajeer_certificate_path'] ?? null,
        'ajeer_certificate' => $provider['ajeer_certificate_path'] ?? null,
        'status' => $provider['status'] ?? 'pending',
        'is_approved' => strtolower((string) ($provider['status'] ?? 'pending')) === 'approved',
        'is_available' => isset($provider['is_available']) ? ((int) $provider['is_available'] === 1) : false,
        'country' => $provider['country'] ?? null,
        'city' => $provider['city'] ?? null,
        'district' => $provider['district'] ?? null,
        'location_address' => $provider['location_address'] ?? null,
        'current_lat' => isset($provider['current_lat']) && $provider['current_lat'] !== null ? (float) $provider['current_lat'] : null,
        'current_lng' => isset($provider['current_lng']) && $provider['current_lng'] !== null ? (float) $provider['current_lng'] : null,
        'location_updated_at' => $provider['location_updated_at'] ?? null,
        'bio' => $provider['bio'] ?? null,
        'experience_years' => (int) ($provider['experience_years'] ?? 0),
        'category_ids' => $categoryIds,
        'categories_locked' => isset($provider['categories_locked']) ? ((int) $provider['categories_locked'] === 1) : !empty($categoryIds),
        'wallet_balance' => (float) ($provider['wallet_balance'] ?? 0),
        'rating' => (float) ($provider['rating'] ?? 0),
        'profile_completed' => $isCompleted,
        'needs_profile_completion' => !$isCompleted,
        'created_at' => $provider['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $provider['updated_at'] ?? ($provider['created_at'] ?? date('Y-m-d H:i:s')),
    ];
}

function hasInputValue($input, $key)
{
    return is_array($input) && array_key_exists($key, $input);
}

function uploadProviderAvatar($file)
{
    $uploadDir = __DIR__ . '/../../uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['success' => false, 'message' => 'صيغة الصورة غير مدعومة'];
    }

    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم الصورة يجب أن يكون أقل من 5MB'];
    }

    $filename = uniqid('provider_', true) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'تعذر حفظ الصورة'];
    }

    return ['success' => true, 'path' => 'uploads/avatars/' . $filename];
}

function uploadProviderResidencyDocument($file)
{
    $uploadDir = __DIR__ . '/../../uploads/provider-documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
        return ['success' => false, 'message' => 'صيغة مستند الإقامة غير مدعومة'];
    }

    if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم مستند الإقامة يجب أن يكون أقل من 8MB'];
    }

    $filename = uniqid('provider_residency_', true) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'تعذر حفظ مستند الإقامة'];
    }

    return ['success' => true, 'path' => 'uploads/provider-documents/' . $filename];
}

function uploadProviderAjeerCertificate($file)
{
    $uploadDir = __DIR__ . '/../../uploads/provider-documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
        return ['success' => false, 'message' => 'صيغة شهادة أجير غير مدعومة'];
    }

    if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم شهادة أجير يجب أن يكون أقل من 8MB'];
    }

    $filename = uniqid('provider_ajeer_', true) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'تعذر حفظ شهادة أجير'];
    }

    return ['success' => true, 'path' => 'uploads/provider-documents/' . $filename];
}

function sameIntegerArrayValues($left, $right)
{
    $leftValues = array_values(array_unique(array_map('intval', is_array($left) ? $left : [])));
    $rightValues = array_values(array_unique(array_map('intval', is_array($right) ? $right : [])));
    sort($leftValues);
    sort($rightValues);
    return $leftValues === $rightValues;
}

function requireProviderAuthId()
{
    $authId = requireAuth();
    $role = getAuthRole();
    if ($role !== 'provider') {
        sendError('Unauthorized', 403);
    }
    return (int) $authId;
}

/**
 * Register as provider (legacy endpoint compatibility)
 */
function registerProvider($input)
{
    global $conn;

    ensureProviderProfileSchema();

    $phone = trim((string) ($input['phone'] ?? ''));
    $fullName = trim((string) ($input['full_name'] ?? 'مقدم خدمة جديد'));
    $email = trim((string) ($input['email'] ?? ''));

    if ($phone === '') {
        sendError('رقم الهاتف مطلوب', 422);
    }

    $provider = getProviderByPhone($phone);
    if ($provider) {
        sendSuccess([
            'id' => (int) $provider['id'],
            'status' => $provider['status'] ?? 'pending',
            'is_new_provider' => false,
        ], 'الحساب موجود بالفعل');
    }

    $status = 'pending';
    $availability = 0;
    $verified = 1;
    $profileCompleted = 0;
    $emailValue = $email !== '' ? $email : null;

    $stmt = $conn->prepare(
        "INSERT INTO providers (full_name, phone, email, status, is_available, is_verified, profile_completed)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        sendError('تعذر إنشاء مقدم الخدمة', 500);
    }
    $stmt->bind_param(
        "ssssiii",
        $fullName,
        $phone,
        $emailValue,
        $status,
        $availability,
        $verified,
        $profileCompleted
    );
    $stmt->execute();

    sendSuccess([
        'id' => (int) $conn->insert_id,
        'status' => $status,
        'is_new_provider' => true,
    ], 'تم إنشاء حساب مقدم الخدمة بنجاح');
}

function checkProviderStatus()
{
    $phone = trim((string) ($_GET['phone'] ?? ''));
    ensureProviderProfileSchema();

    if ($phone === '') {
        sendError('رقم الهاتف مطلوب', 422);
    }

    $provider = getProviderByPhone($phone);
    if (!$provider) {
        sendError('لم يتم العثور على حساب مقدم الخدمة', 404);
    }

    $isCompleted = syncProfileCompletion((int) $provider['id']);
    $provider = getProviderById((int) $provider['id']) ?: $provider;

    $statusMessages = [
        'pending' => 'الحساب قيد مراجعة الإدارة',
        'approved' => 'الحساب جاهز لاستقبال الطلبات',
        'rejected' => 'مرفوض',
        'suspended' => 'موقوف',
    ];

    sendSuccess([
        'id' => (int) $provider['id'],
        'full_name' => $provider['full_name'] ?? '',
        'status' => $provider['status'] ?? 'pending',
        'status_message' => $statusMessages[$provider['status'] ?? 'pending'] ?? ($provider['status'] ?? 'pending'),
        'profile_completed' => $isCompleted,
        'needs_profile_completion' => !$isCompleted,
        'registered_at' => $provider['created_at'] ?? null,
    ]);
}

function getProviderProfile()
{
    ensureProviderProfileSchema();
    $providerId = requireProviderAuthId();

    $provider = getProviderById($providerId);
    if (!$provider) {
        sendError('Provider not found', 404);
    }

    syncProfileCompletion($providerId);
    $provider = getProviderById($providerId) ?: $provider;

    sendSuccess(normalizeProvider($provider));
}

function updateProviderProfile($input)
{
    global $conn;

    ensureProviderProfileSchema();
    $providerId = requireProviderAuthId();

    $provider = getProviderById($providerId);
    if (!$provider) {
        sendError('Provider not found', 404);
    }

    $updates = [];
    $types = '';
    $values = [];

    if (hasInputValue($input, 'full_name')) {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $updates[] = 'full_name = ?';
        $types .= 's';
        $values[] = $fullName;
    }

    if (hasInputValue($input, 'email')) {
        $email = trim((string) ($input['email'] ?? ''));
        $emailValue = $email !== '' ? $email : null;
        $updates[] = 'email = ?';
        $types .= 's';
        $values[] = $emailValue;
    }

    if (hasInputValue($input, 'whatsapp_number')) {
        $whatsapp = trim((string) ($input['whatsapp_number'] ?? ''));
        $whatsappValue = $whatsapp !== '' ? $whatsapp : null;
        $updates[] = 'whatsapp_number = ?';
        $types .= 's';
        $values[] = $whatsappValue;
    }

    if (hasInputValue($input, 'city')) {
        $city = trim((string) ($input['city'] ?? ''));
        $updates[] = 'city = ?';
        $types .= 's';
        $values[] = $city;
    }

    if (hasInputValue($input, 'country')) {
        $country = trim((string) ($input['country'] ?? ''));
        $updates[] = 'country = ?';
        $types .= 's';
        $values[] = $country;
    }

    if (hasInputValue($input, 'district')) {
        $district = trim((string) ($input['district'] ?? ''));
        $updates[] = 'district = ?';
        $types .= 's';
        $values[] = $district;
    }

    if (hasInputValue($input, 'location_address')) {
        $locationAddress = trim((string) ($input['location_address'] ?? ''));
        $updates[] = 'location_address = ?';
        $types .= 's';
        $values[] = $locationAddress;
    }

    $hasLatInput = hasInputValue($input, 'lat');
    $hasLngInput = hasInputValue($input, 'lng');
    if ($hasLatInput xor $hasLngInput) {
        sendError('lat و lng يجب إرسالهما معًا', 422);
    }
    if ($hasLatInput && $hasLngInput) {
        $lat = (float) ($input['lat'] ?? 0);
        $lng = (float) ($input['lng'] ?? 0);
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            sendError('إحداثيات الموقع غير صحيحة', 422);
        }
        $updates[] = 'current_lat = ?';
        $types .= 'd';
        $values[] = $lat;
        $updates[] = 'current_lng = ?';
        $types .= 'd';
        $values[] = $lng;
        $updates[] = 'location_updated_at = NOW()';
    }

    if (hasInputValue($input, 'bio')) {
        $bio = trim((string) ($input['bio'] ?? ''));
        $updates[] = 'bio = ?';
        $types .= 's';
        $values[] = $bio;
    }

    if (hasInputValue($input, 'experience_years')) {
        $experienceYears = max(0, (int) ($input['experience_years'] ?? 0));
        $updates[] = 'experience_years = ?';
        $types .= 'i';
        $values[] = $experienceYears;
    }

    if (hasInputValue($input, 'is_available')) {
        $isAvailable = boolFromMixed($input['is_available']) ? 1 : 0;
        $updates[] = 'is_available = ?';
        $types .= 'i';
        $values[] = $isAvailable;
    }

    $hasAvatarUpload = isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasResidencyUpload = isset($_FILES['residency_document']) && (int) ($_FILES['residency_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasAjeerUpload = isset($_FILES['ajeer_certificate']) && (int) ($_FILES['ajeer_certificate']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasCategoryInput = hasInputValue($input, 'category_ids');

    if (empty($updates) && !$hasAvatarUpload && !$hasResidencyUpload && !$hasAjeerUpload && !$hasCategoryInput) {
        sendSuccess(normalizeProvider($provider), 'لا توجد تغييرات للحفظ');
    }

    if (!empty($updates)) {
        $values[] = $providerId;
        $types .= 'i';
        $sql = "UPDATE providers SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            sendError('تعذر تحديث بيانات مقدم الخدمة', 500);
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }

    if ($hasAvatarUpload) {
        $upload = uploadProviderAvatar($_FILES['avatar']);
        if (!$upload['success']) {
            sendError($upload['message'], 422);
        }
        $avatarPath = $upload['path'];
        $stmt = $conn->prepare("UPDATE providers SET avatar = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $avatarPath, $providerId);
            $stmt->execute();
        }
    }

    if ($hasResidencyUpload) {
        $upload = uploadProviderResidencyDocument($_FILES['residency_document']);
        if (!$upload['success']) {
            sendError($upload['message'], 422);
        }
        $docPath = $upload['path'];
        $stmt = $conn->prepare("UPDATE providers SET residency_document_path = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $docPath, $providerId);
            $stmt->execute();
        }
    }

    if ($hasAjeerUpload) {
        $upload = uploadProviderAjeerCertificate($_FILES['ajeer_certificate']);
        if (!$upload['success']) {
            sendError($upload['message'], 422);
        }
        $docPath = $upload['path'];
        $stmt = $conn->prepare("UPDATE providers SET ajeer_certificate_path = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $docPath, $providerId);
            $stmt->execute();
        }
    }

    if (hasInputValue($input, 'category_ids')) {
        $currentCategoryIds = getProviderCategoryIds($providerId);
        $requestedCategoryIds = keepOnlyExistingCategoryIds(parseCategoryIds($input['category_ids']));
        $categoriesLocked = ((int) ($provider['categories_locked'] ?? 0) === 1) || !empty($currentCategoryIds);

        if ($categoriesLocked && !sameIntegerArrayValues($requestedCategoryIds, $currentCategoryIds)) {
            sendError('لا يمكن تعديل التخصصات بعد حفظها. تواصل مع الإدارة للتعديل.', 422);
        }

        if (!$categoriesLocked) {
            $savedCategoryIds = replaceProviderCategories($providerId, $requestedCategoryIds);
            if (!empty($savedCategoryIds)) {
                $lockStmt = $conn->prepare("UPDATE providers SET categories_locked = 1 WHERE id = ?");
                if ($lockStmt) {
                    $lockStmt->bind_param("i", $providerId);
                    $lockStmt->execute();
                }
            }
        }
    }

    $isCompleted = syncProfileCompletion($providerId);
    $provider = getProviderById($providerId);

    if (!$provider) {
        sendError('Provider not found', 404);
    }

    sendSuccess(
        normalizeProvider($provider),
        $isCompleted ? 'تم استكمال الملف الشخصي بنجاح' : 'تم تحديث الملف الشخصي'
    );
}

function updateAvailability($input)
{
    global $conn;

    ensureProviderProfileSchema();
    $providerId = requireProviderAuthId();
    $provider = getProviderById($providerId);
    if (!$provider) {
        sendError('Provider not found', 404);
    }

    $status = strtolower(trim((string) ($provider['status'] ?? 'pending')));
    if ($status !== 'approved') {
        sendError('لا يمكن استقبال الطلبات قبل اعتماد الحساب من الإدارة', 403);
    }

    if (!hasInputValue($input, 'is_available')) {
        sendError('is_available مطلوب', 422);
    }

    $isAvailable = boolFromMixed($input['is_available']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE providers SET is_available = ? WHERE id = ?");
    if (!$stmt) {
        sendError('تعذر تحديث حالة التوفر', 500);
    }
    $stmt->bind_param("ii", $isAvailable, $providerId);
    $stmt->execute();

    sendSuccess([
        'is_available' => $isAvailable === 1,
    ], $isAvailable === 1 ? 'تم تفعيل استقبال الطلبات' : 'تم إيقاف استقبال الطلبات');
}

function getAvailableCategories()
{
    global $conn;

    $rows = [];
    $rootOnly = isset($_GET['root_only']) && trim((string) $_GET['root_only']) === '1';
    if (providerApiTableExists('service_categories')) {
        $condition = $rootOnly ? "AND (parent_id IS NULL OR parent_id = 0)" : "";
        $stmt = $conn->prepare(
            "SELECT id, parent_id, name_ar, name_en, icon, sort_order
             FROM service_categories
             WHERE is_active = 1 {$condition}
             ORDER BY COALESCE(parent_id, id) ASC,
                      CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC,
                      sort_order ASC, id ASC"
        );
        if (!$stmt) {
            sendError('تعذر جلب الأقسام', 500);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'parent_id' => !empty($row['parent_id']) ? (int) $row['parent_id'] : null,
                'name_ar' => $row['name_ar'] ?? '',
                'name_en' => $row['name_en'] ?? null,
                'icon' => $row['icon'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'special_module' => null,
            ];
        }
    }

    $rows = array_merge($rows, providerSpecialCategories());
    usort($rows, fn($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

    sendSuccess($rows);
}
