<?php
/**
 * Create spare_parts table
 * إنشاء جدول قطع الغيار
 */

require_once __DIR__ . '/../config/database.php';

echo "Creating spare_parts table...\n";

$sql = "CREATE TABLE IF NOT EXISTS spare_parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL,
    category_id INT NULL,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    old_price DECIMAL(10,2) NULL,
    price_with_installation DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_without_installation DECIMAL(10,2) NOT NULL DEFAULT 0,
    old_price_with_installation DECIMAL(10,2) NULL,
    old_price_without_installation DECIMAL(10,2) NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    image VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_spare_parts_store_id (store_id),
    INDEX idx_spare_parts_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "Table 'spare_parts' created successfully!\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->query("CREATE TABLE IF NOT EXISTS store_account_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    entry_type ENUM('credit','debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    source ENUM('manual','withdrawal','return','adjustment') NOT NULL DEFAULT 'manual',
    notes VARCHAR(255) NULL,
    reference_id INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store_account_store (store_id),
    INDEX idx_store_account_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS store_spare_part_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    spare_part_id INT NOT NULL,
    movement_type ENUM('withdrawal','return','adjustment_in','adjustment_out') NOT NULL DEFAULT 'withdrawal',
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store_movements_store (store_id),
    INDEX idx_store_movements_part (spare_part_id),
    INDEX idx_store_movements_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create uploads folder for spare_parts
$uploadsDir = __DIR__ . '/../../uploads/spare_parts';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
    echo "Created uploads/spare_parts folder\n";
}

echo "Done!\n";
