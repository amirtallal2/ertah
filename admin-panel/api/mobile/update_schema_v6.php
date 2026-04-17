<?php
/**
 * Schema update v6
 * - Align legacy database schema with admin pages expectations.
 */

require_once __DIR__ . '/../config/database.php';

function tableExists($conn, $table)
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column)
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function addColumnIfMissing($conn, $table, $column, $definition)
{
    if (!tableExists($conn, $table)) {
        echo "Table {$table} does not exist; skipped {$column}\n";
        return;
    }
    if (!columnExists($conn, $table, $column)) {
        if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")) {
            echo "Added {$table}.{$column}\n";
        } else {
            echo "Failed adding {$table}.{$column}: " . $conn->error . "\n";
        }
    } else {
        echo "{$table}.{$column} already exists\n";
    }
}

function createTableIfMissing($conn, $table, $sql)
{
    if (tableExists($conn, $table)) {
        echo "Table {$table} already exists\n";
        return;
    }
    if ($conn->query($sql)) {
        echo "Created table {$table}\n";
    } else {
        echo "Failed creating table {$table}: " . $conn->error . "\n";
    }
}

createTableIfMissing(
    $conn,
    'countries',
    "CREATE TABLE `countries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar` VARCHAR(100) NOT NULL,
        `name_en` VARCHAR(100) DEFAULT NULL,
        `code` VARCHAR(5) DEFAULT NULL,
        `phone_code` VARCHAR(10) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

if (tableExists($conn, 'countries')) {
    $countryCountRes = $conn->query("SELECT COUNT(*) AS count FROM countries");
    $countryCount = $countryCountRes ? (int) ($countryCountRes->fetch_assoc()['count'] ?? 0) : 0;
    if ($countryCount === 0) {
        if ($conn->query("INSERT INTO countries (name_ar, name_en, code, phone_code, is_active) VALUES ('السعودية', 'Saudi Arabia', 'SA', '966', 1)")) {
            echo "Inserted default country row\n";
        } else {
            echo "Failed inserting default country: " . $conn->error . "\n";
        }
    }
}

addColumnIfMissing($conn, 'cities', 'country_id', 'INT NULL');

if (tableExists($conn, 'cities') && tableExists($conn, 'countries') && columnExists($conn, 'cities', 'country_id')) {
    $defaultCountryRes = $conn->query("SELECT id FROM countries ORDER BY id ASC LIMIT 1");
    $defaultCountryId = $defaultCountryRes ? (int) ($defaultCountryRes->fetch_assoc()['id'] ?? 0) : 0;
    if ($defaultCountryId > 0) {
        if ($conn->query("UPDATE cities SET country_id = {$defaultCountryId} WHERE country_id IS NULL")) {
            echo "Backfilled cities.country_id\n";
        } else {
            echo "Failed backfilling cities.country_id: " . $conn->error . "\n";
        }
    }
}

createTableIfMissing(
    $conn,
    'provider_services',
    "CREATE TABLE `provider_services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `provider_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_provider_category` (`provider_id`, `category_id`),
        INDEX `idx_provider_id` (`provider_id`),
        INDEX `idx_category_id` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

createTableIfMissing(
    $conn,
    'order_spare_parts',
    "CREATE TABLE `order_spare_parts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `provider_id` INT NULL,
        `store_id` INT NULL,
        `spare_part_id` INT NULL,
        `spare_part_name` VARCHAR(255) NOT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `pricing_mode` VARCHAR(32) NULL,
        `requires_installation` TINYINT(1) NOT NULL DEFAULT 1,
        `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `notes` VARCHAR(255) NULL,
        `is_committed` TINYINT(1) NOT NULL DEFAULT 0,
        `committed_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_osp_order` (`order_id`),
        INDEX `idx_osp_spare_part` (`spare_part_id`),
        INDEX `idx_osp_store` (`store_id`),
        INDEX `idx_osp_committed` (`is_committed`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$providersColumns = [
    'city' => 'VARCHAR(100) NULL',
    'district' => 'VARCHAR(100) NULL',
    'total_reviews' => 'INT DEFAULT 0',
    'total_orders' => 'INT DEFAULT 0',
    'completed_orders' => 'INT DEFAULT 0',
    'commission_rate' => 'DECIMAL(5,2) DEFAULT 15.00',
    'experience_years' => 'INT DEFAULT 0',
    'approved_at' => 'DATETIME NULL',
    'approved_by' => 'INT NULL',
    'bio' => 'TEXT NULL',
];
foreach ($providersColumns as $column => $definition) {
    addColumnIfMissing($conn, 'providers', $column, $definition);
}

$storesColumns = [
    'name_en' => 'VARCHAR(100) NULL',
    'phone' => 'VARCHAR(20) NULL',
    'email' => 'VARCHAR(100) NULL',
    'rating' => 'DECIMAL(3,2) DEFAULT 0.00',
];
foreach ($storesColumns as $column => $definition) {
    addColumnIfMissing($conn, 'stores', $column, $definition);
}

$offersColumns = [
    'target_audience' => "ENUM('all','new','existing') NOT NULL DEFAULT 'all'",
];
foreach ($offersColumns as $column => $definition) {
    addColumnIfMissing($conn, 'offers', $column, $definition);
}

$productsColumns = [
    'name_en' => 'VARCHAR(200) NULL',
    'original_price' => 'DECIMAL(10,2) NULL',
    'discount_percent' => 'INT DEFAULT 0',
    'stock_quantity' => 'INT DEFAULT 0',
    'description_ar' => 'TEXT NULL',
    'image' => 'VARCHAR(255) NULL',
];
foreach ($productsColumns as $column => $definition) {
    addColumnIfMissing($conn, 'products', $column, $definition);
}

$ordersColumns = [
    'address_id' => 'INT NULL',
    'problem_description' => 'TEXT NULL',
    'problem_images' => 'LONGTEXT NULL',
    'inspection_fee' => 'DECIMAL(10,2) DEFAULT 0.00',
    'service_fee' => 'DECIMAL(10,2) DEFAULT 0.00',
    'parts_fee' => 'DECIMAL(10,2) DEFAULT 0.00',
    'discount_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
    'payment_method' => "ENUM('cash','wallet','card','apple_pay') DEFAULT 'cash'",
    'payment_status' => "ENUM('pending','paid','refunded','failed') DEFAULT 'pending'",
    'started_at' => 'DATETIME NULL',
    'cancelled_at' => 'DATETIME NULL',
    'cancel_reason' => 'TEXT NULL',
    'cancelled_by' => "ENUM('user','provider','admin') NULL",
    'admin_notes' => 'TEXT NULL',
    'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
];
foreach ($ordersColumns as $column => $definition) {
    addColumnIfMissing($conn, 'orders', $column, $definition);
}

if (tableExists($conn, 'orders') && columnExists($conn, 'orders', 'status')) {
    $sql = "ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','accepted','assigned','on_the_way','arrived','in_progress','completed','cancelled','rejected') DEFAULT 'pending'";
    if ($conn->query($sql)) {
        echo "Updated orders.status enum values\n";
    } else {
        echo "Failed updating orders.status enum: " . $conn->error . "\n";
    }
}

$userAddressColumns = [
    'city' => 'VARCHAR(100) NULL',
    'district' => 'VARCHAR(100) NULL',
    'street' => 'VARCHAR(150) NULL',
];
foreach ($userAddressColumns as $column => $definition) {
    addColumnIfMissing($conn, 'user_addresses', $column, $definition);
}

echo "Schema update v6 completed.\n";
