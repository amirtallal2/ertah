<?php
/**
 * Fix Services and Rewards Schema
 * تصحيح جداول الخدمات والمكافآت
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Fixing Services & Rewards Schema ===\n\n";

function addColumnIfNotExists($conn, $table, $column, $definition)
{
    // Check if table exists first
    $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
    if ($checkTable->num_rows === 0) {
        echo "⚠️ Table '$table' does not exist. Skipping column check.\n";
        return;
    }

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

// 1. Fix Services Table (Critical for Home Screen)
echo "\nChecking 'services' table...\n";
addColumnIfNotExists($conn, 'services', 'image', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists($conn, 'services', 'description_ar', "TEXT");
addColumnIfNotExists($conn, 'services', 'description_en', "TEXT");
addColumnIfNotExists($conn, 'services', 'is_featured', "TINYINT(1) DEFAULT 0");

// 2. Fix Rewards Table (For Rewards Screen)
echo "\nChecking 'rewards' table...\n";
// Create rewards table if not exists
$sql = "CREATE TABLE IF NOT EXISTS rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_en VARCHAR(255) DEFAULT NULL,
    title_ur VARCHAR(255) DEFAULT NULL,
    description TEXT,
    description_en TEXT,
    description_ur TEXT,
    points_required INT NOT NULL DEFAULT 0,
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'fixed',
    icon VARCHAR(255),
    color_class VARCHAR(50) DEFAULT 'primary',
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "✅ Checked/Created 'rewards' table\n";
} else {
    echo "❌ Failed to create 'rewards' table: " . $conn->error . "\n";
}

addColumnIfNotExists($conn, 'rewards', 'title_en', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists($conn, 'rewards', 'title_ur', "VARCHAR(255) DEFAULT NULL");
addColumnIfNotExists($conn, 'rewards', 'description_en', "TEXT");
addColumnIfNotExists($conn, 'rewards', 'description_ur', "TEXT");

// 3. Fix Users Table (Just in case)
echo "\nChecking 'users' table...\n";
addColumnIfNotExists($conn, 'users', 'points', "INT DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'wallet_balance', "DECIMAL(10,2) DEFAULT 0.00");
addColumnIfNotExists($conn, 'users', 'membership_level', "VARCHAR(50) DEFAULT 'silver'");

echo "\nDone!\n";
