<?php
/**
 * Fix Database Schema for Admin Panel
 * تصحيح قاعدة البيانات لتوافق نصوص الأدمن
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Fixing Database Schema ===\n\n";

function addColumnIfNotExists($conn, $table, $column, $definition)
{
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check && $check->num_rows === 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if ($conn->query($sql)) {
            echo "✅ Added column '$column' to table '$table'\n";
        } else {
            echo "❌ Failed to add '$column' to '$table': " . $conn->error . "\n";
        }
    } else {
        echo "ℹ️ Column '$column' already exists in '$table'\n";
    }
}

// 1. Fix Stores Table
echo "\nChecking 'stores' table...\n";
addColumnIfNotExists($conn, 'stores', 'sort_order', "INT NOT NULL DEFAULT 0");
addColumnIfNotExists($conn, 'stores', 'is_featured', "TINYINT(1) NOT NULL DEFAULT 0");
addColumnIfNotExists($conn, 'stores', 'description_ar', "TEXT");
addColumnIfNotExists($conn, 'stores', 'description_en', "TEXT");
addColumnIfNotExists($conn, 'stores', 'address', "VARCHAR(255)");
addColumnIfNotExists($conn, 'stores', 'logo', "VARCHAR(255)");
addColumnIfNotExists($conn, 'stores', 'banner', "VARCHAR(255)");

// 2. Fix Service Categories
echo "\nChecking 'service_categories' table...\n";
addColumnIfNotExists($conn, 'service_categories', 'sort_order', "INT NOT NULL DEFAULT 0");
addColumnIfNotExists($conn, 'service_categories', 'color', "VARCHAR(50)"); // Even if we removed likely fields, DB might need it
addColumnIfNotExists($conn, 'service_categories', 'icon', "VARCHAR(255)");

// 3. Fix Products
echo "\nChecking 'products' table...\n";
addColumnIfNotExists($conn, 'products', 'sort_order', "INT NOT NULL DEFAULT 0");
addColumnIfNotExists($conn, 'products', 'is_featured', "TINYINT(1) NOT NULL DEFAULT 0");

// 4. Fix Services
echo "\nChecking 'services' table...\n";
addColumnIfNotExists($conn, 'services', 'sort_order', "INT NOT NULL DEFAULT 0");

// 5. Fix Spare Parts (Optional)
// spare_parts table created recently, but checking just in case
echo "\nChecking 'spare_parts' table...\n";
// Ensure table exists first (it should)
$checkTable = $conn->query("SHOW TABLES LIKE 'spare_parts'");
if ($checkTable && $checkTable->num_rows > 0) {
    addColumnIfNotExists($conn, 'spare_parts', 'store_id', "INT NULL");
    addColumnIfNotExists($conn, 'spare_parts', 'old_price', "DECIMAL(10,2) NULL");
    addColumnIfNotExists($conn, 'spare_parts', 'sort_order', "INT NOT NULL DEFAULT 0");
    addColumnIfNotExists($conn, 'spare_parts', 'stock_quantity', "INT NOT NULL DEFAULT 0");
    addColumnIfNotExists($conn, 'spare_parts', 'price_with_installation', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    addColumnIfNotExists($conn, 'spare_parts', 'price_without_installation', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    addColumnIfNotExists($conn, 'spare_parts', 'old_price_with_installation', "DECIMAL(10,2) NULL");
    addColumnIfNotExists($conn, 'spare_parts', 'old_price_without_installation', "DECIMAL(10,2) NULL");

    $conn->query("UPDATE spare_parts SET price_with_installation = price WHERE price_with_installation <= 0 OR price_with_installation IS NULL");
    $conn->query("UPDATE spare_parts SET price_without_installation = price WHERE price_without_installation <= 0 OR price_without_installation IS NULL");
    $conn->query("UPDATE spare_parts SET old_price_with_installation = old_price WHERE old_price_with_installation IS NULL AND old_price IS NOT NULL");
    $conn->query("UPDATE spare_parts SET old_price_without_installation = old_price WHERE old_price_without_installation IS NULL AND old_price IS NOT NULL");
}

echo "\nChecking store accounting tables...\n";
$conn->query("CREATE TABLE IF NOT EXISTS `store_account_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `entry_type` ENUM('credit','debit') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `source` ENUM('manual','withdrawal','return','adjustment') NOT NULL DEFAULT 'manual',
    `notes` VARCHAR(255) NULL,
    `reference_id` INT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_store_account_store` (`store_id`),
    INDEX `idx_store_account_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `store_spare_part_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `spare_part_id` INT NOT NULL,
    `movement_type` ENUM('withdrawal','return','adjustment_in','adjustment_out') NOT NULL DEFAULT 'withdrawal',
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NULL,
    `notes` VARCHAR(255) NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_store_movements_store` (`store_id`),
    INDEX `idx_store_movements_part` (`spare_part_id`),
    INDEX `idx_store_movements_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($conn->error) {
    echo "❌ Error while creating store accounting tables: " . $conn->error . "\n";
} else {
    echo "✅ Store accounting tables checked/created\n";
}

echo "\nChecking order spare parts table...\n";
$conn->query("CREATE TABLE IF NOT EXISTS `order_spare_parts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "\nDone!\n";
