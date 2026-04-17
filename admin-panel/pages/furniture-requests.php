<?php
/**
 * صفحة طلبات نقل العفش
 */

require_once '../init.php';
require_once '../includes/special_services.php';
requireLogin();

ensureSpecialServicesSchema();

if (!hasPermission('orders') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}
specialBackfillSpecialRequestsFromOrders(300);

$pageTitle = 'طلبات نقل العفش';
$pageSubtitle = 'إدارة الطلبات المخصصة لقسم نقل العفش';

$action = get('action', 'list');
$id = (int) get('id');
$statusOptions = specialRequestStatusOptions();

function furnitureNullableFloat(string $key): ?float
{
    $raw = trim((string) post($key));
    if ($raw === '') {
        return null;
    }
    return (float) $raw;
}

function furnitureNullableDate(string $key): ?string
{
    $raw = trim((string) post($key));
    return $raw !== '' ? $raw : null;
}

function furnitureNormalizeDetailsJson(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }

    return json_encode($decoded, JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $serviceIdValue = (int) post('service_id');
        $serviceId = $serviceIdValue > 0 ? $serviceIdValue : null;
        $areaIdValue = (int) post('area_id');
        $areaId = $areaIdValue > 0 ? $areaIdValue : null;

        if ($serviceId !== null) {
            $serviceExists = db()->fetch('SELECT id FROM furniture_services WHERE id = ?', [$serviceId]);
            if (!$serviceExists) {
                setFlashMessage('danger', 'الخدمة المختارة غير موجودة');
                redirect('furniture-requests.php');
            }
        }

        $customerName = clean(post('customer_name'));
        $phone = clean(post('phone'));
        if ($customerName === '' || $phone === '') {
            setFlashMessage('danger', 'اسم العميل ورقم الجوال مطلوبان');
            redirect('furniture-requests.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $areaName = null;
        if ($areaId !== null) {
            $areaRow = db()->fetch('SELECT id, name_ar FROM furniture_areas WHERE id = ?', [$areaId]);
            if (!$areaRow) {
                setFlashMessage('danger', 'المنطقة المختارة غير موجودة');
                redirect('furniture-requests.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
            }
            $areaName = $areaRow['name_ar'] ?? null;
        }

        $detailsJson = furnitureNormalizeDetailsJson((string) post('details_json'));
        if (trim((string) post('details_json')) !== '' && $detailsJson === null) {
            setFlashMessage('danger', 'صيغة JSON في تفاصيل الطلب غير صحيحة');
            redirect('furniture-requests.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $data = [
            'service_id' => $serviceId,
            'area_id' => $areaId,
            'area_name' => $areaName,
            'customer_name' => $customerName,
            'phone' => $phone,
            'pickup_city' => clean(post('pickup_city')),
            'pickup_address' => clean(post('pickup_address')),
            'dropoff_city' => clean(post('dropoff_city')),
            'dropoff_address' => clean(post('dropoff_address')),
            'move_date' => furnitureNullableDate('move_date'),
            'preferred_time' => clean(post('preferred_time')),
            'rooms_count' => max(1, (int) post('rooms_count', 1)),
            'floors_from' => max(0, (int) post('floors_from', 0)),
            'floors_to' => max(0, (int) post('floors_to', 0)),
            'elevator_from' => isset($_POST['elevator_from']) ? 1 : 0,
            'elevator_to' => isset($_POST['elevator_to']) ? 1 : 0,
            'needs_packing' => isset($_POST['needs_packing']) ? 1 : 0,
            'estimated_items' => max(0, (int) post('estimated_items', 0)),
            'estimated_weight_kg' => furnitureNullableFloat('estimated_weight_kg'),
            'estimated_distance_meters' => furnitureNullableFloat('estimated_distance_meters'),
            'details_json' => $detailsJson,
            'notes' => clean(post('notes')),
            'status' => normalizeSpecialRequestStatus(post('status', 'new')),
            'estimated_price' => furnitureNullableFloat('estimated_price'),
            'final_price' => furnitureNullableFloat('final_price'),
            'admin_notes' => clean(post('admin_notes')),
        ];

        if ($postAction === 'add') {
            $data['request_number'] = specialGenerateRequestNumber('FM', 'furniture_requests');
            $newId = db()->insert('furniture_requests', $data);
            logActivity('add_furniture_request', 'furniture_requests', $newId);
            setFlashMessage('success', 'تم إضافة طلب نقل العفش بنجاح');
        } else {
            $requestId = (int) post('id');
            db()->update('furniture_requests', $data, 'id = ?', [$requestId]);
            logActivity('update_furniture_request', 'furniture_requests', $requestId);
            setFlashMessage('success', 'تم تحديث طلب نقل العفش بنجاح');
        }

        redirect('furniture-requests.php');
    }

    if ($postAction === 'delete') {
        $requestId = (int) post('id');
        db()->delete('furniture_requests', 'id = ?', [$requestId]);
        logActivity('delete_furniture_request', 'furniture_requests', $requestId);
        setFlashMessage('success', 'تم حذف الطلب بنجاح');
        redirect('furniture-requests.php');
    }
}

$statusFilter = trim((string) get('status'));
$search = trim((string) get('search'));

$where = [];
$params = [];

if ($statusFilter !== '' && isset($statusOptions[$statusFilter])) {
    $where[] = 'fr.status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where[] = '(fr.request_number LIKE ? OR fr.customer_name LIKE ? OR fr.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$requests = db()->fetchAll(
    "SELECT fr.*, fs.name_ar AS service_name, fa.name_ar AS area_display_name
     FROM furniture_requests fr
     LEFT JOIN furniture_services fs ON fs.id = fr.service_id
     LEFT JOIN furniture_areas fa ON fa.id = fr.area_id
     {$whereSql}
     ORDER BY fr.id DESC",
    $params
);

$services = db()->fetchAll('SELECT id, name_ar FROM furniture_services ORDER BY is_active DESC, sort_order ASC, id ASC');
$areas = db()->fetchAll('SELECT id, name_ar FROM furniture_areas WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');

if ($action === 'edit' && $id) {
    $request = db()->fetch('SELECT * FROM furniture_requests WHERE id = ?', [$id]);
    if (!$request) {
        setFlashMessage('danger', 'الطلب غير موجود');
        redirect('furniture-requests.php');
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
                <a href="furniture-requests.php" class="btn btn-outline">إعادة ضبط</a>
            <?php endif; ?>
        </form>

        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة طلب جديد
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-dolly"></i> طلبات نقل العفش</h3>
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
                            <th>المنطقة</th>
                            <th>المسار</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
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
                                <td><?php echo $row['service_name'] ?: '<span class="text-muted">غير محدد</span>'; ?></td>
                                <td><?php echo $row['area_display_name'] ?: ($row['area_name'] ?: '<span class="text-muted">غير محدد</span>'); ?></td>
                                <td>
                                    <small>
                                        <?php echo $row['pickup_city'] ?: '-'; ?>
                                        <i class="fas fa-arrow-left" style="margin: 0 4px;"></i>
                                        <?php echo $row['dropoff_city'] ?: '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo $row['move_date'] ? formatDateAr($row['move_date']) : '-'; ?><br>
                                    <small class="text-muted"><?php echo $row['preferred_time'] ?: '-'; ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php echo specialRequestStatusBadgeClass((string) $row['status']); ?>">
                                        <?php echo specialRequestStatusLabel((string) $row['status']); ?>
                                    </span>
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
                                <td colspan="11" class="text-center">لا توجد طلبات حالياً</td>
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
                <h3 class="modal-title">إضافة طلب نقل عفش</h3>
                <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
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
                            <select name="service_id" class="form-control">
                                <option value="">بدون تحديد</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service['id']; ?>"><?php echo $service['name_ar']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">المنطقة</label>
                        <select name="area_id" class="form-control">
                            <option value="">بدون تحديد</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['id']; ?>"><?php echo $area['name_ar']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">مدينة التحميل</label>
                            <input type="text" name="pickup_city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">عنوان التحميل</label>
                            <input type="text" name="pickup_address" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">مدينة التنزيل</label>
                            <input type="text" name="dropoff_city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">عنوان التنزيل</label>
                            <input type="text" name="dropoff_address" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">تاريخ النقل</label>
                            <input type="date" name="move_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الوقت المفضل</label>
                            <input type="text" name="preferred_time" class="form-control" placeholder="صباحًا / مساءً">
                        </div>
                        <div class="form-group">
                            <label class="form-label">عدد الغرف</label>
                            <input type="number" min="1" name="rooms_count" class="form-control" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">عدد القطع التقريبي</label>
                            <input type="number" min="0" name="estimated_items" class="form-control" value="0">
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

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">الدور (من)</label>
                            <input type="number" min="0" name="floors_from" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الدور (إلى)</label>
                            <input type="number" min="0" name="floors_to" class="form-control" value="0">
                        </div>
                        <div class="form-group" style="display: flex; align-items: end;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="elevator_from" value="1">
                                يوجد مصعد (من)
                            </label>
                        </div>
                        <div class="form-group" style="display: flex; align-items: end;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="elevator_to" value="1">
                                يوجد مصعد (إلى)
                            </label>
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

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="needs_packing" value="1">
                            يحتاج تغليف/توضيب
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ملاحظات العميل</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ملاحظات الإدارة</label>
                        <textarea name="admin_notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">تفاصيل الطلب (JSON)</label>
                        <textarea name="details_json" class="form-control" rows="3"
                            placeholder='مثال: {\"fields\":{\"rooms_count\":\"3\"}}'></textarea>
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

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
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
                        <select name="service_id" class="form-control">
                            <option value="">بدون تحديد</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo (int) ($request['service_id'] ?? 0) === (int) $service['id'] ? 'selected' : ''; ?>>
                                    <?php echo $service['name_ar']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">المنطقة</label>
                    <select name="area_id" class="form-control">
                        <option value="">بدون تحديد</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo $area['id']; ?>" <?php echo (int) ($request['area_id'] ?? 0) === (int) $area['id'] ? 'selected' : ''; ?>>
                                <?php echo $area['name_ar']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">مدينة التحميل</label>
                        <input type="text" name="pickup_city" class="form-control" value="<?php echo $request['pickup_city']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عنوان التحميل</label>
                        <input type="text" name="pickup_address" class="form-control" value="<?php echo $request['pickup_address']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">مدينة التنزيل</label>
                        <input type="text" name="dropoff_city" class="form-control" value="<?php echo $request['dropoff_city']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عنوان التنزيل</label>
                        <input type="text" name="dropoff_address" class="form-control" value="<?php echo $request['dropoff_address']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">تاريخ النقل</label>
                        <input type="date" name="move_date" class="form-control" value="<?php echo $request['move_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوقت المفضل</label>
                        <input type="text" name="preferred_time" class="form-control" value="<?php echo $request['preferred_time']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عدد الغرف</label>
                        <input type="number" min="1" name="rooms_count" class="form-control" value="<?php echo (int) ($request['rooms_count'] ?? 1); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عدد القطع التقريبي</label>
                        <input type="number" min="0" name="estimated_items" class="form-control" value="<?php echo (int) ($request['estimated_items'] ?? 0); ?>">
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

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الدور (من)</label>
                        <input type="number" min="0" name="floors_from" class="form-control" value="<?php echo (int) ($request['floors_from'] ?? 0); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الدور (إلى)</label>
                        <input type="number" min="0" name="floors_to" class="form-control" value="<?php echo (int) ($request['floors_to'] ?? 0); ?>">
                    </div>
                    <div class="form-group" style="display: flex; align-items: end;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="elevator_from" value="1" <?php echo !empty($request['elevator_from']) ? 'checked' : ''; ?>>
                            يوجد مصعد (من)
                        </label>
                    </div>
                    <div class="form-group" style="display: flex; align-items: end;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="elevator_to" value="1" <?php echo !empty($request['elevator_to']) ? 'checked' : ''; ?>>
                            يوجد مصعد (إلى)
                        </label>
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

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="needs_packing" value="1" <?php echo !empty($request['needs_packing']) ? 'checked' : ''; ?>>
                        يحتاج تغليف/توضيب
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظات العميل</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo $request['notes']; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظات الإدارة</label>
                    <textarea name="admin_notes" class="form-control" rows="3"><?php echo $request['admin_notes']; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">تفاصيل الطلب (JSON)</label>
                    <textarea name="details_json" class="form-control" rows="4"><?php echo htmlspecialchars((string) ($request['details_json'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            <div class="card-footer">
                <a href="furniture-requests.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
