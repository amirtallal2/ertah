<?php
/**
 * صفحة إدارة المتاجر
 * Stores Management Page
 */

require_once '../init.php';
require_once '../includes/store_accounting.php';
requireLogin();

ensureStoreSparePartsAccountingSchema();

if (!hasPermission('products') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

function ensureStoreGeoSchema()
{
    $latColumn = db()->fetch("SHOW COLUMNS FROM stores LIKE 'lat'");
    if (!$latColumn) {
        db()->query("ALTER TABLE `stores` ADD COLUMN `lat` DECIMAL(10,8) NULL");
    }

    $lngColumn = db()->fetch("SHOW COLUMNS FROM stores LIKE 'lng'");
    if (!$lngColumn) {
        db()->query("ALTER TABLE `stores` ADD COLUMN `lng` DECIMAL(11,8) NULL");
    }
}

ensureStoreGeoSchema();

$pageTitle = 'المتاجر';
$pageSubtitle = 'إدارة متاجر قطع الغيار والأدوات';

$action = get('action', 'list');
$id = (int) get('id');
$currentAdmin = getCurrentAdmin();
$currentAdminId = (int) ($currentAdmin['id'] ?? 0);

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    // إضافة متجر
    if ($postAction === 'add') {
        $name_ar = post('name_ar');
        $name_en = post('name_en');
        $phone = post('phone');
        $email = post('email');
        $address = post('address');
        $latRaw = trim((string) post('lat', ''));
        $lngRaw = trim((string) post('lng', ''));
        $description_ar = post('description_ar');
        $sort_order = (int) post('sort_order');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;

        $lat = null;
        $lng = null;
        if ($latRaw !== '' || $lngRaw !== '') {
            if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
                setFlashMessage('danger', 'إحداثيات المتجر غير صحيحة');
                redirect('stores.php');
            }
            $lat = (float) $latRaw;
            $lng = (float) $lngRaw;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                setFlashMessage('danger', 'قيمة الإحداثيات خارج النطاق المسموح');
                redirect('stores.php');
            }
        }

        // رفع الشعار
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo'], 'stores');
            if ($upload['success']) {
                $logoPath = $upload['path'];
            }
        }

        // رفع البانر
        $bannerPath = null;
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['banner'], 'stores');
            if ($upload['success']) {
                $bannerPath = $upload['path'];
            }
        }

        $storeId = db()->insert('stores', [
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'description_ar' => $description_ar,
            'logo' => $logoPath,
            'banner' => $bannerPath,
            'sort_order' => $sort_order,
            'is_featured' => $is_featured,
            'is_active' => 1
        ]);

        logActivity('add_store', 'stores', $storeId);
        setFlashMessage('success', 'تم إضافة المتجر بنجاح');
        redirect('stores.php');
    }

    // تعديل متجر
    if ($postAction === 'edit') {
        $storeId = (int) post('store_id');
        $name_ar = post('name_ar');
        $name_en = post('name_en');
        $phone = post('phone');
        $email = post('email');
        $address = post('address');
        $latRaw = trim((string) post('lat', ''));
        $lngRaw = trim((string) post('lng', ''));
        $description_ar = post('description_ar');
        $sort_order = (int) post('sort_order');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $lat = null;
        $lng = null;
        if ($latRaw !== '' || $lngRaw !== '') {
            if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
                setFlashMessage('danger', 'إحداثيات المتجر غير صحيحة');
                redirect('stores.php?action=edit&id=' . $storeId);
            }
            $lat = (float) $latRaw;
            $lng = (float) $lngRaw;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                setFlashMessage('danger', 'قيمة الإحداثيات خارج النطاق المسموح');
                redirect('stores.php?action=edit&id=' . $storeId);
            }
        }

        $data = [
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'description_ar' => $description_ar,
            'sort_order' => $sort_order,
            'is_featured' => $is_featured,
            'is_active' => $is_active
        ];

        // تحديث الشعار
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo'], 'stores');
            if ($upload['success']) {
                $data['logo'] = $upload['path'];
            }
        }

        // تحديث البانر
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['banner'], 'stores');
            if ($upload['success']) {
                $data['banner'] = $upload['path'];
            }
        }

        db()->update('stores', $data, 'id = :id', ['id' => $storeId]);
        logActivity('update_store', 'stores', $storeId);
        setFlashMessage('success', 'تم تحديث بيانات المتجر بنجاح');
        redirect('stores.php');
    }

    // حذف متجر
    if ($postAction === 'delete') {
        $storeId = (int) post('store_id');

        $productsCount = db()->count('products', 'store_id = ?', [$storeId]);
        $sparePartsCount = db()->count('spare_parts', 'store_id = ?', [$storeId]);
        $accountEntriesCount = db()->count('store_account_entries', 'store_id = ?', [$storeId]);
        $movementsCount = db()->count('store_spare_part_movements', 'store_id = ?', [$storeId]);

        if ($productsCount > 0 || $sparePartsCount > 0 || $accountEntriesCount > 0 || $movementsCount > 0) {
            setFlashMessage('danger', 'لا يمكن حذف المتجر لوجود بيانات مرتبطة به (منتجات/قطع غيار/حركات حساب). يمكنك تعطيله بدلاً من ذلك.');
        } else {
            db()->delete('stores', 'id = ?', [$storeId]);
            logActivity('delete_store', 'stores', $storeId);
            setFlashMessage('success', 'تم حذف المتجر بنجاح');
        }
        redirect('stores.php');
    }

    // إضافة حركة مالية لحساب متجر
    if ($postAction === 'add_account_entry') {
        $storeId = (int) post('store_id');
        $entryType = post('entry_type');
        $amount = (float) post('amount');
        $notes = post('notes');

        if (!in_array($entryType, ['credit', 'debit'], true)) {
            setFlashMessage('danger', 'نوع الحركة المالية غير صحيح.');
            redirect('stores.php?action=account&id=' . $storeId);
        }

        if ($amount <= 0) {
            setFlashMessage('danger', 'المبلغ يجب أن يكون أكبر من صفر.');
            redirect('stores.php?action=account&id=' . $storeId);
        }

        if (!db()->count('stores', 'id = ?', [$storeId])) {
            setFlashMessage('danger', 'المتجر غير موجود.');
            redirect('stores.php');
        }

        $entryId = db()->insert('store_account_entries', [
            'store_id' => $storeId,
            'entry_type' => $entryType,
            'amount' => $amount,
            'source' => 'manual',
            'notes' => $notes,
            'created_by' => $currentAdminId ?: null
        ]);

        logActivity('store_account_entry', 'store_account_entries', $entryId);
        setFlashMessage('success', 'تم تسجيل الحركة المالية بنجاح.');
        redirect('stores.php?action=account&id=' . $storeId);
    }

    // إضافة حركة سحب/إرجاع من قطع الغيار
    if ($postAction === 'add_part_movement') {
        $storeId = (int) post('store_id');
        $sparePartId = (int) post('spare_part_id');
        $movementType = post('movement_type');
        $quantity = (int) post('quantity');
        $unitPriceInput = trim((string) post('unit_price'));
        $notes = post('notes');

        $allowedMovementTypes = ['withdrawal', 'return', 'adjustment_in', 'adjustment_out'];

        if (!in_array($movementType, $allowedMovementTypes, true)) {
            setFlashMessage('danger', 'نوع الحركة المخزنية غير صحيح.');
            redirect('stores.php?action=account&id=' . $storeId);
        }

        if ($quantity <= 0) {
            setFlashMessage('danger', 'الكمية يجب أن تكون أكبر من صفر.');
            redirect('stores.php?action=account&id=' . $storeId);
        }

        if (!db()->count('stores', 'id = ?', [$storeId])) {
            setFlashMessage('danger', 'المتجر غير موجود.');
            redirect('stores.php');
        }

        $unitPrice = null;
        if ($unitPriceInput !== '') {
            $unitPrice = (float) $unitPriceInput;
            if ($unitPrice < 0) {
                setFlashMessage('danger', 'سعر القطعة لا يمكن أن يكون سالبًا.');
                redirect('stores.php?action=account&id=' . $storeId);
            }
        }

        $connection = db()->getConnection();
        try {
            $connection->beginTransaction();

            $part = db()->fetch(
                "SELECT id, name_ar, stock_quantity FROM spare_parts WHERE id = ? AND store_id = ?",
                [$sparePartId, $storeId]
            );

            if (!$part) {
                throw new Exception('قطعة الغيار غير موجودة أو غير مرتبطة بهذا المتجر.');
            }

            $currentStock = (int) ($part['stock_quantity'] ?? 0);
            $isOut = in_array($movementType, ['withdrawal', 'adjustment_out'], true);

            if ($isOut) {
                if ($quantity > $currentStock) {
                    throw new Exception('لا يمكن سحب كمية أكبر من المخزون المتاح.');
                }
                $newStock = $currentStock - $quantity;
            } else {
                $newStock = $currentStock + $quantity;
            }

            db()->update('spare_parts', ['stock_quantity' => $newStock], 'id = ?', [$sparePartId]);

            $movementId = db()->insert('store_spare_part_movements', [
                'store_id' => $storeId,
                'spare_part_id' => $sparePartId,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'notes' => $notes,
                'created_by' => $currentAdminId ?: null
            ]);

            // إذا تم إدخال سعر للقطعة، نسجل حركة مالية تلقائية
            if ($unitPrice !== null && $unitPrice > 0) {
                $amount = $unitPrice * $quantity;
                $autoEntryType = $isOut ? 'credit' : 'debit';
                $autoSource = $movementType === 'withdrawal' ? 'withdrawal' : ($movementType === 'return' ? 'return' : 'adjustment');

                $autoNotePrefix = $notes !== '' ? ($notes . ' - ') : '';
                $autoNotes = $autoNotePrefix . 'حركة قطعة: ' . $part['name_ar'] . ' × ' . $quantity;

                db()->insert('store_account_entries', [
                    'store_id' => $storeId,
                    'entry_type' => $autoEntryType,
                    'amount' => $amount,
                    'source' => $autoSource,
                    'notes' => $autoNotes,
                    'reference_id' => $movementId,
                    'created_by' => $currentAdminId ?: null
                ]);
            }

            $connection->commit();
            logActivity('store_part_movement', 'store_spare_part_movements', $movementId);
            setFlashMessage('success', 'تم تسجيل حركة قطع الغيار وتحديث المخزون بنجاح.');
        } catch (Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            setFlashMessage('danger', $e->getMessage());
        }

        redirect('stores.php?action=account&id=' . $storeId);
    }
}

$stores = db()->fetchAll(
    "SELECT s.*,
            (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id) AS products_count,
            (SELECT COUNT(*) FROM spare_parts sp WHERE sp.store_id = s.id) AS spare_parts_count,
            (SELECT COALESCE(SUM(CASE WHEN m.movement_type IN ('withdrawal', 'adjustment_out') THEN m.quantity ELSE 0 END), 0)
               FROM store_spare_part_movements m
              WHERE m.store_id = s.id) AS withdrawn_parts_count,
            (SELECT COALESCE(SUM(CASE WHEN e.entry_type = 'credit' THEN e.amount ELSE 0 END), 0)
               FROM store_account_entries e
              WHERE e.store_id = s.id) AS credit_total,
            (SELECT COALESCE(SUM(CASE WHEN e.entry_type = 'debit' THEN e.amount ELSE 0 END), 0)
               FROM store_account_entries e
              WHERE e.store_id = s.id) AS debit_total
     FROM stores s
     ORDER BY s.sort_order ASC, s.created_at DESC"
);

$editStore = null;
$accountStore = null;
$accountSummary = null;
$movementSummary = null;
$storeSpareParts = [];
$storeAccountEntries = [];
$storeMovements = [];

// عرض بيانات متجر للتعديل
if ($action === 'edit' && $id) {
    $editStore = db()->fetch("SELECT * FROM stores WHERE id = ?", [$id]);
    if (!$editStore) {
        setFlashMessage('danger', 'المتجر غير موجود');
        redirect('stores.php');
    }
}

// عرض حساب متجر
if ($action === 'account' && $id) {
    $accountStore = db()->fetch("SELECT * FROM stores WHERE id = ?", [$id]);
    if (!$accountStore) {
        setFlashMessage('danger', 'المتجر غير موجود');
        redirect('stores.php');
    }

    $accountSummary = db()->fetch(
        "SELECT
            COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) AS credit_total,
            COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) AS debit_total
         FROM store_account_entries
         WHERE store_id = ?",
        [$id]
    );

    $movementSummary = db()->fetch(
        "SELECT
            COALESCE(SUM(CASE WHEN movement_type IN ('withdrawal', 'adjustment_out') THEN quantity ELSE 0 END), 0) AS withdrawn_qty,
            COALESCE(SUM(CASE WHEN movement_type IN ('return', 'adjustment_in') THEN quantity ELSE 0 END), 0) AS returned_qty
         FROM store_spare_part_movements
         WHERE store_id = ?",
        [$id]
    );

    $storeSpareParts = db()->fetchAll(
        "SELECT id, name_ar, stock_quantity, price
         FROM spare_parts
         WHERE store_id = ?
         ORDER BY name_ar ASC",
        [$id]
    );

    $storeAccountEntries = db()->fetchAll(
        "SELECT e.*, a.full_name AS admin_name
         FROM store_account_entries e
         LEFT JOIN admins a ON e.created_by = a.id
         WHERE e.store_id = ?
         ORDER BY e.created_at DESC
         LIMIT 30",
        [$id]
    );

    $storeMovements = db()->fetchAll(
        "SELECT m.*, sp.name_ar AS part_name, a.full_name AS admin_name
         FROM store_spare_part_movements m
         LEFT JOIN spare_parts sp ON m.spare_part_id = sp.id
         LEFT JOIN admins a ON m.created_by = a.id
         WHERE m.store_id = ?
         ORDER BY m.created_at DESC
         LIMIT 30",
        [$id]
    );
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <!-- زر الإضافة -->
    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            إضافة متجر جديد
        </button>
    </div>

    <!-- قائمة المتاجر -->
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-store" style="color: var(--primary-color);"></i>
                المتاجر
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($stores)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏪</div>
                    <h3>لا توجد متاجر</h3>
                    <p>أضف متاجر لبيع المنتجات وقطع الغيار</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الشعار</th>
                                <th>اسم المتجر</th>
                                <th>الهاتف</th>
                                <th>الموقع</th>
                                <th>المنتجات/القطع</th>
                                <th>المسحوب</th>
                                <th>الحساب</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stores as $store): ?>
                                <?php
                                $creditTotal = (float) ($store['credit_total'] ?? 0);
                                $debitTotal = (float) ($store['debit_total'] ?? 0);
                                $balance = $creditTotal - $debitTotal;
                                ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo imageUrl($store['logo'], 'https://ui-avatars.com/api/?name=' . urlencode($store['name_ar'])); ?>"
                                            alt="" class="avatar avatar-md" style="border-radius: 8px;">
                                    </td>
                                    <td>
                                        <strong><?php echo $store['name_ar']; ?></strong>
                                        <?php if ($store['name_en']): ?>
                                            <div style="font-size: 12px; color: #6b7280;"><?php echo $store['name_en']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td dir="ltr"><?php echo $store['phone'] ?: '-'; ?></td>
                                    <td dir="ltr" style="font-size: 12px;">
                                        <?php if ($store['lat'] !== null && $store['lng'] !== null): ?>
                                            <?php echo number_format((float) $store['lat'], 6); ?>,
                                            <?php echo number_format((float) $store['lng'], 6); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <a href="products.php?store_id=<?php echo $store['id']; ?>" class="btn btn-sm btn-outline">
                                                <?php echo (int) $store['products_count']; ?> منتج
                                            </a>
                                            <a href="spare-parts.php?store_id=<?php echo $store['id']; ?>" class="btn btn-sm btn-outline">
                                                <?php echo (int) $store['spare_parts_count']; ?> قطعة غيار
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning"><?php echo (int) $store['withdrawn_parts_count']; ?> قطعة</span>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; line-height: 1.7;">
                                            <div>له: <strong style="color: #059669;"><?php echo number_format($creditTotal, 2); ?> ⃁</strong></div>
                                            <div>عليه: <strong style="color: #dc2626;"><?php echo number_format($debitTotal, 2); ?> ⃁</strong></div>
                                            <div>
                                                الصافي:
                                                <strong style="color: <?php echo $balance >= 0 ? '#059669' : '#dc2626'; ?>;">
                                                    <?php echo number_format(abs($balance), 2); ?> ⃁
                                                    (<?php echo $balance >= 0 ? 'للمتجر' : 'على المتجر'; ?>)
                                                </strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $store['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $store['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <a href="?action=account&id=<?php echo $store['id']; ?>" class="btn btn-sm btn-outline" title="الحساب وحركة القطع">
                                                <i class="fas fa-wallet"></i>
                                            </a>
                                            <a href="?action=edit&id=<?php echo $store['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="store_id" value="<?php echo $store['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
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
            <?php endif; ?>
        </div>
    </div>

    <!-- مودال الإضافة -->
    <div class="modal-overlay" id="add-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">إضافة متجر جديد</h3>
                <button class="modal-close" onclick="hideModal('add-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="form-group">
                        <label class="form-label">اسم المتجر (عربي)</label>
                        <input type="text" name="name_ar" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">اسم المتجر (إنجليزي)</label>
                        <input type="text" name="name_en" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">العنوان</label>
                        <input type="text" name="address" class="form-control">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">خط العرض (Lat)</label>
                            <input type="number" step="0.000001" min="-90" max="90" name="lat" class="form-control" placeholder="مثال: 24.7136">
                        </div>
                        <div class="form-group">
                            <label class="form-label">خط الطول (Lng)</label>
                            <input type="number" step="0.000001" min="-180" max="180" name="lng" class="form-control" placeholder="مثال: 46.6753">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">شعار المتجر</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label class="form-label">صورة البانر</label>
                        <input type="file" name="banner" class="form-control" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label class="form-label">وصف المتجر</label>
                        <textarea name="description_ar" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group" style="display: flex; gap: 20px;">
                        <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_featured" style="width: 20px; height: 20px;">
                            متجر مميز
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'edit' && isset($editStore)): ?>
    <!-- تعديل متجر -->
    <div style="max-width: 800px; margin: 0 auto;">
        <div style="margin-bottom: 20px; display: flex; gap: 10px;">
            <a href="stores.php" class="btn btn-outline">
                <i class="fas fa-arrow-right"></i>
                العودة للقائمة
            </a>
            <a href="stores.php?action=account&id=<?php echo $editStore['id']; ?>" class="btn btn-primary">
                <i class="fas fa-wallet"></i>
                حساب المتجر
            </a>
        </div>

        <div class="card animate-slideUp">
            <div class="card-header">
                <h3 class="card-title">تعديل المتجر: <?php echo $editStore['name_ar']; ?></h3>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="card-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="store_id" value="<?php echo $editStore['id']; ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">اسم المتجر (عربي)</label>
                            <input type="text" name="name_ar" class="form-control" value="<?php echo $editStore['name_ar']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">اسم المتجر (إنجليزي)</label>
                            <input type="text" name="name_en" class="form-control" value="<?php echo $editStore['name_en']; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo $editStore['phone']; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $editStore['email']; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">العنوان</label>
                        <input type="text" name="address" class="form-control" value="<?php echo $editStore['address']; ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">خط العرض (Lat)</label>
                            <input
                                type="number"
                                step="0.000001"
                                min="-90"
                                max="90"
                                name="lat"
                                class="form-control"
                                value="<?php echo $editStore['lat'] !== null ? (float) $editStore['lat'] : ''; ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">خط الطول (Lng)</label>
                            <input
                                type="number"
                                step="0.000001"
                                min="-180"
                                max="180"
                                name="lng"
                                class="form-control"
                                value="<?php echo $editStore['lng'] !== null ? (float) $editStore['lng'] : ''; ?>"
                            >
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">شعار المتجر</label>
                            <?php if ($editStore['logo']): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo imageUrl($editStore['logo']); ?>" alt="" style="width: 80px; height: 80px; border-radius: 10px; object-fit: cover;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>

                        <div class="form-group">
                            <label class="form-label">صورة البانر</label>
                            <?php if ($editStore['banner']): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo imageUrl($editStore['banner']); ?>" alt="" style="height: 80px; width: 100%; border-radius: 10px; object-fit: cover;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="banner" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">وصف المتجر</label>
                        <textarea name="description_ar" class="form-control" rows="4"><?php echo $editStore['description_ar']; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo $editStore['sort_order']; ?>">
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_featured" style="width: 20px; height: 20px;"
                                <?php echo $editStore['is_featured'] ? 'checked' : ''; ?>>
                            متجر مميز
                        </label>

                        <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_active" style="width: 20px; height: 20px;"
                                <?php echo $editStore['is_active'] ? 'checked' : ''; ?>>
                            تفعيل المتجر
                        </label>
                    </div>
                </div>
                <div class="card-footer" style="text-align: left;">
                    <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'account' && isset($accountStore)): ?>
    <?php
    $creditTotal = (float) ($accountSummary['credit_total'] ?? 0);
    $debitTotal = (float) ($accountSummary['debit_total'] ?? 0);
    $balance = $creditTotal - $debitTotal;
    $withdrawnQty = (int) ($movementSummary['withdrawn_qty'] ?? 0);
    $returnedQty = (int) ($movementSummary['returned_qty'] ?? 0);
    $netWithdrawnQty = $withdrawnQty - $returnedQty;

    $movementLabels = [
        'withdrawal' => ['label' => 'سحب', 'class' => 'danger'],
        'return' => ['label' => 'إرجاع', 'class' => 'success'],
        'adjustment_in' => ['label' => 'تسوية +', 'class' => 'info'],
        'adjustment_out' => ['label' => 'تسوية -', 'class' => 'warning'],
    ];

    $entrySourceLabels = [
        'manual' => 'يدوي',
        'withdrawal' => 'سحب',
        'return' => 'إرجاع',
        'adjustment' => 'تسوية',
    ];
    ?>

    <div style="max-width: 1100px; margin: 0 auto;">
        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="stores.php" class="btn btn-outline">
                <i class="fas fa-arrow-right"></i>
                العودة للقائمة
            </a>
            <a href="stores.php?action=edit&id=<?php echo $accountStore['id']; ?>" class="btn btn-outline">
                <i class="fas fa-edit"></i>
                تعديل بيانات المتجر
            </a>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-wallet" style="color: var(--primary-color);"></i>
                    حساب المتجر: <?php echo $accountStore['name_ar']; ?>
                </h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <div style="padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">إجمالي له</div>
                        <div style="font-size: 20px; font-weight: 700; color: #059669;"><?php echo number_format($creditTotal, 2); ?> ⃁</div>
                    </div>
                    <div style="padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">إجمالي عليه</div>
                        <div style="font-size: 20px; font-weight: 700; color: #dc2626;"><?php echo number_format($debitTotal, 2); ?> ⃁</div>
                    </div>
                    <div style="padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">الصافي</div>
                        <div style="font-size: 20px; font-weight: 700; color: <?php echo $balance >= 0 ? '#059669' : '#dc2626'; ?>;">
                            <?php echo number_format(abs($balance), 2); ?> ⃁
                        </div>
                        <div style="font-size: 12px; color: #6b7280;"><?php echo $balance >= 0 ? 'للمتجر' : 'على المتجر'; ?></div>
                    </div>
                    <div style="padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">صافي المسحوب من القطع</div>
                        <div style="font-size: 20px; font-weight: 700;"><?php echo $netWithdrawnQty; ?> قطعة</div>
                        <div style="font-size: 12px; color: #6b7280;">سحب: <?php echo $withdrawnQty; ?> | إرجاع: <?php echo $returnedQty; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-invoice-dollar"></i> إضافة حركة مالية</h3>
                </div>
                <form method="POST">
                    <div class="card-body">
                        <input type="hidden" name="action" value="add_account_entry">
                        <input type="hidden" name="store_id" value="<?php echo $accountStore['id']; ?>">

                        <div class="form-group">
                            <label class="form-label">نوع الحركة</label>
                            <select name="entry_type" class="form-control" required>
                                <option value="credit">له فلوس عندنا (للمتجر)</option>
                                <option value="debit">عليه فلوس (على المتجر)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">المبلغ</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ملاحظة</label>
                            <textarea name="notes" rows="2" class="form-control" placeholder="سبب الحركة المالية"></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">تسجيل الحركة المالية</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cogs"></i> حركة قطع غيار</h3>
                </div>
                <form method="POST">
                    <div class="card-body">
                        <input type="hidden" name="action" value="add_part_movement">
                        <input type="hidden" name="store_id" value="<?php echo $accountStore['id']; ?>">

                        <div class="form-group">
                            <label class="form-label">قطعة الغيار</label>
                            <select name="spare_part_id" class="form-control" required <?php echo empty($storeSpareParts) ? 'disabled' : ''; ?>>
                                <option value="">اختر القطعة</option>
                                <?php foreach ($storeSpareParts as $part): ?>
                                    <option value="<?php echo $part['id']; ?>">
                                        <?php echo $part['name_ar']; ?> (المخزون: <?php echo (int) $part['stock_quantity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">نوع الحركة</label>
                            <select name="movement_type" class="form-control" required>
                                <option value="withdrawal">سحب من المخزون</option>
                                <option value="return">إرجاع للمخزون</option>
                                <option value="adjustment_in">تسوية +</option>
                                <option value="adjustment_out">تسوية -</option>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="form-group">
                                <label class="form-label">الكمية</label>
                                <input type="number" min="1" name="quantity" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">سعر القطعة (اختياري)</label>
                                <input type="number" step="0.01" min="0" name="unit_price" class="form-control"
                                    placeholder="لإنشاء حركة مالية تلقائيًا">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ملاحظة</label>
                            <textarea name="notes" rows="2" class="form-control" placeholder="ملاحظة على الحركة"></textarea>
                        </div>

                        <?php if (empty($storeSpareParts)): ?>
                            <div style="font-size: 12px; color: #b45309;">لا توجد قطع غيار مرتبطة بهذا المتجر حتى الآن.</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" <?php echo empty($storeSpareParts) ? 'disabled' : ''; ?>>
                            تسجيل حركة القطعة
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> آخر الحركات المالية</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>المصدر</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($storeAccountEntries as $entry): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php echo $entry['entry_type'] === 'credit' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $entry['entry_type'] === 'credit' ? 'له' : 'عليه'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format((float) $entry['amount'], 2); ?> ⃁</td>
                                        <td>
                                            <div><?php echo $entrySourceLabels[$entry['source']] ?? $entry['source']; ?></div>
                                            <?php if (!empty($entry['notes'])): ?>
                                                <div style="font-size: 12px; color: #6b7280;"><?php echo $entry['notes']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 12px; white-space: nowrap;">
                                            <?php echo formatDateTime($entry['created_at']); ?>
                                            <?php if (!empty($entry['admin_name'])): ?>
                                                <div style="color: #9ca3af;"><?php echo $entry['admin_name']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($storeAccountEntries)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">لا توجد حركات مالية</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-exchange-alt"></i> آخر حركات قطع الغيار</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>الحركة</th>
                                    <th>القطعة</th>
                                    <th>الكمية</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($storeMovements as $movement): ?>
                                    <?php
                                    $typeInfo = $movementLabels[$movement['movement_type']] ?? ['label' => $movement['movement_type'], 'class' => 'secondary'];
                                    $movementTotal = $movement['unit_price'] !== null ? ((float) $movement['unit_price'] * (int) $movement['quantity']) : null;
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?php echo $typeInfo['class']; ?>"><?php echo $typeInfo['label']; ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo $movement['part_name'] ?? ('#' . $movement['spare_part_id']); ?></div>
                                            <?php if (!empty($movement['notes'])): ?>
                                                <div style="font-size: 12px; color: #6b7280;"><?php echo $movement['notes']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo (int) $movement['quantity']; ?></div>
                                            <?php if ($movementTotal !== null): ?>
                                                <div style="font-size: 12px; color: #6b7280;">
                                                    <?php echo number_format($movementTotal, 2); ?> ⃁
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 12px; white-space: nowrap;">
                                            <?php echo formatDateTime($movement['created_at']); ?>
                                            <?php if (!empty($movement['admin_name'])): ?>
                                                <div style="color: #9ca3af;"><?php echo $movement['admin_name']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($storeMovements)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">لا توجد حركات قطع غيار</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
