<?php
/**
 * Schema update v10
 * - Hardens complaint_replies for admin/user support conversations.
 */

require_once __DIR__ . '/../config/database.php';

function tableExistsV10(mysqli $conn, string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }

    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function columnExistsV10(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !tableExistsV10($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function indexExistsV10(mysqli $conn, string $table, string $index): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $index);
    if ($safeTable === '' || $safeIndex === '' || !tableExistsV10($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    return $result && $result->num_rows > 0;
}

function primaryIsIdV10(mysqli $conn, string $table): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '' || !tableExistsV10($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = 'PRIMARY'");
    if (!$result || $result->num_rows !== 1) {
        return false;
    }

    $row = $result->fetch_assoc();
    return strtolower((string) ($row['Column_name'] ?? '')) === 'id';
}

function idAutoIncrementV10(mysqli $conn, string $table): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '' || !tableExistsV10($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE 'id'");
    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $row = $result->fetch_assoc();
    return stripos((string) ($row['Extra'] ?? ''), 'auto_increment') !== false;
}

function addColumnIfMissingV10(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!tableExistsV10($conn, $table)) {
        echo "Table {$table} does not exist; skipped {$column}\n";
        return;
    }
    if (columnExistsV10($conn, $table, $column)) {
        echo "{$table}.{$column} already exists\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")) {
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Failed adding {$table}.{$column}: {$conn->error}\n";
    }
}

function addIndexIfMissingV10(mysqli $conn, string $table, string $index, string $columnsSql): void
{
    if (!tableExistsV10($conn, $table)) {
        echo "Table {$table} does not exist; skipped index {$index}\n";
        return;
    }
    if (indexExistsV10($conn, $table, $index)) {
        echo "{$table}.{$index} already exists\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnsSql})")) {
        echo "Added index {$table}.{$index}\n";
    } else {
        echo "Failed adding index {$table}.{$index}: {$conn->error}\n";
    }
}

$createRepliesSql = "CREATE TABLE IF NOT EXISTS `complaint_replies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `user_id` INT NULL,
    `admin_id` INT NULL,
    `message` TEXT NULL,
    `attachments` LONGTEXT NULL,
    `sender_type` VARCHAR(20) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint_replies_complaint` (`complaint_id`),
    INDEX `idx_complaint_replies_user` (`user_id`),
    INDEX `idx_complaint_replies_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createRepliesSql)) {
    echo "complaint_replies table is ready\n";
} else {
    echo "Failed preparing complaint_replies table: {$conn->error}\n";
}

addColumnIfMissingV10($conn, 'complaint_replies', 'id', 'INT NOT NULL');
addColumnIfMissingV10($conn, 'complaint_replies', 'complaint_id', 'INT NOT NULL');
addColumnIfMissingV10($conn, 'complaint_replies', 'user_id', 'INT NULL');
addColumnIfMissingV10($conn, 'complaint_replies', 'admin_id', 'INT NULL');
addColumnIfMissingV10($conn, 'complaint_replies', 'message', 'TEXT NULL');
addColumnIfMissingV10($conn, 'complaint_replies', 'attachments', 'LONGTEXT NULL');
addColumnIfMissingV10($conn, 'complaint_replies', 'sender_type', 'VARCHAR(20) NULL');
addColumnIfMissingV10($conn, 'complaint_replies', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');

if (tableExistsV10($conn, 'complaint_replies')) {
    if (!primaryIsIdV10($conn, 'complaint_replies') && columnExistsV10($conn, 'complaint_replies', 'id')) {
        if ($conn->query("ALTER TABLE `complaint_replies` ADD PRIMARY KEY (`id`)")) {
            echo "Added PRIMARY KEY on complaint_replies.id\n";
        } else {
            echo "Failed adding PRIMARY KEY on complaint_replies.id: {$conn->error}\n";
        }
    }

    if (!idAutoIncrementV10($conn, 'complaint_replies') && columnExistsV10($conn, 'complaint_replies', 'id')) {
        if ($conn->query("ALTER TABLE `complaint_replies` MODIFY COLUMN `id` INT NOT NULL AUTO_INCREMENT")) {
            echo "Enabled AUTO_INCREMENT on complaint_replies.id\n";
        } else {
            echo "Failed enabling AUTO_INCREMENT on complaint_replies.id: {$conn->error}\n";
        }
    }

    if (columnExistsV10($conn, 'complaint_replies', 'updated_at')) {
        if ($conn->query(
            "ALTER TABLE `complaint_replies`
             MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        )) {
            echo "Normalized complaint_replies.updated_at\n";
        } else {
            echo "Failed normalizing complaint_replies.updated_at: {$conn->error}\n";
        }
    }
}

addIndexIfMissingV10($conn, 'complaint_replies', 'idx_complaint_replies_complaint', '`complaint_id`');
addIndexIfMissingV10($conn, 'complaint_replies', 'idx_complaint_replies_user', '`user_id`');
addIndexIfMissingV10($conn, 'complaint_replies', 'idx_complaint_replies_admin', '`admin_id`');

echo "Schema update v10 completed.\n";
