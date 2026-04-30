<?php
/**
 * Schema update v12
 * - Migrates legacy OneSignal setting keys to the current keys.
 * - Normalizes the REST API key if it was saved with an Authorization prefix.
 */

require_once __DIR__ . '/../config/database.php';

function tableExistsV12(mysqli $conn, string $table): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return false;
    }

    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safeTable) . "'");
    return $result && $result->num_rows > 0;
}

function columnExistsV12(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !tableExistsV12($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query(
        "SHOW COLUMNS FROM `{$safeTable}` LIKE '" . $conn->real_escape_string($safeColumn) . "'"
    );
    return $result && $result->num_rows > 0;
}

function normalizeOneSignalRestApiKeyV12(string $raw): string
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

function settingValueV12(mysqli $conn, string $key): string
{
    if (
        !tableExistsV12($conn, 'app_settings')
        || !columnExistsV12($conn, 'app_settings', 'setting_key')
        || !columnExistsV12($conn, 'app_settings', 'setting_value')
    ) {
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

function upsertSettingV12(mysqli $conn, string $key, string $value, string $description): void
{
    if (!tableExistsV12($conn, 'app_settings')) {
        echo "app_settings table does not exist\n";
        return;
    }

    if ($key === 'onesignal_rest_api_key' || $key === 'one_signal_rest_api_key') {
        $value = normalizeOneSignalRestApiKeyV12($value);
    }

    $existsResult = $conn->query(
        "SELECT setting_key FROM app_settings WHERE setting_key = '" . $conn->real_escape_string($key) . "' LIMIT 1"
    );
    $exists = $existsResult && $existsResult->num_rows > 0;

    if ($exists) {
        $stmt = $conn->prepare("UPDATE app_settings SET setting_value = ?, description = ? WHERE setting_key = ?");
        if ($stmt) {
            $stmt->bind_param('sss', $value, $description, $key);
            if ($stmt->execute()) {
                echo "Updated app_settings.{$key}\n";
            } else {
                echo "Failed updating app_settings.{$key}: {$stmt->error}\n";
            }
        }
        return;
    }

    $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo "Failed preparing app_settings.{$key}: {$conn->error}\n";
        return;
    }
    $stmt->bind_param('sss', $key, $value, $description);
    if ($stmt->execute()) {
        echo "Added app_settings.{$key}\n";
    } else {
        echo "Failed adding app_settings.{$key}: {$stmt->error}\n";
    }
}

if (!tableExistsV12($conn, 'app_settings')) {
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

$legacyAppId = settingValueV12($conn, 'one_signal_app_id');
$currentAppId = settingValueV12($conn, 'onesignal_app_id');
if ($currentAppId === '' && $legacyAppId !== '') {
    upsertSettingV12($conn, 'onesignal_app_id', $legacyAppId, 'OneSignal App ID');
    echo "Migrated one_signal_app_id to onesignal_app_id\n";
} elseif ($currentAppId === '') {
    upsertSettingV12($conn, 'onesignal_app_id', '', 'OneSignal App ID');
}

$legacyRestKey = normalizeOneSignalRestApiKeyV12(settingValueV12($conn, 'one_signal_rest_api_key'));
$currentRestKeyRaw = settingValueV12($conn, 'onesignal_rest_api_key');
$currentRestKey = normalizeOneSignalRestApiKeyV12($currentRestKeyRaw);

if ($currentRestKey === '' && $legacyRestKey !== '') {
    upsertSettingV12($conn, 'onesignal_rest_api_key', $legacyRestKey, 'OneSignal REST API Key');
    echo "Migrated one_signal_rest_api_key to onesignal_rest_api_key\n";
} elseif ($currentRestKeyRaw !== '' && $currentRestKeyRaw !== $currentRestKey) {
    upsertSettingV12($conn, 'onesignal_rest_api_key', $currentRestKey, 'OneSignal REST API Key');
    echo "Normalized onesignal_rest_api_key\n";
} elseif ($currentRestKey === '') {
    upsertSettingV12($conn, 'onesignal_rest_api_key', '', 'OneSignal REST API Key');
}

echo "Schema update v12 completed.\n";
