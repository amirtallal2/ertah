<?php
require_once __DIR__ . '/../config/database.php';

// Add `attachments` column to `orders` table if not exists
$sql = "SHOW COLUMNS FROM `orders` LIKE 'attachments'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `attachments` JSON DEFAULT NULL");
    echo "Added `attachments` column to `orders` table.\n";
} else {
    echo "`attachments` column already exists.\n";
}

// Add `problem_details` column to `orders` table if not exists
$sql = "SHOW COLUMNS FROM `orders` LIKE 'problem_details'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `problem_details` JSON DEFAULT NULL");
    echo "Added `problem_details` column to `orders` table.\n";
} else {
    echo "`problem_details` column already exists.\n";
}

echo "Schema update completed.\n";
?>