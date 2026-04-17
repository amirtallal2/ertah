<?php
/**
 * Run Migration Script
 * تشغيل ملفات الترحيل
 */

header('Content-Type: text/plain');

require_once __DIR__ . '/../config/database.php';

echo "=== Running Database Migration ===\n\n";

$sqlFile = __DIR__ . '/../../database/migrations/create_missing_tables.sql';

if (!file_exists($sqlFile)) {
    die("❌ Migration file not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Remove comments
$sql = preg_replace('/--.*$/m', '', $sql);

// Split by semicolon
$queries = explode(';', $sql);

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query))
        continue;

    if ($conn->query($query) === TRUE) {
        echo "✅ Query executed successfully: " . substr($query, 0, 50) . "...\n";
    } else {
        echo "⚠️ Error executing query: " . $conn->error . "\n";
        echo "Query: " . substr($query, 0, 100) . "...\n\n";
    }
}

echo "\n=== Migration Completed ===\n";
