<?php
/**
 * صفحة إدارة المشرفين والصلاحيات
 * Admins Management Page
 */

require_once '../init.php';
requireLogin();

// التحقق من الصلاحية (فقط السوبر أدمن)
$currentAdmin = getCurrentAdmin();
if ($currentAdmin['role'] !== 'super_admin') {
    die("صلاحيات غير كافية. هذه الصفحة للمدير العام فقط.");
}

$pageTitle = 'المشرفين والصلاحيات';
$pageSubtitle = 'إدارة حسابات المشرفين وتحديد صلاحياتهم';

$action = get('action', 'list');
$id = (int)get('id');

// قائمة الصلاحيات المتاحة في النظام
$availablePermissions = [
    'dashboard' => 'لوحة القيادة',
    'users' => 'إدارة المستخدمين',
    'providers' => 'إدارة الخدمات ومقدميها',
    'orders' => 'إدارة الطلبات',
    'products' => 'إدارة المنتجات والمتاجر',
    'offers' => 'إدارة العروض والكوبونات',
    'notifications' => 'إدارة الإشعارات',
    'complaints' => 'إدارة الشكاوى والتقييمات',
    'settings' => 'إعدادات النظام والمحتوى',
    'financial' => 'التقارير المالية والمعاملات'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');
    
    if ($postAction === 'add' || $postAction === 'edit') {
        $username = clean(post('username'));
        $email = clean(post('email'));
        $fullName = clean(post('full_name'));
        $password = post('password');
        $role = post('role'); // super_admin or admin
        $perms = $_POST['permissions'] ?? [];
        
        // التحقق من التكرار
        if ($postAction === 'add') {
            $exists = db()->fetch("SELECT 1 FROM admins WHERE username = ? OR email = ?", [$username, $email]);
            if ($exists) {
                setFlashMessage('danger', 'اسم المستخدم أو البريد الإلكتروني مستخدم مسبقاً');
                redirect('admins.php');
            }
        }
        
        $data = [
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'role' => $role,
            // إذا كان سوبر أدمن، الصلاحيات لا تهم (دائماً الكل)، لكن سنخزنها كـ NULL
            'permissions' => $role === 'super_admin' ? null : json_encode($perms),
            'is_active' => (int)post('is_active', 1)
        ];
        
        if (!empty($password)) {
            $data['password'] = hashPassword($password);
        }
        
        if ($postAction === 'add') {
            if (empty($password)) {
                setFlashMessage('danger', 'كلمة المرور مطلوبة');
                redirect('admins.php');
            }
            db()->insert('admins', $data);
            setFlashMessage('success', 'تم إضافة المشرف بنجاح');
        } else {
            // Edit
            $adminId = (int)post('id');
            // منع تعديل السوبر أدمن الرئيسي لنفسه (أو آخر سوبر أدمن) - سنبسطها هنا
            db()->update('admins', $data, 'id = ?', [$adminId]);
            setFlashMessage('success', 'تم تحديث بيانات المشرف');
        }
        redirect('admins.php');
    }
    
    if ($postAction === 'delete') {
        $adminId = (int)post('id');
        if ($adminId == $_SESSION['admin_id']) {
            setFlashMessage('danger', 'لا يمكنك حذف حسابك الحالي');
        } else {
            db()->delete('admins', 'id = ?', [$adminId]);
            setFlashMessage('success', 'تم حذف المشرف');
        }
        redirect('admins.php');
    }
}

// جلب المشرف
if ($action === 'edit' && $id) {
    $adminToEdit = db()->fetch("SELECT * FROM admins WHERE id = ?", [$id]);
    $adminPerms = json_decode($adminToEdit['permissions'] ?? '[]', true);
    if(!$adminPerms) $adminPerms = [];
}

// جلب القائمة
$admins = db()->fetchAll("SELECT * FROM admins ORDER BY created_at DESC");

include '../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    
    <!-- نموذج الإضافة/التعديل -->
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <?php echo $action === 'edit' ? 'تعديل بيانات المشرف' : 'إضافة مشرف جديد'; ?>
            </h3>
            <?php if ($action === 'edit'): ?>
                <a href="admins.php" class="btn btn-sm btn-outline">إلغاء</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $action === 'edit' ? 'edit' : 'add'; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $adminToEdit['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" required
                           value="<?php echo $action === 'edit' ? $adminToEdit['full_name'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>اسم المستخدم (للدخول)</label>
                    <input type="text" name="username" class="form-control" required
                           value="<?php echo $action === 'edit' ? $adminToEdit['username'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?php echo $action === 'edit' ? $adminToEdit['email'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور <?php echo $action === 'edit' ? '(اتركها فارغة لعدم التغيير)' : ''; ?></label>
                    <input type="password" name="password" class="form-control" <?php echo $action === 'add' ? 'required' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label>الدور (Role)</label>
                    <select name="role" class="form-control" id="role-select" onchange="togglePermissions()">
                        <option value="admin" <?php echo ($action === 'edit' && $adminToEdit['role'] === 'admin') ? 'selected' : ''; ?>>مشرف (صلاحيات محددة)</option>
                        <option value="super_admin" <?php echo ($action === 'edit' && $adminToEdit['role'] === 'super_admin') ? 'selected' : ''; ?>>مدير عام (كافة الصلاحيات)</option>
                    </select>
                </div>
                
                <div class="form-group" id="permissions-box">
                    <label style="display: block; margin-bottom: 10px; font-weight: bold;">تحديد الصلاحيات</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <?php foreach($availablePermissions as $key => $label): ?>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                                <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>"
                                    <?php echo ($action === 'edit' && in_array($key, $adminPerms)) ? 'checked' : ''; ?>>
                                <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <script>
                    function togglePermissions() {
                        var role = document.getElementById('role-select').value;
                        var box = document.getElementById('permissions-box');
                        if (role === 'super_admin') {
                            box.style.opacity = '0.5';
                            box.style.pointerEvents = 'none';
                        } else {
                            box.style.opacity = '1';
                            box.style.pointerEvents = 'auto';
                        }
                    }
                    // Run initially
                    togglePermissions();
                </script>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" 
                            <?php echo ($action === 'add' || ($action === 'edit' && $adminToEdit['is_active'])) ? 'checked' : ''; ?>>
                        حساب نشط
                    </label>
                </div>
                
                <button class="btn btn-primary btn-block">
                    <?php echo $action === 'edit' ? 'حفظ التغييرات' : 'إضافة المشرف'; ?>
                </button>
            </form>
        </div>
    </div>
    
    <!-- قائمة المشرفين -->
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">فريق العمل</h3>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>المشرف</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                        <th>تحكم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $adm): ?>
                    <tr>
                        <td>
                            <div style="font-weight: bold;"><?php echo $adm['full_name']; ?></div>
                            <div style="font-size: 11px; color: #666;"><?php echo $adm['username']; ?></div>
                        </td>
                        <td>
                            <?php if($adm['role'] === 'super_admin'): ?>
                                <span class="badge badge-warning">مدير عام</span>
                            <?php else: ?>
                                <span class="badge badge-primary">مشرف</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($adm['is_active']): ?>
                                <span class="badge badge-success">نشط</span>
                            <?php else: ?>
                                <span class="badge badge-danger">موقوف</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 11px;">
                            <?php echo $adm['last_login'] ? timeAgo($adm['last_login']) : '-'; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="?action=edit&id=<?php echo $adm['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a>
                                <?php if($adm['id'] != $_SESSION['admin_id']): ?>
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المشرف؟');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $adm['id']; ?>">
                                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
