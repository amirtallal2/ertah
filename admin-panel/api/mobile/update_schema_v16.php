<?php
/**
 * Schema update v16
 * Changes OTP SMS sender name from legacy Ertah values to Darfix.
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';

const V16_SMS_SENDER_ID = 'Darfix';

function v16TableExists(string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }

    return (bool) db()->fetch('SHOW TABLES LIKE ?', [$safe]);
}

function v16AppSettingsColumns(): array
{
    $columns = db()->fetchAll('SHOW COLUMNS FROM `app_settings`');
    $names = array_map(static fn($row) => (string) ($row['Field'] ?? ''), $columns);

    $keyColumn = in_array('setting_key', $names, true)
        ? 'setting_key'
        : (in_array('key_name', $names, true) ? 'key_name' : (in_array('key', $names, true) ? 'key' : ''));
    $valueColumn = in_array('setting_value', $names, true)
        ? 'setting_value'
        : (in_array('value', $names, true) ? 'value' : '');
    $descriptionColumn = in_array('description', $names, true) ? 'description' : '';

    return [$keyColumn, $valueColumn, $descriptionColumn];
}

function v16NormalizeSenderId($value): string
{
    $senderId = trim((string) $value);
    $compact = strtolower(preg_replace('/[\s_\-]+/', '', $senderId) ?? '');

    if ($senderId === '' || in_array($compact, ['ertah', 'ertahapp', 'ertahsms'], true)) {
        return V16_SMS_SENDER_ID;
    }

    return $senderId;
}

function v16IsLegacySenderId($value): bool
{
    $senderId = trim((string) $value);
    $compact = strtolower(preg_replace('/[\s_\-]+/', '', $senderId) ?? '');

    return in_array($compact, ['ertah', 'ertahapp', 'ertahsms'], true);
}

function v16GetSetting(string $key, string $keyColumn, string $valueColumn): ?string
{
    $row = db()->fetch(
        "SELECT `{$valueColumn}` AS setting_value FROM `app_settings` WHERE `{$keyColumn}` = ? LIMIT 1",
        [$key]
    );

    if (!$row) {
        return null;
    }

    return (string) ($row['setting_value'] ?? '');
}

function v16UpsertSetting(string $key, string $value, string $keyColumn, string $valueColumn, string $descriptionColumn = ''): string
{
    $existing = db()->fetch(
        "SELECT `{$keyColumn}` FROM `app_settings` WHERE `{$keyColumn}` = ? LIMIT 1",
        [$key]
    );

    if ($existing) {
        db()->query(
            "UPDATE `app_settings` SET `{$valueColumn}` = ? WHERE `{$keyColumn}` = ?",
            [$value, $key]
        );
        return 'updated';
    }

    if ($descriptionColumn !== '') {
        db()->query(
            "INSERT INTO `app_settings` (`{$keyColumn}`, `{$valueColumn}`, `{$descriptionColumn}`) VALUES (?, ?, ?)",
            [$key, $value, 'OTP SMS sender ID']
        );
    } else {
        db()->query(
            "INSERT INTO `app_settings` (`{$keyColumn}`, `{$valueColumn}`) VALUES (?, ?)",
            [$key, $value]
        );
    }

    return 'inserted';
}

echo "Preparing OTP SMS sender update...\n";

if (!v16TableExists('app_settings')) {
    echo "app_settings table missing; cannot update sender settings.\n";
    echo "Schema update v16 completed.\n";
    exit;
}

[$keyColumn, $valueColumn, $descriptionColumn] = v16AppSettingsColumns();
if ($keyColumn === '' || $valueColumn === '') {
    echo "app_settings key/value columns not recognized; cannot update sender settings.\n";
    echo "Schema update v16 completed.\n";
    exit;
}

$smsCurrent = v16GetSetting('sms_sender_id', $keyColumn, $valueColumn);
$smsNormalized = v16NormalizeSenderId($smsCurrent ?? '');

if ($smsCurrent === null || trim($smsCurrent) === '' || $smsNormalized !== $smsCurrent) {
    $status = v16UpsertSetting('sms_sender_id', V16_SMS_SENDER_ID, $keyColumn, $valueColumn, $descriptionColumn);
    echo "sms_sender_id={$status}: " . V16_SMS_SENDER_ID . "\n";
} else {
    echo "sms_sender_id=kept: {$smsCurrent}\n";
}

$whatsappCurrent = v16GetSetting('whatsapp_sender', $keyColumn, $valueColumn);
if ($whatsappCurrent !== null && v16IsLegacySenderId($whatsappCurrent)) {
    $status = v16UpsertSetting('whatsapp_sender', V16_SMS_SENDER_ID, $keyColumn, $valueColumn, $descriptionColumn);
    echo "whatsapp_sender={$status}: " . V16_SMS_SENDER_ID . "\n";
} elseif ($whatsappCurrent === null) {
    echo "whatsapp_sender=missing: no change\n";
} else {
    echo "whatsapp_sender=kept: {$whatsappCurrent}\n";
}

echo "Schema update v16 completed.\n";
