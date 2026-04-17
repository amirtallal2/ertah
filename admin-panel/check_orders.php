<?php
require_once 'init.php';

try {
    $pdo = db()->getConnection();

    $stmt = $pdo->prepare("DESCRIBE orders");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Columns in 'orders' table:<br>";
    print_r($columns);

    // Also check order_items if exists
    try {
        $stmt = $pdo->prepare("DESCRIBE order_items");
        $stmt->execute();
        $itemColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<br><br>Columns in 'order_items' table:<br>";
        print_r($itemColumns);
    } catch (Exception $e) {
        echo "<br><br>Table 'order_items' does not exist.";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
