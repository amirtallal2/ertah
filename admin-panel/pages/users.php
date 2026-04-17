<?php
/**
 * صفحة إدارة المستخدمين
 * Users Management Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'المستخدمين';
$pageSubtitle = 'إدارة مستخدمي التطبيق';
$usersPagePath = basename((string) ($_SERVER['PHP_SELF'] ?? 'users.php'));
if ($usersPagePath === '' || $usersPagePath === '.' || $usersPagePath === '..') {
    $usersPagePath = 'users.php';
}

$action = get('action', 'list');
$id = (int) get('id');
ensureUsersMembershipSchema();

function tableHasColumn($table, $column)
{
    static $cache = [];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $table)) {
        return false;
    }

    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $rows = db()->fetchAll("SHOW COLUMNS FROM `{$table}`");
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
        return false;
    }

    $columns = [];
    foreach ($rows as $row) {
        $columns[$row['Field']] = true;
    }

    $cache[$cacheKey] = !empty($columns[$column]);
    return $cache[$cacheKey];
}

function ensureUsersMembershipSchema()
{
    if (!tableHasColumn('users', 'membership_level')) {
        db()->query("ALTER TABLE `users` ADD COLUMN `membership_level` VARCHAR(50) DEFAULT 'silver'");
        return;
    }

    try {
        $column = db()->fetch("SHOW COLUMNS FROM `users` LIKE 'membership_level'");
        $columnType = strtolower(trim((string) ($column['Type'] ?? '')));
        if ($columnType !== '' && strpos($columnType, 'varchar') === false) {
            db()->query("ALTER TABLE `users` MODIFY COLUMN `membership_level` VARCHAR(50) DEFAULT 'silver'");
        }
    } catch (Throwable $e) {
        error_log('users.php ensure membership schema failed: ' . $e->getMessage());
    }
}

function membershipLevelOptions(): array
{
    return [
        'silver' => 'عادية (Silver)',
        'gold' => 'مميزة (Gold)',
        'platinum' => 'بلاتينية (Platinum)',
        'premium' => 'Premium',
        'vip' => 'VIP',
    ];
}

function membershipLevelLabel($level): string
{
    $options = membershipLevelOptions();
    $normalized = strtolower(trim((string) $level));
    return $options[$normalized] ?? ($options['silver']);
}

function purgeUserRelatedRows($userId)
{
    $tables = ['complaint_replies', 'complaints', 'transactions'];

    foreach ($tables as $table) {
        if (tableHasColumn($table, 'user_id')) {
            db()->delete($table, 'user_id = ?', [$userId]);
        }
    }
}

function tableExistsSafe($table): bool
{
    static $cache = [];

    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($table === '') {
        return false;
    }

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $row = db()->fetch("SHOW TABLES LIKE ?", [$table]);
        $cache[$table] = !empty($row);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function filterTableDataByColumns(string $table, array $data): array
{
    if (empty($data) || !tableExistsSafe($table)) {
        return [];
    }

    $filtered = [];
    foreach ($data as $column => $value) {
        if (tableHasColumn($table, (string) $column)) {
            $filtered[$column] = $value;
        }
    }

    return $filtered;
}

function safeInsertNotificationForUser(int $userId, string $title, string $body, string $type = 'system'): bool
{
    if ($userId <= 0 || !tableExistsSafe('notifications')) {
        return false;
    }

    $data = [
        'user_id' => $userId,
        'title' => $title,
        'body' => $body,
        'type' => $type,
        'created_at' => date('Y-m-d H:i:s')
    ];
    if (tableHasColumn('notifications', 'is_read')) {
        $data['is_read'] = 0;
    }

    $data = filterTableDataByColumns('notifications', $data);
    if (empty($data)) {
        return false;
    }

    try {
        db()->insert('notifications', $data);
        return true;
    } catch (Throwable $e) {
        error_log('users.php notification insert failed: ' . $e->getMessage());
        return false;
    }
}

function safeInsertWalletTransaction(array $data): bool
{
    if (!tableExistsSafe('transactions')) {
        return false;
    }

    $payload = filterTableDataByColumns('transactions', $data);
    if (empty($payload)) {
        return false;
    }

    try {
        db()->insert('transactions', $payload);
        return true;
    } catch (Throwable $e) {
        error_log('users.php transaction insert failed: ' . $e->getMessage());
        return false;
    }
}

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'toggle_status') {
        $userId = (int) post('user_id');
        $user = db()->fetch("SELECT is_active FROM users WHERE id = ?", [$userId]);
        if ($user) {
            db()->update(
                'users',
                ['is_active' => $user['is_active'] ? 0 : 1],
                'id = :id',
                ['id' => $userId]
            );
            logActivity('toggle_user_status', 'users', $userId);
            setFlashMessage('success', 'تم تحديث حالة المستخدم بنجاح');
        }
        redirect($usersPagePath);
    }

    if ($postAction === 'update_info') {
        $userId = (int) post('user_id');
        $fullName = post('full_name');
        $phone = post('phone');
        $email = post('email');

        // التحقق من البيانات
        if (empty($fullName) || empty($phone)) {
            setFlashMessage('danger', 'الاسم ورقم الهاتف مطلوبان');
            redirect($usersPagePath . '?action=view&id=' . $userId);
        }

        // التحقق من تكرار الهاتف أو البريد
        $exists = db()->fetch(
            "SELECT id FROM users WHERE (phone = ? OR (email = ? AND email != '')) AND id != ?",
            [$phone, $email, $userId]
        );

        if ($exists) {
            setFlashMessage('danger', 'رقم الهاتف أو البريد الإلكتروني مسجل مسبقاً لمستخدم آخر');
            redirect($usersPagePath . '?action=view&id=' . $userId);
        }

        db()->update(
            'users',
            [
                'full_name' => $fullName,
                'phone' => $phone,
                'email' => $email
            ],
            'id = :id',
            ['id' => $userId]
        );

        // إرسال إشعار للمستخدم
        safeInsertNotificationForUser(
            $userId,
            'تحديث البيانات الشخصية',
            'تم تحديث بيانات ملفك الشخصي من قبل الإدارة',
            'system'
        );

        logActivity('update_user_info', 'users', $userId);
        setFlashMessage('success', 'تم تحديث بيانات المستخدم بنجاح');
        redirect($usersPagePath . '?action=view&id=' . $userId);
    }

    if ($postAction === 'update_wallet') {
        $userId = (int) post('user_id');
        $amount = (float) post('amount');
        $txType = post('transaction_type'); // deposit, reward, withdrawal
        $description = post('description');

        $allowedTypes = ['deposit', 'reward', 'withdrawal'];
        if ($userId <= 0 || $amount <= 0 || !in_array($txType, $allowedTypes, true)) {
            setFlashMessage('danger', 'بيانات تعديل المحفظة غير صالحة');
            redirect($usersPagePath . '?action=view&id=' . $userId);
        }

        $user = db()->fetch("SELECT wallet_balance FROM users WHERE id = ?", [$userId]);
        if ($user) {
            try {
                $currentBalance = (float) $user['wallet_balance'];

                // تحديد العملية حسابية (إضافة أم خصم)
                $isAddition = in_array($txType, ['deposit', 'reward'], true);

                $newBalance = $isAddition
                    ? $currentBalance + $amount
                    : $currentBalance - $amount;

                if ($newBalance < 0) {
                    $newBalance = 0;
                }

                db()->update(
                    'users',
                    ['wallet_balance' => $newBalance],
                    'id = :id',
                    ['id' => $userId]
                );

                // وصف افتراضي إذا لم يتم إدخاله
                if (empty($description)) {
                    $descriptions = [
                        'deposit' => 'إيداع رصيد من لوحة التحكم',
                        'reward' => 'مكافأة من الإدارة',
                        'withdrawal' => 'خصم رصيد من لوحة التحكم'
                    ];
                    $description = $descriptions[$txType] ?? 'تعديل من لوحة التحكم';
                }

                // تسجيل المعاملة (مع حماية توافق الأعمدة)
                safeInsertWalletTransaction([
                    'user_id' => $userId,
                    'type' => $txType,
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'description' => $description,
                    'status' => 'completed'
                ]);

                // إرسال إشعار للمستخدم
                $notifTitle = 'تحديث رصيد المحفظة';
                $notifBody = $isAddition
                    ? "تم إضافة رصيد بقيمة " . number_format($amount, 2) . " ⃁ إلى محفظتك. السبب: $description"
                    : "تم خصم رصيد بقيمة " . number_format($amount, 2) . " ⃁ من محفظتك. السبب: $description";

                safeInsertNotificationForUser($userId, $notifTitle, $notifBody, 'wallet');

                logActivity('update_wallet', 'users', $userId);
                setFlashMessage('success', 'تم تحديث رصيد المحفظة بنجاح');
            } catch (Throwable $e) {
                error_log('users.php update_wallet failed: ' . $e->getMessage());
                setFlashMessage('danger', 'تعذر تحديث المحفظة حالياً. تحقق من بنية الجداول ثم أعد المحاولة.');
            }
        }
        redirect($usersPagePath . '?action=view&id=' . $userId);
    }

    if ($postAction === 'update_points') {
        $userId = (int) post('user_id');
        $amount = (int) post('amount');
        $type = post('type'); // add or subtract

        $user = db()->fetch("SELECT points FROM users WHERE id = ?", [$userId]);
        if ($user) {
            $currentPoints = (int) $user['points'];
            $newPoints = $type === 'add'
                ? $currentPoints + $amount
                : $currentPoints - $amount;

            if ($newPoints < 0)
                $newPoints = 0;

            db()->update(
                'users',
                ['points' => $newPoints],
                'id = :id',
                ['id' => $userId]
            );

            // تسجيل العملية فيسجل المعاملات (اختياري للظهور في السجل)
            // نستخدم نوع 'reward' أو 'points' إذا كان مدعوماً، أو مجرد وصف واضح
            // سنستخدم 'reward' لضمان ظهورها في API المكافآت مع الوصف المناسب
            $desc = $type === 'add' ? "إضافة $amount نقطة مكافأة من الإدارة" : "خصم $amount نقطة من الإدارة";

            // ملاحظة: جدول المعاملات قد يتوقع مبلغ مالي، هنا سنخزن عدد النقاط في الحقل amount 
            // ولكن يجب الانتباه عند العرض. في تطبيق التوصيل عادة النقاط منفصلة عن الرصيد المالي.
            // لغرض العرض في شاشة المكافآت (history)، سنضيفها هنا.
            safeInsertWalletTransaction([
                'user_id' => $userId,
                'type' => 'reward',
                'amount' => $type === 'add' ? $amount : -$amount, // نخزن القيمة موجبة أو سالبة
                'balance_after' => $newPoints, // هنا نخزن رصيد النقاط بدلاً من رصيد المحفظة لهذا السجل
                'description' => $desc,
                'status' => 'completed'
            ]);

            // إرسال إشعار
            safeInsertNotificationForUser(
                $userId,
                'تحديث رصيد النقاط',
                $type === 'add'
                    ? "مبروك! تم إضافة $amount نقطة إلى رصيدك. رصيدك الحالي: $newPoints نقطة"
                    : "تم خصم $amount نقطة من رصيدك. رصيدك الحالي: $newPoints نقطة",
                'promotion'
            );

            logActivity('update_points', 'users', $userId);
            setFlashMessage('success', 'تم تحديث النقاط بنجاح');
        }
        redirect($usersPagePath . '?action=view&id=' . $userId);
    }

    if ($postAction === 'update_membership') {
        $userId = (int) post('user_id');
        $membershipLevel = strtolower(trim((string) post('membership_level', 'silver')));
        $allowedLevels = array_keys(membershipLevelOptions());

        if ($userId <= 0) {
            setFlashMessage('danger', 'معرف المستخدم غير صالح');
            redirect($usersPagePath);
        }

        if (!in_array($membershipLevel, $allowedLevels, true)) {
            setFlashMessage('danger', 'مستوى العضوية غير صالح');
            redirect($usersPagePath . '?action=view&id=' . $userId);
        }

        try {
            db()->update(
                'users',
                ['membership_level' => $membershipLevel],
                'id = :id',
                ['id' => $userId]
            );

            safeInsertNotificationForUser(
                $userId,
                'تحديث العضوية',
                'تم تحديث مستوى عضويتك إلى: ' . membershipLevelLabel($membershipLevel),
                'system'
            );

            logActivity('update_membership', 'users', $userId, [], ['membership_level' => $membershipLevel]);
            setFlashMessage('success', 'تم تحديث مستوى العضوية بنجاح');
        } catch (Throwable $e) {
            error_log('users.php update_membership failed: ' . $e->getMessage());
            setFlashMessage('danger', 'تعذر تحديث العضوية حالياً. تحقق من بنية الجدول ثم أعد المحاولة.');
        }
        redirect($usersPagePath . '?action=view&id=' . $userId);
    }

    if ($postAction === 'delete') {
        $userId = (int) post('user_id');
        if ($userId <= 0) {
            setFlashMessage('danger', 'معرّف المستخدم غير صالح');
            redirect($usersPagePath);
        }

        $user = db()->fetch("SELECT id, full_name FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            setFlashMessage('danger', 'المستخدم غير موجود');
            redirect($usersPagePath);
        }

        $conn = db()->getConnection();

        try {
            $conn->beginTransaction();

            // تنظيف البيانات المرتبطة التي قد لا تُحذف تلقائياً عبر العلاقات.
            purgeUserRelatedRows($userId);
            db()->delete('users', 'id = ?', [$userId]);

            $conn->commit();

            logActivity('delete_user', 'users', $userId, ['full_name' => $user['full_name']], ['deleted' => true]);
            setFlashMessage('success', 'تم حذف المستخدم نهائياً من المنصة');
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            setFlashMessage('danger', 'تعذر حذف المستخدم نهائياً. حاول مرة أخرى');
        }

        redirect($usersPagePath);
    }
}

// البحث والفلترة
$search = get('search');
$status = get('status');
$page = max(1, (int) get('page', 1));

$where = '1=1';
$params = [];

if ($search) {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status !== '' && $status !== null) {
    $where .= " AND is_active = ?";
    $params[] = (int) $status;
}

$totalUsers = db()->count('users', $where, $params);
$pagination = paginate($totalUsers, $page);

$users = db()->fetchAll("
    SELECT * FROM users 
    WHERE {$where}
    ORDER BY created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
", $params);

// عرض تفاصيل مستخدم
if ($action === 'view' && $id) {
    $categoryDisplayMap = getServiceCategoryDisplayMap(false);

    $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$user) {
        setFlashMessage('danger', 'المستخدم غير موجود');
        redirect($usersPagePath);
    }

    $userOrders = db()->fetchAll("
        SELECT o.*, c.name_ar as category_name
        FROM orders o
        LEFT JOIN service_categories c ON o.category_id = c.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ", [$id]);

    $userTransactions = db()->fetchAll("
        SELECT * FROM transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ", [$id]);

    $userAddresses = db()->fetchAll("SELECT * FROM user_addresses WHERE user_id = ?", [$id]);
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <!-- قائمة المستخدمين -->
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users" style="color: var(--primary-color);"></i>
                جميع المستخدمين (
                <?php echo number_format($totalUsers); ?>)
            </h3>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo $usersPagePath; ?>?status=1"
                    class="btn btn-sm <?php echo $status === '1' ? 'btn-success' : 'btn-outline'; ?>">
                    نشط
                </a>
                <a href="<?php echo $usersPagePath; ?>?status=0"
                    class="btn btn-sm <?php echo $status === '0' ? 'btn-danger' : 'btn-outline'; ?>">
                    غير نشط
                </a>
                <a href="<?php echo $usersPagePath; ?>"
                    class="btn btn-sm <?php echo $status === '' || $status === null ? 'btn-primary' : 'btn-outline'; ?>">
                    الكل
                </a>
            </div>
        </div>

        <div class="card-body">
            <!-- البحث -->
            <form method="GET" class="search-box">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="البحث بالاسم، الهاتف، البريد..."
                        value="<?php echo $search; ?>" id="search-input">
                </div>
            </form>

            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👤</div>
                    <h3>لا يوجد مستخدمين</h3>
                    <p>لم يتم العثور على أي مستخدمين مطابقين للبحث</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th>المستخدم</th>
                                <th>الهاتف</th>
                                <th>العضوية</th>
                                <th>المحفظة</th>
                                <th>الطلبات</th>
                                <th>الحالة</th>
                                <th>التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><input type="checkbox" class="select-item" value="<?php echo $user['id']; ?>"></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <img src="<?php echo imageUrl($user['avatar'], 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=fbcc26&color=fff'); ?>"
                                                alt="" class="avatar avatar-circle">
                                            <div>
                                                <strong>
                                                    <?php echo $user['full_name']; ?>
                                                </strong>
                                                <?php if ($user['email']): ?>
                                                    <div style="font-size: 12px; color: #6b7280;">
                                                        <?php echo $user['email']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td dir="ltr">
                                        <?php echo $user['phone']; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo membershipLevelLabel($user['membership_level'] ?? 'silver'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--success-color); font-weight: 600;">
                                            <?php echo number_format($user['wallet_balance'], 2); ?> ⃁
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $ordersCount = db()->count('orders', 'user_id = ?', [$user['id']]);
                                        echo $ordersCount;
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $user['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo timeAgo($user['created_at']); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="?action=view&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline"
                                                title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit"
                                                    class="btn btn-sm <?php echo $user['is_active'] ? 'btn-danger' : 'btn-success'; ?>"
                                                    title="<?php echo $user['is_active'] ? 'إيقاف' : 'تفعيل'; ?>">
                                                    <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;"
                                                onsubmit="return confirm('سيتم حذف المستخدم نهائياً مع بياناته المرتبطة. هل أنت متأكد؟');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="حذف نهائي">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- التصفح -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination" style="margin-top: 20px;">
                        <a href="?page=1&search=<?php echo $search; ?>&status=<?php echo $status; ?>"
                            class="page-link <?php echo $pagination['current_page'] == 1 ? 'disabled' : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                        <a href="?page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>"
                            class="page-link <?php echo !$pagination['has_prev'] ? 'disabled' : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>

                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>"
                                class="page-link <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <a href="?page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>"
                            class="page-link <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                        <a href="?page=<?php echo $pagination['total_pages']; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>"
                            class="page-link <?php echo $pagination['current_page'] == $pagination['total_pages'] ? 'disabled' : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'view' && isset($user)): ?>
    <!-- تفاصيل المستخدم -->
    <div style="margin-bottom: 20px;">
        <a href="<?php echo $usersPagePath; ?>" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            العودة للقائمة
        </a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
        <!-- معلومات المستخدم -->
        <div class="card animate-slideUp">
            <div class="card-body" style="text-align: center;">
                <img src="<?php echo imageUrl($user['avatar'], 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&size=150&background=fbcc26&color=fff'); ?>"
                    alt=""
                    style="width: 120px; height: 120px; border-radius: 50%; margin-bottom: 20px; border: 4px solid var(--primary-color);">

                <h3 style="margin-bottom: 5px;">
                    <?php echo $user['full_name']; ?>
                    <button type="button" onclick="showModal('edit-user-modal')" class="btn btn-sm btn-outline"
                        style="padding: 2px 8px; font-size: 10px; margin-right: 5px;">
                        <i class="fas fa-edit"></i>
                    </button>
                </h3>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    <?php echo $user['phone']; ?>
                </p>

                <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 20px;">
                    <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $user['is_active'] ? 'نشط' : 'غير نشط'; ?>
                    </span>
                    <?php if ($user['is_verified']): ?>
                        <span class="badge badge-info">
                            <i class="fas fa-check"></i> موثق
                        </span>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom: 15px;">
                    <span class="badge badge-info">
                        <?php echo membershipLevelLabel($user['membership_level'] ?? 'silver'); ?>
                    </span>
                    <button type="button" onclick="showModal('membership-modal')" class="btn btn-sm btn-outline"
                        style="padding: 2px 8px; font-size: 10px; margin-right: 6px;">
                        <i class="fas fa-crown"></i> تعديل العضوية
                    </button>
                </div>

                <div style="background: var(--gray-50); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="font-size: 28px; font-weight: 700; color: var(--success-color);">
                        <?php echo number_format($user['wallet_balance'], 2); ?> ⃁
                    </div>
                    <div style="color: #6b7280; font-size: 14px;">رصيد المحفظة</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div style="background: var(--gray-50); border-radius: 10px; padding: 15px;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--gray-800);">
                            <?php echo number_format($user['points']); ?>
                            <button type="button" onclick="showModal('points-modal')" class="btn btn-sm btn-outline"
                                style="float: left; padding: 2px 8px; font-size: 10px;">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div style="color: #6b7280; font-size: 12px;">النقاط</div>
                    </div>
                    <div style="background: var(--gray-50); border-radius: 10px; padding: 15px;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--gray-800);">
                            <?php echo db()->count('orders', 'user_id = ?', [$user['id']]); ?>
                        </div>
                        <div style="color: #6b7280; font-size: 12px;">الطلبات</div>
                    </div>
                </div>

                <div style="text-align: right; border-top: 1px solid var(--gray-200); padding-top: 20px;">
                    <div style="margin-bottom: 10px;">
                        <strong>البريد:</strong>
                        <?php echo $user['email'] ?: '-'; ?>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>العضوية:</strong>
                        <?php echo membershipLevelLabel($user['membership_level'] ?? 'silver'); ?>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>كود الإحالة:</strong>
                        <?php echo $user['referral_code'] ?: '-'; ?>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>التسجيل:</strong>
                        <?php echo formatDateTime($user['created_at']); ?>
                    </div>
                    <div>
                        <strong>آخر دخول:</strong>
                        <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'لم يسجل دخول'; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit"
                            class="btn <?php echo $user['is_active'] ? 'btn-danger' : 'btn-success'; ?> btn-block">
                            <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                            <?php echo $user['is_active'] ? 'إيقاف' : 'تفعيل'; ?>
                        </button>
                    </form>
                    <button type="button" onclick="showModal('wallet-modal')" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-wallet"></i>
                        المحفظة
                    </button>
                    <button type="button" onclick="showModal('membership-modal')" class="btn btn-outline" style="flex: 1;">
                        <i class="fas fa-crown"></i>
                        العضوية
                    </button>
                </div>
            </div>
        </div>

        <div>
            <!-- طلبات المستخدم -->
            <div class="card animate-slideUp" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clipboard-list" style="color: var(--secondary-color);"></i>
                        آخر الطلبات
                    </h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($userOrders)): ?>
                        <div class="empty-state" style="padding: 30px;">
                            <div class="empty-state-icon" style="width: 60px; height: 60px; font-size: 24px;">📋</div>
                            <h3 style="font-size: 14px;">لا توجد طلبات</h3>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>الخدمة</th>
                                        <th>المبلغ</th>
                                        <th>الحالة</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userOrders as $order): ?>
                                        <tr>
                                            <td><strong>
                                                    <?php echo $order['order_number']; ?>
                                                </strong></td>
                                            <td>
                                                <?php echo $categoryDisplayMap[(int) ($order['category_id'] ?? 0)] ?? ($order['category_name'] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <?php echo number_format($order['total_amount'], 2); ?> ⃁
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getOrderStatusColor($order['status']); ?>">
                                                    <?php echo getOrderStatusAr($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo formatDateAr($order['created_at']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- معاملات المحفظة -->
            <div class="card animate-slideUp">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-wallet" style="color: var(--success-color);"></i>
                        معاملات المحفظة
                    </h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($userTransactions)): ?>
                        <div class="empty-state" style="padding: 30px;">
                            <div class="empty-state-icon" style="width: 60px; height: 60px; font-size: 24px;">💰</div>
                            <h3 style="font-size: 14px;">لا توجد معاملات</h3>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>النوع</th>
                                        <th>المبلغ</th>
                                        <th>الرصيد بعدها</th>
                                        <th>الوصف</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userTransactions as $tx): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $txTypes = [
                                                    'deposit' => ['إيداع', 'success'],
                                                    'withdrawal' => ['سحب', 'danger'],
                                                    'payment' => ['دفع', 'warning'],
                                                    'refund' => ['استرداد', 'info'],
                                                    'reward' => ['مكافأة', 'primary'],
                                                    'referral_bonus' => ['مكافأة إحالة', 'secondary']
                                                ];
                                                $txInfo = $txTypes[$tx['type']] ?? [$tx['type'], 'secondary'];
                                                ?>
                                                <span class="badge badge-<?php echo $txInfo[1]; ?>">
                                                    <?php echo $txInfo[0]; ?>
                                                </span>
                                            </td>
                                            <td
                                                style="color: <?php echo in_array($tx['type'], ['deposit', 'refund', 'reward', 'referral_bonus']) ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: 600;">
                                                <?php echo in_array($tx['type'], ['deposit', 'refund', 'reward', 'referral_bonus']) ? '+' : '-'; ?>
                                                <?php echo number_format($tx['amount'], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo number_format($tx['balance_after'], 2); ?> ⃁
                                            </td>
                                            <td>
                                                <?php echo $tx['description'] ?: '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo formatDateTime($tx['created_at']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- عناوين المستخدم -->
    <div class="card animate-slideUp" style="margin-top: 25px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i>
                عناوين المستخدم
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($userAddresses)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <p style="color: #999;">لا توجد عناوين مسجلة</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>العنوان</th>
                            <th>التفاصيل</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userAddresses as $addr): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary"><?php echo $addr['label'] ?: $addr['type']; ?></span>
                                </td>
                                <td><?php echo $addr['address']; ?></td>
                                <td><?php echo $addr['details']; ?></td>
                                <td>
                                    <?php if ($addr['is_default']): ?>
                                        <span class="badge badge-success">افتراضي</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- مودال تعديل المحفظة -->
    <div class="modal-overlay" id="wallet-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">تعديل رصيد المحفظة</h3>
                <button class="modal-close" onclick="hideModal('wallet-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="<?php echo $usersPagePath; ?>?action=view&id=<?php echo (int) $user['id']; ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_wallet">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                    <div class="form-group">
                        <label class="form-label">الرصيد الحالي</label>
                        <div
                            style="font-size: 24px; font-weight: 700; color: var(--success-color); padding: 15px; background: var(--gray-50); border-radius: 10px; text-align: center;">
                            <?php echo number_format($user['wallet_balance'], 2); ?> ⃁
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوع العملية</label>
                        <select name="transaction_type" class="form-control" required>
                            <option value="deposit">إيداع (شحن رصيد)</option>
                            <option value="reward">مكافأة (رصيد إضافي)</option>
                            <option value="withdrawal">خصم رصيد</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">المبلغ</label>
                        <input type="number" name="amount" class="form-control" min="0" step="0.01" required
                            placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">وصف المعاملة (اختياري)</label>
                        <textarea name="description" class="form-control" rows="2"
                            placeholder="مثال: مكافأة الأداء المتميز..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('wallet-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تأكيد</button>
                </div>
            </form>
        </div>
    </div>

    <!-- مودال تعديل بيانات المستخدم -->
    <div class="modal-overlay" id="edit-user-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">تعديل بيانات المستخدم</h3>
                <button class="modal-close" onclick="hideModal('edit-user-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="<?php echo $usersPagePath; ?>?action=view&id=<?php echo (int) $user['id']; ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_info">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                    <div class="form-group">
                        <label class="form-label">الاسم الكامل</label>
                        <input type="text" name="full_name" class="form-control" required
                            value="<?php echo $user['full_name']; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" required value="<?php echo $user['phone']; ?>"
                            dir="ltr">
                    </div>

                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>"
                            dir="ltr">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('edit-user-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>

    <!-- مودال تعديل العضوية -->
    <div class="modal-overlay" id="membership-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">تعديل مستوى العضوية</h3>
                <button class="modal-close" onclick="hideModal('membership-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="<?php echo $usersPagePath; ?>?action=view&id=<?php echo (int) $user['id']; ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_membership">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                    <div class="form-group">
                        <label class="form-label">العضوية الحالية</label>
                        <div
                            style="font-size: 16px; font-weight: 700; color: var(--primary-color); padding: 12px; background: var(--gray-50); border-radius: 10px; text-align: center;">
                            <?php echo membershipLevelLabel($user['membership_level'] ?? 'silver'); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">المستوى الجديد</label>
                        <select name="membership_level" class="form-control" required>
                            <?php foreach (membershipLevelOptions() as $levelValue => $levelLabel): ?>
                                <option value="<?php echo $levelValue; ?>" <?php echo strtolower((string) ($user['membership_level'] ?? 'silver')) === $levelValue ? 'selected' : ''; ?>>
                                    <?php echo $levelLabel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('membership-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- مودال تعديل النقاط -->
    <div class="modal-overlay" id="points-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">تعديل نقاط المكافآت</h3>
                <button class="modal-close" onclick="hideModal('points-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="<?php echo $usersPagePath; ?>?action=view&id=<?php echo (int) $user['id']; ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_points">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">

                    <div class="form-group">
                        <label class="form-label">النقاط الحالية</label>
                        <div
                            style="font-size: 24px; font-weight: 700; color: var(--warning-color); padding: 15px; background: var(--gray-50); border-radius: 10px; text-align: center;">
                            <?php echo number_format($user['points']); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوع العملية</label>
                        <select name="type" class="form-control" required>
                            <option value="add">إضافة نقاط</option>
                            <option value="subtract">خصم نقاط</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">عدد النقاط</label>
                        <input type="number" name="amount" class="form-control" min="1" required placeholder="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('points-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تأكيد</button>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
