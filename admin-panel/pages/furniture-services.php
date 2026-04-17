<?php
/**
 * صفحة إدارة خدمات نقل العفش
 */

require_once '../init.php';
require_once '../includes/special_services.php';
requireLogin();

ensureSpecialServicesSchema();

if (!hasPermission('services') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'خدمات نقل العفش';
$pageSubtitle = 'إدارة باقات وخدمات نقل العفش بشكل مستقل';

$action = get('action', 'list');
$id = (int) get('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $data = [
            'name_ar' => clean(post('name_ar')),
            'name_en' => clean(post('name_en')),
            'name_ur' => clean(post('name_ur')),
            'description_ar' => clean(post('description_ar')),
            'description_en' => clean(post('description_en')),
            'description_ur' => clean(post('description_ur')),
            'base_price' => (float) post('base_price'),
            'price_per_kg' => (float) post('price_per_kg'),
            'price_per_meter' => (float) post('price_per_meter'),
            'minimum_charge' => (float) post('minimum_charge'),
            'price_note' => clean(post('price_note')),
            'estimated_duration_hours' => (float) post('estimated_duration_hours'),
            'sort_order' => (int) post('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['name_ur'] === '') {
            $data['name_ur'] = $data['name_en'] !== '' ? $data['name_en'] : $data['name_ar'];
        }
        if ($data['description_ur'] === '') {
            $data['description_ur'] = $data['description_en'] !== '' ? $data['description_en'] : $data['description_ar'];
        }

        if ($data['name_ar'] === '') {
            setFlashMessage('danger', 'اسم الخدمة (عربي) مطلوب');
            redirect('furniture-services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'services');
            if ($upload['success']) {
                $data['image'] = $upload['path'];
            }
        }

        if ($postAction === 'add') {
            $newId = db()->insert('furniture_services', $data);
            logActivity('add_furniture_service', 'furniture_services', $newId);
            setFlashMessage('success', 'تمت إضافة خدمة نقل العفش بنجاح');
        } else {
            $serviceId = (int) post('id');
            db()->update('furniture_services', $data, 'id = ?', [$serviceId]);
            logActivity('update_furniture_service', 'furniture_services', $serviceId);
            setFlashMessage('success', 'تم تحديث الخدمة بنجاح');
        }

        redirect('furniture-services.php');
    }

    if ($postAction === 'delete') {
        $serviceId = (int) post('id');

        $linkedRequests = (int) db()->count('furniture_requests', 'service_id = ?', [$serviceId]);
        if ($linkedRequests > 0) {
            setFlashMessage('danger', 'لا يمكن حذف الخدمة لوجود طلبات مرتبطة بها. يمكنك تعطيلها بدلاً من ذلك.');
            redirect('furniture-services.php');
        }

        db()->delete('furniture_services', 'id = ?', [$serviceId]);
        logActivity('delete_furniture_service', 'furniture_services', $serviceId);
        setFlashMessage('success', 'تم حذف الخدمة بنجاح');
        redirect('furniture-services.php');
    }
}

$services = db()->fetchAll(
    "SELECT fs.*, 
            (SELECT COUNT(*) FROM furniture_requests fr WHERE fr.service_id = fs.id) AS requests_count
     FROM furniture_services fs
     ORDER BY fs.sort_order ASC, fs.id DESC"
);

if ($action === 'edit' && $id) {
    $service = db()->fetch('SELECT * FROM furniture_services WHERE id = ?', [$id]);
    if (!$service) {
        setFlashMessage('danger', 'الخدمة غير موجودة');
        redirect('furniture-services.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة خدمة نقل عفش
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-truck-moving"></i> خدمات نقل العفش</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الخدمة</th>
                            <th>السعر الأساسي</th>
                            <th>تسعير مرن</th>
                            <th>مدة تقديرية</th>
                            <th>عدد الطلبات</th>
                            <th>الحالة</th>
                            <th>الترتيب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $item): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?php echo imageUrl($item['image']); ?>" alt=""
                                            style="width: 52px; height: 52px; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $item['name_ar']; ?></strong><br>
                                    <small class="text-muted"><?php echo $item['name_en'] ?: '-'; ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo $item['name_ur'] ?? '-'; ?></small>
                                </td>
                                <td>
                                    <?php echo number_format((float) $item['base_price'], 2); ?> ⃁
                                    <?php if (!empty($item['price_note'])): ?>
                                        <div style="font-size: 12px; color: #6b7280;"><?php echo $item['price_note']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 12px; line-height: 1.7;">
                                        /كجم:
                                        <?php echo number_format((float) ($item['price_per_kg'] ?? 0), 2); ?> ⃁
                                        <br>
                                        /متر:
                                        <?php echo number_format((float) ($item['price_per_meter'] ?? 0), 2); ?> ⃁
                                        <br>
                                        حد أدنى:
                                        <?php echo number_format((float) ($item['minimum_charge'] ?? 0), 2); ?> ⃁
                                    </div>
                                </td>
                                <td><?php echo number_format((float) $item['estimated_duration_hours'], 1); ?> ساعة</td>
                                <td>
                                    <span class="badge badge-info"><?php echo (int) ($item['requests_count'] ?? 0); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo !empty($item['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo !empty($item['is_active']) ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td><?php echo (int) ($item['sort_order'] ?? 0); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="9" class="text-center">لا توجد خدمات نقل عفش مضافة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="add-modal">
        <div class="modal" style="width: 760px; max-width: 92%;">
            <div class="modal-header">
                <h3 class="modal-title">إضافة خدمة نقل عفش</h3>
                <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">الاسم (عربي)</label>
                            <input type="text" name="name_ar" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الاسم (إنجليزي)</label>
                            <input type="text" name="name_en" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الاسم (أردو)</label>
                            <input type="text" name="name_ur" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">السعر الأساسي (⃁)</label>
                            <input type="number" step="0.01" min="0" name="base_price" class="form-control" value="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">سعر/كجم (⃁)</label>
                            <input type="number" step="0.01" min="0" name="price_per_kg" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">سعر/متر (⃁)</label>
                            <input type="number" step="0.01" min="0" name="price_per_meter" class="form-control" value="0">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">حد أدنى للطلب (⃁)</label>
                            <input type="number" step="0.01" min="0" name="minimum_charge" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">المدة التقديرية (ساعة)</label>
                            <input type="number" step="0.5" min="0" name="estimated_duration_hours" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الترتيب</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ملاحظة السعر</label>
                        <input type="text" name="price_note" class="form-control" placeholder="مثال: يبدأ من">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الوصف (عربي)</label>
                        <textarea name="description_ar" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الوصف (إنجليزي)</label>
                        <textarea name="description_en" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف (أردو)</label>
                        <textarea name="description_ur" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">صورة الخدمة</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_active" checked>
                            خدمة نشطة
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

<?php elseif ($action === 'edit' && isset($service)): ?>
    <div class="card animate-slideUp" style="max-width: 900px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">تعديل خدمة: <?php echo $service['name_ar']; ?></h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $service['id']; ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الاسم (عربي)</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo $service['name_ar']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم (إنجليزي)</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo $service['name_en']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم (أردو)</label>
                        <input type="text" name="name_ur" class="form-control" value="<?php echo $service['name_ur'] ?? ''; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">السعر الأساسي (⃁)</label>
                        <input type="number" step="0.01" min="0" name="base_price" class="form-control"
                            value="<?php echo (float) $service['base_price']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">سعر/كجم (⃁)</label>
                        <input type="number" step="0.01" min="0" name="price_per_kg" class="form-control"
                            value="<?php echo (float) ($service['price_per_kg'] ?? 0); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">سعر/متر (⃁)</label>
                        <input type="number" step="0.01" min="0" name="price_per_meter" class="form-control"
                            value="<?php echo (float) ($service['price_per_meter'] ?? 0); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">حد أدنى للطلب (⃁)</label>
                        <input type="number" step="0.01" min="0" name="minimum_charge" class="form-control"
                            value="<?php echo (float) ($service['minimum_charge'] ?? 0); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">المدة التقديرية (ساعة)</label>
                        <input type="number" step="0.5" min="0" name="estimated_duration_hours" class="form-control"
                            value="<?php echo (float) $service['estimated_duration_hours']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control"
                            value="<?php echo (int) ($service['sort_order'] ?? 0); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظة السعر</label>
                    <input type="text" name="price_note" class="form-control" value="<?php echo $service['price_note']; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف (عربي)</label>
                    <textarea name="description_ar" class="form-control" rows="3"><?php echo $service['description_ar']; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف (إنجليزي)</label>
                    <textarea name="description_en" class="form-control" rows="3"><?php echo $service['description_en']; ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف (أردو)</label>
                    <textarea name="description_ur" class="form-control" rows="3"><?php echo $service['description_ur'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">صورة الخدمة</label>
                    <?php if (!empty($service['image'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo imageUrl($service['image']); ?>" alt=""
                                style="width: 110px; object-fit: cover; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" <?php echo !empty($service['is_active']) ? 'checked' : ''; ?>>
                        خدمة نشطة
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <a href="furniture-services.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
