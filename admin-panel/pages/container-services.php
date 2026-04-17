<?php
/**
 * صفحة إدارة خدمات الحاويات
 */

require_once '../init.php';
require_once '../includes/special_services.php';
requireLogin();

ensureSpecialServicesSchema();

if (!hasPermission('services') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'خدمات الحاويات';
$pageSubtitle = 'إدارة أنواع الحاويات والتسعير الخاص بها';

$action = get('action', 'list');
$id = (int) get('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $storeIdValue = (int) post('store_id');
        $storeId = $storeIdValue > 0 ? $storeIdValue : null;
        if ($storeId !== null) {
            $storeExists = db()->fetch('SELECT id FROM container_stores WHERE id = ?', [$storeId]);
            if (!$storeExists) {
                setFlashMessage('danger', 'متجر الحاويات المختار غير موجود');
                redirect('container-services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
            }
        }

        $data = [
            'store_id' => $storeId,
            'name_ar' => clean(post('name_ar')),
            'name_en' => clean(post('name_en')),
            'name_ur' => clean(post('name_ur')),
            'description_ar' => clean(post('description_ar')),
            'description_en' => clean(post('description_en')),
            'description_ur' => clean(post('description_ur')),
            'container_size' => clean(post('container_size')),
            'capacity_ton' => (float) post('capacity_ton'),
            'daily_price' => (float) post('daily_price'),
            'weekly_price' => (float) post('weekly_price'),
            'monthly_price' => (float) post('monthly_price'),
            'delivery_fee' => (float) post('delivery_fee'),
            'price_per_kg' => (float) post('price_per_kg'),
            'price_per_meter' => (float) post('price_per_meter'),
            'minimum_charge' => (float) post('minimum_charge'),
            'price_note' => clean(post('price_note')),
            'sort_order' => (int) post('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['name_ur'] === '') {
            $data['name_ur'] = $data['name_en'] !== '' ? $data['name_en'] : $data['name_ar'];
        }
        if ($data['description_ur'] === '') {
            $data['description_ur'] = $data['description_en'] !== '' ? $data['description_en'] : $data['description_ar'];
        }

        if ($data['name_ar'] === '' || $data['container_size'] === '') {
            setFlashMessage('danger', 'اسم الخدمة ومقاس الحاوية مطلوبان');
            redirect('container-services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'services');
            if ($upload['success']) {
                $data['image'] = $upload['path'];
            }
        }

        if ($postAction === 'add') {
            $newId = db()->insert('container_services', $data);
            logActivity('add_container_service', 'container_services', $newId);
            setFlashMessage('success', 'تمت إضافة خدمة الحاويات بنجاح');
        } else {
            $serviceId = (int) post('id');
            db()->update('container_services', $data, 'id = ?', [$serviceId]);
            logActivity('update_container_service', 'container_services', $serviceId);
            setFlashMessage('success', 'تم تحديث خدمة الحاويات بنجاح');
        }

        redirect('container-services.php');
    }

    if ($postAction === 'delete') {
        $serviceId = (int) post('id');

        $linkedRequests = (int) db()->count('container_requests', 'container_service_id = ?', [$serviceId]);
        if ($linkedRequests > 0) {
            setFlashMessage('danger', 'لا يمكن حذف الخدمة لوجود طلبات مرتبطة بها. يمكنك تعطيلها بدلاً من ذلك.');
            redirect('container-services.php');
        }

        db()->delete('container_services', 'id = ?', [$serviceId]);
        logActivity('delete_container_service', 'container_services', $serviceId);
        setFlashMessage('success', 'تم حذف الخدمة بنجاح');
        redirect('container-services.php');
    }
}

$services = db()->fetchAll(
    "SELECT cs.*, cst.name_ar AS store_name,
            (SELECT COUNT(*) FROM container_requests cr WHERE cr.container_service_id = cs.id) AS requests_count
     FROM container_services cs
     LEFT JOIN container_stores cst ON cst.id = cs.store_id
     ORDER BY cs.sort_order ASC, cs.id DESC"
);

$containerStores = db()->fetchAll(
    'SELECT id, name_ar, is_active FROM container_stores ORDER BY is_active DESC, sort_order ASC, id ASC'
);

if ($action === 'edit' && $id) {
    $service = db()->fetch('SELECT * FROM container_services WHERE id = ?', [$id]);
    if (!$service) {
        setFlashMessage('danger', 'الخدمة غير موجودة');
        redirect('container-services.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة خدمة حاويات
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-boxes-stacked"></i> خدمات الحاويات</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الخدمة</th>
                            <th>المقاس / السعة</th>
                            <th>المتجر</th>
                            <th>التسعير</th>
                            <th>تسعير مرن</th>
                            <th>الطلبات</th>
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
                                    <?php echo $item['container_size']; ?><br>
                                    <small class="text-muted">سعة: <?php echo number_format((float) $item['capacity_ton'], 2); ?> طن</small>
                                </td>
                                <td>
                                    <?php if (!empty($item['store_name'])): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars((string) $item['store_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">غير مرتبط</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 12px; line-height: 1.8;">
                                        يومي: <?php echo number_format((float) $item['daily_price'], 2); ?> ⃁<br>
                                        أسبوعي: <?php echo number_format((float) $item['weekly_price'], 2); ?> ⃁<br>
                                        شهري: <?php echo number_format((float) $item['monthly_price'], 2); ?> ⃁
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 12px; line-height: 1.8;">
                                        /كجم: <?php echo number_format((float) ($item['price_per_kg'] ?? 0), 2); ?> ⃁<br>
                                        /متر: <?php echo number_format((float) ($item['price_per_meter'] ?? 0), 2); ?> ⃁<br>
                                        حد أدنى: <?php echo number_format((float) ($item['minimum_charge'] ?? 0), 2); ?> ⃁
                                    </div>
                                </td>
                                <td><span class="badge badge-info"><?php echo (int) ($item['requests_count'] ?? 0); ?></span></td>
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
                                <td colspan="10" class="text-center">لا توجد خدمات حاويات مضافة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="add-modal">
        <div class="modal" style="width: 820px; max-width: 92%;">
            <div class="modal-header">
                <h3 class="modal-title">إضافة خدمة حاويات</h3>
                <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
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
                        <div class="form-group">
                            <label class="form-label">متجر الحاويات</label>
                            <select name="store_id" class="form-control">
                                <option value="">بدون تحديد</option>
                                <?php foreach ($containerStores as $store): ?>
                                    <option value="<?php echo (int) $store['id']; ?>">
                                        <?php echo htmlspecialchars((string) $store['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">مقاس الحاوية</label>
                            <input type="text" name="container_size" class="form-control" placeholder="مثال: 20 قدم" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">السعة (طن)</label>
                            <input type="number" step="0.01" min="0" name="capacity_ton" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رسوم التوصيل (⃁)</label>
                            <input type="number" step="0.01" min="0" name="delivery_fee" class="form-control" value="0">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">سعر يومي (⃁)</label>
                            <input type="number" step="0.01" min="0" name="daily_price" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">سعر أسبوعي (⃁)</label>
                            <input type="number" step="0.01" min="0" name="weekly_price" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">سعر شهري (⃁)</label>
                            <input type="number" step="0.01" min="0" name="monthly_price" class="form-control" value="0">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">سعر/كجم (⃁)</label>
                            <input type="number" step="0.01" min="0" name="price_per_kg" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">سعر/متر (⃁)</label>
                            <input type="number" step="0.01" min="0" name="price_per_meter" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">حد أدنى للطلب (⃁)</label>
                            <input type="number" step="0.01" min="0" name="minimum_charge" class="form-control" value="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ملاحظة السعر</label>
                        <input type="text" name="price_note" class="form-control" placeholder="مثال: يشمل النقل والتركيب">
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">الوصف (عربي)</label>
                            <textarea name="description_ar" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الترتيب</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
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
    <div class="card animate-slideUp" style="max-width: 980px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">تعديل خدمة: <?php echo $service['name_ar']; ?></h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $service['id']; ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
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
                    <div class="form-group">
                        <label class="form-label">متجر الحاويات</label>
                        <select name="store_id" class="form-control">
                            <option value="">بدون تحديد</option>
                            <?php foreach ($containerStores as $storeItem): ?>
                                <option value="<?php echo (int) $storeItem['id']; ?>" <?php echo (int) ($service['store_id'] ?? 0) === (int) $storeItem['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $storeItem['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">مقاس الحاوية</label>
                        <input type="text" name="container_size" class="form-control" value="<?php echo $service['container_size']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعة (طن)</label>
                        <input type="number" step="0.01" min="0" name="capacity_ton" class="form-control"
                            value="<?php echo (float) $service['capacity_ton']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">رسوم التوصيل (⃁)</label>
                        <input type="number" step="0.01" min="0" name="delivery_fee" class="form-control"
                            value="<?php echo (float) $service['delivery_fee']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">سعر يومي (⃁)</label>
                        <input type="number" step="0.01" min="0" name="daily_price" class="form-control"
                            value="<?php echo (float) $service['daily_price']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">سعر أسبوعي (⃁)</label>
                        <input type="number" step="0.01" min="0" name="weekly_price" class="form-control"
                            value="<?php echo (float) $service['weekly_price']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">سعر شهري (⃁)</label>
                        <input type="number" step="0.01" min="0" name="monthly_price" class="form-control"
                            value="<?php echo (float) $service['monthly_price']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
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
                    <div class="form-group">
                        <label class="form-label">حد أدنى للطلب (⃁)</label>
                        <input type="number" step="0.01" min="0" name="minimum_charge" class="form-control"
                            value="<?php echo (float) ($service['minimum_charge'] ?? 0); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">ملاحظة السعر</label>
                        <input type="text" name="price_note" class="form-control" value="<?php echo $service['price_note']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control"
                            value="<?php echo (int) ($service['sort_order'] ?? 0); ?>">
                    </div>
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
                <a href="container-services.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
