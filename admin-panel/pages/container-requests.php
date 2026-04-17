<?php
/**
 * صفحة طلبات الحاويات
 */

require_once '../init.php';
require_once '../includes/special_services.php';
requireLogin();

ensureSpecialServicesSchema();

if (!hasPermission('orders') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}
specialBackfillSpecialRequestsFromOrders(300);

$pageTitle = 'طلبات الحاويات';
$pageSubtitle = 'إدارة الطلبات المخصصة لقسم الحاويات';

$action = get('action', 'list');
$id = (int) get('id');
$statusOptions = specialRequestStatusOptions();

function containerNullableFloat(string $key): ?float
{
    $raw = trim((string) post($key));
    if ($raw === '') {
        return null;
    }
    return (float) $raw;
}

function containerNullableDate(string $key): ?string
{
    $raw = trim((string) post($key));
    return $raw !== '' ? $raw : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $serviceIdValue = (int) post('container_service_id');
        $serviceId = $serviceIdValue > 0 ? $serviceIdValue : null;
        $serviceDefaultStoreId = null;

        if ($serviceId !== null) {
            $serviceExists = db()->fetch('SELECT id, store_id FROM container_services WHERE id = ?', [$serviceId]);
            if (!$serviceExists) {
                setFlashMessage('danger', 'الخدمة المختارة غير موجودة');
                redirect('container-requests.php');
            }
            $storeFromService = (int) ($serviceExists['store_id'] ?? 0);
            if ($storeFromService > 0) {
                $serviceDefaultStoreId = $storeFromService;
            }
        }

        $storeIdValue = (int) post('container_store_id');
        $storeId = $storeIdValue > 0 ? $storeIdValue : null;
        if ($storeId === null && $serviceDefaultStoreId !== null) {
            $storeId = $serviceDefaultStoreId;
        }
        if ($storeId !== null) {
            $storeExists = db()->fetch('SELECT id FROM container_stores WHERE id = ?', [$storeId]);
            if (!$storeExists) {
                setFlashMessage('danger', 'متجر الحاويات المختار غير موجود');
                redirect('container-requests.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
            }
        }

        $customerName = clean(post('customer_name'));
        $phone = clean(post('phone'));
        if ($customerName === '' || $phone === '') {
            setFlashMessage('danger', 'اسم العميل ورقم الجوال مطلوبان');
            redirect('container-requests.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $data = [
            'container_service_id' => $serviceId,
            'container_store_id' => $storeId,
            'customer_name' => $customerName,
            'phone' => $phone,
            'site_city' => clean(post('site_city')),
            'site_address' => clean(post('site_address')),
            'start_date' => containerNullableDate('start_date'),
            'end_date' => containerNullableDate('end_date'),
            'duration_days' => max(1, (int) post('duration_days', 1)),
            'quantity' => max(1, (int) post('quantity', 1)),
            'estimated_weight_kg' => containerNullableFloat('estimated_weight_kg'),
            'estimated_distance_meters' => containerNullableFloat('estimated_distance_meters'),
            'needs_loading_help' => isset($_POST['needs_loading_help']) ? 1 : 0,
            'needs_operator' => isset($_POST['needs_operator']) ? 1 : 0,
            'purpose' => clean(post('purpose')),
            'notes' => clean(post('notes')),
            'status' => normalizeSpecialRequestStatus(post('status', 'new')),
            'estimated_price' => containerNullableFloat('estimated_price'),
            'final_price' => containerNullableFloat('final_price'),
            'admin_notes' => clean(post('admin_notes')),
        ];

        if ($postAction === 'add') {
            $data['request_number'] = specialGenerateRequestNumber('CT', 'container_requests');
            $newId = db()->insert('container_requests', $data);
            logActivity('add_container_request', 'container_requests', $newId);
            setFlashMessage('success', 'تم إضافة طلب الحاويات بنجاح');
        } else {
            $requestId = (int) post('id');
            db()->update('container_requests', $data, 'id = ?', [$requestId]);
            logActivity('update_container_request', 'container_requests', $requestId);
            setFlashMessage('success', 'تم تحديث طلب الحاويات بنجاح');
        }

        redirect('container-requests.php');
    }

    if ($postAction === 'delete') {
        $requestId = (int) post('id');
        db()->delete('container_requests', 'id = ?', [$requestId]);
        logActivity('delete_container_request', 'container_requests', $requestId);
        setFlashMessage('success', 'تم حذف الطلب بنجاح');
        redirect('container-requests.php');
    }
}

$statusFilter = trim((string) get('status'));
$search = trim((string) get('search'));

$where = [];
$params = [];

if ($statusFilter !== '' && isset($statusOptions[$statusFilter])) {
    $where[] = 'cr.status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where[] = '(cr.request_number LIKE ? OR cr.customer_name LIKE ? OR cr.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$ordersJoinSql = '';
$orderPaymentSelectSql = 'NULL AS order_payment_status';
$orderGatewaySelectSql = 'NULL AS order_gateway_status';
if (specialServiceTableExists('orders')) {
    $ordersJoinSql = 'LEFT JOIN orders o ON o.id = cr.source_order_id';
    if (specialServiceColumnExists('orders', 'payment_status')) {
        $orderPaymentSelectSql = 'o.payment_status AS order_payment_status';
    }
    if (specialServiceColumnExists('orders', 'myfatoorah_invoice_status')) {
        $orderGatewaySelectSql = 'o.myfatoorah_invoice_status AS order_gateway_status';
    }
}

$requests = db()->fetchAll(
    "SELECT cr.*, cs.name_ar AS service_name, cs.container_size, cst.name_ar AS store_name, {$orderPaymentSelectSql}, {$orderGatewaySelectSql}
     FROM container_requests cr
     LEFT JOIN container_services cs ON cs.id = cr.container_service_id
     LEFT JOIN container_stores cst ON cst.id = COALESCE(cr.container_store_id, cs.store_id)
     {$ordersJoinSql}
     {$whereSql}
     ORDER BY cr.id DESC",
    $params
);

$services = db()->fetchAll('SELECT id, name_ar, container_size FROM container_services ORDER BY is_active DESC, sort_order ASC, id ASC');
$stores = db()->fetchAll('SELECT id, name_ar FROM container_stores ORDER BY is_active DESC, sort_order ASC, id ASC');

if ($action === 'edit' && $id) {
    $request = db()->fetch('SELECT * FROM container_requests WHERE id = ?', [$id]);
    if (!$request) {
        setFlashMessage('danger', 'الطلب غير موجود');
        redirect('container-requests.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select name="status" class="form-control" style="min-width: 180px;">
                <option value="">كل الحالات</option>
                <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                    <option value="<?php echo $statusKey; ?>" <?php echo $statusFilter === $statusKey ? 'selected' : ''; ?>>
                        <?php echo $statusLabel; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-control" style="min-width: 220px;"
                placeholder="بحث برقم الطلب/اسم العميل/الجوال" value="<?php echo $search; ?>">
            <button type="submit" class="btn btn-outline">بحث</button>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a href="container-requests.php" class="btn btn-outline">إعادة ضبط</a>
            <?php endif; ?>
        </form>

        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة طلب جديد
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-truck-ramp-box"></i> طلبات الحاويات</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>طلب التطبيق</th>
                            <th>العميل</th>
                            <th>الخدمة</th>
                            <th>المتجر</th>
                            <th>الموقع</th>
                            <th>المدة / الكمية</th>
                            <th>الحالة</th>
                            <th>الدفع</th>
                            <th>التسعير</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $row): ?>
                            <tr>
                                <td><strong><?php echo $row['request_number']; ?></strong></td>
                                <td>
                                    <?php if (!empty($row['source_order_id'])): ?>
                                        <a href="orders.php?action=view&id=<?php echo (int) $row['source_order_id']; ?>" class="badge badge-primary">
                                            #<?php echo (int) $row['source_order_id']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">يدوي</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $row['customer_name']; ?><br>
                                    <small class="text-muted"><?php echo $row['phone']; ?></small>
                                </td>
                                <td>
                                    <?php echo $row['service_name'] ?: '<span class="text-muted">غير محدد</span>'; ?><br>
                                    <small class="text-muted"><?php echo $row['container_size'] ?: '-'; ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($row['store_name'])): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars((string) $row['store_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">غير محدد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $row['site_city'] ?: '-'; ?><br>
                                    <small class="text-muted"><?php echo $row['site_address'] ?: '-'; ?></small>
                                </td>
                                <td>
                                    <small>
                                        من: <?php echo $row['start_date'] ? formatDateAr($row['start_date']) : '-'; ?><br>
                                        إلى: <?php echo $row['end_date'] ? formatDateAr($row['end_date']) : '-'; ?><br>
                                        <?php echo (int) ($row['duration_days'] ?? 1); ?> يوم - <?php echo (int) ($row['quantity'] ?? 1); ?> حاوية
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo specialRequestStatusBadgeClass((string) $row['status']); ?>">
                                        <?php echo specialRequestStatusLabel((string) $row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $orderId = (int) ($row['source_order_id'] ?? 0);
                                        $paymentStatusRaw = strtolower(trim((string) ($row['order_payment_status'] ?? '')));
                                        $paymentClass = 'badge-warning';
                                        $paymentLabel = 'قيد الدفع';
                                        if ($orderId <= 0) {
                                            $paymentClass = 'badge-dark';
                                            $paymentLabel = 'يدوي';
                                        } elseif ($paymentStatusRaw === 'paid') {
                                            $paymentClass = 'badge-success';
                                            $paymentLabel = 'مدفوع';
                                        } elseif ($paymentStatusRaw === 'failed') {
                                            $paymentClass = 'badge-danger';
                                            $paymentLabel = 'فشل الدفع';
                                        } elseif ($paymentStatusRaw === 'refunded') {
                                            $paymentClass = 'badge-info';
                                            $paymentLabel = 'مسترد';
                                        } elseif ($paymentStatusRaw === 'pending' || $paymentStatusRaw === '') {
                                            $paymentClass = 'badge-warning';
                                            $paymentLabel = 'قيد الدفع';
                                        } else {
                                            $paymentClass = 'badge-primary';
                                            $paymentLabel = $paymentStatusRaw;
                                        }
                                        $gatewayStatus = trim((string) ($row['order_gateway_status'] ?? ''));
                                    ?>
                                    <span class="badge <?php echo $paymentClass; ?>">
                                        <?php echo htmlspecialchars($paymentLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <?php if ($gatewayStatus !== ''): ?>
                                        <div style="margin-top: 6px; font-size: 11px; color: #475569;">
                                            MyFatoorah: <?php echo htmlspecialchars($gatewayStatus, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        مبدئي: <?php echo $row['estimated_price'] !== null ? number_format((float) $row['estimated_price'], 2) . ' ⃁' : '-'; ?><br>
                                        نهائي: <?php echo $row['final_price'] !== null ? number_format((float) $row['final_price'], 2) . ' ⃁' : '-'; ?><br>
                                        وزن: <?php echo $row['estimated_weight_kg'] !== null ? number_format((float) $row['estimated_weight_kg'], 2) . ' كجم' : '-'; ?><br>
                                        مسافة: <?php echo $row['estimated_distance_meters'] !== null ? number_format((float) $row['estimated_distance_meters'], 2) . ' متر' : '-'; ?>
                                    </small>
                                </td>
                                <td><?php echo formatDateTime($row['created_at']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <?php if (!empty($row['source_order_id'])): ?>
                                            <a href="orders.php?action=view&id=<?php echo (int) $row['source_order_id']; ?>" class="btn btn-sm btn-outline" title="معاينة الطلب">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف الطلب؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="12" class="text-center">لا توجد طلبات حالياً</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="add-modal">
        <div class="modal" style="width: 920px; max-width: 95%;">
            <div class="modal-header">
                <h3 class="modal-title">إضافة طلب حاويات</h3>
                <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">اسم العميل</label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الجوال</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الخدمة</label>
                            <select name="container_service_id" class="form-control">
                                <option value="">بدون تحديد</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service['id']; ?>">
                                        <?php echo $service['name_ar']; ?> (<?php echo $service['container_size']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">متجر الحاويات</label>
                            <select name="container_store_id" class="form-control">
                                <option value="">تلقائي حسب الخدمة</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo (int) $store['id']; ?>">
                                        <?php echo htmlspecialchars((string) $store['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">المدينة</label>
                            <input type="text" name="site_city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">موقع/عنوان التركيب</label>
                            <input type="text" name="site_address" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">تاريخ البداية</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">تاريخ النهاية</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">مدة الإيجار (يوم)</label>
                            <input type="number" min="1" name="duration_days" class="form-control" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكمية</label>
                            <input type="number" min="1" name="quantity" class="form-control" value="1">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">وزن تقريبي (كجم)</label>
                            <input type="number" min="0" step="0.01" name="estimated_weight_kg" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">مسافة تقديرية (متر)</label>
                            <input type="number" min="0" step="0.01" name="estimated_distance_meters" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-control">
                                <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                                    <option value="<?php echo $statusKey; ?>" <?php echo $statusKey === 'new' ? 'selected' : ''; ?>>
                                        <?php echo $statusLabel; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">سعر مبدئي (⃁)</label>
                            <input type="number" step="0.01" min="0" name="estimated_price" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">سعر نهائي (⃁)</label>
                            <input type="number" step="0.01" min="0" name="final_price" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="needs_loading_help" value="1">
                                يحتاج دعم تحميل/تنزيل
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="needs_operator" value="1">
                                يحتاج مشغّل/سائق
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الغرض من الطلب</label>
                        <input type="text" name="purpose" class="form-control" placeholder="مثال: مشروع إنشائي">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ملاحظات العميل</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ملاحظات الإدارة</label>
                        <textarea name="admin_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ الطلب</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'edit' && isset($request)): ?>
    <div class="card animate-slideUp" style="max-width: 980px; margin: 0 auto;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">تعديل طلب: <?php echo $request['request_number']; ?></h3>
            <span class="badge <?php echo specialRequestStatusBadgeClass((string) $request['status']); ?>">
                <?php echo specialRequestStatusLabel((string) $request['status']); ?>
            </span>
        </div>
        <form method="POST">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $request['id']; ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">اسم العميل</label>
                        <input type="text" name="customer_name" class="form-control" value="<?php echo $request['customer_name']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم الجوال</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $request['phone']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الخدمة</label>
                        <select name="container_service_id" class="form-control">
                            <option value="">بدون تحديد</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo (int) ($request['container_service_id'] ?? 0) === (int) $service['id'] ? 'selected' : ''; ?>>
                                    <?php echo $service['name_ar']; ?> (<?php echo $service['container_size']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">متجر الحاويات</label>
                        <select name="container_store_id" class="form-control">
                            <option value="">تلقائي حسب الخدمة</option>
                            <?php foreach ($stores as $storeItem): ?>
                                <option value="<?php echo (int) $storeItem['id']; ?>" <?php echo (int) ($request['container_store_id'] ?? 0) === (int) $storeItem['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $storeItem['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">المدينة</label>
                        <input type="text" name="site_city" class="form-control" value="<?php echo $request['site_city']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">موقع/عنوان التركيب</label>
                        <input type="text" name="site_address" class="form-control" value="<?php echo $request['site_address']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">تاريخ البداية</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $request['start_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ النهاية</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $request['end_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">مدة الإيجار (يوم)</label>
                        <input type="number" min="1" name="duration_days" class="form-control" value="<?php echo (int) ($request['duration_days'] ?? 1); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الكمية</label>
                        <input type="number" min="1" name="quantity" class="form-control" value="<?php echo (int) ($request['quantity'] ?? 1); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">وزن تقريبي (كجم)</label>
                        <input type="number" min="0" step="0.01" name="estimated_weight_kg" class="form-control"
                            value="<?php echo $request['estimated_weight_kg']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">مسافة تقديرية (متر)</label>
                        <input type="number" min="0" step="0.01" name="estimated_distance_meters" class="form-control"
                            value="<?php echo $request['estimated_distance_meters']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-control">
                            <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                                <option value="<?php echo $statusKey; ?>" <?php echo $request['status'] === $statusKey ? 'selected' : ''; ?>>
                                    <?php echo $statusLabel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">سعر مبدئي (⃁)</label>
                        <input type="number" step="0.01" min="0" name="estimated_price" class="form-control"
                            value="<?php echo $request['estimated_price']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">سعر نهائي (⃁)</label>
                        <input type="number" step="0.01" min="0" name="final_price" class="form-control"
                            value="<?php echo $request['final_price']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="needs_loading_help" value="1" <?php echo !empty($request['needs_loading_help']) ? 'checked' : ''; ?>>
                            يحتاج دعم تحميل/تنزيل
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="needs_operator" value="1" <?php echo !empty($request['needs_operator']) ? 'checked' : ''; ?>>
                            يحتاج مشغّل/سائق
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الغرض من الطلب</label>
                    <input type="text" name="purpose" class="form-control" value="<?php echo $request['purpose']; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظات العميل</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo $request['notes']; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظات الإدارة</label>
                    <textarea name="admin_notes" class="form-control" rows="3"><?php echo $request['admin_notes']; ?></textarea>
                </div>
            </div>
            <div class="card-footer">
                <a href="container-requests.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
