<?php
/**
 * Schema update v4
 * - Appointment confirmation workflow columns on orders
 * - Customer reliability controls on users
 */

require_once __DIR__ . '/../config/database.php';

$orderColumns = [
    'confirmation_status' => "ENUM('pending','confirmed','unreachable','cancelled') DEFAULT 'pending'",
    'confirmation_due_at' => 'DATETIME DEFAULT NULL',
    'confirmation_attempts' => 'INT DEFAULT 0',
    'confirmation_notes' => 'TEXT DEFAULT NULL',
    'confirmed_at' => 'DATETIME DEFAULT NULL'
];

$userColumns = [
    'no_show_count' => 'INT DEFAULT 0',
    'is_blacklisted' => 'TINYINT(1) DEFAULT 0',
    'blacklist_reason' => 'TEXT DEFAULT NULL'
];

$reviewColumns = [
    'quality_rating' => 'TINYINT NULL',
    'speed_rating' => 'TINYINT NULL',
    'price_rating' => 'TINYINT NULL',
    'behavior_rating' => 'TINYINT NULL',
    'tags' => 'JSON DEFAULT NULL'
];

foreach ($orderColumns as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `orders` LIKE '$col'");
    if ($res && $res->num_rows == 0) {
        if ($conn->query("ALTER TABLE `orders` ADD COLUMN `$col` $definition")) {
            echo "Added orders.$col\n";
        } else {
            echo "Failed to add orders.$col: " . $conn->error . "\n";
        }
    } else {
        echo "orders.$col already exists\n";
    }
}

foreach ($userColumns as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `users` LIKE '$col'");
    if ($res && $res->num_rows == 0) {
        if ($conn->query("ALTER TABLE `users` ADD COLUMN `$col` $definition")) {
            echo "Added users.$col\n";
        } else {
            echo "Failed to add users.$col: " . $conn->error . "\n";
        }
    } else {
        echo "users.$col already exists\n";
    }
}

foreach ($reviewColumns as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `reviews` LIKE '$col'");
    if ($res && $res->num_rows == 0) {
        if ($conn->query("ALTER TABLE `reviews` ADD COLUMN `$col` $definition")) {
            echo "Added reviews.$col\n";
        } else {
            echo "Failed to add reviews.$col: " . $conn->error . "\n";
        }
    } else {
        echo "reviews.$col already exists\n";
    }
}

$serviceColumns = [
    'warranty_days' => 'INT DEFAULT 14',
    'parent_id' => 'INT NULL DEFAULT NULL'
];

foreach ($serviceColumns as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `service_categories` LIKE '$col'");
    if ($res && $res->num_rows == 0) {
        if ($conn->query("ALTER TABLE `service_categories` ADD COLUMN `$col` $definition")) {
            echo "Added service_categories.$col\n";
        } else {
            echo "Failed to add service_categories.$col: " . $conn->error . "\n";
        }
    } else {
        echo "service_categories.$col already exists\n";
    }
}

$serviceParentIndex = $conn->query("SHOW INDEX FROM `service_categories` WHERE Key_name = 'idx_service_categories_parent_id'");
if ($serviceParentIndex && $serviceParentIndex->num_rows == 0) {
    if ($conn->query("ALTER TABLE `service_categories` ADD INDEX `idx_service_categories_parent_id` (`parent_id`)")) {
        echo "Added service_categories.idx_service_categories_parent_id\n";
    } else {
        echo "Failed to add service_categories.idx_service_categories_parent_id: " . $conn->error . "\n";
    }
} else {
    echo "service_categories.idx_service_categories_parent_id already exists\n";
}

// Default configuration for confirmation lead time (hours)
$settingsTable = $conn->query("SHOW TABLES LIKE 'app_settings'");
if ($settingsTable && $settingsTable->num_rows > 0) {
    $defaultSettings = [
        'confirmation_lead_hours' => '2',
        'no_show_blacklist_threshold' => '3',
        'app_font' => 'cairo'
    ];

    foreach ($defaultSettings as $key => $value) {
        $exists = $conn->prepare("SELECT setting_key FROM app_settings WHERE setting_key = ? LIMIT 1");
        if ($exists) {
            $exists->bind_param("s", $key);
            $exists->execute();
            $row = $exists->get_result()->fetch_assoc();
            if (!$row) {
                $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->bind_param("ss", $key, $value);
                if ($stmt->execute()) {
                    echo "Added app_settings.$key = $value\n";
                } else {
                    echo "Failed to add app_settings.$key: " . $conn->error . "\n";
                }
            } else {
                echo "app_settings.$key already exists\n";
            }
        }
    }
}

echo "Schema update v4 completed.\n";
