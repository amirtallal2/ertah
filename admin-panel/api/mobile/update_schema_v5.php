<?php
/**
 * Schema update v5
 * - Add problem_detail_options table for category/service-bound issue details
 */

require_once __DIR__ . '/../config/database.php';

$createSql = "
CREATE TABLE IF NOT EXISTS `problem_detail_options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `service_id` INT NULL,
  `title_ar` VARCHAR(255) NOT NULL,
  `title_en` VARCHAR(255) NULL,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_problem_detail_category` (`category_id`),
  INDEX `idx_problem_detail_service` (`service_id`),
  INDEX `idx_problem_detail_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if ($conn->query($createSql)) {
    echo "problem_detail_options table is ready.\n";
} else {
    echo "Failed to create problem_detail_options: " . $conn->error . "\n";
}

echo "Schema update v5 completed.\n";
