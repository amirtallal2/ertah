<?php
/**
 * Fix Transactions Schema
 * إصلاح جدول المعاملات
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Fixing Transactions Schema ===\n\n";

// Create transactions table if not exists
$sql = "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance_after DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    status VARCHAR(50) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "✅ Checked/Created 'transactions' table\n";
} else {
    echo "❌ Failed to create 'transactions' table: " . $conn->error . "\n";
}

// Create user_addresses table if not exists (Bonus fix)
$sqlAddr = "CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'home',
    label VARCHAR(100),
    address TEXT NOT NULL,
    details TEXT,
    is_default TINYINT(1) DEFAULT 0,
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sqlAddr)) {
    echo "✅ Checked/Created 'user_addresses' table\n";
} else {
    echo "❌ Failed to create 'user_addresses' table: " . $conn->error . "\n";
}

echo "\nDone!\n";
