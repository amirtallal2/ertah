<?php
/**
 * Mobile API - Addresses
 * إدارة العناوين
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getAddresses();
        break;
    case 'add':
        addAddress($input);
        break;
    case 'delete':
        deleteAddress($input);
        break;
    default:
        sendError('Invalid action', 400);
}

/**
 * Get user addresses
 */
function getAddresses()
{
    global $conn;
    $userId = requireAuth();
    $table = resolveAddressesTable();
    ensureAddressLocationColumns($table);
    $hasCountryCodeColumn = hasAddressCountryCodeColumn($table);
    $hasCityNameColumn = hasAddressColumn($table, 'city_name');
    $hasVillageNameColumn = hasAddressColumn($table, 'village_name');

    if ($table === 'user_addresses') {
        $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    } else {
        $stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC");
    }

    if (!$stmt) {
        sendError('Database error: ' . $conn->error, 500);
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $title = $table === 'user_addresses'
            ? ($row['label'] ?: ($row['type'] ?? 'العنوان'))
            : $row['title'];
        $notes = $table === 'user_addresses'
            ? ($row['details'] ?? null)
            : ($row['notes'] ?? null);

        $data[] = [
            'id' => (int) $row['id'],
            'title' => $title,
            'address' => $row['address'],
            'lat' => (double) $row['lat'],
            'lng' => (double) $row['lng'],
            'notes' => $notes,
            'country_code' => $hasCountryCodeColumn ? normalizeCountryCode($row['country_code'] ?? '') : '',
            'city_name' => $hasCityNameColumn ? trim((string) ($row['city_name'] ?? '')) : '',
            'village_name' => $hasVillageNameColumn ? trim((string) ($row['village_name'] ?? '')) : '',
            'is_default' => (bool) $row['is_default']
        ];
    }

    sendSuccess($data);
}

/**
 * Add new address
 */
function addAddress($input)
{
    global $conn;
    $userId = requireAuth();
    $table = resolveAddressesTable();
    ensureAddressLocationColumns($table);
    $hasCountryCodeColumn = hasAddressCountryCodeColumn($table);
    $hasCityNameColumn = hasAddressColumn($table, 'city_name');
    $hasVillageNameColumn = hasAddressColumn($table, 'village_name');

    $title = $input['title'] ?? '';
    $address = $input['address'] ?? '';
    $lat = $input['lat'] ?? 0.0;
    $lng = $input['lng'] ?? 0.0;
    $notes = $input['notes'] ?? '';
    $type = $input['type'] ?? 'home';
    $countryCode = normalizeCountryCode($input['country_code'] ?? '');
    $cityName = trim((string) ($input['city_name'] ?? ''));
    $villageName = trim((string) ($input['village_name'] ?? ''));
    $isDefault = !empty($input['is_default']) ? 1 : 0;

    if (empty($title) || empty($address)) {
        sendError('العنوان والاسم مطلوبين', 422);
    }

    if ($isDefault) {
        if ($table === 'user_addresses') {
            $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        }
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        }
    }

    if ($table === 'user_addresses') {
        $insertData = [
            'user_id' => $userId,
            'type' => $type,
            'label' => $title,
            'address' => $address,
            'details' => $notes,
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'is_default' => $isDefault,
        ];
    } else {
        $insertData = [
            'user_id' => $userId,
            'title' => $title,
            'address' => $address,
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'notes' => $notes,
            'is_default' => $isDefault,
        ];
    }

    if ($hasCountryCodeColumn) {
        $insertData['country_code'] = $countryCode;
    }
    if ($hasCityNameColumn) {
        $insertData['city_name'] = $cityName;
    }
    if ($hasVillageNameColumn) {
        $insertData['village_name'] = $villageName;
    }

    $inserted = insertAddressRow($table, $insertData);

    if ($inserted) {
        $id = $conn->insert_id;

        // Return the created address
        sendSuccess([
            'id' => $id,
            'title' => $title,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'notes' => $notes,
            'country_code' => $countryCode,
            'city_name' => $cityName,
            'village_name' => $villageName,
            'is_default' => (bool) $isDefault
        ], 'تم حفظ العنوان بنجاح');
    } else {
        sendError('حدث خطأ أثناء حفظ العنوان: ' . $conn->error, 500);
    }
}

/**
 * Delete address
 */
function deleteAddress($input)
{
    global $conn;
    $userId = requireAuth();
    $table = resolveAddressesTable();

    $addressId = $input['address_id'] ?? 0;

    if ($table === 'user_addresses') {
        $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    } else {
        $stmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
    }

    if (!$stmt) {
        sendError('Database error: ' . $conn->error, 500);
    }

    $stmt->bind_param("ii", $addressId, $userId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendSuccess(null, 'تم حذف العنوان بنجاح');
        } else {
            sendError('العنوان غير موجود أو لا تملك صلاحية حذفه', 404);
        }
    } else {
        sendError('حدث خطأ أثناء حذف العنوان', 500);
    }
}

/**
 * Resolve addresses table name for this database schema.
 */
function resolveAddressesTable()
{
    global $conn;

    foreach (['user_addresses', 'addresses'] as $table) {
        $escaped = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '$escaped'");
        if ($result && $result->num_rows > 0) {
            return $table;
        }
    }

    sendError('لم يتم العثور على جدول العناوين', 500);
}

function hasAddressCountryCodeColumn($table)
{
    return hasAddressColumn($table, 'country_code');
}

function hasAddressColumn($table, $column)
{
    global $conn;

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($safeTable === '') {
        return false;
    }
    if ($safeColumn === '') {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function ensureAddressLocationColumns($table)
{
    global $conn;
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($safeTable === '') {
        return;
    }

    if (!hasAddressColumn($safeTable, 'country_code')) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `country_code` VARCHAR(8) NULL");
    }
    if (!hasAddressColumn($safeTable, 'city_name')) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `city_name` VARCHAR(120) NULL");
    }
    if (!hasAddressColumn($safeTable, 'village_name')) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `village_name` VARCHAR(120) NULL");
    }
}

function insertAddressRow($table, array $data)
{
    global $conn;

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($safeTable === '' || empty($data)) {
        return false;
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO `{$safeTable}` (`" . implode('`, `', $columns) . "`) VALUES ({$placeholders})";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $types = '';
    $values = [];
    foreach ($columns as $column) {
        $value = $data[$column];
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
            $value = $value === null ? null : (string) $value;
        }
        $values[] = $value;
    }

    $stmt->bind_param($types, ...$values);
    return $stmt->execute();
}

function normalizeCountryCode($value)
{
    $code = strtoupper(trim((string) $value));
    if ($code === '' || $code === 'NULL' || $code === '-') {
        return '';
    }

    return preg_replace('/[^A-Z]/', '', $code);
}
