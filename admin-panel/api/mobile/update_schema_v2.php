<?php
require_once __DIR__ . '/../config/database.php';

// Add pricing/invoice columns to orders table
$columns = [
    'min_estimate' => 'DECIMAL(10,2) DEFAULT NULL',
    'max_estimate' => 'DECIMAL(10,2) DEFAULT NULL',
    'labor_cost' => 'DECIMAL(10,2) DEFAULT 0.00',
    'parts_cost' => 'DECIMAL(10,2) DEFAULT 0.00',
    'invoice_items' => 'JSON DEFAULT NULL',
    'invoice_status' => "ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none'",
    'inspection_notes' => 'TEXT DEFAULT NULL'
];

foreach ($columns as $col => $def) {
    $sql = "SHOW COLUMNS FROM `orders` LIKE '$col'";
    $result = $conn->query($sql);
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE `orders` ADD COLUMN `$col` $def");
        echo "Added `$col` to orders table.\n";
    } else {
        echo "`$col` already exists.\n";
    }
}

echo "Schema update v2 completed.\n";
?>