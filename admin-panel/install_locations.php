<?php
/**
 * سكربت تثبيت جداول الدول والمدن - نسخة إصلاحية
 * Locations Database Installer - Force Reinstall
 */

require_once 'init.php';

try {
    echo "<h1>جاري تثبيت جداول المواقع (الدول والمدن)...</h1>";

    // تعطيل فحص المفاتيح الخارجية مؤقتاً للحذف
    db()->query("SET FOREIGN_KEY_CHECKS = 0");

    // 1. حذف الجداول القديمة لضمان الهيكلية الصحيحة
    db()->query("DROP TABLE IF EXISTS `cities`");
    db()->query("DROP TABLE IF EXISTS `countries`");

    echo "<p style='color:orange'>⚠️ تم حذف الجداول القديمة (cities, countries).</p>";

    db()->query("SET FOREIGN_KEY_CHECKS = 1");

    // 2. إنشاء جدول الدول
    $sqlCountries = "CREATE TABLE `countries` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `name_ar` VARCHAR(100) NOT NULL,
        `name_en` VARCHAR(100) NOT NULL,
        `code` VARCHAR(5) NOT NULL,
        `phone_code` VARCHAR(10) NOT NULL,
        `flag` VARCHAR(255),
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    db()->query($sqlCountries);
    echo "<p style='color:green'>✅ تم إنشاء جدول الدول (countries).</p>";

    // 3. إنشاء جدول المدن
    $sqlCities = "CREATE TABLE `cities` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `country_id` INT NOT NULL,
        `name_ar` VARCHAR(100) NOT NULL,
        `name_en` VARCHAR(100) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    db()->query($sqlCities);
    echo "<p style='color:green'>✅ تم إنشاء جدول المدن (cities).</p>";

    // 4. إضافة السعودية
    db()->insert('countries', [
        'name_ar' => 'المملكة العربية السعودية',
        'name_en' => 'Saudi Arabia',
        'code' => 'SA',
        'phone_code' => '966',
        'is_active' => 1
    ]);
    $countryId = db()->getConnection()->lastInsertId();
    echo "<p>✅ تم إضافة السعودية.</p>";

    // 5. إضافة المدن
    $cities = [
        ['name_ar' => 'الرياض', 'name_en' => 'Riyadh'],
        ['name_ar' => 'جدة', 'name_en' => 'Jeddah'],
        ['name_ar' => 'الدمام', 'name_en' => 'Dammam'],
        ['name_ar' => 'مكة المكرمة', 'name_en' => 'Makkah'],
        ['name_ar' => 'المدينة المنورة', 'name_en' => 'Madinah'],
        ['name_ar' => 'الخبر', 'name_en' => 'Khobar'],
        ['name_ar' => 'أبها', 'name_en' => 'Abha'],
        ['name_ar' => 'تبوك', 'name_en' => 'Tabuk'],
        ['name_ar' => 'حائل', 'name_en' => 'Hail'],
        ['name_ar' => 'جازان', 'name_en' => 'Jazan']
    ];

    foreach ($cities as $city) {
        db()->insert('cities', [
            'country_id' => $countryId,
            'name_ar' => $city['name_ar'],
            'name_en' => $city['name_en'],
            'is_active' => 1
        ]);
    }
    echo "<p>✅ تم إضافة المدن الرئيسية.</p>";


    echo "<h3>🎉 تم التثبيت بنجاح وتحديث الهيكلية!</h3>";
    echo "<a href='index.php' style='padding:10px; background:blue; color:white; text-decoration:none;'>العودة للوحة التحكم</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>خطأ: " . $e->getMessage() . "</h3>";
}
