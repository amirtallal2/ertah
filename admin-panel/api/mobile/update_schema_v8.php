<?php
/**
 * Schema update v8
 * - Service areas (GPS coverage zones)
 * - Address geo metadata columns (country/city/village)
 * - Offers target-link columns for app routing
 */

require_once __DIR__ . '/../config/database.php';

function tableExistsV8(mysqli $conn, string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function columnExistsV8(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !tableExistsV8($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function indexExistsV8(mysqli $conn, string $table, string $index): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $index);
    if ($safeTable === '' || $safeIndex === '' || !tableExistsV8($conn, $safeTable)) {
        return false;
    }

    $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    return $result && $result->num_rows > 0;
}

function addColumnIfMissingV8(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!tableExistsV8($conn, $table)) {
        echo "Table {$table} does not exist; skipped {$column}\n";
        return;
    }
    if (columnExistsV8($conn, $table, $column)) {
        echo "{$table}.{$column} already exists\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")) {
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Failed adding {$table}.{$column}: {$conn->error}\n";
    }
}

function addIndexIfMissingV8(mysqli $conn, string $table, string $index, string $columnsSql): void
{
    if (!tableExistsV8($conn, $table)) {
        echo "Table {$table} does not exist; skipped index {$index}\n";
        return;
    }
    if (indexExistsV8($conn, $table, $index)) {
        echo "Index {$table}.{$index} already exists\n";
        return;
    }

    if ($conn->query("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnsSql})")) {
        echo "Added index {$table}.{$index}\n";
    } else {
        echo "Failed adding index {$table}.{$index}: {$conn->error}\n";
    }
}

// 1) Service areas table
$createServiceAreasSql = "CREATE TABLE IF NOT EXISTS `service_areas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `name_en` VARCHAR(150) NULL,
    `name_ur` VARCHAR(150) NULL,
    `country_code` VARCHAR(8) NOT NULL DEFAULT 'SA',
    `city_name` VARCHAR(120) NULL,
    `city_name_en` VARCHAR(120) NULL,
    `city_name_ur` VARCHAR(120) NULL,
    `village_name` VARCHAR(120) NULL,
    `village_name_en` VARCHAR(120) NULL,
    `village_name_ur` VARCHAR(120) NULL,
    `geometry_type` ENUM('circle','polygon') NOT NULL DEFAULT 'circle',
    `center_lat` DECIMAL(10,8) NULL,
    `center_lng` DECIMAL(11,8) NULL,
    `radius_km` DECIMAL(8,3) NULL,
    `polygon_json` LONGTEXT NULL,
    `notes` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `priority` INT NOT NULL DEFAULT 0,
    `created_by` INT NULL,
    `updated_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($createServiceAreasSql)) {
    echo "service_areas table is ready\n";
} else {
    echo "Failed preparing service_areas table: {$conn->error}\n";
}

$serviceAreaColumns = [
    'name_en' => "VARCHAR(150) NULL",
    'name_ur' => "VARCHAR(150) NULL",
    'country_code' => "VARCHAR(8) NOT NULL DEFAULT 'SA'",
    'city_name' => "VARCHAR(120) NULL",
    'city_name_en' => "VARCHAR(120) NULL",
    'city_name_ur' => "VARCHAR(120) NULL",
    'village_name' => "VARCHAR(120) NULL",
    'village_name_en' => "VARCHAR(120) NULL",
    'village_name_ur' => "VARCHAR(120) NULL",
    'geometry_type' => "ENUM('circle','polygon') NOT NULL DEFAULT 'circle'",
    'center_lat' => "DECIMAL(10,8) NULL",
    'center_lng' => "DECIMAL(11,8) NULL",
    'radius_km' => "DECIMAL(8,3) NULL",
    'polygon_json' => "LONGTEXT NULL",
    'notes' => "VARCHAR(255) NULL",
    'is_active' => "TINYINT(1) NOT NULL DEFAULT 1",
    'priority' => "INT NOT NULL DEFAULT 0",
    'created_by' => "INT NULL",
    'updated_by' => "INT NULL",
    'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
];
foreach ($serviceAreaColumns as $column => $definition) {
    addColumnIfMissingV8($conn, 'service_areas', $column, $definition);
}
addIndexIfMissingV8($conn, 'service_areas', 'idx_service_areas_country', '`country_code`');
addIndexIfMissingV8($conn, 'service_areas', 'idx_service_areas_active', '`is_active`');
addIndexIfMissingV8($conn, 'service_areas', 'idx_service_areas_priority', '`priority`');

// 2) Address location metadata columns
foreach (['user_addresses', 'addresses'] as $table) {
    if (!tableExistsV8($conn, $table)) {
        echo "Table {$table} not found; skipped geo columns\n";
        continue;
    }
    addColumnIfMissingV8($conn, $table, 'country_code', "VARCHAR(8) NULL");
    addColumnIfMissingV8($conn, $table, 'city_name', "VARCHAR(120) NULL");
    addColumnIfMissingV8($conn, $table, 'village_name', "VARCHAR(120) NULL");
}

// 3) Offers target-link columns (for opening targeted destination from app)
if (tableExistsV8($conn, 'offers')) {
    addColumnIfMissingV8(
        $conn,
        'offers',
        'link_type',
        "ENUM('none','category','offer','product','spare_parts','external') NOT NULL DEFAULT 'none'"
    );
    addColumnIfMissingV8($conn, 'offers', 'link_id', "INT NULL");
    addColumnIfMissingV8($conn, 'offers', 'link', "VARCHAR(500) NULL");
    addIndexIfMissingV8($conn, 'offers', 'idx_offers_link_type', '`link_type`');
}

// 4) Ensure supported_countries exists in app_settings
if (tableExistsV8($conn, 'app_settings')) {
    $existsResult = $conn->query("SELECT setting_key FROM app_settings WHERE setting_key = 'supported_countries' LIMIT 1");
    $exists = $existsResult && $existsResult->num_rows > 0;
    if (!$exists) {
        if ($conn->query("INSERT INTO app_settings (setting_key, setting_value, description) VALUES ('supported_countries', 'SA', 'الدول المتاحة لتقديم الخدمات (حسب GPS)')")) {
            echo "Inserted app_settings.supported_countries default value\n";
        } else {
            echo "Failed inserting supported_countries: {$conn->error}\n";
        }
    } else {
        echo "app_settings.supported_countries already exists\n";
    }
}

echo "Schema update v8 completed.\n";
