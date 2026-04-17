<?php
/**
 * Mobile API - Users Endpoints
 * نقاط نهاية المستخدمين
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
if (!empty($_POST)) {
    $input = array_merge($input, $_POST);
}
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

ensureUsersMobileSchema();

switch ($action) {
    case 'profile':
        if ($method === 'GET') {
            getProfile();
        } else {
            updateProfile($input);
        }
        break;
    case 'addresses':
        if ($method === 'GET') {
            getAddresses();
        } elseif ($method === 'POST') {
            addAddress($input);
        } elseif ($method === 'PUT') {
            updateAddress($input);
        } elseif ($method === 'DELETE') {
            deleteAddress();
        }
        break;
    case 'wallet':
        getWallet();
        break;
    case 'transactions':
        getTransactions();
        break;
    case 'notifications':
        getNotifications();
        break;
    case 'device_token':
        updateDeviceToken($input);
        break;
    case 'delete_account':
        if ($method === 'POST') {
            deleteAccount($input);
        } else {
            sendError('Invalid method', 405);
        }
        break;
    default:
        sendError('Invalid action', 400);
}

function usersTableExists(): bool
{
    global $conn;
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $result = $conn->query("SHOW TABLES LIKE 'users'");
    $exists = (bool) ($result && $result->num_rows > 0);
    return $exists;
}

function userProfileColumnExists(string $column): bool
{
    global $conn;

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }

    if (!usersTableExists()) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM users LIKE '{$column}'");
    return (bool) ($result && $result->num_rows > 0);
}

function ensureUsersMobileSchema(): void
{
    global $conn;

    if (!usersTableExists()) {
        return;
    }

    $requiredColumns = [
        'membership_level' => "VARCHAR(50) DEFAULT 'silver'",
        'city' => 'VARCHAR(100) NULL',
        'country' => 'VARCHAR(100) NULL',
        'gender' => 'VARCHAR(20) NULL',
        'birth_date' => 'DATE NULL',
    ];

    foreach ($requiredColumns as $column => $definition) {
        if (!userProfileColumnExists($column)) {
            $conn->query("ALTER TABLE `users` ADD COLUMN `{$column}` {$definition}");
        }
    }

    if (!userProfileColumnExists('device_token')) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `device_token` VARCHAR(255) NULL");
    }

    $deletionColumns = [
        'deleted_at' => 'DATETIME NULL',
        'deletion_requested_at' => 'DATETIME NULL',
        'deletion_reason' => 'VARCHAR(255) NULL',
    ];

    foreach ($deletionColumns as $column => $definition) {
        if (!userProfileColumnExists($column)) {
            $conn->query("ALTER TABLE `users` ADD COLUMN `{$column}` {$definition}");
        }
    }

    if (usersApiTableExists('providers') && !usersApiTableColumnExists('providers', 'device_token')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `device_token` VARCHAR(255) NULL");
    }

    if (usersApiTableExists('providers')) {
        foreach ($deletionColumns as $column => $definition) {
            if (!usersApiTableColumnExists('providers', $column)) {
                $conn->query("ALTER TABLE `providers` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }

    if (usersApiTableExists('notifications')) {
        if (!usersApiTableColumnExists('notifications', 'provider_id')) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `provider_id` INT NULL");
        }
        if (!usersApiTableColumnExists('notifications', 'data')) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `data` LONGTEXT NULL");
        }

        $indexResult = $conn->query("SHOW INDEX FROM `notifications` WHERE Key_name = 'idx_notifications_provider'");
        $hasProviderIndex = (bool) ($indexResult && $indexResult->num_rows > 0);
        if (!$hasProviderIndex && usersApiTableColumnExists('notifications', 'provider_id')) {
            $conn->query("ALTER TABLE `notifications` ADD INDEX `idx_notifications_provider` (`provider_id`)");
        }
    }
}

function usersApiTableExists(string $table): bool
{
    global $conn;
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return (bool) ($result && $result->num_rows > 0);
}

function usersApiTableColumnExists(string $table, string $column): bool
{
    global $conn;

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }

    if (!usersApiTableExists($table)) {
        return false;
    }

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");

    return (bool) ($result && $result->num_rows > 0);
}

function ensureDeviceTokenColumn(string $table): bool
{
    global $conn;

    if (!in_array($table, ['users', 'providers'], true)) {
        return false;
    }

    if (!usersApiTableExists($table)) {
        return false;
    }

    if (usersApiTableColumnExists($table, 'device_token')) {
        return true;
    }

    $safeTable = $conn->real_escape_string($table);
    $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `device_token` VARCHAR(255) NULL");

    return usersApiTableColumnExists($table, 'device_token');
}

function notificationsColumnExists(string $column): bool
{
    global $conn;

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }

    if (!usersApiTableExists('notifications')) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `notifications` LIKE '{$column}'");
    return (bool) ($result && $result->num_rows > 0);
}

/**
 * Get user profile
 */
function getProfile()
{
    global $conn;

    $userId = requireAuth();

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        sendError('User not found', 404);
    }

    $profile = formatUser($user);

    if (usersApiTableExists('orders')) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS completed_orders_count FROM orders WHERE user_id = ? AND status = 'completed'");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $profile['completed_orders_count'] = (int) ($row['completed_orders_count'] ?? 0);
        }
    }

    if (!isset($profile['completed_orders_count'])) {
        $profile['completed_orders_count'] = 0;
    }
    $profile['rating'] = 5.0;
    $profile['is_premium'] = in_array(strtolower((string) ($profile['membership_level'] ?? 'silver')), ['gold', 'platinum', 'premium', 'vip'], true);

    sendSuccess($profile);
}

/**
 * Update user profile
 */
function updateProfile($input)
{
    global $conn;

    $userId = requireAuth();

    if (!is_array($input)) {
        $input = [];
    }

    $currentStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if (!$currentStmt) {
        sendError('تعذر تحميل بيانات المستخدم', 500);
    }
    $currentStmt->bind_param("i", $userId);
    $currentStmt->execute();
    $currentUser = $currentStmt->get_result()->fetch_assoc();

    if (!$currentUser) {
        sendError('User not found', 404);
    }

    $hasFullNameField = array_key_exists('full_name', $input);
    $hasEmailField = array_key_exists('email', $input);
    $hasPhoneField = array_key_exists('phone', $input);

    $fullName = trim((string) ($input['full_name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $currentFullName = trim((string) ($currentUser['full_name'] ?? ''));
    $currentEmail = trim((string) ($currentUser['email'] ?? ''));
    $currentPhone = trim((string) ($currentUser['phone'] ?? ''));

    $allowPhoneUpdate = filter_var($input['allow_phone_update'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $hasAvatarUpload = isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    $updates = [];
    $types = '';
    $values = [];

    if ($hasFullNameField && $fullName === '') {
        sendError('الاسم الكامل مطلوب', 422);
    }

    if ($fullName !== '' && $fullName !== $currentFullName) {
        if (utf8SafeLength($fullName) < 2) {
            sendError('الاسم قصير جدًا', 422);
        }
        $updates[] = 'full_name = ?';
        $types .= 's';
        $values[] = $fullName;
    }

    if (array_key_exists('membership_level', $input)) {
        $requestedMembership = strtolower(trim((string) ($input['membership_level'] ?? '')));
        $currentMembership = strtolower(trim((string) ($currentUser['membership_level'] ?? 'silver')));
        if ($requestedMembership !== '' && $requestedMembership !== $currentMembership) {
            sendError('لا يمكن تعديل مستوى العضوية من التطبيق', 403);
        }
    }

    if ($hasEmailField) {
        if ($email === '') {
            if ($currentEmail !== '') {
                $updates[] = 'email = ?';
                $types .= 's';
                $values[] = null;
            }
        } elseif (strcasecmp($email, $currentEmail) !== 0) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendError('صيغة البريد الإلكتروني غير صحيحة', 422);
            }

            $existsStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            if ($existsStmt) {
                $existsStmt->bind_param("si", $email, $userId);
                $existsStmt->execute();
                if ($existsStmt->get_result()->num_rows > 0) {
                    sendError('البريد الإلكتروني مستخدم بالفعل', 422);
                }
            }

            $updates[] = 'email = ?';
            $types .= 's';
            $values[] = $email;
        }
    }

    if ($allowPhoneUpdate && $hasPhoneField) {
        if ($phone === '') {
            sendError('رقم الجوال لا يمكن أن يكون فارغًا', 422);
        }

        if ($phone !== $currentPhone) {
            $phoneExistsStmt = $conn->prepare("SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1");
            if ($phoneExistsStmt) {
                $phoneExistsStmt->bind_param("si", $phone, $userId);
                $phoneExistsStmt->execute();
                if ($phoneExistsStmt->get_result()->num_rows > 0) {
                    sendError('رقم الجوال مستخدم بالفعل', 422);
                }
            }

            $updates[] = 'phone = ?';
            $types .= 's';
            $values[] = $phone;
        }
    }

    foreach (['city', 'country', 'gender', 'birth_date'] as $optionalColumn) {
        if (!array_key_exists($optionalColumn, $input)) {
            continue;
        }
        if (!userProfileColumnExists($optionalColumn)) {
            continue;
        }

        $rawValue = trim((string) ($input[$optionalColumn] ?? ''));
        $currentValue = trim((string) ($currentUser[$optionalColumn] ?? ''));
        if ($rawValue === $currentValue) {
            continue;
        }

        $updates[] = "{$optionalColumn} = ?";
        $types .= 's';
        $values[] = $rawValue !== '' ? $rawValue : null;
    }

    if (empty($updates) && !$hasAvatarUpload) {
        sendSuccess(formatUser($currentUser), 'لا توجد تغييرات للحفظ');
    }

    if (!empty($updates)) {
        $values[] = $userId;
        $types .= 'i';

        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }

    // Handle avatar upload
    if ($hasAvatarUpload) {
        $avatar = uploadUserFile($_FILES['avatar'], 'avatars');
        if ($avatar) {
            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->bind_param("si", $avatar, $userId);
            $stmt->execute();
        } else {
            sendError('تعذر رفع الصورة الشخصية', 422);
        }
    }

    // Fetch updated user
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    sendSuccess(formatUser($user), 'تم تحديث الملف الشخصي');
}

/**
 * Get user addresses
 */
function getAddresses()
{
    global $conn;

    $userId = requireAuth();

    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'label' => $row['label'],
            'address' => $row['address'],
            'details' => $row['details'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'is_default' => (bool) $row['is_default'],
            'created_at' => $row['created_at']
        ];
    }

    sendSuccess($addresses);
}

/**
 * Add new address
 */
function addAddress($input)
{
    global $conn;

    $userId = requireAuth();

    $type = $input['type'] ?? 'home';
    $label = $input['label'] ?? null;
    $address = $input['address'] ?? '';
    $details = $input['details'] ?? null;
    $lat = $input['lat'] ?? null;
    $lng = $input['lng'] ?? null;
    $isDefault = $input['is_default'] ?? false;

    if (empty($address)) {
        sendError('العنوان مطلوب', 422);
    }

    // If setting as default, unset other defaults
    if ($isDefault) {
        $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, type, label, address, details, lat, lng, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssddi", $userId, $type, $label, $address, $details, $lat, $lng, $isDefault);
    $stmt->execute();

    sendSuccess(['id' => $conn->insert_id], 'تم إضافة العنوان');
}

/**
 * Update address
 */
function updateAddress($input)
{
    global $conn;

    $userId = requireAuth();
    $addressId = $input['id'] ?? $_GET['id'] ?? 0;

    // Verify ownership
    $stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addressId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        sendError('Address not found', 404);
    }

    $updates = [];
    $types = '';
    $values = [];

    foreach (['type', 'label', 'address', 'details'] as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $types .= 's';
            $values[] = $input[$field];
        }
    }

    foreach (['lat', 'lng'] as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $types .= 'd';
            $values[] = $input[$field];
        }
    }

    if (isset($input['is_default']) && $input['is_default']) {
        $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $updates[] = "is_default = 1";
    }

    if (!empty($updates)) {
        $values[] = $addressId;
        $types .= 'i';

        $sql = "UPDATE user_addresses SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }

    sendSuccess(null, 'تم تحديث العنوان');
}

/**
 * Delete address
 */
function deleteAddress()
{
    global $conn;

    $userId = requireAuth();
    $addressId = $_GET['id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addressId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        sendError('Address not found', 404);
    }

    sendSuccess(null, 'تم حذف العنوان');
}

/**
 * Get wallet info
 */
function getWallet()
{
    global $conn;

    $userId = requireAuth();

    $stmt = $conn->prepare("SELECT wallet_balance, points, membership_level FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    sendSuccess([
        'balance' => (float) $user['wallet_balance'],
        'points' => (int) $user['points'],
        'membership_level' => $user['membership_level']
    ]);
}

/**
 * Get user transactions
 */
function getTransactions()
{
    global $conn;

    $userId = requireAuth();
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    $offset = ($page - 1) * $perPage;

    // Get total
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Get transactions
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $userId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'amount' => (float) $row['amount'],
            'balance_after' => (float) $row['balance_after'],
            'description' => $row['description'],
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }

    sendPaginated($transactions, $page, $perPage, $total);
}

/**
 * Get user notifications
 */
function getNotifications()
{
    global $conn;

    $userId = requireAuth();
    $accountType = strtolower((string) (getAuthRole() ?? 'user'));
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    $offset = ($page - 1) * $perPage;

    $targetColumn = 'user_id';
    if ($accountType === 'provider' && notificationsColumnExists('provider_id')) {
        $targetColumn = 'provider_id';
    }

    // Get total
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE {$targetColumn} = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Get notifications
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE {$targetColumn} = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $userId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $payload = null;
        if (array_key_exists('data', $row) && !empty($row['data'])) {
            $decoded = json_decode((string) $row['data'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $notifications[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'body' => $row['body'],
            'type' => $row['type'],
            'data' => $payload,
            'is_read' => (bool) $row['is_read'],
            'created_at' => $row['created_at']
        ];
    }

    sendPaginated($notifications, $page, $perPage, $total);
}

/**
 * Save mobile push token (OneSignal subscription id, etc.) for current user.
 */
function updateDeviceToken($input)
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }

    $userId = requireAuth();
    $accountType = strtolower((string) (getAuthRole() ?? 'user'));
    $targetTable = $accountType === 'provider' ? 'providers' : 'users';
    $token = trim((string) ($input['device_token'] ?? ''));

    if (!usersApiTableExists($targetTable)) {
        sendSuccess(null, 'تم تجاهل تحديث التوكن لعدم توفر جدول الحساب');
    }

    if (!ensureDeviceTokenColumn($targetTable)) {
        sendSuccess(null, 'تم تجاهل تحديث التوكن لعدم توفر الحقل');
    }

    if ($token === '') {
        $stmt = $conn->prepare("UPDATE {$targetTable} SET device_token = NULL WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        sendSuccess(null, 'تم حذف توكن الإشعارات');
    }

    if (utf8SafeLength($token) < 8) {
        sendError('device_token غير صالح', 422);
    }

    $stmt = $conn->prepare("UPDATE {$targetTable} SET device_token = ? WHERE id = ?");
    $stmt->bind_param("si", $token, $userId);
    $stmt->execute();

    sendSuccess([
        'device_token' => $token,
        'account_type' => $accountType === 'provider' ? 'provider' : 'user'
    ], 'تم تحديث توكن الإشعارات');
}

/**
 * Delete (anonymize) current account and remove personal data.
 */
function deleteAccount(array $input): void
{
    global $conn;

    $userId = requireAuth();
    $role = strtolower((string) (getAuthRole() ?? 'user'));

    $confirm = $input['confirm'] ?? null;
    $confirmed = $confirm === true || $confirm === 1 || $confirm === '1' || $confirm === 'true';
    if (!$confirmed) {
        sendError('Confirmation required', 422);
    }

    $reason = trim((string) ($input['reason'] ?? ''));
    $reason = $reason === '' ? '' : $reason;
    $now = date('Y-m-d H:i:s');

    if ($role === 'provider') {
        if (!usersApiTableExists('providers')) {
            sendError('Provider account not found', 404);
        }

        $placeholderPhone = 'deleted_' . $userId;
        $placeholderName = 'Deleted Provider';

        $updates = [
            'full_name = ?',
            'phone = ?',
            'email = NULL',
            "avatar = 'default-provider.png'",
            "status = 'suspended'",
            'is_available = 0',
            'device_token = NULL',
            'deleted_at = ?',
            'deletion_requested_at = ?',
            'deletion_reason = ?',
        ];
        $values = [$placeholderName, $placeholderPhone, $now, $now, $reason];
        $types = 'sssss';

        $nullableColumns = [
            'whatsapp_number',
            'city',
            'country',
            'district',
            'bio',
            'residency_document_path',
            'ajeer_certificate_path',
        ];
        foreach ($nullableColumns as $column) {
            if (usersApiTableColumnExists('providers', $column)) {
                $updates[] = "{$column} = NULL";
            }
        }

        $updatesSql = implode(', ', $updates);
        $stmt = $conn->prepare("UPDATE providers SET {$updatesSql} WHERE id = ?");
        if (!$stmt) {
            sendError('Failed to update provider account', 500);
        }
        $types .= 'i';
        $values[] = $userId;
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();

        if (usersApiTableExists('provider_services')) {
            $stmt = $conn->prepare("DELETE FROM provider_services WHERE provider_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (usersApiTableExists('complaints') && usersApiTableColumnExists('complaints', 'provider_id')) {
            $stmt = $conn->prepare("DELETE FROM complaints WHERE provider_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (usersApiTableExists('notifications') && usersApiTableColumnExists('notifications', 'provider_id')) {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE provider_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }
        }

        sendSuccess(['account_type' => 'provider'], 'تم حذف الحساب بنجاح');
    }

    $placeholderPhone = 'deleted_' . $userId;
    $placeholderName = 'Deleted User';

    $updates = [
        'full_name = ?',
        'phone = ?',
        'email = NULL',
        "avatar = 'default-user.png'",
        'is_active = 0',
        'device_token = NULL',
        'referral_code = NULL',
        'referred_by = NULL',
        'city = NULL',
        'country = NULL',
        'gender = NULL',
        'birth_date = NULL',
        'deleted_at = ?',
        'deletion_requested_at = ?',
        'deletion_reason = ?',
    ];
    $values = [$placeholderName, $placeholderPhone, $now, $now, $reason];
    $types = 'sssss';

    if (userProfileColumnExists('otp_code')) {
        $updates[] = 'otp_code = NULL';
    }

    $updatesSql = implode(', ', $updates);
    $stmt = $conn->prepare("UPDATE users SET {$updatesSql} WHERE id = ?");
    if (!$stmt) {
        sendError('Failed to update user account', 500);
    }
    $types .= 'i';
    $values[] = $userId;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();

    if (usersApiTableExists('user_addresses')) {
        $stmt = $conn->prepare("DELETE FROM user_addresses WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $cleanupTables = [
        'complaints',
        'furniture_requests',
        'container_requests',
    ];
    foreach ($cleanupTables as $table) {
        if (usersApiTableExists($table) && usersApiTableColumnExists($table, 'user_id')) {
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if (usersApiTableExists('notifications') && usersApiTableColumnExists('notifications', 'user_id')) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    sendSuccess(['account_type' => 'user'], 'تم حذف الحساب بنجاح');
}

/**
 * Format user for response
 */
function formatUser($user)
{
    $membershipLevel = (string) ($user['membership_level'] ?? 'silver');
    $membershipLevelNormalized = strtolower(trim($membershipLevel));
    $profileCompleted = computeUserProfileCompletion($user);

    return [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'] ?? '',
        'phone' => $user['phone'],
        'email' => $user['email'],
        'avatar' => $user['avatar'],
        'wallet_balance' => (float) ($user['wallet_balance'] ?? 0),
        'points' => (int) ($user['points'] ?? 0),
        'membership_level' => $membershipLevel,
        'is_premium' => in_array($membershipLevelNormalized, ['gold', 'platinum', 'premium', 'vip'], true),
        'referral_code' => $user['referral_code'],
        'is_active' => (bool) $user['is_active'],
        'is_verified' => (bool) $user['is_verified'],
        'created_at' => $user['created_at'],
        'updated_at' => $user['updated_at'] ?? null,
        'city' => $user['city'] ?? null,
        'country' => $user['country'] ?? null,
        'gender' => $user['gender'] ?? null,
        'birth_date' => $user['birth_date'] ?? null,
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
    if (isUserAvatarMissing($avatar)) {
        return false;
    }

    return true;
}

function isUserAvatarMissing($avatarValue)
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
    $defaultFile = 'default-user.png';
    if ($normalized === $defaultFile) {
        return true;
    }

    $suffix = '/' . $defaultFile;
    if (strlen($normalized) >= strlen($suffix) && substr($normalized, -strlen($suffix)) === $suffix) {
        return true;
    }

    return false;
}

/**
 * Upload file helper
 */
function uploadUserFile($file, $folder)
{
    $uploadDir = __DIR__ . '/../../uploads/' . $folder . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/' . $folder . '/' . $filename;
    }

    return null;
}

/**
 * UTF-8 safe length helper that does not hard-require mbstring.
 */
function utf8SafeLength($value): int
{
    $value = (string) $value;

    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }

    if (function_exists('preg_match_all')) {
        $matched = @preg_match_all('/./us', $value, $matches);
        if (is_int($matched) && $matched >= 0) {
            return $matched;
        }
    }

    return strlen($value);
}
