<?php
require_once 'init.php';

try {
    $pdo = db()->getConnection();

    // Update requests_count for some services to make them appear in "Most Requested"
    // Use random counts between 50 and 500 for variety

    $services = $pdo->query("SELECT id FROM services ORDER BY RAND() LIMIT 5")->fetchAll();

    foreach ($services as $service) {
        $count = rand(50, 500);
        $stmt = $pdo->prepare("UPDATE services SET requests_count = ? WHERE id = ?");
        $stmt->execute([$count, $service['id']]);
        echo "Updated service ID {$service['id']} with requests_count = {$count}<br>";
    }

    // Ensure at least one service has a high count
    $stmt = $pdo->query("UPDATE services SET requests_count = 1000 WHERE id = (SELECT id FROM services ORDER BY id ASC LIMIT 1)");
    echo "Updated first service with requests_count = 1000 (Top Service)<br>";

    echo "✅ Dummy data updated successfully!";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
