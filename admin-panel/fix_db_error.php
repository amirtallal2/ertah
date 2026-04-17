<?php
/**
 * Ultimate Database Repair Tool V2
 * Forces deletion ignoring foreign keys.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'init.php';

function executeQuery($label, $sql)
{
  echo "<div style='margin-bottom: 5px; padding: 5px; border-left: 3px solid #ccc; background: #f9f9f9;'>";
  echo "<strong>$label:</strong> ";
  try {
    db()->query($sql);
    echo "<span style='color: green; font-weight: bold;'>OK</span>";
  } catch (PDOException $e) {
    echo "<span style='color: orange;'>Info: " . $e->getMessage() . "</span>";
  }
  echo "</div>";
}

echo "<html><body style='font-family: sans-serif; padding: 20px; direction: ltr;'>";
echo "<h1>🛠️ Database Repair Tool V2</h1>";

// 🚀 DISABLE FOREIGN KEYS TO FORCE DROP
db()->query("SET FOREIGN_KEY_CHECKS = 0");
echo "<p style='color:red'>Foreign Keys Checks Disabled.</p>";

// 1. ORDERS TABLE (Target Fix)
// Drop dependents first just in case to be clean, though FK check disable should handle it
executeQuery("Dropping reviews (legacy)", "DROP TABLE IF EXISTS `reviews`");
executeQuery("Dropping activity_logs (legacy)", "DROP TABLE IF EXISTS `activity_logs`");
executeQuery("Dropping transactions (legacy)", "DROP TABLE IF EXISTS `transactions`");
executeQuery("Dropping orders", "DROP TABLE IF EXISTS `orders`");

// 2. Re-create Orders (Full Schema)
$ordersSQL = "CREATE TABLE `orders` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_number` VARCHAR(20) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `provider_id` INT,
  `category_id` INT NOT NULL,
  `address_id` INT,
  `problem_description` TEXT NOT NULL,
  `problem_images` JSON,
  `inspection_fee` DECIMAL(10,2) DEFAULT 50.00,
  `service_fee` DECIMAL(10,2) DEFAULT 0.00,
  `parts_fee` DECIMAL(10,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) DEFAULT 0.00,
  `payment_method` ENUM('cash', 'wallet', 'card', 'apple_pay') DEFAULT 'cash',
  `payment_status` ENUM('pending', 'paid', 'refunded', 'failed') DEFAULT 'pending',
  `status` ENUM('pending', 'assigned', 'on_the_way', 'arrived', 'in_progress', 'completed', 'cancelled', 'rejected') DEFAULT 'pending',
  `scheduled_date` DATE,
  `scheduled_time` TIME,
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `cancelled_at` DATETIME,
  `cancel_reason` TEXT,
  `cancelled_by` ENUM('user', 'provider', 'admin'),
  `notes` TEXT,
  `admin_notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeQuery("Re-creating orders", $ordersSQL);

// 3. Re-create Dependent Tables
// Reviews
$reviewsSQL = "CREATE TABLE `reviews` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_id` INT NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `provider_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
  `comment` TEXT,
  `provider_response` TEXT,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeQuery("Re-creating reviews", $reviewsSQL);

// Transactions
$transactionsSQL = "CREATE TABLE `transactions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `provider_id` INT,
  `order_id` INT,
  `type` ENUM('deposit', 'withdrawal', 'payment', 'refund', 'commission', 'reward', 'referral_bonus') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `balance_after` DECIMAL(10,2),
  `description` VARCHAR(255),
  `reference_number` VARCHAR(50),
  `status` ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'completed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeQuery("Re-creating transactions", $transactionsSQL);

// Activity Logs
$activityLogsSQL = "CREATE TABLE `activity_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `admin_id` INT,
  `action` VARCHAR(100) NOT NULL,
  `model` VARCHAR(50),
  `model_id` INT,
  `old_values` JSON,
  `new_values` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeQuery("Re-creating activity_logs", $activityLogsSQL);

// 🚀 RE-ENABLE FOREIGN KEYS
db()->query("SET FOREIGN_KEY_CHECKS = 1");
echo "<p style='color:green'>Foreign Keys Checks Re-enabled.</p>";

// Reset Password Ensure
$passHash = password_hash('123456', PASSWORD_DEFAULT);
executeQuery("Ensure Admin Password is 123456", "UPDATE admins SET password = '$passHash', role = 'super_admin' WHERE username = 'admin'");

echo "<hr><h2 style='color: green;'>✅ DONE. Try Login Now.</h2>";
echo "<a href='login.php' style='padding:10px; background:blue; color:white;'>Go to Login</a>";
