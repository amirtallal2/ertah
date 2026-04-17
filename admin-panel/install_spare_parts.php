<?php
/**
 * تثبيت جدول قطع الغيار
 * Install Spare Parts Table
 */

require_once 'init.php';
require_once 'includes/store_accounting.php';

try {
    echo "<h1>جاري تثبيت جدول قطع الغيار...</h1>";

    $sql = "CREATE TABLE IF NOT EXISTS `spare_parts` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `store_id` INT NULL,
        `category_id` INT NULL,
        `name_ar` VARCHAR(255) NOT NULL,
        `name_en` VARCHAR(255) NOT NULL,
        `description_ar` TEXT,
        `description_en` TEXT,
        `image` VARCHAR(255),
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `old_price` DECIMAL(10,2) NULL,
        `price_with_installation` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `price_without_installation` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `old_price_with_installation` DECIMAL(10,2) NULL,
        `old_price_without_installation` DECIMAL(10,2) NULL,
        `stock_quantity` INT DEFAULT 0,
        `sort_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    db()->query($sql);
    ensureStoreSparePartsAccountingSchema();

    echo "<p style='color:green'>✅ تم إنشاء/تحديث جدول قطع الغيار (spare_parts) وربطه بالمتاجر بنجاح.</p>";
    echo "<a href='pages/spare-parts.php'>الذهاب لصفحة قطع الغيار</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>خطأ: " . $e->getMessage() . "</h3>";
}
