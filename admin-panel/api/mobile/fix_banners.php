<?php
/**
 * Fix existing banners with bad dates
 * تصحيح البانرات الموجودة
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Banner Fix Script ===\n\n";

// Check current banners
$stmt = $conn->query("SELECT id, title, position, is_active, start_date, end_date, image FROM banners");
$banners = $stmt->fetch_all(MYSQLI_ASSOC);

echo "Total banners in database: " . count($banners) . "\n\n";

foreach ($banners as $b) {
    echo "ID: {$b['id']}\n";
    echo "  Title: {$b['title']}\n";
    echo "  Position: {$b['position']}\n";
    echo "  Active: {$b['is_active']}\n";
    echo "  Start Date: " . ($b['start_date'] ?: 'NULL') . "\n";
    echo "  End Date: " . ($b['end_date'] ?: 'NULL') . "\n";
    echo "  Image: {$b['image']}\n";
    echo "\n";
}

// Fix bad dates (0000-00-00 or empty strings)
echo "Fixing bad dates...\n";

$conn->query("UPDATE banners SET start_date = NULL WHERE start_date = '0000-00-00' OR start_date = ''");
$fixed1 = $conn->affected_rows;

$conn->query("UPDATE banners SET end_date = NULL WHERE end_date = '0000-00-00' OR end_date = ''");
$fixed2 = $conn->affected_rows;

echo "Fixed start_date: $fixed1 rows\n";
echo "Fixed end_date: $fixed2 rows\n\n";

// Check active home_slider banners
$stmt = $conn->query("SELECT * FROM banners WHERE is_active = 1 AND position = 'home_slider' AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())");
$activeSliders = $stmt->fetch_all(MYSQLI_ASSOC);

echo "Active home_slider banners (should appear in app): " . count($activeSliders) . "\n";
foreach ($activeSliders as $b) {
    echo "  - ID {$b['id']}: {$b['title']}\n";
}

echo "\nDone!\n";
