<?php
/**
 * دوال المصادقة والجلسات
 * Authentication Functions
 */

session_name(SESSION_NAME);
session_start();

/**
 * التحقق من تسجيل الدخول
 */
function isLoggedIn()
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * الحصول على بيانات المشرف الحالي
 */
function getCurrentAdmin()
{
    if (!isLoggedIn()) {
        return null;
    }

    $admin = db()->fetch(
        "SELECT * FROM admins WHERE id = ? AND is_active = 1",
        [$_SESSION['admin_id']]
    );

    return $admin;
}

/**
 * تسجيل الدخول
 */
function login($username, $password)
{
    $admin = db()->fetch(
        "SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1",
        [$username, $username]
    );

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_name'] = $admin['full_name'];

        // تحديث وقت آخر تسجيل دخول
        db()->update(
            'admins',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $admin['id']]
        );

        // تسجيل النشاط
        logActivity('login', 'admins', $admin['id']);

        return true;
    }

    return false;
}

/**
 * تسجيل الخروج
 */
function logout()
{
    if (isLoggedIn()) {
        logActivity('logout', 'admins', $_SESSION['admin_id']);
    }

    session_unset();
    session_destroy();

    // إعادة التوجيه لصفحة تسجيل الدخول
    header('Location: login.php');
    exit;
}

/**
 * التحقق من الصلاحيات
 */
function hasPermission($permission)
{
    $admin = getCurrentAdmin();
    if (!$admin)
        return false;

    // المدير الأعلى لديه جميع الصلاحيات
    if ($admin['role'] === 'super_admin') {
        return true;
    }

    // جلب الصلاحيات من قاعدة البيانات
    $permissions = json_decode($admin['permissions'] ?? '[]', true);

    // تأكد أن النتيجة مصفوفة
    if (!is_array($permissions)) {
        $permissions = [];
    }

    return in_array($permission, $permissions);
}

/**
 * حماية الصفحة (يجب تسجيل الدخول)
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * إنشاء توكن CSRF
 */
function generateCSRFToken()
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * التحقق من توكن CSRF
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * تسجيل النشاط
 */
function logActivity($action, $model = null, $modelId = null, $oldValues = null, $newValues = null)
{
    if (!isLoggedIn())
        return;

    try {
        db()->insert('activity_logs', [
            'admin_id' => $_SESSION['admin_id'],
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Throwable $e) {
        // Ignore logging failures so admin actions keep working even if schema is missing.
    }
}

/**
 * تشفير كلمة المرور
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}
