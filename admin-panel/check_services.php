<?php
require_once 'init.php';

try {
    $pdo = db()->getConnection();

    $stmt = $pdo->prepare("DESCRIBE services");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Columns in 'services' table:<br>";
    print_r($columns);

    // Also check content
    $stmt = $pdo->query("SELECT * FROM services LIMIT 5");
    $rows = $stmt->fetchAll();
    echo "<br><br>First 5 rows:<br>";
    print_r($rows);

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
