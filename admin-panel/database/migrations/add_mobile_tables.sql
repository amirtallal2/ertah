-- User Addresses Table
-- جدول عناوين المستخدمين

CREATE TABLE IF NOT EXISTS `user_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('home','work','other') DEFAULT 'home',
  `label` varchar(100) DEFAULT NULL,
  `address` text NOT NULL,
  `details` text DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some sample data

-- Sample Categories
INSERT IGNORE INTO `service_categories` (`id`, `name_ar`, `name_en`, `icon`, `is_active`, `sort_order`) VALUES
(1, 'سباكة', 'Plumbing', '🔧', 1, 1),
(2, 'كهرباء', 'Electrical', '⚡', 1, 2),
(3, 'تكييف', 'AC', '❄️', 1, 3),
(4, 'تنظيف', 'Cleaning', '🧹', 1, 4),
(5, 'نجارة', 'Carpentry', '🔨', 1, 5),
(6, 'أجهزة منزلية', 'Appliances', '🌀', 1, 6),
(7, 'دهان', 'Painting', '🎨', 1, 7),
(8, 'تبليط', 'Tiling', '🔲', 1, 8);

-- Sample Cities
INSERT IGNORE INTO `cities` (`id`, `name_ar`, `name_en`, `is_active`) VALUES
(1, 'الرياض', 'Riyadh', 1),
(2, 'جدة', 'Jeddah', 1),
(3, 'الدمام', 'Dammam', 1),
(4, 'مكة المكرمة', 'Makkah', 1),
(5, 'المدينة المنورة', 'Madinah', 1),
(6, 'الخبر', 'Khobar', 1);

-- Sample Banners
INSERT IGNORE INTO `banners` (`id`, `title`, `image`, `position`, `link_type`, `is_active`, `sort_order`) VALUES
(1, 'خدمات التنظيف', 'https://j.top4top.io/p_3621g6yx21.jpeg', 'home_slider', 'none', 1, 1),
(2, 'خدمات السباكة', 'https://i.top4top.io/p_3621bdx2z1.jpeg', 'home_slider', 'none', 1, 2),
(3, 'صيانة منزلية', 'https://l.top4top.io/p_3621gov4g3.jpeg', 'home_slider', 'none', 1, 3);

-- Sample Offers
INSERT IGNORE INTO `offers` (`id`, `title_ar`, `description_ar`, `discount_type`, `discount_value`, `start_date`, `end_date`, `is_active`) VALUES
(1, 'خصم 30% على صيانة المكيفات', 'استمتع بخصم خاص على جميع خدمات صيانة وتنظيف المكيفات', 'percentage', 30, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1),
(2, 'خصم 50 ريال على طلبك الأول', 'خصم خاص للمستخدمين الجدد على أول طلب', 'fixed', 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 1),
(3, 'خصم 20% على خدمات التنظيف', 'تنظيف شامل للمنزل بخصم مميز', 'percentage', 20, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1);

-- Sample Stores
INSERT IGNORE INTO `stores` (`id`, `name_ar`, `description`, `is_active`) VALUES
(1, 'WiFi Adapters', 'متجر متخصص في الإلكترونيات', 1),
(2, 'دهانات نكو', 'جميع أنواع الدهانات والأصباغ', 1),
(3, 'المثال الذكي', 'أجهزة ذكية ومعدات منزلية', 1),
(4, 'أدوات عدة', 'أدوات منزلية وصناعية', 1);
