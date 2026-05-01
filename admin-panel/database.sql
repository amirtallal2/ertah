-- =====================================================
-- قاعدة بيانات لوحة تحكم تطبيق Darfix
-- Darfix Admin Panel Database
-- Updated: Jan 2026
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS `ertah_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ertah_db`;

-- =====================================================
-- جدول المشرفين (Admins)
-- =====================================================
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `avatar` VARCHAR(255) DEFAULT 'default.png',
  `role` ENUM('super_admin', 'admin') DEFAULT 'admin',
  `permissions` JSON DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة مشرف سوبر افتراضي
-- كلمة المرور: 123456
INSERT INTO `admins` (`username`, `email`, `password`, `full_name`, `role`, `permissions`) VALUES
('admin', 'admin@ertah.com', '$2y$10$OMJqH3Qzg2YLunD6QQIcz.3HfISvT7syIeC9Um8ji00itzqOC4g9C', 'Super Admin', 'super_admin', NULL);
-- تم تعيين كلمة المرور: 123456

-- =====================================================
-- جدول المستخدمين (Users)
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(100),
  `avatar` VARCHAR(255) DEFAULT 'default-user.png',
  `wallet_balance` DECIMAL(10,2) DEFAULT 0.00,
  `points` INT DEFAULT 0,
  `membership_level` ENUM('silver', 'gold', 'platinum') DEFAULT 'silver',
  `referral_code` VARCHAR(20) UNIQUE,
  `referred_by` INT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_verified` TINYINT(1) DEFAULT 0,
  `device_token` VARCHAR(255),
  `last_login` DATETIME,
  `deleted_at` DATETIME NULL,
  `deletion_requested_at` DATETIME NULL,
  `deletion_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`referred_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول عناوين المستخدمين (User Addresses)
-- =====================================================
DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `type` ENUM('home', 'work', 'other') DEFAULT 'home',
  `label` VARCHAR(50),
  `address` TEXT NOT NULL,
  `details` TEXT,
  `lat` DECIMAL(10, 8),
  `lng` DECIMAL(11, 8),
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول فئات الخدمات (Service Categories)
-- =====================================================
DROP TABLE IF EXISTS `service_categories`;
CREATE TABLE `service_categories` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `parent_id` INT DEFAULT NULL,
  `name_ar` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100),
  `icon` VARCHAR(50),
  `image` VARCHAR(255),
  `warranty_days` INT DEFAULT 14,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_service_categories_parent_id` (`parent_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `service_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `service_categories` (`name_ar`, `name_en`, `icon`, `sort_order`) VALUES
('سباكة', 'Plumbing', '🔧', 1),
('كهرباء', 'Electrical', '⚡', 2),
('تكييف', 'AC', '❄️', 3),
('تنظيف', 'Cleaning', '🧹', 4);

-- =====================================================
-- جدول مقدمي الخدمات (Providers)
-- =====================================================
DROP TABLE IF EXISTS `providers`;
CREATE TABLE `providers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(100),
  `password` VARCHAR(255),
  `avatar` VARCHAR(255) DEFAULT 'default-provider.png',
  `status` ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
  `is_available` TINYINT(1) DEFAULT 1,
  `rating` DECIMAL(3,2) DEFAULT 0.00,
  `wallet_balance` DECIMAL(10,2) DEFAULT 0.00,
  `deleted_at` DATETIME NULL,
  `deletion_requested_at` DATETIME NULL,
  `deletion_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الطلبات (Orders)
-- =====================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_number` VARCHAR(20) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `provider_id` INT,
  `category_id` INT NOT NULL,
  `status` ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
  `total_amount` DECIMAL(10,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- خدمات نقل العفش (Furniture Moving Services)
-- =====================================================
DROP TABLE IF EXISTS `furniture_services`;
CREATE TABLE `furniture_services` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(150) NOT NULL,
  `name_en` VARCHAR(150) DEFAULT NULL,
  `description_ar` TEXT DEFAULT NULL,
  `description_en` TEXT DEFAULT NULL,
  `base_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_note` VARCHAR(255) DEFAULT NULL,
  `estimated_duration_hours` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `image` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_furniture_services_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- مناطق نقل العفش (Furniture Areas)
-- =====================================================
DROP TABLE IF EXISTS `furniture_areas`;
CREATE TABLE `furniture_areas` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(120) NOT NULL,
  `name_en` VARCHAR(120) DEFAULT NULL,
  `name_ur` VARCHAR(120) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_furniture_areas_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furniture_areas` (`name_ar`, `name_en`, `name_ur`, `sort_order`) VALUES
('الرياض', 'Riyadh', NULL, 1),
('جدة', 'Jeddah', NULL, 2),
('الدمام', 'Dammam', NULL, 3);

-- =====================================================
-- حقول نموذج طلب نقل العفش (Furniture Request Fields)
-- =====================================================
DROP TABLE IF EXISTS `furniture_request_fields`;
CREATE TABLE `furniture_request_fields` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `field_key` VARCHAR(80) NOT NULL,
  `label_ar` VARCHAR(150) NOT NULL,
  `label_en` VARCHAR(150) DEFAULT NULL,
  `label_ur` VARCHAR(150) DEFAULT NULL,
  `field_type` VARCHAR(30) NOT NULL DEFAULT 'text',
  `placeholder_ar` VARCHAR(255) DEFAULT NULL,
  `placeholder_en` VARCHAR(255) DEFAULT NULL,
  `placeholder_ur` VARCHAR(255) DEFAULT NULL,
  `options_json` LONGTEXT DEFAULT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_furniture_request_field_key` (`field_key`),
  INDEX `idx_furniture_request_fields_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furniture_request_fields`
(`field_key`,`label_ar`,`label_en`,`label_ur`,`field_type`,`placeholder_ar`,`placeholder_en`,`placeholder_ur`,`is_required`,`is_active`,`sort_order`) VALUES
('rooms_count','عدد الغرف','Rooms Count',NULL,'number','مثال: 3','Example: 3',NULL,1,1,1),
('floors_from','الدور في موقع التحميل','Pickup Floor',NULL,'number','مثال: 2','Example: 2',NULL,1,1,2),
('floors_to','الدور في موقع التنزيل','Dropoff Floor',NULL,'number','مثال: 4','Example: 4',NULL,1,1,3),
('elevator_from','هل يوجد مصعد في موقع التحميل؟','Elevator at Pickup?',NULL,'checkbox',NULL,NULL,NULL,0,1,4),
('elevator_to','هل يوجد مصعد في موقع التنزيل؟','Elevator at Dropoff?',NULL,'checkbox',NULL,NULL,NULL,0,1,5),
('needs_packing','هل تحتاج خدمة تغليف؟','Needs Packing?',NULL,'checkbox',NULL,NULL,NULL,0,1,6),
('estimated_items','عدد القطع التقريبي','Estimated Items',NULL,'number','مثال: 25','Example: 25',NULL,0,1,7);

-- =====================================================
-- طلبات نقل العفش (Furniture Moving Requests)
-- =====================================================
DROP TABLE IF EXISTS `furniture_requests`;
CREATE TABLE `furniture_requests` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `request_number` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT DEFAULT NULL,
  `service_id` INT DEFAULT NULL,
  `area_id` INT DEFAULT NULL,
  `area_name` VARCHAR(150) DEFAULT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `pickup_city` VARCHAR(100) DEFAULT NULL,
  `pickup_address` TEXT DEFAULT NULL,
  `dropoff_city` VARCHAR(100) DEFAULT NULL,
  `dropoff_address` TEXT DEFAULT NULL,
  `move_date` DATE DEFAULT NULL,
  `preferred_time` VARCHAR(50) DEFAULT NULL,
  `rooms_count` INT NOT NULL DEFAULT 1,
  `floors_from` INT NOT NULL DEFAULT 0,
  `floors_to` INT NOT NULL DEFAULT 0,
  `elevator_from` TINYINT(1) NOT NULL DEFAULT 0,
  `elevator_to` TINYINT(1) NOT NULL DEFAULT 0,
  `needs_packing` TINYINT(1) NOT NULL DEFAULT 0,
  `estimated_items` INT NOT NULL DEFAULT 0,
  `details_json` LONGTEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'new',
  `estimated_price` DECIMAL(10,2) DEFAULT NULL,
  `final_price` DECIMAL(10,2) DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_furniture_requests_status` (`status`),
  INDEX `idx_furniture_requests_service` (`service_id`),
  INDEX `idx_furniture_requests_area` (`area_id`),
  INDEX `idx_furniture_requests_user` (`user_id`),
  INDEX `idx_furniture_requests_move_date` (`move_date`),
  INDEX `idx_furniture_requests_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- خدمات الحاويات (Container Services)
-- =====================================================
DROP TABLE IF EXISTS `container_services`;
CREATE TABLE `container_services` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(150) NOT NULL,
  `name_en` VARCHAR(150) DEFAULT NULL,
  `name_ur` VARCHAR(150) DEFAULT NULL,
  `description_ar` TEXT DEFAULT NULL,
  `description_en` TEXT DEFAULT NULL,
  `description_ur` TEXT DEFAULT NULL,
  `container_size` VARCHAR(100) NOT NULL,
  `capacity_ton` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `daily_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `weekly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_per_kg` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_per_meter` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `minimum_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `store_id` INT DEFAULT NULL,
  `price_note` VARCHAR(255) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_container_services_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- طلبات الحاويات (Container Requests)
-- =====================================================
DROP TABLE IF EXISTS `container_requests`;
CREATE TABLE `container_requests` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `request_number` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT DEFAULT NULL,
  `container_service_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `site_city` VARCHAR(100) DEFAULT NULL,
  `site_address` TEXT DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `duration_days` INT NOT NULL DEFAULT 1,
  `quantity` INT NOT NULL DEFAULT 1,
  `needs_loading_help` TINYINT(1) NOT NULL DEFAULT 0,
  `needs_operator` TINYINT(1) NOT NULL DEFAULT 0,
  `purpose` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'new',
  `estimated_price` DECIMAL(10,2) DEFAULT NULL,
  `final_price` DECIMAL(10,2) DEFAULT NULL,
  `estimated_weight_kg` DECIMAL(10,2) DEFAULT NULL,
  `estimated_distance_meters` DECIMAL(10,2) DEFAULT NULL,
  `details_json` LONGTEXT DEFAULT NULL,
  `media_json` LONGTEXT DEFAULT NULL,
  `source_order_id` INT DEFAULT NULL,
  `container_store_id` INT DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_container_requests_status` (`status`),
  INDEX `idx_container_requests_service` (`container_service_id`),
  INDEX `idx_container_requests_user` (`user_id`),
  INDEX `idx_container_requests_start_date` (`start_date`),
  INDEX `idx_container_requests_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- متاجر الحاويات وحساباتها (Container Stores Accounts)
-- =====================================================
DROP TABLE IF EXISTS `container_stores`;
CREATE TABLE `container_stores` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(150) NOT NULL,
  `name_en` VARCHAR(150) DEFAULT NULL,
  `name_ur` VARCHAR(150) DEFAULT NULL,
  `contact_person` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(40) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `reviews_count` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_container_stores_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `container_store_account_entries`;
CREATE TABLE `container_store_account_entries` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT NOT NULL,
  `entry_type` ENUM('credit','debit') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `source` ENUM('manual','request','payment','settlement','adjustment') NOT NULL DEFAULT 'manual',
  `reference_type` VARCHAR(60) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_container_store_account_store` (`store_id`),
  INDEX `idx_container_store_account_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `container_store_reviews`;
CREATE TABLE `container_store_reviews` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `order_id` INT DEFAULT NULL,
  `rating` TINYINT NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `quality_rating` TINYINT DEFAULT NULL,
  `speed_rating` TINYINT DEFAULT NULL,
  `price_rating` TINYINT DEFAULT NULL,
  `behavior_rating` TINYINT DEFAULT NULL,
  `tags` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_container_store_review_order` (`order_id`),
  INDEX `idx_container_store_reviews_store` (`store_id`),
  INDEX `idx_container_store_reviews_user` (`user_id`),
  INDEX `idx_container_store_reviews_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المدن (Cities)
-- =====================================================
DROP TABLE IF EXISTS `cities`;
CREATE TABLE `cities` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cities` (`name_ar`, `name_en`) VALUES 
('الرياض', 'Riyadh'),
('جدة', 'Jeddah'),
('الدمام', 'Dammam');

-- =====================================================
-- جدول أقسام المنتجات (Product Categories)
-- =====================================================
DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100),
  `image` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المتاجر (Stores)
-- =====================================================
DROP TABLE IF EXISTS `stores`;
CREATE TABLE `stores` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المنتجات (Products)
-- =====================================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(200) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `store_id` INT,
  `category_id` INT,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`),
  FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول التقييمات (Reviews)
-- =====================================================
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `provider_id` INT,
  `order_id` INT,
  `rating` INT NOT NULL COMMENT '1 to 5',
  `comment` TEXT,
  `is_verified` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المعاملات المالية (Transactions)
-- =====================================================
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المكافآت (Loyalty Rewards)
-- =====================================================
DROP TABLE IF EXISTS `rewards`;
CREATE TABLE `rewards` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `title_en` VARCHAR(200) DEFAULT NULL,
  `title_ur` VARCHAR(200) DEFAULT NULL,
  `description` TEXT,
  `description_en` TEXT,
  `description_ur` TEXT,
  `points_required` INT NOT NULL,
  `discount_value` DECIMAL(10,2) DEFAULT 0,
  `discount_type` ENUM('percentage', 'fixed') DEFAULT 'fixed',
  `icon` VARCHAR(50) DEFAULT 'gift',
  `color_class` VARCHAR(50) DEFAULT 'warning',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الإعدادات (Settings)
-- =====================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `key` VARCHAR(100) UNIQUE,
  `value` TEXT,
  `group` VARCHAR(50) DEFAULT 'general',
  `about_us` TEXT,
  `terms_and_conditions` TEXT,
  `privacy_policy` TEXT,
  `refund_policy` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`) VALUES ('app_name', 'Darfix');

-- =====================================================
-- جدول محتوى الصفحات الثابتة (Static App Pages Content)
-- =====================================================
DROP TABLE IF EXISTS `app_content_pages`;
CREATE TABLE `app_content_pages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_key` VARCHAR(50) NOT NULL,
  `language_code` VARCHAR(5) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `content` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_page_lang` (`page_key`, `language_code`),
  KEY `idx_page_key` (`page_key`),
  KEY `idx_language_code` (`language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_content_pages` (`page_key`, `language_code`, `title`, `content`) VALUES
('about', 'ar', 'عن Darfix', 'Darfix هو تطبيق سعودي رائد في مجال الخدمات المنزلية، نربط بين العملاء ومقدمي الخدمات المحترفين بسرعة وسهولة في مختلف مدن المملكة.\n\nنوفر لك تجربة موثوقة تشمل الحجز السريع، متابعة الطلب لحظياً، طرق دفع آمنة، ودعم متواصل لضمان أفضل تجربة خدمة.'),
('about', 'en', 'About Darfix', 'Darfix is a leading Saudi home-services app that connects customers with trusted service providers quickly and reliably across the Kingdom.\n\nWe provide a smooth experience with fast booking, real-time order tracking, secure payments, and responsive support.'),
('about', 'ur', 'Darfix کے بارے میں', 'Darfix سعودی عرب میں گھریلو خدمات کے لیے ایک نمایاں ایپ ہے جو صارفین کو قابلِ اعتماد سروس فراہم کنندگان سے تیزی اور آسانی کے ساتھ جوڑتی ہے۔\n\nہم تیز بکنگ، آرڈر کی لائیو ٹریکنگ، محفوظ ادائیگی اور مسلسل سپورٹ فراہم کرتے ہیں تاکہ بہترین تجربہ مل سکے۔'),
('privacy', 'ar', 'سياسة الخصوصية', 'تطبيق Darfix يحترم خصوصيتك. نقوم بجمع البيانات التي تقدمها (الاسم، رقم الهاتف، البريد الإلكتروني، العناوين، تفاصيل الطلب، الصور) والبيانات الناتجة عن الاستخدام (الموقع عند طلب الخدمة، معلومات الجهاز، سجلات الاستخدام).\n\nنستخدم هذه البيانات لتقديم الخدمة، ربطك بمقدمي خدمات مناسبين، تنفيذ الطلبات، إرسال الإشعارات، منع الاحتيال، وتحسين التطبيق.\n\nنشارك البيانات فقط مع مقدمي الخدمات المعنيين ومع مزودي خدمات موثوقين مثل الدفع والرسائل والخرائط وفق ضوابط تعاقدية. لا نبيع بياناتك.\n\nيمكنك الوصول إلى بياناتك أو تحديثها أو حذف حسابك من داخل التطبيق (الإعدادات > حذف الحساب) أو عبر فريق الدعم. عند حذف الحساب نحذف أو نُجهّل بياناتك الشخصية، وقد نحتفظ ببعض السجلات للالتزامات القانونية أو المحاسبية أو الأمان.\n\nللاستفسارات: support@darfix.org.'),
('privacy', 'en', 'Privacy Policy', 'Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org.'),
('privacy', 'ur', 'رازداری کی پالیسی', 'Darfix respects your privacy. We collect data you provide (name, phone number, email, addresses, order details, photos) and data generated during use (location when you request a service, device info, usage logs).\n\nWe use this data to deliver services, match you with providers, process orders, send notifications, prevent fraud, and improve the app.\n\nWe share data only with relevant service providers and trusted vendors such as payment, messaging, and maps under contractual safeguards. We do not sell your data.\n\nYou can access, update, or delete your account from the app (Settings > Delete Account) or by contacting support. When you delete your account, we remove or anonymize personal data; some records may be retained for legal, accounting, or safety obligations.\n\nFor questions: support@darfix.org.'),
('terms', 'ar', 'شروط الاستخدام', 'باستخدامك لتطبيق Darfix، فإنك توافق على الالتزام بشروط الاستخدام.\n\n1) يجب استخدام التطبيق بطريقة قانونية وعدم إساءة الاستخدام.\n2) الأسعار والمواعيد تخضع لتأكيد مقدم الخدمة.\n3) يمكن إلغاء الطلب وفق سياسة الإلغاء المعتمدة.\n4) يحق للتطبيق تحديث هذه الشروط عند الحاجة.'),
('terms', 'en', 'Terms of Use', 'By using Darfix, you agree to comply with these terms.\n\n1) The app must be used lawfully and without abuse.\n2) Pricing and scheduling are subject to provider confirmation.\n3) Orders may be canceled according to the cancellation policy.\n4) We reserve the right to update these terms when needed.'),
('terms', 'ur', 'استعمال کی شرائط', 'Darfix ایپ استعمال کرنے سے آپ ان شرائط کی پابندی سے اتفاق کرتے ہیں۔\n\n1) ایپ کو قانونی طور پر اور بغیر غلط استعمال کے استعمال کیا جائے۔\n2) قیمت اور وقت سروس فراہم کنندہ کی تصدیق کے تابع ہیں۔\n3) آرڈر منسوخی پالیسی کے مطابق منسوخ کیا جا سکتا ہے۔\n4) ضرورت کے مطابق ان شرائط میں تبدیلی کا حق محفوظ ہے۔'),
('refund', 'ar', 'سياسة الاسترداد', 'بعد تأكيد الطلب وبدء إجراءات التنفيذ، تصبح عملية الدفع غير قابلة للاسترداد.\n\nيمكن قبول طلبات الاسترداد فقط في الحالات التي يتعذر فيها تقديم الخدمة من طرفنا أو عند وجود خطأ في عملية الخصم.\n\nيتم تقديم طلب الاسترداد خلال 7 أيام عمل من تاريخ الدفع عبر فريق الدعم.'),
('refund', 'en', 'Refund Policy', 'Payments become non-refundable once the order is confirmed and processing starts.\n\nRefund requests are accepted only if the service cannot be delivered by us or if there is a billing error.\n\nPlease submit refund requests within 7 business days of payment via support.'),
('refund', 'ur', 'واپسی کی پالیسی', 'آرڈر کی تصدیق اور پراسیسنگ شروع ہونے کے بعد ادائیگی ناقابل واپسی ہو جاتی ہے۔\n\nواپسی کی درخواستیں صرف اس صورت میں قبول کی جائیں گی جب سروس فراہم نہ ہو سکے یا ادائیگی میں غلطی ہو۔\n\nبراہ کرم ادائیگی کے 7 کاروباری دنوں کے اندر سپورٹ سے رابطہ کریں۔');

-- =====================================================
-- جدول البانرات (Banners)
-- =====================================================
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `title` VARCHAR(200),
  `title_en` VARCHAR(200) DEFAULT NULL,
  `title_ur` VARCHAR(200) DEFAULT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `subtitle_en` VARCHAR(255) DEFAULT NULL,
  `subtitle_ur` VARCHAR(255) DEFAULT NULL,
  `background_color` VARCHAR(20) NOT NULL DEFAULT '#FBCC26',
  `background_color_end` VARCHAR(20) DEFAULT NULL,
  `image` VARCHAR(255) NOT NULL,
  `link` VARCHAR(500),
  `link_type` ENUM('category', 'offer', 'product', 'external', 'none') DEFAULT 'none',
  `link_id` INT,
  `position` ENUM('home_slider', 'home_middle', 'home_mid', 'home_popup', 'category', 'offer') DEFAULT 'home_slider',
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `start_date` DATE,
  `end_date` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول العروض (Offers)
-- =====================================================
DROP TABLE IF EXISTS `offers`;
CREATE TABLE `offers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `title_ar` VARCHAR(200) NOT NULL,
  `title_en` VARCHAR(200),
  `description_ar` TEXT,
  `description_en` TEXT,
  `image` VARCHAR(255),
  `discount_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
  `discount_value` DECIMAL(10,2) NOT NULL,
  `min_order_amount` DECIMAL(10,2) DEFAULT 0,
  `max_discount_amount` DECIMAL(10,2),
  `category_id` INT,
  `target_audience` ENUM('all', 'new', 'existing') NOT NULL DEFAULT 'all',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `usage_limit` INT DEFAULT NULL,
  `used_count` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول أكواد الخصم (Promo Codes)
-- =====================================================
DROP TABLE IF EXISTS `promo_codes`;
CREATE TABLE `promo_codes` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255),
  `discount_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
  `discount_value` DECIMAL(10,2) NOT NULL,
  `min_order_amount` DECIMAL(10,2) DEFAULT 0,
  `max_discount_amount` DECIMAL(10,2),
  `start_date` DATE,
  `end_date` DATE,
  `usage_limit` INT DEFAULT NULL,
  `usage_per_user` INT DEFAULT 1,
  `used_count` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الإشعارات (Notifications)
-- =====================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `provider_id` INT,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL,
  `type` ENUM('order', 'promotion', 'system', 'wallet', 'review') DEFAULT 'system',
  `data` JSON,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الشكاوى (Complaints)
-- =====================================================
DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
  `user_id` INT,
  `provider_id` INT,
  `order_id` INT,
  `subject` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `attachments` JSON,
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `status` ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
  `assigned_to` INT,
  `admin_notes` TEXT,
  `resolution` TEXT,
  `resolved_at` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول سجل النشاطات (Activity Logs)
-- =====================================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `admin_id` INT,
  `action` VARCHAR(100) NOT NULL,
  `model` VARCHAR(50),
  `model_id` INT,
  `old_values` JSON,
  `new_values` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
