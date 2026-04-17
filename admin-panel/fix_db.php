<?php
require_once 'init.php';

try {
    $pdo = db()->getConnection();

    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM spare_parts LIKE 'stock_quantity'");
    $stmt->execute();
    $exists = $stmt->fetch();

    if (!$exists) {
        // Add the column
        $sql = "ALTER TABLE spare_parts ADD COLUMN stock_quantity INT DEFAULT 0 AFTER price";
        $pdo->exec($sql);
        echo "✅ Created column 'stock_quantity' successfully.<br>";
    } else {
        echo "ℹ️ Column 'stock_quantity' already exists.<br>";
    }

    // Also check for sort_order just in case
    $stmt = $pdo->prepare("SHOW COLUMNS FROM spare_parts LIKE 'sort_order'");
    $stmt->execute();
    $exists = $stmt->fetch();

    if (!$exists) {
        $sql = "ALTER TABLE spare_parts ADD COLUMN sort_order INT DEFAULT 0";
        $pdo->exec($sql);
        echo "✅ Created column 'sort_order' successfully.<br>";
    } else {
        echo "ℹ️ Column 'sort_order' already exists.<br>";
    }

    echo "Done!";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
