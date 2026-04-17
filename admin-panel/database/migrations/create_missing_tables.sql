-- Drop tables if they exist to reset schema
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `spare_parts`;

-- Create Services Table
CREATE TABLE `services` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name_ar` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100),
  `description_ar` TEXT,
  `description_en` TEXT,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `image` VARCHAR(255),
  `category_id` INT,
  `requests_count` INT DEFAULT 0,
  `rating` DECIMAL(3,2) DEFAULT 5.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`) ON DELETE SET NULL
);

-- Create Spare Parts Table
CREATE TABLE `spare_parts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT NULL,
  `category_id` INT NULL,
  `name_ar` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100),
  `description_ar` TEXT,
  `description_en` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `old_price` DECIMAL(10,2),
  `stock_quantity` INT DEFAULT 0,
  `image` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_spare_parts_store_id` (`store_id`),
  INDEX `idx_spare_parts_category_id` (`category_id`)
);

-- Create Store Account Entries Table
CREATE TABLE IF NOT EXISTS `store_account_entries` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT NOT NULL,
  `entry_type` ENUM('credit','debit') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `source` ENUM('manual','withdrawal','return','adjustment') NOT NULL DEFAULT 'manual',
  `notes` VARCHAR(255),
  `reference_id` INT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_store_account_store` (`store_id`),
  INDEX `idx_store_account_created` (`created_at`)
);

-- Create Store Spare Part Movements Table
CREATE TABLE IF NOT EXISTS `store_spare_part_movements` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT NOT NULL,
  `spare_part_id` INT NOT NULL,
  `movement_type` ENUM('withdrawal','return','adjustment_in','adjustment_out') NOT NULL DEFAULT 'withdrawal',
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2),
  `notes` VARCHAR(255),
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_store_movements_store` (`store_id`),
  INDEX `idx_store_movements_part` (`spare_part_id`),
  INDEX `idx_store_movements_created` (`created_at`)
);

-- Create Order Spare Parts Table (Provider requested parts per order)
CREATE TABLE IF NOT EXISTS `order_spare_parts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `provider_id` INT,
  `store_id` INT,
  `spare_part_id` INT,
  `spare_part_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(255),
  `is_committed` TINYINT(1) NOT NULL DEFAULT 0,
  `committed_at` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_osp_order` (`order_id`),
  INDEX `idx_osp_spare_part` (`spare_part_id`),
  INDEX `idx_osp_store` (`store_id`),
  INDEX `idx_osp_committed` (`is_committed`)
);

-- Service Area to Services Mapping Table
CREATE TABLE IF NOT EXISTS `service_area_services` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `service_area_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_service_area_service` (`service_area_id`, `service_id`),
  INDEX `idx_sas_service_area` (`service_area_id`),
  INDEX `idx_sas_service` (`service_id`)
);

-- Spare Part to Services Mapping Table
CREATE TABLE IF NOT EXISTS `spare_part_services` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `spare_part_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_spare_part_service` (`spare_part_id`, `service_id`),
  INDEX `idx_sps_spare_part` (`spare_part_id`),
  INDEX `idx_sps_service` (`service_id`)
);

-- Spare Part to Service Areas Mapping Table
CREATE TABLE IF NOT EXISTS `spare_part_service_areas` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `spare_part_id` INT NOT NULL,
  `service_area_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_spare_part_service_area` (`spare_part_id`, `service_area_id`),
  INDEX `idx_spsa_spare_part` (`spare_part_id`),
  INDEX `idx_spsa_service_area` (`service_area_id`)
);

-- Seed Services
INSERT INTO `services` (`name_ar`, `name_en`, `description_ar`, `price`, `image`, `category_id`, `requests_count`, `rating`, `is_featured`) VALUES
('صيانة مكيف سبليت', 'Split AC Maintenance', 'غسيل وتنظيف شامل للمكيف', 150.00, 'https://i.ibb.co/3W0Q0Q0/ac-maintenance.jpg', 3, 1250, 4.8, 1),
('تأسيس سباكة', 'Plumbing Installation', 'تأسيس مواسير وصرف للحمام والمطبخ', 500.00, 'https://i.ibb.co/0Q0Q0Q0/plumbing.jpg', 1, 850, 4.9, 1),
('تنظيف شقق', 'Apartment Cleaning', 'تنظيف شامل للأرضيات والجدران', 300.00, 'https://i.ibb.co/0Q0Q0Q0/cleaning.jpg', 4, 2100, 4.7, 1),
('فحص كهرباء', 'Electrical Inspection', 'فحص شامل للوحة الكهرباء والأسلاك', 100.00, 'https://i.ibb.co/0Q0Q0Q0/electric.jpg', 2, 600, 4.6, 1);

-- Seed Spare Parts
INSERT INTO `spare_parts` (`store_id`, `name_ar`, `name_en`, `description_ar`, `price`, `old_price`, `stock_quantity`, `image`, `is_active`) VALUES
(NULL, 'كمبروسر مكيف', 'AC Compressor', 'كمبروسر أصلي ضمان سنة شامل التركيب', 1200.00, 1500.00, 10, 'https://i.ibb.co/0Q0Q0Q0/compressor.jpg', 1),
(NULL, 'موتور مياه', 'Water Pump', 'موتور نصف حصان صامت', 450.00, 600.00, 15, 'https://i.ibb.co/0Q0Q0Q0/water-pump.jpg', 1),
(NULL, 'فلتر مياه', 'Water Filter', 'فلتر 7 مراحل تايواني', 750.00, 900.00, 20, 'https://i.ibb.co/0Q0Q0Q0/filter.jpg', 1),
(NULL, 'سخان فوري', 'Instant Heater', 'سخان مياه فوري موفر للطاقة', 350.00, 450.00, 12, 'https://i.ibb.co/0Q0Q0Q0/heater.jpg', 1);

-- Ensure Offers exist
INSERT IGNORE INTO `offers` (`title_ar`, `description_ar`, `discount_type`, `discount_value`, `start_date`, `end_date`, `is_active`) VALUES
('عرض الصيف', 'خصم خاص على جميع خدمات التكييف', 'percentage', 25.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1),
('باقة التوفير', 'اطلب 3 خدمات واحصل على الرابعة مجانا', 'fixed', 100.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 1);
