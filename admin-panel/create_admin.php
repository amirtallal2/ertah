<?php
require_once 'init.php';

// بيانات الأدمن
$username = 'admin';
$password = '123456';
$email = 'admin@ertah.com';

// تشفير كلمة المرور
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// التحقق من وجود المستخدم
$exists = db()->fetch("SELECT * FROM admins WHERE username = ?", [$username]);

if ($exists) {
    // تحديث كلمة المرور
    db()->update('admins', [
        'password' => $hashed_password,
        'role' => 'super_admin' // التأكد من أنه سوبر أدمن
    ], "username = ?", [$username]);
    echo "<h1>تم تحديث كلمة مرور الأدمن بنجاح!</h1>";
} else {
    // إضافة مستخدم جديد
    db()->insert('admins', [
        'username' => $username,
        'email' => $email,
        'password' => $hashed_password,
        'full_name' => 'Super Admin',
        'role' => 'super_admin',
        'is_active' => 1
    ]);
    echo "<h1>تم إنشاء حساب الأدمن بنجاح!</h1>";
}

echo "<p><strong>اسم المستخدم:</strong> $username</p>";
echo "<p><strong>كلمة المرور:</strong> $password</p>";
echo "<p><a href='login.php'>الذهاب لصفحة تسجيل الدخول</a></p>";
