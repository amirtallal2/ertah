<?php
// Fix path: we assume this is run from the directory itself or adjusts correctly
require_once __DIR__ . '/../config/database.php';

// Add is_rated column to orders table
$sql = "ALTER TABLE orders ADD COLUMN is_rated BOOLEAN DEFAULT 0";

if ($conn->query($sql) === TRUE) {
    echo "Column is_rated added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}
?>