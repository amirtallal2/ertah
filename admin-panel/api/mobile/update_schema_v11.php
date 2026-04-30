<?php
/**
 * Schema update v11
 * - Hardens the admin notifications table and related settings.
 */

require_once __DIR__ . '/../config/database.php';

function safeNameV11(string $value): string
{
    return preg_replace('/[^a-zA-Z0-9_]/', '', $value);
}

function tableExistsV11(mysqli $conn, string $table): bool
{
    $safeTable = safeNameV11($table);
    if ($safeTable === '') {
        return false;
    }

    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safeTable) . "'");
    return $result && $result->num_rows > 0;
}

function columnExistsV11(mysqli $conn, string $table, string $column): bool
{
    $safeTable = safeNameV11($table);
    $safeColumn = safeNameV11($column);
    if ($safeTable === '' || $safeColumn === '' || !tableExistsV11($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query(
        "SHOW COLUMNS FROM `{$safeTable}` LIKE '" . $conn->real_escape_string($safeColumn) . "'"
    );
    return $result && $result->num_rows > 0;
}

function indexExistsV11(mysqli $conn, string $table, string $index): bool
{
    $safeTable = safeNameV11($table);
    $safeIndex = safeNameV11($index);
    if ($safeTable === '' || $safeIndex === '' || !tableExistsV11($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query(
        "SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '" . $conn->real_escape_string($safeIndex) . "'"
    );
    return $result && $result->num_rows > 0;
}

function primaryKeyColumnsV11(mysqli $conn, string $table): array
{
    $safeTable = safeNameV11($table);
    if ($safeTable === '' || !tableExistsV11($conn, $safeTable)) {
        return [];
    }

    $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = 'PRIMARY'");
    if (!$result) {
        return [];
    }

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $seq = (int) ($row['Seq_in_index'] ?? 0);
        $name = (string) ($row['Column_name'] ?? '');
        if ($seq > 0 && $name !== '') {
            $columns[$seq] = $name;
        }
    }
    ksort($columns);
    return array_values($columns);
}

function columnAutoIncrementV11(mysqli $conn, string $table, string $column): bool
{
    $safeTable = safeNameV11($table);
    $safeColumn = safeNameV11($column);
    if ($safeTable === '' || $safeColumn === '' || !columnExistsV11($conn, $safeTable, $safeColumn)) {
        return false;
    }

    $result = $conn->query(
        "SHOW COLUMNS FROM `{$safeTable}` LIKE '" . $conn->real_escape_string($safeColumn) . "'"
    );
    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $row = $result->fetch_assoc();
    return stripos((string) ($row['Extra'] ?? ''), 'auto_increment') !== false;
}

function addColumnIfMissingV11(mysqli $conn, string $table, string $column, string $definition): void
{
    $safeTable = safeNameV11($table);
    $safeColumn = safeNameV11($column);
    if ($safeTable === '' || $safeColumn === '' || !tableExistsV11($conn, $safeTable)) {
        echo "Table {$table} does not exist; skipped {$column}\n";
        return;
    }
    if (columnExistsV11($conn, $safeTable, $safeColumn)) {
        echo "{$safeTable}.{$safeColumn} already exists\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}")) {
        echo "Added {$safeTable}.{$safeColumn}\n";
    } else {
        echo "Failed adding {$safeTable}.{$safeColumn}: {$conn->error}\n";
    }
}

function modifyColumnV11(mysqli $conn, string $table, string $column, string $definition): void
{
    $safeTable = safeNameV11($table);
    $safeColumn = safeNameV11($column);
    if ($safeTable === '' || $safeColumn === '' || !columnExistsV11($conn, $safeTable, $safeColumn)) {
        return;
    }

    if ($conn->query("ALTER TABLE `{$safeTable}` MODIFY COLUMN `{$safeColumn}` {$definition}")) {
        echo "Normalized {$safeTable}.{$safeColumn}\n";
    } else {
        echo "Failed normalizing {$safeTable}.{$safeColumn}: {$conn->error}\n";
    }
}

function addIndexIfMissingV11(mysqli $conn, string $table, string $index, array $columns): void
{
    $safeTable = safeNameV11($table);
    $safeIndex = safeNameV11($index);
    if ($safeTable === '' || $safeIndex === '' || !tableExistsV11($conn, $safeTable)) {
        echo "Table {$table} does not exist; skipped index {$index}\n";
        return;
    }
    if (indexExistsV11($conn, $safeTable, $safeIndex)) {
        echo "{$safeTable}.{$safeIndex} already exists\n";
        return;
    }

    $safeColumns = [];
    foreach ($columns as $column) {
        $safeColumn = safeNameV11((string) $column);
        if ($safeColumn !== '' && columnExistsV11($conn, $safeTable, $safeColumn)) {
            $safeColumns[] = "`{$safeColumn}`";
        }
    }
    if (empty($safeColumns)) {
        echo "No valid columns for index {$safeIndex}\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$safeTable}` ADD INDEX `{$safeIndex}` (" . implode(', ', $safeColumns) . ")")) {
        echo "Added index {$safeTable}.{$safeIndex}\n";
    } else {
        echo "Failed adding index {$safeTable}.{$safeIndex}: {$conn->error}\n";
    }
}

function ensureNotificationSettingV11(mysqli $conn, string $key, string $value, string $description): void
{
    if (!tableExistsV11($conn, 'app_settings')) {
        return;
    }
    if (!columnExistsV11($conn, 'app_settings', 'setting_key') || !columnExistsV11($conn, 'app_settings', 'setting_value')) {
        return;
    }

    $safeKey = $conn->real_escape_string($key);
    $result = $conn->query("SELECT setting_key FROM app_settings WHERE setting_key = '{$safeKey}' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        echo "app_settings.{$key} already exists\n";
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO app_settings (setting_key, setting_value, description) VALUES (?, ?, ?)"
    );
    if (!$stmt) {
        echo "Failed preparing app_settings insert for {$key}: {$conn->error}\n";
        return;
    }
    $stmt->bind_param('sss', $key, $value, $description);
    if ($stmt->execute()) {
        echo "Added app_settings.{$key}\n";
    } else {
        echo "Failed adding app_settings.{$key}: {$stmt->error}\n";
    }
}

function normalizeOneSignalRestApiKeyV11(string $raw): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    $value = trim($value, " \t\n\r\0\x0B\"'");
    $value = preg_replace('/^authorization\s*:\s*/i', '', $value);
    $value = preg_replace('/^(key|basic|bearer)\s+/i', '', trim((string) $value));
    return trim((string) $value, " \t\n\r\0\x0B\"'");
}

function settingValueV11(mysqli $conn, string $key): string
{
    if (!tableExistsV11($conn, 'app_settings') || !columnExistsV11($conn, 'app_settings', 'setting_key')) {
        return '';
    }

    $safeKey = $conn->real_escape_string($key);
    $result = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = '{$safeKey}' LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        return '';
    }

    $row = $result->fetch_assoc();
    return trim((string) ($row['setting_value'] ?? ''));
}

function upsertSettingV11(mysqli $conn, string $key, string $value, string $description): void
{
    if (!tableExistsV11($conn, 'app_settings')) {
        return;
    }

    $existsResult = $conn->query(
        "SELECT setting_key FROM app_settings WHERE setting_key = '" . $conn->real_escape_string($key) . "' LIMIT 1"
    );
    $exists = $existsResult && $existsResult->num_rows > 0;

    if ($key === 'onesignal_rest_api_key' || $key === 'one_signal_rest_api_key') {
        $value = normalizeOneSignalRestApiKeyV11($value);
    }

    if ($exists) {
        $stmt = $conn->prepare("UPDATE app_settings SET setting_value = ?, description = ? WHERE setting_key = ?");
        if ($stmt) {
            $stmt->bind_param('sss', $value, $description, $key);
            $stmt->execute();
        }
        return;
    }

    ensureNotificationSettingV11($conn, $key, $value, $description);
}

function migrateSettingAliasV11(mysqli $conn, string $legacyKey, string $targetKey, string $description): void
{
    $legacyValue = settingValueV11($conn, $legacyKey);
    $targetValue = settingValueV11($conn, $targetKey);
    if ($legacyValue === '' || $targetValue !== '') {
        return;
    }

    upsertSettingV11($conn, $targetKey, $legacyValue, $description);
    echo "Migrated app_settings.{$legacyKey} to {$targetKey}\n";
}

$createNotificationsSql = "CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `provider_id` INT NULL,
    `title` VARCHAR(200) NULL,
    `body` TEXT NULL,
    `type` VARCHAR(40) NULL DEFAULT 'system',
    `data` LONGTEXT NULL,
    `is_read` TINYINT(1) NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_provider` (`provider_id`),
    INDEX `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($createNotificationsSql)) {
    echo "notifications table is ready\n";
} else {
    echo "Failed preparing notifications table: {$conn->error}\n";
}

addColumnIfMissingV11($conn, 'notifications', 'id', 'INT NOT NULL');
addColumnIfMissingV11($conn, 'notifications', 'user_id', 'INT NULL');
addColumnIfMissingV11($conn, 'notifications', 'provider_id', 'INT NULL');
addColumnIfMissingV11($conn, 'notifications', 'title', 'VARCHAR(200) NULL');
addColumnIfMissingV11($conn, 'notifications', 'body', 'TEXT NULL');
addColumnIfMissingV11($conn, 'notifications', 'type', "VARCHAR(40) NULL DEFAULT 'system'");
addColumnIfMissingV11($conn, 'notifications', 'data', 'LONGTEXT NULL');
addColumnIfMissingV11($conn, 'notifications', 'is_read', 'TINYINT(1) NULL DEFAULT 0');
addColumnIfMissingV11($conn, 'notifications', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');

modifyColumnV11($conn, 'notifications', 'title', 'VARCHAR(200) NULL');
modifyColumnV11($conn, 'notifications', 'body', 'TEXT NULL');
modifyColumnV11($conn, 'notifications', 'type', "VARCHAR(40) NULL DEFAULT 'system'");
modifyColumnV11($conn, 'notifications', 'data', 'LONGTEXT NULL');
modifyColumnV11($conn, 'notifications', 'is_read', 'TINYINT(1) NULL DEFAULT 0');
modifyColumnV11($conn, 'notifications', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');

if (tableExistsV11($conn, 'notifications') && columnExistsV11($conn, 'notifications', 'id')) {
    $primaryColumns = primaryKeyColumnsV11($conn, 'notifications');
    if (empty($primaryColumns)) {
        if ($conn->query("ALTER TABLE `notifications` ADD PRIMARY KEY (`id`)")) {
            echo "Added PRIMARY KEY on notifications.id\n";
        } else {
            echo "Failed adding PRIMARY KEY on notifications.id: {$conn->error}\n";
        }
    } elseif ($primaryColumns === ['id']) {
        echo "notifications.id is already PRIMARY KEY\n";
    } else {
        echo "notifications has a different PRIMARY KEY; skipped changing it\n";
    }

    if (!columnAutoIncrementV11($conn, 'notifications', 'id') && primaryKeyColumnsV11($conn, 'notifications') === ['id']) {
        if ($conn->query("ALTER TABLE `notifications` MODIFY COLUMN `id` INT NOT NULL AUTO_INCREMENT")) {
            echo "Enabled AUTO_INCREMENT on notifications.id\n";
        } else {
            echo "Failed enabling AUTO_INCREMENT on notifications.id: {$conn->error}\n";
        }
    } else {
        echo "notifications.id AUTO_INCREMENT is ready\n";
    }
}

addIndexIfMissingV11($conn, 'notifications', 'idx_notifications_user', ['user_id']);
addIndexIfMissingV11($conn, 'notifications', 'idx_notifications_provider', ['provider_id']);
addIndexIfMissingV11($conn, 'notifications', 'idx_notifications_created', ['created_at']);

if (!tableExistsV11($conn, 'app_settings')) {
    if ($conn->query(
        "CREATE TABLE IF NOT EXISTS `app_settings` (
            `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `setting_value` TEXT NULL,
            `description` VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    )) {
        echo "app_settings table is ready\n";
    } else {
        echo "Failed preparing app_settings table: {$conn->error}\n";
    }
}

ensureNotificationSettingV11($conn, 'notifications_enabled', '1', 'Enable or disable push notifications');
ensureNotificationSettingV11($conn, 'onesignal_app_id', '', 'OneSignal App ID');
ensureNotificationSettingV11($conn, 'onesignal_rest_api_key', '', 'OneSignal REST API Key');
ensureNotificationSettingV11($conn, 'notifications_logo_url', '', 'Notification large icon/logo URL');
ensureNotificationSettingV11($conn, 'notifications_small_icon', 'ic_stat_onesignal_default', 'Android notification small icon name');

migrateSettingAliasV11($conn, 'one_signal_app_id', 'onesignal_app_id', 'OneSignal App ID');
migrateSettingAliasV11($conn, 'one_signal_rest_api_key', 'onesignal_rest_api_key', 'OneSignal REST API Key');

$currentRestKey = settingValueV11($conn, 'onesignal_rest_api_key');
$normalizedRestKey = normalizeOneSignalRestApiKeyV11($currentRestKey);
if ($currentRestKey !== '' && $normalizedRestKey !== $currentRestKey) {
    upsertSettingV11($conn, 'onesignal_rest_api_key', $normalizedRestKey, 'OneSignal REST API Key');
    echo "Normalized app_settings.onesignal_rest_api_key\n";
}

echo "Schema update v11 completed.\n";
