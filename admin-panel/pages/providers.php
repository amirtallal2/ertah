<?php
/**
 * صفحة إدارة مقدمي الخدمات
 * Providers Management Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'مقدمي الخدمات';
$pageSubtitle = 'إدارة مقدمي الخدمات والفنيين';

$action = get('action', 'list');
$id = (int) get('id');

function providerAdminTableExists(string $table): bool
{
    static $cache = [];

    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    if (array_key_exists($safe, $cache)) {
        return $cache[$safe];
    }

    try {
        $quoted = db()->getConnection()->quote($safe);
        $cache[$safe] = !empty(db()->fetch("SHOW TABLES LIKE {$quoted}"));
    } catch (Throwable $e) {
        $cache[$safe] = false;
    }

    return $cache[$safe];
}

function providerAdminTableColumnExists(string $table, string $column): bool
{
    static $cache = [];

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $cacheKey = $safeTable . ':' . $safeColumn;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!providerAdminTableExists($safeTable)) {
        $cache[$cacheKey] = false;
        return false;
    }

    try {
        $quotedColumn = db()->getConnection()->quote($safeColumn);
        $cache[$cacheKey] = !empty(db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}"));
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function providerAdminColumnExists(string $column): bool
{
    return providerAdminTableColumnExists('providers', $column);
}

function providerDocumentUrl(string $path): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return '';
    }

    if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return $trimmed;
    }

    if (strpos($trimmed, 'uploads/') === 0) {
        return APP_URL . '/' . ltrim($trimmed, '/');
    }

    return imageUrl($trimmed);
}

function providerAdminFilterKnownColumns(array $data): array
{
    $filtered = [];
    foreach ($data as $column => $value) {
        if (providerAdminColumnExists((string) $column)) {
            $filtered[$column] = $value;
        }
    }
    return $filtered;
}

function ensureProvidersAdminSchema(): void
{
    if (!providerAdminTableExists('providers')) {
        return;
    }

    $columns = [
        'approved_at' => "DATETIME NULL",
        'approved_by' => "INT NULL",
        'whatsapp_number' => "VARCHAR(32) NULL",
        'residency_document_path' => "VARCHAR(255) NULL",
        'ajeer_certificate_path' => "VARCHAR(255) NULL",
        'categories_locked' => "TINYINT(1) NOT NULL DEFAULT 0",
    ];
    foreach ($columns as $name => $definition) {
        if (!providerAdminColumnExists($name)) {
            try {
                db()->query("ALTER TABLE providers ADD COLUMN {$name} {$definition}");
            } catch (Throwable $e) {
                error_log('providers.php schema ensure failed for column ' . $name . ': ' . $e->getMessage());
            }
        }
    }
}

ensureProvidersAdminSchema();

$hasProvidersTable = providerAdminTableExists('providers');
$hasProviderServicesTable = providerAdminTableExists('provider_services');
$hasServiceCategoriesTable = providerAdminTableExists('service_categories');
$hasProviderStatusColumn = providerAdminColumnExists('status');
$hasProviderCreatedAtColumn = providerAdminColumnExists('created_at');
$hasProviderCommissionColumn = providerAdminColumnExists('commission_rate');

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasProvidersTable) {
    $postAction = post('action');

    if ($postAction === 'approve') {
        $providerId = (int) post('provider_id');
        $approvePayload = providerAdminFilterKnownColumns([
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $_SESSION['admin_id'] ?? null,
        ]);
        if (empty($approvePayload)) {
            setFlashMessage('danger', 'لا يمكن الموافقة لأن أعمدة الحالة غير متاحة في قاعدة البيانات');
            redirect('providers.php');
        }
        db()->update(
            'providers',
            $approvePayload,
            'id = :id',
            ['id' => $providerId]
        );
        logActivity('approve_provider', 'providers', $providerId);
        setFlashMessage('success', 'تم قبول مقدم الخدمة بنجاح');
        redirect('providers.php');
    }

    if ($postAction === 'reject') {
        $providerId = (int) post('provider_id');
        $rejectPayload = providerAdminFilterKnownColumns([
            'status' => 'rejected',
        ]);
        if (empty($rejectPayload)) {
            setFlashMessage('danger', 'لا يمكن رفض مقدم الخدمة لأن عمود الحالة غير متاح');
            redirect('providers.php');
        }
        db()->update(
            'providers',
            $rejectPayload,
            'id = :id',
            ['id' => $providerId]
        );
        logActivity('reject_provider', 'providers', $providerId);
        setFlashMessage('success', 'تم رفض مقدم الخدمة');
        redirect('providers.php');
    }

    if ($postAction === 'suspend') {
        $providerId = (int) post('provider_id');
        $suspendPayload = providerAdminFilterKnownColumns([
            'status' => 'suspended',
        ]);
        if (empty($suspendPayload)) {
            setFlashMessage('danger', 'لا يمكن إيقاف مقدم الخدمة لأن عمود الحالة غير متاح');
            redirect('providers.php');
        }
        db()->update(
            'providers',
            $suspendPayload,
            'id = :id',
            ['id' => $providerId]
        );
        logActivity('suspend_provider', 'providers', $providerId);
        setFlashMessage('success', 'تم إيقاف مقدم الخدمة');
        redirect('providers.php');
    }

    if ($postAction === 'activate') {
        $providerId = (int) post('provider_id');
        $activatePayload = providerAdminFilterKnownColumns([
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $_SESSION['admin_id'] ?? null,
        ]);
        if (empty($activatePayload)) {
            setFlashMessage('danger', 'لا يمكن تفعيل مقدم الخدمة لأن أعمدة الحالة غير متاحة');
            redirect('providers.php');
        }
        db()->update(
            'providers',
            $activatePayload,
            'id = :id',
            ['id' => $providerId]
        );
        logActivity('activate_provider', 'providers', $providerId);
        setFlashMessage('success', 'تم تفعيل مقدم الخدمة');
        redirect('providers.php');
    }

    if ($postAction === 'update_commission') {
        $providerId = (int) post('provider_id');
        $commission = (float) post('commission_rate');
        if (!$hasProviderCommissionColumn) {
            setFlashMessage('danger', 'لا يمكن تحديث العمولة لأن العمود غير موجود في قاعدة البيانات');
            redirect('providers.php?action=view&id=' . $providerId);
        }
        db()->update(
            'providers',
            ['commission_rate' => $commission],
            'id = :id',
            ['id' => $providerId]
        );
        logActivity('update_commission', 'providers', $providerId);
        setFlashMessage('success', 'تم تحديث نسبة العمولة');
        redirect('providers.php?action=view&id=' . $providerId);
    }

    if ($postAction === 'update_profile') {
        $providerId = (int) post('provider_id');
        $provider = db()->fetch("SELECT * FROM providers WHERE id = ?", [$providerId]);
        if (!$provider) {
            setFlashMessage('danger', 'مقدم الخدمة غير موجود');
            redirect('providers.php');
        }

        $updateData = [
            'full_name' => trim((string) post('full_name')),
            'email' => trim((string) post('email')) !== '' ? trim((string) post('email')) : null,
            'phone' => trim((string) post('phone')),
            'whatsapp_number' => trim((string) post('whatsapp_number')) !== '' ? trim((string) post('whatsapp_number')) : null,
            'country' => trim((string) post('country')) !== '' ? trim((string) post('country')) : null,
            'city' => trim((string) post('city')) !== '' ? trim((string) post('city')) : null,
            'district' => trim((string) post('district')) !== '' ? trim((string) post('district')) : null,
            'bio' => trim((string) post('bio')) !== '' ? trim((string) post('bio')) : null,
            'experience_years' => max(0, (int) post('experience_years')),
            'is_available' => post('is_available') === '1' ? 1 : 0,
            'categories_locked' => post('categories_locked') === '1' ? 1 : 0,
            'status' => in_array(post('status'), ['pending', 'approved', 'rejected', 'suspended'], true) ? post('status') : ($provider['status'] ?? 'pending'),
        ];

        if (providerAdminColumnExists('residency_document_path')) {
            $residencyPath = trim((string) post('residency_document_path'));
            $updateData['residency_document_path'] = $residencyPath !== '' ? $residencyPath : null;
        }

        if (($updateData['status'] ?? null) === 'approved') {
            $updateData['approved_at'] = date('Y-m-d H:i:s');
            $updateData['approved_by'] = $_SESSION['admin_id'] ?? null;
        }

        $updateData = providerAdminFilterKnownColumns($updateData);
        if (!empty($updateData)) {
            db()->update('providers', $updateData, 'id = :id', ['id' => $providerId]);
        }

        if ($hasProviderServicesTable) {
            $selectedCategoryIds = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? $_POST['category_ids'] : [];
            $categoryIds = [];
            foreach ($selectedCategoryIds as $rawCategoryId) {
                $categoryId = (int) $rawCategoryId;
                if ($categoryId > 0) {
                    $categoryIds[$categoryId] = $categoryId;
                }
            }
            db()->delete('provider_services', 'provider_id = ?', [$providerId]);
            foreach ($categoryIds as $categoryId) {
                db()->insert('provider_services', [
                    'provider_id' => $providerId,
                    'category_id' => $categoryId,
                ]);
            }
        }

        logActivity('update_provider_profile', 'providers', $providerId);
        setFlashMessage('success', 'تم تحديث بيانات مقدم الخدمة');
        redirect('providers.php?action=view&id=' . $providerId);
    }

    if ($postAction === 'delete') {
        $providerId = (int) post('provider_id');
        $provider = db()->fetch("SELECT id, full_name FROM providers WHERE id = ?", [$providerId]);
        if (!$provider) {
            setFlashMessage('danger', 'مقدم الخدمة غير موجود');
            redirect('providers.php');
        }

        if (providerAdminTableColumnExists('orders', 'provider_id')) {
            db()->query("UPDATE orders SET provider_id = NULL WHERE provider_id = ?", [$providerId]);
        }
        if (providerAdminTableExists('order_providers')) {
            db()->query("DELETE FROM order_providers WHERE provider_id = ?", [$providerId]);
        }
        if ($hasProviderServicesTable) {
            db()->query("DELETE FROM provider_services WHERE provider_id = ?", [$providerId]);
        }
        if (providerAdminTableExists('reviews')) {
            db()->query("DELETE FROM reviews WHERE provider_id = ?", [$providerId]);
        }
        db()->delete('providers', 'id = ?', [$providerId]);

        logActivity('delete_provider', 'providers', $providerId, ['full_name' => $provider['full_name'] ?? '']);
        setFlashMessage('success', 'تم حذف مقدم الخدمة');
        redirect('providers.php');
    }
}

// البحث والفلترة
$search = get('search');
$status = get('status');
$serviceFilterId = (int) get('service_id');
$page = max(1, (int) get('page', 1));
$hasCategoryHierarchy = $hasProviderServicesTable && $hasServiceCategoriesTable && hasServiceCategoryParentColumn();
$serviceFilterOptions = ($hasProviderServicesTable && $hasServiceCategoriesTable) ? getServiceCategoriesHierarchy(false) : [];
$serviceFilterLabel = '';
if ($serviceFilterId > 0 && !empty($serviceFilterOptions)) {
    foreach ($serviceFilterOptions as $serviceFilterOption) {
        if ((int) ($serviceFilterOption['id'] ?? 0) === $serviceFilterId) {
            $serviceFilterLabel = (string) ($serviceFilterOption['display_name_ar'] ?? $serviceFilterOption['name_ar'] ?? '');
            break;
        }
    }
}

$where = '1=1';
$params = [];

if ($search) {
    $searchTerm = "%{$search}%";
    $searchClauses = [];
    foreach (['full_name', 'phone', 'email', 'city'] as $providerSearchColumn) {
        if (!providerAdminColumnExists($providerSearchColumn)) {
            continue;
        }
        $searchClauses[] = "providers.{$providerSearchColumn} LIKE ?";
        $params[] = $searchTerm;
    }

    if ($hasProviderServicesTable && $hasServiceCategoriesTable) {
        if ($hasCategoryHierarchy) {
            $searchClauses[] = "EXISTS (
                SELECT 1
                FROM provider_services ps
                JOIN service_categories c ON ps.category_id = c.id
                LEFT JOIN service_categories pcat ON pcat.id = c.parent_id
                WHERE ps.provider_id = providers.id
                  AND (
                      c.name_ar LIKE ?
                      OR c.name_en LIKE ?
                      OR pcat.name_ar LIKE ?
                      OR pcat.name_en LIKE ?
                  )
            )";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        } else {
            $searchClauses[] = "EXISTS (
                SELECT 1
                FROM provider_services ps
                JOIN service_categories c ON ps.category_id = c.id
                WHERE ps.provider_id = providers.id
                  AND (c.name_ar LIKE ? OR c.name_en LIKE ?)
            )";
            $params = array_merge($params, [$searchTerm, $searchTerm]);
        }
    }

    if (!empty($searchClauses)) {
        $where .= " AND (" . implode(' OR ', $searchClauses) . ")";
    }
}

if ($serviceFilterId > 0 && $hasProviderServicesTable && $hasServiceCategoriesTable) {
    if ($hasCategoryHierarchy) {
        $isMainCategory = (int) db()->count(
            'service_categories',
            'id = ? AND (parent_id IS NULL OR parent_id = 0)',
            [$serviceFilterId]
        ) > 0;

        if ($isMainCategory) {
            $where .= " AND EXISTS (
                SELECT 1
                FROM provider_services ps
                JOIN service_categories c ON ps.category_id = c.id
                WHERE ps.provider_id = providers.id
                  AND (ps.category_id = ? OR c.parent_id = ?)
            )";
            $params[] = $serviceFilterId;
            $params[] = $serviceFilterId;
        } else {
            $where .= " AND EXISTS (
                SELECT 1
                FROM provider_services ps
                WHERE ps.provider_id = providers.id
                  AND ps.category_id = ?
            )";
            $params[] = $serviceFilterId;
        }
    } else {
        $where .= " AND EXISTS (
            SELECT 1
            FROM provider_services ps
            WHERE ps.provider_id = providers.id
              AND ps.category_id = ?
        )";
        $params[] = $serviceFilterId;
    }
}

if ($status && $hasProviderStatusColumn) {
    $where .= " AND providers.status = ?";
    $params[] = $status;
}

$totalProviders = $hasProvidersTable ? db()->count('providers', $where, $params) : 0;
$pagination = paginate($totalProviders, $page);

$providers = [];
if ($hasProvidersTable) {
    $orderByColumn = $hasProviderCreatedAtColumn ? 'created_at' : 'id';
    $providers = db()->fetchAll("
        SELECT * FROM providers 
        WHERE {$where}
        ORDER BY {$orderByColumn} DESC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
    ", $params);
}

$providerServicesByProvider = [];
if ($hasProviderServicesTable && $hasServiceCategoriesTable && !empty($providers)) {
    $providerIds = array_values(array_filter(array_map(static function ($provider) {
        return (int) ($provider['id'] ?? 0);
    }, $providers)));

    if (!empty($providerIds)) {
        $placeholders = implode(',', array_fill(0, count($providerIds), '?'));

        if ($hasCategoryHierarchy) {
            $providerServicesRows = db()->fetchAll(
                "SELECT ps.provider_id, c.name_ar, c.image, pcat.name_ar AS parent_name_ar
                 FROM provider_services ps
                 JOIN service_categories c ON c.id = ps.category_id
                 LEFT JOIN service_categories pcat ON pcat.id = c.parent_id
                 WHERE ps.provider_id IN ({$placeholders})
                 ORDER BY ps.provider_id ASC, c.sort_order ASC, c.id ASC",
                $providerIds
            );
        } else {
            $providerServicesRows = db()->fetchAll(
                "SELECT ps.provider_id, c.name_ar, c.image, NULL AS parent_name_ar
                 FROM provider_services ps
                 JOIN service_categories c ON c.id = ps.category_id
                 WHERE ps.provider_id IN ({$placeholders})
                 ORDER BY ps.provider_id ASC, c.sort_order ASC, c.id ASC",
                $providerIds
            );
        }

        foreach ($providerServicesRows as $serviceRow) {
            $providerId = (int) ($serviceRow['provider_id'] ?? 0);
            if ($providerId <= 0) {
                continue;
            }

            $serviceName = trim((string) ($serviceRow['name_ar'] ?? ''));
            $parentName = trim((string) ($serviceRow['parent_name_ar'] ?? ''));
            $displayName = $parentName !== '' ? ($parentName . ' > ' . $serviceName) : $serviceName;

            if ($displayName === '') {
                continue;
            }

            if (!isset($providerServicesByProvider[$providerId])) {
                $providerServicesByProvider[$providerId] = [];
            }

            $alreadyAdded = false;
            foreach ($providerServicesByProvider[$providerId] as $existingService) {
                if (($existingService['name'] ?? '') === $displayName) {
                    $alreadyAdded = true;
                    break;
                }
            }
            if ($alreadyAdded) {
                continue;
            }

            $providerServicesByProvider[$providerId][] = [
                'name' => $displayName,
                'image' => (string) ($serviceRow['image'] ?? ''),
            ];
        }
    }
}

// إحصائيات سريعة
$stats = [
    'total' => $hasProvidersTable ? db()->count('providers') : 0,
    'pending' => $hasProviderStatusColumn ? db()->count('providers', "status = 'pending'") : 0,
    'approved' => $hasProviderStatusColumn ? db()->count('providers', "status = 'approved'") : 0,
    'rejected' => $hasProviderStatusColumn ? db()->count('providers', "status = 'rejected'") : 0,
    'suspended' => $hasProviderStatusColumn ? db()->count('providers', "status = 'suspended'") : 0,
];

// عرض/تعديل تفاصيل مقدم خدمة
if (($action === 'view' || $action === 'edit') && $id) {
    $categoryDisplayMap = ($hasProviderServicesTable && $hasServiceCategoriesTable) ? getServiceCategoryDisplayMap(false) : [];

    $provider = db()->fetch("SELECT * FROM providers WHERE id = ?", [$id]);
    if (!$provider) {
        setFlashMessage('danger', 'مقدم الخدمة غير موجود');
        redirect('providers.php');
    }

    $providerServices = [];
    if ($hasProviderServicesTable && $hasServiceCategoriesTable) {
        if (hasServiceCategoryParentColumn()) {
            $providerServices = db()->fetchAll("
                SELECT c.*, pcat.name_ar AS parent_name_ar FROM provider_services ps
                JOIN service_categories c ON ps.category_id = c.id
                LEFT JOIN service_categories pcat ON pcat.id = c.parent_id
                WHERE ps.provider_id = ?
            ", [$id]);
        } else {
            $providerServices = db()->fetchAll("
                SELECT c.*, NULL AS parent_name_ar FROM provider_services ps
                JOIN service_categories c ON ps.category_id = c.id
                WHERE ps.provider_id = ?
            ", [$id]);
        }
    }
    $providerCategoryIds = array_values(array_filter(array_map(static function ($serviceRow) {
        return (int) ($serviceRow['id'] ?? 0);
    }, $providerServices)));
    $editCategoryOptions = ($hasProviderServicesTable && $hasServiceCategoriesTable) ? getServiceCategoriesHierarchy(false) : [];

    // الطلبات
    $providerOrders = [];
    if (providerAdminTableExists('orders') && providerAdminTableColumnExists('orders', 'provider_id')) {
        $providerOrdersSql = "SELECT o.*";
        if (providerAdminTableExists('users')) {
            $providerOrdersSql .= ", u.full_name as user_name";
        } else {
            $providerOrdersSql .= ", NULL as user_name";
        }
        if ($hasServiceCategoriesTable) {
            $providerOrdersSql .= ", c.name_ar as category_name";
        } else {
            $providerOrdersSql .= ", NULL as category_name";
        }
        $providerOrdersSql .= " FROM orders o";
        if (providerAdminTableExists('users')) {
            $providerOrdersSql .= " LEFT JOIN users u ON o.user_id = u.id";
        }
        if ($hasServiceCategoriesTable) {
            $providerOrdersSql .= " LEFT JOIN service_categories c ON o.category_id = c.id";
        }
        $providerOrdersSql .= providerAdminTableColumnExists('orders', 'created_at')
            ? " WHERE o.provider_id = ? ORDER BY o.created_at DESC LIMIT 10"
            : " WHERE o.provider_id = ? ORDER BY o.id DESC LIMIT 10";
        $providerOrders = db()->fetchAll($providerOrdersSql, [$id]);
    }

    // التقييمات
    $providerReviews = [];
    if (providerAdminTableExists('reviews') && providerAdminTableColumnExists('reviews', 'provider_id')) {
        $providerReviewsSql = "SELECT r.*";
        if (providerAdminTableExists('users')) {
            $providerReviewsSql .= ", u.full_name as user_name";
        } else {
            $providerReviewsSql .= ", NULL as user_name";
        }
        $providerReviewsSql .= " FROM reviews r";
        if (providerAdminTableExists('users')) {
            $providerReviewsSql .= " LEFT JOIN users u ON r.user_id = u.id";
        }
        $providerReviewsSql .= providerAdminTableColumnExists('reviews', 'created_at')
            ? " WHERE r.provider_id = ? ORDER BY r.created_at DESC LIMIT 5"
            : " WHERE r.provider_id = ? ORDER BY r.id DESC LIMIT 5";
        $providerReviews = db()->fetchAll($providerReviewsSql, [$id]);
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <!-- إحصائيات سريعة -->
    <div class="stats-grid" style="margin-bottom: 25px;">
        <a href="?status=" class="stat-card animate-slideUp" style="text-decoration: none;">
            <div class="stat-icon secondary">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php echo number_format($stats['total']); ?>
                </h3>
                <p>إجمالي مقدمي الخدمات</p>
            </div>
        </a>

        <a href="?status=pending" class="stat-card animate-slideUp" style="text-decoration: none; animation-delay: 0.1s;">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php echo number_format($stats['pending']); ?>
                </h3>
                <p>بانتظار الموافقة</p>
            </div>
        </a>

        <a href="?status=approved" class="stat-card animate-slideUp" style="text-decoration: none; animation-delay: 0.2s;">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php echo number_format($stats['approved']); ?>
                </h3>
                <p>مقبولين</p>
            </div>
        </a>

        <a href="?status=suspended" class="stat-card animate-slideUp" style="text-decoration: none; animation-delay: 0.3s;">
            <div class="stat-icon danger">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php echo number_format($stats['suspended']); ?>
                </h3>
                <p>موقوفين</p>
            </div>
        </a>
    </div>

    <!-- قائمة مقدمي الخدمات -->
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-tie" style="color: var(--secondary-color);"></i>
                مقدمي الخدمات
                <?php if ($status): ?>
                    <span class="badge <?php echo getProviderStatusColor($status); ?>" style="margin-right: 10px;">
                        <?php echo getProviderStatusAr($status); ?>
                    </span>
                <?php endif; ?>
                <?php if ($serviceFilterLabel !== ''): ?>
                    <span class="badge badge-info" style="margin-right: 10px;">
                        الخدمة: <?php echo htmlspecialchars($serviceFilterLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </h3>
        </div>

        <div class="card-body">
            <!-- البحث -->
            <form method="GET" class="search-box">
                <input type="hidden" name="status" value="<?php echo $status; ?>">
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <div class="search-input" style="flex: 1; min-width: 240px;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="البحث بالاسم، الهاتف، المدينة أو الخدمة..."
                            value="<?php echo htmlspecialchars((string) $search, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <?php if ($hasProviderServicesTable): ?>
                        <select name="service_id" class="form-control" style="min-width: 240px;">
                            <option value="">كل الخدمات</option>
                            <?php foreach ($serviceFilterOptions as $serviceFilterOption): ?>
                                <option value="<?php echo (int) $serviceFilterOption['id']; ?>" <?php echo $serviceFilterId === (int) $serviceFilterOption['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($serviceFilterOption['display_name_ar'] ?? $serviceFilterOption['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">فلترة</button>
                    <?php if ($search || $serviceFilterId > 0 || $status): ?>
                        <a href="providers.php" class="btn btn-outline">مسح الفلاتر</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (empty($providers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👷</div>
                    <h3>لا يوجد مقدمي خدمات</h3>
                    <p>لم يتم العثور على أي مقدمي خدمات مطابقين</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>مقدم الخدمة</th>
                                <th>الهاتف</th>
                                <th>المدينة</th>
                                <th>الخدمات المنتسب لها</th>
                                <th>التقييم</th>
                                <th>الطلبات</th>
                                <th>العمولة</th>
                                <th>الحالة</th>
                                <th>التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $provider): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <img src="<?php echo imageUrl($provider['avatar'], 'https://ui-avatars.com/api/?name=' . urlencode($provider['full_name']) . '&background=7466ed&color=fff'); ?>"
                                                alt="" class="avatar avatar-circle">
                                            <div>
                                                <strong>
                                                    <?php echo $provider['full_name']; ?>
                                                </strong>
                                                <?php if ($provider['email']): ?>
                                                    <div style="font-size: 12px; color: #6b7280;">
                                                        <?php echo $provider['email']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td dir="ltr">
                                        <?php echo $provider['phone']; ?>
                                    </td>
                                    <td>
                                        <?php echo $provider['city'] ?: '-'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $providerId = (int) ($provider['id'] ?? 0);
                                        $providerServices = $providerServicesByProvider[$providerId] ?? [];
                                        ?>
                                        <?php if (empty($providerServices)): ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php else: ?>
                                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                                <?php foreach (array_slice($providerServices, 0, 3) as $serviceMeta): ?>
                                                    <?php
                                                    $serviceName = (string) ($serviceMeta['name'] ?? '');
                                                    $serviceImage = trim((string) ($serviceMeta['image'] ?? ''));
                                                    $serviceImageUrl = $serviceImage !== '' ? imageUrl($serviceImage) : '';
                                                    ?>
                                                    <span class="badge badge-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 8px;">
                                                        <?php if ($serviceImageUrl !== ''): ?>
                                                            <img src="<?php echo htmlspecialchars($serviceImageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                                alt=""
                                                                style="width: 16px; height: 16px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.5);">
                                                        <?php else: ?>
                                                            <i class="fas fa-tools" style="font-size: 10px;"></i>
                                                        <?php endif; ?>
                                                        <span><?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($providerServices) > 3): ?>
                                                    <span class="badge badge-info">+<?php echo count($providerServices) - 3; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                                            <span>
                                                <?php echo number_format($provider['rating'], 1); ?>
                                            </span>
                                            <span style="color: #9ca3af; font-size: 12px;">(
                                                <?php echo $provider['total_reviews']; ?>)
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: var(--success-color);">
                                            <?php echo $provider['completed_orders']; ?>
                                        </span>
                                        /
                                        <span>
                                            <?php echo $provider['total_orders']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $provider['commission_rate']; ?>%
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getProviderStatusColor($provider['status']); ?>">
                                            <?php echo getProviderStatusAr($provider['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo timeAgo($provider['created_at']); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="?action=view&id=<?php echo $provider['id']; ?>" class="btn btn-sm btn-outline"
                                                title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?php echo $provider['id']; ?>" class="btn btn-sm btn-outline"
                                                title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($provider['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="قبول">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="رفض">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($provider['status'] === 'approved'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="إيقاف">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($provider['status'] === 'suspended'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="تفعيل">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف مقدم الخدمة؟');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="حذف">
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
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode((string) $status); ?>&search=<?php echo urlencode((string) $search); ?>&service_id=<?php echo $serviceFilterId; ?>"
                                class="page-link <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'view' && isset($provider)): ?>
    <!-- تفاصيل مقدم الخدمة -->
    <div style="margin-bottom: 20px;">
        <a href="providers.php" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            العودة للقائمة
        </a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">
        <!-- معلومات مقدم الخدمة -->
        <div class="card animate-slideUp">
            <div class="card-body" style="text-align: center;">
                <img src="<?php echo imageUrl($provider['avatar'], 'https://ui-avatars.com/api/?name=' . urlencode($provider['full_name']) . '&size=150&background=7466ed&color=fff'); ?>"
                    alt=""
                    style="width: 120px; height: 120px; border-radius: 50%; margin-bottom: 20px; border: 4px solid var(--secondary-color);">

                <h3 style="margin-bottom: 5px;">
                    <?php echo $provider['full_name']; ?>
                </h3>
                <p style="color: #6b7280; margin-bottom: 15px;">
                    <?php echo $provider['phone']; ?>
                </p>

                <span class="badge <?php echo getProviderStatusColor($provider['status']); ?>" style="margin-bottom: 20px;">
                    <?php echo getProviderStatusAr($provider['status']); ?>
                </span>

                <!-- التقييم -->
                <div
                    style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <i class="fas fa-star" style="color: #f59e0b; font-size: 28px;"></i>
                        <span style="font-size: 32px; font-weight: 700; color: #92400e;">
                            <?php echo number_format($provider['rating'], 1); ?>
                        </span>
                    </div>
                    <div style="color: #92400e; font-size: 14px;">
                        <?php echo $provider['total_reviews']; ?> تقييم
                    </div>
                </div>

                <!-- الإحصائيات -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div style="background: var(--gray-50); border-radius: 10px; padding: 15px;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">
                            <?php echo $provider['completed_orders']; ?>
                        </div>
                        <div style="color: #6b7280; font-size: 12px;">طلب مكتمل</div>
                    </div>
                    <div style="background: var(--gray-50); border-radius: 10px; padding: 15px;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--gray-800);">
                            <?php echo $provider['total_orders']; ?>
                        </div>
                        <div style="color: #6b7280; font-size: 12px;">إجمالي الطلبات</div>
                    </div>
                </div>

                <!-- رصيد المحفظة -->
                <div
                    style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="font-size: 28px; font-weight: 700; color: #065f46;">
                        <?php echo number_format($provider['wallet_balance'], 2); ?> ⃁
                    </div>
                    <div style="color: #065f46; font-size: 14px;">رصيد المحفظة</div>
                </div>

                <!-- المعلومات -->
                <div style="text-align: right; border-top: 1px solid var(--gray-200); padding-top: 20px;">
                    <div style="margin-bottom: 10px;"><strong>البريد:</strong>
                        <?php echo $provider['email'] ?: '-'; ?>
                    </div>
                    <div style="margin-bottom: 10px;"><strong>المدينة:</strong>
                        <?php echo $provider['city'] ?: '-'; ?>
                    </div>
                    <div style="margin-bottom: 10px;"><strong>الحي:</strong>
                        <?php echo $provider['district'] ?: '-'; ?>
                    </div>
                    <div style="margin-bottom: 10px;"><strong>ملف الإقامة:</strong>
                        <?php if (!empty($provider['residency_document_path'])): ?>
                            <?php $residencyUrl = providerDocumentUrl((string) $provider['residency_document_path']); ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                                <a href="<?php echo htmlspecialchars($residencyUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
                                    <i class="fas fa-eye"></i> معاينة
                                </a>
                                <a href="<?php echo htmlspecialchars($residencyUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                                    <i class="fas fa-file-arrow-down"></i> فتح
                                </a>
                            </div>
                            <?php if (preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', (string) $provider['residency_document_path'])): ?>
                                <div style="margin-top: 12px;">
                                    <img src="<?php echo htmlspecialchars($residencyUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="مستند الإقامة" style="width: 100%; max-height: 220px; object-fit: contain; border-radius: 12px; border: 1px solid #e5e7eb; background: #fff;">
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                    <?php if (providerAdminColumnExists('ajeer_certificate_path')): ?>
                        <div style="margin-bottom: 10px;"><strong>شهادة أجير:</strong>
                            <?php if (!empty($provider['ajeer_certificate_path'])): ?>
                                <?php $ajeerUrl = providerDocumentUrl((string) $provider['ajeer_certificate_path']); ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                                    <a href="<?php echo htmlspecialchars($ajeerUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i> معاينة
                                    </a>
                                    <a href="<?php echo htmlspecialchars($ajeerUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file-arrow-down"></i> فتح
                                    </a>
                                </div>
                                <?php if (preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', (string) $provider['ajeer_certificate_path'])): ?>
                                    <div style="margin-top: 12px;">
                                        <img src="<?php echo htmlspecialchars($ajeerUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="شهادة أجير" style="width: 100%; max-height: 220px; object-fit: contain; border-radius: 12px; border: 1px solid #e5e7eb; background: #fff;">
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div style="margin-bottom: 10px;"><strong>سنوات الخبرة:</strong>
                        <?php echo $provider['experience_years']; ?>
                    </div>
                    <div style="margin-bottom: 10px;"><strong>نسبة العمولة:</strong>
                        <?php echo $provider['commission_rate']; ?>%
                    </div>
                    <div style="margin-bottom: 10px;"><strong>التسجيل:</strong>
                        <?php echo formatDateTime($provider['created_at']); ?>
                    </div>
                    <?php if ($provider['approved_at']): ?>
                        <div><strong>تاريخ الموافقة:</strong>
                            <?php echo formatDateTime($provider['approved_at']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- نبذة -->
                <?php if ($provider['bio']): ?>
                    <div style="text-align: right; border-top: 1px solid var(--gray-200); padding-top: 20px; margin-top: 20px;">
                        <strong>نبذة:</strong>
                        <p style="color: #6b7280; margin-top: 5px;">
                            <?php echo $provider['bio']; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- الإجراءات -->
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <a href="?action=edit&id=<?php echo (int) $provider['id']; ?>" class="btn btn-outline" style="flex: 1;">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                    <?php if ($provider['status'] === 'pending'): ?>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-check"></i> قبول
                            </button>
                        </form>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-block">
                                <i class="fas fa-times"></i> رفض
                            </button>
                        </form>
                    <?php elseif ($provider['status'] === 'approved'): ?>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="action" value="suspend">
                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-block">
                                <i class="fas fa-ban"></i> إيقاف
                            </button>
                        </form>
                    <?php elseif ($provider['status'] === 'suspended'): ?>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-check"></i> تفعيل
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <form method="POST" style="margin-top: 10px;" onsubmit="return confirm('هل أنت متأكد من حذف مقدم الخدمة؟');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="provider_id" value="<?php echo (int) $provider['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-trash"></i> حذف مقدم الخدمة
                    </button>
                </form>

                <!-- تعديل العمولة -->
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="update_commission">
                    <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="commission_rate" class="form-control"
                            value="<?php echo $provider['commission_rate']; ?>" min="0" max="100" step="0.5"
                            style="flex: 1;">
                        <button type="submit" class="btn btn-primary">تحديث العمولة</button>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <!-- الخدمات -->
            <div class="card animate-slideUp" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tools" style="color: var(--primary-color);"></i>
                        الخدمات المقدمة
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($providerServices)): ?>
                        <p style="color: #6b7280;">لم يتم تحديد خدمات بعد</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                            <?php foreach ($providerServices as $service): ?>
                                <?php
                                    $serviceLabel = $service['display_name_ar'] ?? (!empty($service['parent_name_ar']) ? $service['parent_name_ar'] . ' > ' . $service['name_ar'] : $service['name_ar']);
                                    $serviceImage = !empty($service['image']) ? imageUrl((string) $service['image']) : '';
                                ?>
                                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 14px; background: #fff;">
                                    <div style="width: 52px; height: 52px; border-radius: 12px; overflow: hidden; background: #f3f4f6; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php if ($serviceImage !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($serviceImage, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-tools" style="color: #6b7280;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 700; color: #111827;">
                                            <?php echo htmlspecialchars((string) $serviceLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <?php if (!empty($service['name_ur'])): ?>
                                            <div style="font-size: 12px; color: #6b7280;">
                                                <?php echo htmlspecialchars((string) $service['name_ur'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- الطلبات -->
            <div class="card animate-slideUp" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clipboard-list" style="color: var(--success-color);"></i>
                        آخر الطلبات
                    </h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($providerOrders)): ?>
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
                                        <th>العميل</th>
                                        <th>الخدمة</th>
                                        <th>المبلغ</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($providerOrders as $order): ?>
                                        <tr>
                                            <td><strong>
                                                    <?php echo $order['order_number']; ?>
                                                </strong></td>
                                            <td>
                                                <?php echo $order['user_name'] ?? 'غير معروف'; ?>
                                            </td>
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
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- التقييمات -->
            <div class="card animate-slideUp">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-star" style="color: #f59e0b;"></i>
                        آخر التقييمات
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($providerReviews)): ?>
                        <p style="color: #6b7280; text-align: center;">لا توجد تقييمات بعد</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <?php foreach ($providerReviews as $review): ?>
                                <div style="background: var(--gray-50); border-radius: 12px; padding: 15px;">
                                    <div
                                        style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                        <strong>
                                            <?php echo $review['user_name'] ?? 'مستخدم'; ?>
                                        </strong>
                                        <div style="display: flex; gap: 3px;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star"
                                                    style="color: <?php echo $i <= $review['rating'] ? '#f59e0b' : '#e5e7eb'; ?>; font-size: 14px;"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if ($review['comment']): ?>
                                        <p style="color: #6b7280; font-size: 14px; margin: 0;">
                                            <?php echo $review['comment']; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'edit' && isset($provider)): ?>
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <a href="providers.php?action=view&id=<?php echo (int) $provider['id']; ?>" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            الرجوع لملف مقدم الخدمة
        </a>
        <a href="providers.php" class="btn btn-outline">القائمة</a>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-edit" style="color: var(--primary-color);"></i>
                تعديل بيانات مقدم الخدمة
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" id="provider-edit-action" value="update_profile">
                <input type="hidden" name="provider_id" value="<?php echo (int) $provider['id']; ?>">

                <div style="display: grid; grid-template-columns: repeat(2, minmax(260px, 1fr)); gap: 14px;">
                    <div>
                        <label class="form-label">الاسم الكامل</label>
                        <input type="text" name="full_name" class="form-control" required
                            value="<?php echo htmlspecialchars((string) ($provider['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label class="form-label">الهاتف</label>
                        <input type="text" name="phone" class="form-control" required
                            value="<?php echo htmlspecialchars((string) ($provider['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($provider['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label class="form-label">واتساب</label>
                        <input type="text" name="whatsapp_number" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($provider['whatsapp_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label class="form-label">الدولة</label>
                        <input type="text" name="country" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($provider['country'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label class="form-label">المدينة</label>
                        <input type="text" name="city" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($provider['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label class="form-label">الحي</label>
                        <input type="text" name="district" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($provider['district'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label class="form-label">سنوات الخبرة</label>
                        <input type="number" name="experience_years" class="form-control" min="0"
                            value="<?php echo (int) ($provider['experience_years'] ?? 0); ?>">
                    </div>
                    <div>
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-control">
                            <?php foreach (['pending' => 'قيد المراجعة', 'approved' => 'مقبول', 'rejected' => 'مرفوض', 'suspended' => 'موقوف'] as $statusValue => $statusLabel): ?>
                                <option value="<?php echo $statusValue; ?>" <?php echo (($provider['status'] ?? '') === $statusValue) ? 'selected' : ''; ?>>
                                    <?php echo $statusLabel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">متاح لاستقبال الطلبات</label>
                        <select name="is_available" class="form-control">
                            <option value="1" <?php echo ((int) ($provider['is_available'] ?? 0) === 1) ? 'selected' : ''; ?>>نعم</option>
                            <option value="0" <?php echo ((int) ($provider['is_available'] ?? 0) !== 1) ? 'selected' : ''; ?>>لا</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">قفل التخصصات</label>
                        <select name="categories_locked" class="form-control">
                            <option value="1" <?php echo ((int) ($provider['categories_locked'] ?? 0) === 1) ? 'selected' : ''; ?>>مقفلة</option>
                            <option value="0" <?php echo ((int) ($provider['categories_locked'] ?? 0) !== 1) ? 'selected' : ''; ?>>مفتوحة</option>
                        </select>
                    </div>
                    <?php if (providerAdminColumnExists('residency_document_path')): ?>
                        <div style="grid-column: 1 / -1;">
                            <label class="form-label">مسار ملف الإقامة</label>
                            <input type="text" name="residency_document_path" class="form-control"
                                value="<?php echo htmlspecialchars((string) ($provider['residency_document_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="uploads/provider-documents/example.jpg">
                            <?php $editResidencyPath = trim((string) ($provider['residency_document_path'] ?? '')); ?>
                            <?php if ($editResidencyPath !== ''): ?>
                                <?php $editResidencyUrl = providerDocumentUrl($editResidencyPath); ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">
                                    <a href="<?php echo htmlspecialchars($editResidencyUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i> معاينة الملف
                                    </a>
                                    <a href="<?php echo htmlspecialchars($editResidencyUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file-arrow-down"></i> فتح
                                    </a>
                                </div>
                                <?php if (preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', $editResidencyPath)): ?>
                                    <div style="margin-top: 12px; max-width: 320px;">
                                        <img src="<?php echo htmlspecialchars($editResidencyUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="معاينة ملف الإقامة" style="width: 100%; max-height: 220px; object-fit: contain; border-radius: 12px; border: 1px solid #e5e7eb; background: #fff;">
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">نبذة مقدم الخدمة</label>
                        <textarea name="bio" class="form-control" rows="4"><?php echo htmlspecialchars((string) ($provider['bio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <?php if (!empty($editCategoryOptions)): ?>
                    <div style="margin-top: 20px;">
                        <label class="form-label">التخصصات</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; border: 1px solid var(--gray-200); border-radius: 10px; padding: 12px;">
                            <?php foreach ($editCategoryOptions as $category): ?>
                                <?php $categoryId = (int) ($category['id'] ?? 0); ?>
                                <label style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid var(--gray-200); border-radius: 999px; cursor: pointer;">
                                    <input type="checkbox" name="category_ids[]" value="<?php echo $categoryId; ?>"
                                        <?php echo in_array($categoryId, $providerCategoryIds, true) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars((string) ($category['display_name_ar'] ?? $category['name_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ التعديلات
                    </button>
                    <?php if (($provider['status'] ?? '') !== 'approved'): ?>
                        <button type="submit" class="btn btn-success" onclick="document.getElementById('provider-edit-action').value='approve';">
                            <i class="fas fa-check"></i> موافقة فورية
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <form method="POST" style="margin-top: 16px;" onsubmit="return confirm('هل أنت متأكد من حذف مقدم الخدمة؟');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="provider_id" value="<?php echo (int) $provider['id']; ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> حذف مقدم الخدمة
                </button>
            </form>
        </div>
    </div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
