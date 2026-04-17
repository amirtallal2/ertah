<?php
/**
 * صفحة تسجيل الدخول
 * Login Page
 */

require_once 'init.php';

// إذا كان المستخدم مسجل الدخول، توجيهه للوحة التحكم
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

// معالجة طلب تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $password = post('password');

    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        if (login($username, $password)) {
            redirect('index.php');
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - لوحة تحكم Darfix</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="login-page">
        <div class="login-container animate-slideUp">
            <div class="login-logo">
                <div
                    style="width: 80px; height: 80px; background: linear-gradient(135deg, #fbcc26, #f5c01f); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 40px;">
                    🏠
                </div>
                <h1>لوحة تحكم Darfix</h1>
                <p>مرحباً بك، سجل الدخول للمتابعة</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>
                        <?php echo $error; ?>
                    </span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">اسم المستخدم أو البريد</label>
                    <div class="input-group">
                        <i class="fas fa-user input-group-icon"></i>
                        <input type="text" class="form-control" name="username" placeholder="أدخل اسم المستخدم"
                            value="<?php echo post('username'); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-group-icon"></i>
                        <input type="password" class="form-control" name="password" placeholder="أدخل كلمة المرور"
                            required>
                    </div>
                </div>

                <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                    <label
                        style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: #6b7280;">
                        <input type="checkbox" name="remember" style="width: 18px; height: 18px;">
                        تذكرني
                    </label>
                    <a href="#" style="font-size: 14px; color: #7466ed;">نسيت كلمة المرور؟</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </button>
            </form>

            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #9ca3af;">
                    ©
                    <?php echo date('Y'); ?> Darfix - جميع الحقوق محفوظة
                </p>
            </div>
        </div>
    </div>
</body>

</html>
