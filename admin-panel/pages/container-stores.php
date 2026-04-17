<?php
/**
 * صفحة إدارة متاجر الحاويات
 */

require_once '../init.php';
require_once '../includes/special_services.php';
requireLogin();

ensureSpecialServicesSchema();

$admin = getCurrentAdmin();
if (
    !hasPermission('services')
    && !hasPermission('financial')
    && ($admin['role'] ?? '') !== 'super_admin'
) {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'متاجر الحاويات';
$pageSubtitle = 'إدارة متاجر موردي الحاويات والحسابات المالية المرتبطة بهم';

$action = get('action', 'list');
$id = (int) get('id');
$currentAdminId = (int) ($admin['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $storeNameAr = clean(post('name_ar'));
        if ($storeNameAr === '') {
            setFlashMessage('danger', 'اسم المتجر (عربي) مطلوب');
            redirect('container-stores.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $data = [
            'name_ar' => $storeNameAr,
            'name_en' => clean(post('name_en')),
            'name_ur' => clean(post('name_ur')),
            'contact_person' => clean(post('contact_person')),
            'phone' => clean(post('phone')),
            'email' => clean(post('email')),
            'address' => clean(post('address')),
            'notes' => clean(post('notes')),
            'sort_order' => (int) post('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['name_ur'] === '') {
            $data['name_ur'] = $data['name_en'] !== '' ? $data['name_en'] : $data['name_ar'];
        }

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo'], 'stores');
            if ($upload['success']) {
                $data['logo'] = $upload['path'];
            }
        }

        if ($postAction === 'add') {
            $newId = db()->insert('container_stores', $data);
            logActivity('add_container_store', 'container_stores', $newId);
            setFlashMessage('success', 'تمت إضافة متجر الحاويات بنجاح');
        } else {
            $storeId = (int) post('id');
            db()->update('container_stores', $data, 'id = ?', [$storeId]);
            logActivity('update_container_store', 'container_stores', $storeId);
            setFlashMessage('success', 'تم تحديث متجر الحاويات بنجاح');
        }

        redirect('container-stores.php');
    }

    if ($postAction === 'delete') {
        $storeId = (int) post('id');
        $servicesCount = (int) db()->count('container_services', 'store_id = ?', [$storeId]);
        $requestsCount = (int) (db()->fetch(
            "SELECT COUNT(*) AS total
             FROM container_requests cr
             LEFT JOIN container_services cs ON cs.id = cr.container_service_id
             WHERE COALESCE(cr.container_store_id, cs.store_id) = ?",
            [$storeId]
        )['total'] ?? 0);
        $entriesCount = (int) db()->count('container_store_account_entries', 'store_id = ?', [$storeId]);

        if ($servicesCount > 0 || $requestsCount > 0 || $entriesCount > 0) {
            setFlashMessage('danger', 'لا يمكن حذف المتجر لوجود بيانات مرتبطة به (خدمات/طلبات/حركات حساب). يمكنك تعطيله بدلاً من ذلك.');
            redirect('container-stores.php');
        }

        db()->delete('container_stores', 'id = ?', [$storeId]);
        logActivity('delete_container_store', 'container_stores', $storeId);
        setFlashMessage('success', 'تم حذف متجر الحاويات بنجاح');
        redirect('container-stores.php');
    }

    if ($postAction === 'add_account_entry') {
        $storeId = (int) post('store_id');
        $entryType = trim((string) post('entry_type'));
        $amount = (float) post('amount');
        $source = trim((string) post('source', 'manual'));
        $notes = clean(post('notes'));

        if (!db()->count('container_stores', 'id = ?', [$storeId])) {
            setFlashMessage('danger', 'المتجر غير موجود');
            redirect('container-stores.php');
        }

        if (!in_array($entryType, ['credit', 'debit'], true)) {
            setFlashMessage('danger', 'نوع الحركة المالية غير صحيح');
            redirect('container-stores.php?action=account&id=' . $storeId);
        }

        if ($amount <= 0) {
            setFlashMessage('danger', 'المبلغ يجب أن يكون أكبر من صفر');
            redirect('container-stores.php?action=account&id=' . $storeId);
        }

        $allowedSources = ['manual', 'request', 'payment', 'settlement', 'adjustment'];
        if (!in_array($source, $allowedSources, true)) {
            $source = 'manual';
        }

        $entryId = db()->insert('container_store_account_entries', [
            'store_id' => $storeId,
            'entry_type' => $entryType,
            'amount' => $amount,
            'source' => $source,
            'notes' => $notes,
            'created_by' => $currentAdminId > 0 ? $currentAdminId : null,
        ]);

        logActivity('add_container_store_account_entry', 'container_store_account_entries', $entryId);
        setFlashMessage('success', 'تم تسجيل الحركة المالية بنجاح');
        redirect('container-stores.php?action=account&id=' . $storeId);
    }
}

$stores = db()->fetchAll(
    "SELECT cs.*,
            (SELECT COUNT(*) FROM container_services srv WHERE srv.store_id = cs.id) AS services_count,
            (
                SELECT COUNT(*)
                FROM container_requests req
                LEFT JOIN container_services srv ON srv.id = req.container_service_id
                WHERE COALESCE(req.container_store_id, srv.store_id) = cs.id
            ) AS requests_count,
            COALESCE((
                SELECT SUM(CASE WHEN ae.entry_type = 'credit' THEN ae.amount ELSE -ae.amount END)
                FROM container_store_account_entries ae
                WHERE ae.store_id = cs.id
            ), 0) AS account_balance
     FROM container_stores cs
     ORDER BY cs.is_active DESC, cs.sort_order ASC, cs.id DESC"
);

if ($action === 'edit' && $id) {
    $store = db()->fetch('SELECT * FROM container_stores WHERE id = ?', [$id]);
    if (!$store) {
        setFlashMessage('danger', 'المتجر غير موجود');
        redirect('container-stores.php');
    }
}

if ($action === 'account' && $id) {
    $store = db()->fetch('SELECT * FROM container_stores WHERE id = ?', [$id]);
    if (!$store) {
        setFlashMessage('danger', 'المتجر غير موجود');
        redirect('container-stores.php');
    }

    $totals = db()->fetch(
        "SELECT
            COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) AS total_credit,
            COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) AS total_debit
         FROM container_store_account_entries
         WHERE store_id = ?",
        [$id]
    );

    $entries = db()->fetchAll(
        "SELECT e.*, a.full_name AS admin_name
         FROM container_store_account_entries e
         LEFT JOIN admins a ON a.id = e.created_by
         WHERE e.store_id = ?
         ORDER BY e.id DESC
         LIMIT 250",
        [$id]
    );
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة متجر حاويات
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-store"></i> متاجر الحاويات</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الشعار</th>
                            <th>المتجر</th>
                            <th>التواصل</th>
                            <th>الخدمات</th>
                            <th>الطلبات</th>
                            <th>الرصيد</th>
                            <th>الحالة</th>
                            <th>الترتيب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stores as $item): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($item['logo'])): ?>
                                        <img src="<?php echo imageUrl($item['logo']); ?>" alt=""
                                            style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars((string) $item['name_ar'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars((string) ($item['name_en'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars((string) ($item['contact_person'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?><br>
                                        <span dir="ltr"><?php echo htmlspecialchars((string) ($item['phone'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></span><br>
                                        <?php echo htmlspecialchars((string) ($item['email'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                </td>
                                <td><span class="badge badge-info"><?php echo (int) ($item['services_count'] ?? 0); ?></span></td>
                                <td><span class="badge badge-primary"><?php echo (int) ($item['requests_count'] ?? 0); ?></span></td>
                                <td>
                                    <?php
                                        $balance = (float) ($item['account_balance'] ?? 0);
                                        $balanceClass = $balance >= 0 ? 'badge-success' : 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $balanceClass; ?>">
                                        <?php echo number_format($balance, 2); ?> ⃁
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo !empty($item['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo !empty($item['is_active']) ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td><?php echo (int) ($item['sort_order'] ?? 0); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?action=account&id=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-outline" title="حساب المتجر">
                                            <i class="fas fa-wallet"></i>
                                        </a>
                                        <a href="?action=edit&id=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-outline" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف المتجر؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stores)): ?>
                            <tr>
                                <td colspan="9" class="text-center">لا توجد متاجر حاويات مضافة حالياً</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="add-modal">
        <div class="modal" style="width: 860px; max-width: 94%;">
            <div class="modal-header">
                <h3 class="modal-title">إضافة متجر حاويات</h3>
                <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">اسم المتجر (عربي)</label>
                            <input type="text" name="name_ar" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اسم المتجر (إنجليزي)</label>
                            <input type="text" name="name_en" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">اسم المتجر (أردو)</label>
                            <input type="text" name="name_ur" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">الشخص المسؤول</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الجوال</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">العنوان</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الترتيب</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الشعار</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_active" checked>
                            متجر نشط
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

<?php elseif ($action === 'edit' && isset($store)): ?>
    <div class="card animate-slideUp" style="max-width: 940px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">تعديل متجر: <?php echo htmlspecialchars((string) $store['name_ar'], ENT_QUOTES, 'UTF-8'); ?></h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo (int) $store['id']; ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">اسم المتجر (عربي)</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo htmlspecialchars((string) $store['name_ar'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اسم المتجر (إنجليزي)</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo htmlspecialchars((string) ($store['name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">اسم المتجر (أردو)</label>
                        <input type="text" name="name_ur" class="form-control" value="<?php echo htmlspecialchars((string) ($store['name_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الشخص المسؤول</label>
                        <input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars((string) ($store['contact_person'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم الجوال</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars((string) ($store['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars((string) ($store['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($store['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($store['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int) ($store['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الشعار</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <?php if (!empty($store['logo'])): ?>
                            <div style="margin-top:8px;">
                                <img src="<?php echo imageUrl($store['logo']); ?>" alt="" style="width: 52px; height: 52px; border-radius: 8px; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" <?php echo !empty($store['is_active']) ? 'checked' : ''; ?>>
                        متجر نشط
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <a href="container-stores.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'account' && isset($store)): ?>
    <?php
        $totalCredit = (float) ($totals['total_credit'] ?? 0);
        $totalDebit = (float) ($totals['total_debit'] ?? 0);
        $balance = $totalCredit - $totalDebit;
    ?>
    <div class="card animate-slideUp" style="margin-bottom: 20px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="card-title"><i class="fas fa-wallet"></i> حساب متجر: <?php echo htmlspecialchars((string) $store['name_ar'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <a href="container-stores.php" class="btn btn-outline btn-sm">رجوع</a>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 15px;">
                <div class="stat-card" style="padding: 14px;">
                    <div class="text-muted">إجمالي الدائن</div>
                    <div style="font-size: 20px; font-weight: 700; color: #16a34a;"><?php echo number_format($totalCredit, 2); ?> ⃁</div>
                </div>
                <div class="stat-card" style="padding: 14px;">
                    <div class="text-muted">إجمالي المدين</div>
                    <div style="font-size: 20px; font-weight: 700; color: #dc2626;"><?php echo number_format($totalDebit, 2); ?> ⃁</div>
                </div>
                <div class="stat-card" style="padding: 14px;">
                    <div class="text-muted">الرصيد الحالي</div>
                    <div style="font-size: 20px; font-weight: 700; color: <?php echo $balance >= 0 ? '#166534' : '#b91c1c'; ?>;">
                        <?php echo number_format($balance, 2); ?> ⃁
                    </div>
                </div>
            </div>

            <form method="POST" style="border:1px solid #e5e7eb; border-radius: 12px; padding: 12px; margin-bottom: 18px;">
                <input type="hidden" name="action" value="add_account_entry">
                <input type="hidden" name="store_id" value="<?php echo (int) $store['id']; ?>">
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 2fr auto; gap: 10px; align-items: end;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">النوع</label>
                        <select name="entry_type" class="form-control" required>
                            <option value="credit">دائن (+)</option>
                            <option value="debit">مدين (-)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">المصدر</label>
                        <select name="source" class="form-control">
                            <option value="manual">يدوي</option>
                            <option value="request">طلب حاوية</option>
                            <option value="payment">دفعة</option>
                            <option value="settlement">تسوية</option>
                            <option value="adjustment">تعديل</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">المبلغ (⃁)</label>
                        <input type="number" min="0.01" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">ملاحظات</label>
                        <input type="text" name="notes" class="form-control" placeholder="تفاصيل الحركة">
                    </div>
                    <button type="submit" class="btn btn-primary" style="height: 41px;">إضافة حركة</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>النوع</th>
                            <th>المصدر</th>
                            <th>المبلغ</th>
                            <th>ملاحظات</th>
                            <th>بواسطة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?php echo (int) $entry['id']; ?></td>
                                <td>
                                    <span class="badge <?php echo $entry['entry_type'] === 'credit' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $entry['entry_type'] === 'credit' ? 'دائن' : 'مدين'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($entry['source'] ?? 'manual'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format((float) ($entry['amount'] ?? 0), 2); ?> ⃁</td>
                                <td><?php echo htmlspecialchars((string) ($entry['notes'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($entry['admin_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo formatDateTime($entry['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($entries)): ?>
                            <tr>
                                <td colspan="7" class="text-center">لا توجد حركات حساب حتى الآن</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
