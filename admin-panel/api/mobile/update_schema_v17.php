<?php
/**
 * Schema update v17
 * Prepares OneSignal settings for automatic order push notifications.
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';

const V17_ONESIGNAL_APP_ID = '13bf8a95-130c-4e86-b4cc-e91bd3b12322';

function v17TableExists(string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }

    return (bool) db()->fetch('SHOW TABLES LIKE ?', [$safe]);
}

function v17ColumnExists(string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !v17TableExists($safeTable)) {
        return false;
    }

    return (bool) db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE ?", [$safeColumn]);
}

function v17NormalizeOneSignalRestApiKey(string $raw): string
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

function v17GetSetting(string $key): string
{
    if (!v17TableExists('app_settings')) {
        return '';
    }

    $row = db()->fetch('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1', [$key]);
    return trim((string) ($row['setting_value'] ?? ''));
}

function v17UpsertSetting(string $key, string $value, string $description = ''): string
{
    if (!v17TableExists('app_settings')) {
        db()->query(
            "CREATE TABLE IF NOT EXISTS `app_settings` (
                `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                `setting_value` TEXT NULL,
                `description` VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $exists = db()->fetch('SELECT setting_key FROM app_settings WHERE setting_key = ? LIMIT 1', [$key]);
    if ($exists) {
        db()->query(
            'UPDATE app_settings SET setting_value = ?, description = ? WHERE setting_key = ?',
            [$value, $description, $key]
        );
        return 'updated';
    }

    db()->query(
        'INSERT INTO app_settings (setting_key, setting_value, description) VALUES (?, ?, ?)',
        [$key, $value, $description]
    );
    return 'inserted';
}

echo "Preparing OneSignal automatic order push settings...\n";

echo 'notifications_enabled=' . v17UpsertSetting('notifications_enabled', '1', 'Enable or disable push notifications') . "\n";
echo 'onesignal_app_id=' . v17UpsertSetting('onesignal_app_id', V17_ONESIGNAL_APP_ID, 'OneSignal App ID') . "\n";

$envRestKey = v17NormalizeOneSignalRestApiKey((string) (getenv('ONESIGNAL_REST_API_KEY') ?: getenv('ONE_SIGNAL_REST_API_KEY') ?: ''));
$currentRestKey = v17NormalizeOneSignalRestApiKey(v17GetSetting('onesignal_rest_api_key'));
$legacyRestKey = v17NormalizeOneSignalRestApiKey(v17GetSetting('one_signal_rest_api_key'));

if ($envRestKey !== '') {
    echo 'onesignal_rest_api_key=' . v17UpsertSetting('onesignal_rest_api_key', $envRestKey, 'OneSignal REST API Key') . " from ENV\n";
} elseif ($currentRestKey !== '') {
    echo 'onesignal_rest_api_key=' . v17UpsertSetting('onesignal_rest_api_key', $currentRestKey, 'OneSignal REST API Key') . " normalized\n";
} elseif ($legacyRestKey !== '') {
    echo 'onesignal_rest_api_key=' . v17UpsertSetting('onesignal_rest_api_key', $legacyRestKey, 'OneSignal REST API Key') . " migrated from legacy\n";
} else {
    echo "onesignal_rest_api_key=missing; add it from Admin > Notifications before testing automatic push.\n";
}

if (v17ColumnExists('users', 'device_token')) {
    echo "users.device_token=ready\n";
} elseif (v17TableExists('users')) {
    db()->query('ALTER TABLE `users` ADD COLUMN `device_token` VARCHAR(255) NULL');
    echo "users.device_token=added\n";
}

if (v17ColumnExists('providers', 'device_token')) {
    echo "providers.device_token=ready\n";
} elseif (v17TableExists('providers')) {
    db()->query('ALTER TABLE `providers` ADD COLUMN `device_token` VARCHAR(255) NULL');
    echo "providers.device_token=added\n";
}

echo "Schema update v17 completed.\n";
