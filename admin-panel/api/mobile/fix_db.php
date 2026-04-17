<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Adjust path to config/database.php
// We are in admin-panel/api/mobile/
// database.php is in admin-panel/api/config/database.php ? 
// Step 2207 says: require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database.php';

echo "Adding otp_code column...\n";

// Check if column exists
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_code'");
if ($res && $res->num_rows > 0) {
    echo "Column exists.\n";
} else {
    $sql = "ALTER TABLE users ADD COLUMN otp_code VARCHAR(10) NULL AFTER phone";
    if ($conn->query($sql)) {
        echo "Column added successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}

// Ensure is_verified exists too
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
    echo "Added is_verified.\n";
}

echo "Done.\n";
