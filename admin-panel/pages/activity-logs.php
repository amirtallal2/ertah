<?php
/**
 * صفحة سجل النشاطات
 * Activity Logs Page
 */

require_once '../init.php';
requireLogin();

$currentAdmin = getCurrentAdmin();
if (!$currentAdmin || $currentAdmin['role'] !== 'super_admin') {
    die('صلاحيات غير كافية. هذه الصفحة للمدير العام فقط.');
}

$pageTitle = 'سجل النشاطات';
$pageSubtitle = 'متابعة نشاط المشرفين على لوحة التحكم';

$action = get('action', 'list');
$id = (int) get('id');

$search = trim((string) get('search'));
$adminId = (int) get('admin_id');
$model = trim((string) get('model'));
$activityAction = trim((string) get('activity_action'));
$dateFrom = trim((string) get('date_from'));
$dateTo = trim((string) get('date_to'));
$page = max(1, (int) get('page', 1));

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function buildQueryString($filters, $overrides = [])
{
    $params = array_merge($filters, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return http_build_query($params);
}

function decodeJsonValue($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $value;
}

function formatJsonForView($value)
{
    if ($value === null || $value === '') {
        return 'لا يوجد';
    }

    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    return (string) $value;
}

function actionMeta($action)
{
    $map = [
        'login' => ['تسجيل دخول', 'badge-success', 'fa-right-to-bracket'],
        'logout' => ['تسجيل خروج', 'badge-secondary', 'fa-right-from-bracket'],
        'add_offer' => ['إضافة عرض', 'badge-success', 'fa-plus'],
        'update_offer' => ['تحديث عرض', 'badge-primary', 'fa-pen'],
        'delete_offer' => ['حذف عرض', 'badge-danger', 'fa-trash'],
        'add_category' => ['إضافة فئة', 'badge-success', 'fa-plus'],
        'update_category' => ['تحديث فئة', 'badge-primary', 'fa-pen'],
        'delete_category' => ['حذف فئة', 'badge-danger', 'fa-trash'],
        'add_product' => ['إضافة منتج', 'badge-success', 'fa-plus'],
        'update_product' => ['تحديث منتج', 'badge-primary', 'fa-pen'],
        'delete_product' => ['حذف منتج', 'badge-danger', 'fa-trash'],
        'add_store' => ['إضافة متجر', 'badge-success', 'fa-plus'],
        'update_store' => ['تحديث متجر', 'badge-primary', 'fa-pen'],
        'delete_store' => ['حذف متجر', 'badge-danger', 'fa-trash'],
        'update_settings' => ['تحديث إعدادات', 'badge-primary', 'fa-gear'],
        'send_notification' => ['إرسال إشعار', 'badge-warning', 'fa-bell'],
        'add_promo_code' => ['إضافة كود خصم', 'badge-success', 'fa-ticket-alt'],
        'update_promo_code' => ['تحديث كود خصم', 'badge-primary', 'fa-ticket-alt'],
        'delete_promo_code' => ['حذف كود خصم', 'badge-danger', 'fa-ticket-alt']
    ];

    if (isset($map[$action])) {
        return $map[$action];
    }

    $fallbackLabel = str_replace('_', ' ', $action);
    return [$fallbackLabel, 'badge-secondary', 'fa-circle'];
}

function modelLabel($model)
{
    $labels = [
        'admins' => 'المشرفين',
        'users' => 'المستخدمين',
        'providers' => 'مقدمي الخدمات',
        'orders' => 'الطلبات',
        'services' => 'الخدمات',
        'service_categories' => 'فئات الخدمات',
        'stores' => 'المتاجر',
        'products' => 'المنتجات',
        'offers' => 'العروض',
        'promo_codes' => 'أكواد الخصم',
        'banners' => 'البانرات',
        'settings' => 'الإعدادات',
        'notifications' => 'الإشعارات',
        'complaints' => 'الشكاوى'
    ];

    if (!$model) {
        return '-';
    }

    return $labels[$model] ?? $model;
}

$baseFilters = [
    'search' => $search,
    'admin_id' => $adminId > 0 ? $adminId : '',
    'model' => $model,
    'activity_action' => $activityAction,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
];

$activityTableExists = true;
try {
    db()->fetch("SELECT 1 FROM activity_logs LIMIT 1");
} catch (Throwable $e) {
    $activityTableExists = false;
    $activityTableError = $e->getMessage();
}

if ($activityTableExists) {
    $where = '1=1';
    $params = [];

    if ($adminId > 0) {
        $where .= " AND al.admin_id = ?";
        $params[] = $adminId;
    }

    if ($model !== '') {
        $where .= " AND al.model = ?";
        $params[] = $model;
    }

    if ($activityAction !== '') {
        $where .= " AND al.action = ?";
        $params[] = $activityAction;
    }

    if ($dateFrom !== '') {
        $where .= " AND DATE(al.created_at) >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $where .= " AND DATE(al.created_at) <= ?";
        $params[] = $dateTo;
    }

    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $where .= " AND (
            al.action LIKE ?
            OR COALESCE(al.model, '') LIKE ?
            OR CAST(COALESCE(al.model_id, '') AS CHAR) LIKE ?
            OR COALESCE(al.ip_address, '') LIKE ?
            OR COALESCE(a.full_name, '') LIKE ?
            OR COALESCE(a.username, '') LIKE ?
        )";
        $params = array_merge($params, [
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm
        ]);
    }

    $totalLogs = db()->count(
        'activity_logs al LEFT JOIN admins a ON a.id = al.admin_id',
        $where,
        $params
    );
    $pagination = paginate($totalLogs, $page);

    $logs = db()->fetchAll("
        SELECT al.*, a.full_name AS admin_name, a.username AS admin_username
        FROM activity_logs al
        LEFT JOIN admins a ON a.id = al.admin_id
        WHERE {$where}
        ORDER BY al.created_at DESC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ", $params);

    $actionOptions = db()->fetchAll("
        SELECT action, COUNT(*) AS total
        FROM activity_logs
        GROUP BY action
        ORDER BY total DESC, action ASC
        LIMIT 100
    ");

    $modelOptions = db()->fetchAll("
        SELECT model, COUNT(*) AS total
        FROM activity_logs
        WHERE model IS NOT NULL AND model != ''
        GROUP BY model
        ORDER BY total DESC, model ASC
        LIMIT 100
    ");

    $adminOptions = db()->fetchAll("
        SELECT id, full_name, username
        FROM admins
        ORDER BY full_name ASC
    ");

    $stats = db()->fetch("
        SELECT
            COUNT(*) AS total_logs,
            SUM(DATE(created_at) = CURDATE()) AS today_logs,
            SUM(action = 'login' AND DATE(created_at) = CURDATE()) AS today_logins,
            SUM(action = 'logout' AND DATE(created_at) = CURDATE()) AS today_logouts,
            COUNT(DISTINCT admin_id) AS active_admins
        FROM activity_logs
    ");
}

if ($activityTableExists && $action === 'view' && $id > 0) {
    $logDetails = db()->fetch("
        SELECT al.*, a.full_name AS admin_name, a.username AS admin_username
        FROM activity_logs al
        LEFT JOIN admins a ON a.id = al.admin_id
        WHERE al.id = ?
        LIMIT 1
    ", [$id]);

    if (!$logDetails) {
        setFlashMessage('danger', 'سجل النشاط غير موجود');
        redirect('activity-logs.php?' . buildQueryString($baseFilters, ['action' => 'list', 'page' => $page]));
    }

    $oldValues = decodeJsonValue($logDetails['old_values']);
    $newValues = decodeJsonValue($logDetails['new_values']);
}

include '../includes/header.php';
?>

<?php if (!$activityTableExists): ?>
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-triangle-exclamation" style="color: var(--danger-color);"></i>
                تعذر تحميل سجل النشاطات
            </h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 10px;">جدول <code>activity_logs</code> غير متاح حالياً.</p>
            <p style="color: #6b7280; font-size: 13px;">
                <?php echo e($activityTableError ?? 'Unknown error'); ?>
            </p>
        </div>
    </div>

<?php elseif ($action === 'view' && isset($logDetails)): ?>
    <?php [$actionLabel, $actionClass, $actionIcon] = actionMeta($logDetails['action']); ?>
    <div style="margin-bottom: 20px;">
        <a href="activity-logs.php?<?php echo e(buildQueryString($baseFilters, ['action' => 'list', 'page' => $page])); ?>"
            class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            العودة لسجل النشاطات
        </a>
    </div>

    <div class="card animate-slideUp" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas <?php echo e($actionIcon); ?>" style="color: var(--primary-color);"></i>
                تفاصيل النشاط #<?php echo (int) $logDetails['id']; ?>
            </h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                <div>
                    <strong>المشرف:</strong>
                    <?php echo e($logDetails['admin_name'] ?: 'غير معروف'); ?>
                    <?php if (!empty($logDetails['admin_username'])): ?>
                        <span style="color: #6b7280;">(<?php echo e($logDetails['admin_username']); ?>)</span>
                    <?php endif; ?>
                </div>
                <div>
                    <strong>وقت التنفيذ:</strong>
                    <?php echo e(formatDateTime($logDetails['created_at'])); ?>
                </div>
                <div>
                    <strong>الإجراء:</strong>
                    <span class="badge <?php echo e($actionClass); ?>">
                        <?php echo e($actionLabel); ?>
                    </span>
                </div>
                <div>
                    <strong>الكيان:</strong>
                    <?php echo e(modelLabel($logDetails['model'])); ?>
                    <?php if (!empty($logDetails['model_id'])): ?>
                        <span style="color: #6b7280;">#<?php echo (int) $logDetails['model_id']; ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <strong>IP:</strong>
                    <?php echo e($logDetails['ip_address'] ?: '-'); ?>
                </div>
                <div>
                    <strong>User Agent:</strong>
                    <?php echo e($logDetails['user_agent'] ?: '-'); ?>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="card animate-slideUp">
            <div class="card-header">
                <h3 class="card-title">القيم السابقة (Old Values)</h3>
            </div>
            <div class="card-body">
                <pre style="white-space: pre-wrap; direction: ltr; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; font-size: 12px; max-height: 380px; overflow: auto;"><?php echo e(formatJsonForView($oldValues)); ?></pre>
            </div>
        </div>
        <div class="card animate-slideUp">
            <div class="card-header">
                <h3 class="card-title">القيم الجديدة (New Values)</h3>
            </div>
            <div class="card-body">
                <pre style="white-space: pre-wrap; direction: ltr; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; font-size: 12px; max-height: 380px; overflow: auto;"><?php echo e(formatJsonForView($newValues)); ?></pre>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="stats-grid" style="margin-bottom: 25px;">
        <div class="stat-card animate-slideUp" style="animation-delay: 0.05s;">
            <div class="stat-icon primary"><i class="fas fa-history"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format((int) ($stats['total_logs'] ?? 0)); ?></h3>
                <p>إجمالي السجلات</p>
            </div>
        </div>
        <div class="stat-card animate-slideUp" style="animation-delay: 0.1s;">
            <div class="stat-icon success"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format((int) ($stats['today_logs'] ?? 0)); ?></h3>
                <p>سجلات اليوم</p>
            </div>
        </div>
        <div class="stat-card animate-slideUp" style="animation-delay: 0.15s;">
            <div class="stat-icon secondary"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format((int) ($stats['active_admins'] ?? 0)); ?></h3>
                <p>مشرفون نشطون</p>
            </div>
        </div>
        <div class="stat-card animate-slideUp" style="animation-delay: 0.2s;">
            <div class="stat-icon warning"><i class="fas fa-right-to-bracket"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format((int) ($stats['today_logins'] ?? 0)); ?></h3>
                <p>تسجيل دخول اليوم</p>
            </div>
        </div>
    </div>

    <div class="card animate-slideUp" style="margin-bottom: 20px;">
        <div class="card-body">
            <form method="GET" style="display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; align-items: end;">
                <input type="hidden" name="action" value="list">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control"
                        value="<?php echo e($search); ?>"
                        placeholder="إجراء، كيان، مشرف، IP ...">
                </div>
                <div class="form-group">
                    <label class="form-label">المشرف</label>
                    <select name="admin_id" class="form-control">
                        <option value="">الكل</option>
                        <?php foreach ($adminOptions as $admin): ?>
                            <option value="<?php echo (int) $admin['id']; ?>"
                                <?php echo $adminId === (int) $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo e($admin['full_name'] . ' (' . $admin['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الكيان</label>
                    <select name="model" class="form-control">
                        <option value="">الكل</option>
                        <?php foreach ($modelOptions as $opt): ?>
                            <option value="<?php echo e($opt['model']); ?>"
                                <?php echo $model === $opt['model'] ? 'selected' : ''; ?>>
                                <?php echo e(modelLabel($opt['model']) . ' (' . $opt['total'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الإجراء</label>
                    <select name="activity_action" class="form-control">
                        <option value="">الكل</option>
                        <?php foreach ($actionOptions as $opt): ?>
                            <option value="<?php echo e($opt['action']); ?>"
                                <?php echo $activityAction === $opt['action'] ? 'selected' : ''; ?>>
                                <?php echo e(str_replace('_', ' ', $opt['action']) . ' (' . $opt['total'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo e($dateFrom); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo e($dateTo); ?>">
                </div>
                <div style="display: flex; gap: 8px; grid-column: span 2;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        تطبيق
                    </button>
                    <a href="activity-logs.php" class="btn btn-outline">
                        <i class="fas fa-rotate-left"></i>
                        إعادة ضبط
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color: var(--primary-color);"></i>
                سجلات النشاطات (<?php echo number_format((int) $totalLogs); ?>)
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🧾</div>
                    <h3>لا توجد نتائج</h3>
                    <p>لا توجد سجلات مطابقة للفلاتر المحددة</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الوقت</th>
                                <th>المشرف</th>
                                <th>الإجراء</th>
                                <th>الكيان</th>
                                <th>IP</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php [$actionLabel, $actionClass, $actionIcon] = actionMeta($log['action']); ?>
                                <tr>
                                    <td>
                                        <div style="font-size: 13px; font-weight: 600;">
                                            <?php echo e(formatDateTime($log['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #6b7280;">
                                            <?php echo e(timeAgo($log['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo e($log['admin_name'] ?: 'غير معروف'); ?></div>
                                        <div style="font-size: 12px; color: #6b7280;">
                                            <?php echo e($log['admin_username'] ?: '-'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e($actionClass); ?>">
                                            <i class="fas <?php echo e($actionIcon); ?>"></i>
                                            <?php echo e($actionLabel); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo e(modelLabel($log['model'])); ?></div>
                                        <?php if (!empty($log['model_id'])): ?>
                                            <div style="font-size: 12px; color: #6b7280;">#<?php echo (int) $log['model_id']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($log['ip_address'] ?: '-'); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline"
                                            href="activity-logs.php?<?php echo e(buildQueryString($baseFilters, ['action' => 'view', 'id' => (int) $log['id'], 'page' => $pagination['current_page']])); ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination" style="margin-top: 20px;">
                        <a href="activity-logs.php?<?php echo e(buildQueryString($baseFilters, ['action' => 'list', 'page' => 1])); ?>"
                            class="page-link <?php echo $pagination['current_page'] == 1 ? 'disabled' : ''; ?>">
                            الأولى
                        </a>
                        <a href="activity-logs.php?<?php echo e(buildQueryString($baseFilters, ['action' => 'list', 'page' => max(1, $pagination['current_page'] - 1)])); ?>"
                            class="page-link <?php echo !$pagination['has_prev'] ? 'disabled' : ''; ?>">
                            السابق
                        </a>

                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                            <a href="activity-logs.php?<?php echo e(buildQueryString($baseFilters, ['action' => 'list', 'page' => $i])); ?>"
                                class="page-link <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <a href="activity-logs.php?<?php echo e(buildQueryString($baseFilters, ['action' => 'list', 'page' => min($pagination['total_pages'], $pagination['current_page'] + 1)])); ?>"
                            class="page-link <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                            التالي
                        </a>
                        <a href="activity-logs.php?<?php echo e(buildQueryString($baseFilters, ['action' => 'list', 'page' => $pagination['total_pages']])); ?>"
                            class="page-link <?php echo $pagination['current_page'] == $pagination['total_pages'] ? 'disabled' : ''; ?>">
                            الأخيرة
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
