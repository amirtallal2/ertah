<?php
/**
 * صفحة الملف الشخصي للأدمن
 * Admin Profile Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'الملف الشخصي';
$pageSubtitle = 'تعديل بيانات حسابك';

$adminId = $_SESSION['admin_id'];
$admin = db()->fetch("SELECT * FROM admins WHERE id = ?", [$adminId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = clean(post('full_name'));
    $email = clean(post('email'));
    $phone = clean(post('phone'));
    $currentPassword = post('current_password');
    $newPassword = post('new_password');
    $confirmPassword = post('confirm_password');

    // التحقق من الحقول الأساسية
    if (empty($fullName) || empty($email)) {
        setFlashMessage('danger', 'يرجى ملء جميع الحقول المطلوبة');
    } else {
        // التحقق من البريد الإلكتروني (يجب أن لا يكون مكرراً لمستخدم آخر)
        $exists = db()->fetch("SELECT id FROM admins WHERE email = ? AND id != ?", [$email, $adminId]);
        if ($exists) {
            setFlashMessage('danger', 'البريد الإلكتروني مستخدم بالفعل');
        } else {
            // تحديث البيانات الأساسية
            $data = [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone
            ];

            // تحديث الصورة الرمزية
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['avatar'], 'admins');
                if ($upload['success']) {
                    $data['avatar'] = $upload['path'];
                }
            }

            // تحديث كلمة المرور (إذا تم إدخالها)
            if (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    setFlashMessage('danger', 'يرجى إدخال كلمة المرور الحالية لتغيير كلمة المرور');
                    // سنقوم بإعادة التوجيه هنا لتجنب متابعة الكود وحفظ البيانات الجزئية إذا أردنا صرامة تامة
                    // لكن هنا سنحدث البيانات العادية ونظهر الخطأ لكلمة المرور فقط
                } elseif (!password_verify($currentPassword, $admin['password'])) {
                    setFlashMessage('danger', 'كلمة المرور الحالية غير صحيحة');
                } elseif ($newPassword !== $confirmPassword) {
                    setFlashMessage('danger', 'كلمة المرور الجديدة غير متطابقة');
                } else {
                    $data['password'] = hashPassword($newPassword);
                    setFlashMessage('success', 'تم تحديث البيانات وكلمة المرور بنجاح');
                    db()->update('admins', $data, 'id = ?', [$adminId]);
                    logActivity('update_profile', 'admins', $adminId);
                    redirect('profile.php');
                }
            } else {
                // تحديث البيانات فقط بدون كلمة المرور
                db()->update('admins', $data, 'id = ?', [$adminId]);
                logActivity('update_profile', 'admins', $adminId);
                setFlashMessage('success', 'تم تحديث الملف الشخصي بنجاح');
                redirect('profile.php');
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card animate-slideUp" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">تعديل الملف الشخصي</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">

            <div style="display: flex; gap: 30px; align-items: flex-start;">
                <!-- الجزء الأيسر: الصورة -->
                <div style="width: 200px; text-align: center;">
                    <div
                        style="width: 150px; height: 150px; margin: 0 auto 15px; border-radius: 50%; overflow: hidden; border: 3px solid #eee;">
                        <img src="<?php echo imageUrl($admin['avatar'], 'https://ui-avatars.com/api/?name=' . urlencode($admin['full_name'])); ?>"
                            alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <label class="btn btn-outline btn-sm btn-block" style="cursor: pointer;">
                        <i class="fas fa-camera"></i> تغيير الصورة
                        <input type="file" name="avatar" accept="image/*" style="display: none;">
                    </label>
                </div>

                <!-- الجزء الأيمن: البيانات -->
                <div style="flex: 1;">
                    <div class="form-group">
                        <label class="form-label">الاسم الكامل</label>
                        <input type="text" name="full_name" class="form-control"
                            value="<?php echo $admin['full_name']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $admin['email']; ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">اسم المستخدم (للدخول)</label>
                        <input type="text" class="form-control" value="<?php echo $admin['username']; ?>" disabled
                            style="background-color: #f3f4f6; cursor: not-allowed;">
                        <small class="text-muted">لا يمكن تغيير اسم المستخدم</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?php echo $admin['phone'] ?? ''; ?>">
                    </div>

                    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">
                    <h4 style="margin-bottom: 20px;">تغيير كلمة المرور</h4>

                    <div class="form-group">
                        <label class="form-label">كلمة المرور الحالية</label>
                        <input type="password" name="current_password" class="form-control"
                            placeholder="اتركها فارغة إذا كنت لا تريد التغيير">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">تأكيد كلمة المرور</label>
                            <input type="password" name="confirm_password" class="form-control">
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>