<?php
/**
 * إعدادات قاعدة البيانات
 * Database Configuration
 */

// Helper to read environment variables with defaults
$env = function ($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
};

// إعدادات قاعدة البيانات
define('DB_HOST', $env('DB_HOST', 'localhost'));
define('DB_NAME', $env('DB_NAME', 'ertah_admin'));
define('DB_USER', $env('DB_USER', 'ertah_admin'));
define('DB_PASS', $env('DB_PASS', 'amAM123123@'));
define('DB_CHARSET', 'utf8mb4');

// إعدادات التطبيق
define('APP_NAME', 'Darfix');

// Prefer APP_URL from environment, otherwise auto-detect based on current host/path.
$appUrl = rtrim((string) $env('APP_URL', ''), '/');
if ($appUrl === '') {
    $isHttps = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $isHttps = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        $isHttps = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $isHttps = true;
    }

    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $adminPanelPos = strpos($scriptName, '/admin-panel');

    if ($adminPanelPos !== false) {
        $basePath = substr($scriptName, 0, $adminPanelPos + strlen('/admin-panel'));
    } else {
        $basePath = '/admin-panel';
    }

    $appUrl = $scheme . '://' . $host . $basePath;
}

define('APP_URL', $appUrl);
define('APP_VERSION', '1.0.0');

// Payment Gateway (MyFatoorah)
define(
    'MYFATOORAH_BASE_URL',
    rtrim((string) $env('MYFATOORAH_BASE_URL', 'https://api-sa.myfatoorah.com'), '/')
);
define('MYFATOORAH_TOKEN', trim((string) $env('MYFATOORAH_TOKEN', '')));

// Push Notifications (OneSignal)
define(
    'ONESIGNAL_APP_ID',
    trim((string) $env('ONESIGNAL_APP_ID', '13bf8a95-130c-4e86-b4cc-e91bd3b12322'))
);
define(
    'ONESIGNAL_REST_API_KEY',
    trim((string) $env('ONESIGNAL_REST_API_KEY', ''))
);

// إعدادات الأمان
define('SESSION_NAME', 'ERTAH_ADMIN_SESSION');
define('SESSION_LIFETIME', 86400); // 24 ساعة
define('CSRF_TOKEN_NAME', 'csrf_token');

// إعدادات الملفات
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'tif', 'ico', 'heic', 'heif', 'avif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);

// إعدادات التصفح
define('ITEMS_PER_PAGE', 20);

// المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// تمكين/إيقاف عرض الأخطاء حسب البيئة
$appEnv = strtolower((string) $env('APP_ENV', ''));
if ($appEnv === '') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalHost = in_array($host, ['localhost', '127.0.0.1'], true)
        || strpos($host, '192.168.') === 0
        || strpos($host, '10.') === 0
        || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host);
    $appEnv = $isLocalHost ? 'development' : 'production';
}

if ($appEnv === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
