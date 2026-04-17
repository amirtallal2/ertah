<?php
/**
 * الصفحة الرئيسية - لوحة التحكم
 * Dashboard Page
 */

require_once 'init.php';
requireLogin();

$pageTitle = 'لوحة التحكم';
$pageSubtitle = 'نظرة عامة على التطبيق';

// إحصائيات سريعة
$stats = [
    'users' => db()->count('users'),
    'providers' => db()->count('providers', "status = 'approved'"),
    'pending_providers' => db()->count('providers', "status = 'pending'"),
    'orders' => db()->count('orders'),
    'pending_orders' => db()->count('orders', "status = 'pending'"),
    'completed_orders' => db()->count('orders', "status = 'completed'"),
    'products' => db()->count('products', "is_active = 1"),
    'stores' => db()->count('stores', "is_active = 1"),
    'complaints' => db()->count('complaints', "status IN ('open', 'in_progress')"),
];

// حساب إجمالي الإيرادات
$totalRevenue = db()->fetch("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
$stats['revenue'] = $totalRevenue['total'] ?? 0;

// آخر الطلبات
$recentOrders = db()->fetchAll("
    SELECT o.*, u.full_name as user_name, c.name_ar as category_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN service_categories c ON o.category_id = c.id
    ORDER BY o.created_at DESC
    LIMIT 5
");

// آخر المستخدمين
$recentUsers = db()->fetchAll("
    SELECT * FROM users ORDER BY created_at DESC LIMIT 5
");

// طلبات مقدمي الخدمات الجدد
$pendingProviders = db()->fetchAll("
    SELECT * FROM providers WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5
");

include 'includes/header.php';
?>

<!-- إحصائيات الداشبورد -->
<div class="stats-grid">
    <div class="stat-card animate-slideUp" style="animation-delay: 0.1s;">
        <div class="stat-icon primary">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['users']); ?>
            </h3>
            <p>المستخدمين</p>
            <span class="stat-change up">
                <i class="fas fa-arrow-up"></i>
                12% هذا الشهر
            </span>
        </div>
    </div>

    <div class="stat-card animate-slideUp" style="animation-delay: 0.2s;">
        <div class="stat-icon secondary">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['providers']); ?>
            </h3>
            <p>مقدمي الخدمات</p>
            <?php if ($stats['pending_providers'] > 0): ?>
                <span class="stat-change" style="background: rgba(251, 204, 38, 0.15); color: #f5c01f;">
                    <i class="fas fa-clock"></i>
                    <?php echo $stats['pending_providers']; ?> بانتظار الموافقة
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card animate-slideUp" style="animation-delay: 0.3s;">
        <div class="stat-icon success">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['orders']); ?>
            </h3>
            <p>إجمالي الطلبات</p>
            <span class="stat-change up">
                <i class="fas fa-check-circle"></i>
                <?php echo number_format($stats['completed_orders']); ?> مكتمل
            </span>
        </div>
    </div>

    <div class="stat-card animate-slideUp" style="animation-delay: 0.4s;">
        <div class="stat-icon warning">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['revenue'], 2); ?>
            </h3>
            <p>إجمالي الإيرادات (⃁)</p>
            <span class="stat-change up">
                <i class="fas fa-arrow-up"></i>
                8% هذا الأسبوع
            </span>
        </div>
    </div>
</div>

<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card animate-slideUp" style="animation-delay: 0.5s;">
        <div class="stat-icon info">
            <i class="fas fa-store"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['stores']); ?>
            </h3>
            <p>المتاجر</p>
        </div>
    </div>

    <div class="stat-card animate-slideUp" style="animation-delay: 0.6s;">
        <div class="stat-icon primary">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['products']); ?>
            </h3>
            <p>المنتجات</p>
        </div>
    </div>

    <div class="stat-card animate-slideUp" style="animation-delay: 0.7s;">
        <div class="stat-icon danger">
            <i class="fas fa-headset"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['complaints']); ?>
            </h3>
            <p>شكاوى مفتوحة</p>
        </div>
    </div>

    <div class="stat-card animate-slideUp" style="animation-delay: 0.8s;">
        <div class="stat-icon secondary">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>
                <?php echo number_format($stats['pending_orders']); ?>
            </h3>
            <p>طلبات معلقة</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- آخر الطلبات -->
    <div class="card animate-slideUp" style="animation-delay: 0.9s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clipboard-list" style="color: var(--primary-color);"></i>
                آخر الطلبات
            </h3>
            <a href="pages/orders.php" class="btn btn-outline btn-sm">عرض الكل</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recentOrders)): ?>
                <div class="empty-state" style="padding: 40px;">
                    <div class="empty-state-icon" style="width: 80px; height: 80px; font-size: 32px;">📋</div>
                    <h3 style="font-size: 16px;">لا توجد طلبات</h3>
                    <p style="font-size: 13px;">لم يتم تسجيل أي طلبات بعد</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>العميل</th>
                                <th>الخدمة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong>
                                            <?php echo $order['order_number']; ?>
                                        </strong></td>
                                    <td>
                                        <?php echo $order['user_name'] ?? 'غير معروف'; ?>
                                    </td>
                                    <td>
                                        <?php echo $order['category_name'] ?? '-'; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getOrderStatusColor($order['status']); ?>">
                                            <?php echo getOrderStatusAr($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- آخر المستخدمين -->
    <div class="card animate-slideUp" style="animation-delay: 1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users" style="color: var(--secondary-color);"></i>
                المستخدمين الجدد
            </h3>
            <a href="pages/users.php" class="btn btn-outline btn-sm">عرض الكل</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recentUsers)): ?>
                <div class="empty-state" style="padding: 40px;">
                    <div class="empty-state-icon" style="width: 80px; height: 80px; font-size: 32px;">👤</div>
                    <h3 style="font-size: 16px;">لا يوجد مستخدمين</h3>
                    <p style="font-size: 13px;">لم يتم تسجيل أي مستخدمين بعد</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>الهاتف</th>
                                <th>التسجيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="<?php echo imageUrl($user['avatar'], 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name'])); ?>"
                                                alt="" class="avatar avatar-sm avatar-circle">
                                            <span>
                                                <?php echo $user['full_name']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td dir="ltr">
                                        <?php echo $user['phone']; ?>
                                    </td>
                                    <td>
                                        <?php echo timeAgo($user['created_at']); ?>
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

<?php if (!empty($pendingProviders)): ?>
    <!-- طلبات مقدمي الخدمات -->
    <div class="card animate-slideUp" style="margin-top: 25px; animation-delay: 1.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-clock" style="color: var(--warning-color);"></i>
                طلبات مقدمي خدمات جديدة
            </h3>
            <a href="pages/providers.php?status=pending" class="btn btn-outline btn-sm">عرض الكل</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>الهاتف</th>
                            <th>المدينة</th>
                            <th>تاريخ الطلب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingProviders as $provider): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="<?php echo imageUrl($provider['avatar'], 'https://ui-avatars.com/api/?name=' . urlencode($provider['full_name'])); ?>"
                                            alt="" class="avatar avatar-sm avatar-circle">
                                        <span>
                                            <?php echo $provider['full_name']; ?>
                                        </span>
                                    </div>
                                </td>
                                <td dir="ltr">
                                    <?php echo $provider['phone']; ?>
                                </td>
                                <td>
                                    <?php echo $provider['city'] ?? '-'; ?>
                                </td>
                                <td>
                                    <?php echo timeAgo($provider['created_at']); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="pages/providers.php?action=view&id=<?php echo $provider['id']; ?>"
                                            class="btn btn-sm btn-outline">
                                            <i class="fas fa-eye"></i>
                                            عرض
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>