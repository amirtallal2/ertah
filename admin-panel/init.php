<?php
/**
 * ملف التهيئة
 * Bootstrap File
 */

// تحميل إعدادات قاعدة البيانات
require_once __DIR__ . '/config/config.php';

// تحميل الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/database.php';

// تحميل دوال المساعدة
require_once __DIR__ . '/includes/functions.php';

// تحميل دوال المصادقة
require_once __DIR__ . '/includes/auth.php';
