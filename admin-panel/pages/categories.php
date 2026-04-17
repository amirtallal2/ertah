<?php
/**
 * صفحة إدارة فئات الخدمات
 * Service Categories Management Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'فئات الخدمات';
$pageSubtitle = 'إدارة الأقسام الرئيسية والفرعية';

/**
 * تجهيز مخطط قاعدة البيانات لدعم الأقسام الفرعية.
 */
function ensureServiceCategoriesHierarchySchema(): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }
    $schemaChecked = true;

    $hasParentColumn = db()->fetch("SHOW COLUMNS FROM service_categories LIKE 'parent_id'");
    if (!$hasParentColumn) {
        db()->query("ALTER TABLE service_categories ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id");
    }

    $hasParentIndex = db()->fetch("SHOW INDEX FROM service_categories WHERE Key_name = 'idx_service_categories_parent_id'");
    if (!$hasParentIndex) {
        db()->query("ALTER TABLE service_categories ADD INDEX idx_service_categories_parent_id (parent_id)");
    }

    $hasUrduColumn = db()->fetch("SHOW COLUMNS FROM service_categories LIKE 'name_ur'");
    if (!$hasUrduColumn) {
        db()->query("ALTER TABLE service_categories ADD COLUMN name_ur VARCHAR(255) NULL DEFAULT NULL AFTER name_en");
    }
}

/**
 * التحقق من القسم الرئيسي المختار.
 */
function resolveParentCategoryId(int $requestedParentId): ?int
{
    if ($requestedParentId <= 0) {
        return null;
    }

    $parent = db()->fetch(
        "SELECT id FROM service_categories WHERE id = ? AND parent_id IS NULL",
        [$requestedParentId]
    );

    return $parent ? (int) $parent['id'] : null;
}

ensureServiceCategoriesHierarchySchema();

$action = get('action', 'list');
$id = (int) get('id');

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    // إضافة فئة
    if ($postAction === 'add') {
        $nameAr = post('name_ar');
        $nameEn = post('name_en');
        $nameUr = post('name_ur');
        $sortOrder = (int) post('sort_order');
        $warrantyDays = max(0, (int) post('warranty_days'));

        $requestedParentId = (int) post('parent_id');
        $parentId = resolveParentCategoryId($requestedParentId);

        if ($nameAr === '') {
            setFlashMessage('danger', 'اسم الفئة بالعربي مطلوب');
            redirect('categories.php');
        }

        if ($requestedParentId > 0 && $parentId === null) {
            setFlashMessage('danger', 'القسم الرئيسي المختار غير صالح');
            redirect('categories.php');
        }

        // رفع الأيقونة
        $iconPath = null;
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['icon'], 'categories');
            if ($upload['success']) {
                $iconPath = $upload['path'];
            }
        }

        // رفع الصورة
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'categories');
            if ($upload['success']) {
                $imagePath = $upload['path'];
            }
        }

        $newId = db()->insert('service_categories', [
            'parent_id' => $parentId,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'name_ur' => $nameUr !== '' ? $nameUr : ($nameEn !== '' ? $nameEn : $nameAr),
            'icon' => $iconPath,
            'image' => $imagePath,
            'warranty_days' => $warrantyDays,
            'sort_order' => $sortOrder,
            'is_active' => 1
        ]);

        logActivity('add_category', 'service_categories', $newId);
        setFlashMessage('success', $parentId ? 'تم إضافة القسم الفرعي بنجاح' : 'تم إضافة القسم الرئيسي بنجاح');
        redirect('categories.php');
    }

    // تعديل فئة
    if ($postAction === 'edit') {
        $categoryId = (int) post('category_id');
        $nameAr = post('name_ar');
        $nameEn = post('name_en');
        $nameUr = post('name_ur');
        $sortOrder = (int) post('sort_order');
        $warrantyDays = max(0, (int) post('warranty_days'));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $category = db()->fetch('SELECT id, parent_id FROM service_categories WHERE id = ?', [$categoryId]);
        if (!$category) {
            setFlashMessage('danger', 'الفئة غير موجودة');
            redirect('categories.php');
        }

        $requestedParentId = (int) post('parent_id');
        $parentId = resolveParentCategoryId($requestedParentId);

        if ($nameAr === '') {
            setFlashMessage('danger', 'اسم الفئة بالعربي مطلوب');
            redirect('categories.php?action=edit&id=' . $categoryId);
        }

        if ($requestedParentId > 0 && $parentId === null) {
            setFlashMessage('danger', 'القسم الرئيسي المختار غير صالح');
            redirect('categories.php?action=edit&id=' . $categoryId);
        }

        if ($parentId !== null && $parentId === $categoryId) {
            setFlashMessage('danger', 'لا يمكن ربط القسم بنفسه');
            redirect('categories.php?action=edit&id=' . $categoryId);
        }

        $childrenCount = (int) db()->count('service_categories', 'parent_id = ?', [$categoryId]);
        if ($childrenCount > 0 && $parentId !== null) {
            setFlashMessage('danger', 'لا يمكن تحويل قسم رئيسي لديه أقسام فرعية إلى قسم فرعي');
            redirect('categories.php?action=edit&id=' . $categoryId);
        }

        $data = [
            'parent_id' => $parentId,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'name_ur' => $nameUr !== '' ? $nameUr : ($nameEn !== '' ? $nameEn : $nameAr),
            'warranty_days' => $warrantyDays,
            'sort_order' => $sortOrder,
            'is_active' => $isActive
        ];

        // تحديث الصورة إذا وجدت
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'categories');
            if ($upload['success']) {
                $data['image'] = $upload['path'];
            }
        }

        // تحديث الأيقونة إذا وجدت
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['icon'], 'categories');
            if ($upload['success']) {
                $data['icon'] = $upload['path'];
            }
        }

        db()->update('service_categories', $data, 'id = :id', ['id' => $categoryId]);
        logActivity('update_category', 'service_categories', $categoryId);
        setFlashMessage('success', 'تم تحديث الفئة بنجاح');
        redirect('categories.php');
    }

    // حذف فئة
    if ($postAction === 'delete') {
        $categoryId = (int) post('category_id');

        $childrenCount = (int) db()->count('service_categories', 'parent_id = ?', [$categoryId]);
        if ($childrenCount > 0) {
            setFlashMessage('danger', 'لا يمكن حذف القسم الرئيسي لوجود أقسام فرعية مرتبطة به. احذف الأقسام الفرعية أولاً.');
            redirect('categories.php');
        }

        $servicesCount = (int) db()->count('services', 'category_id = ?', [$categoryId]);
        if ($servicesCount > 0) {
            setFlashMessage('danger', 'لا يمكن حذف الفئة لوجود خدمات مرتبطة بها. قم بنقل الخدمات أولاً إلى قسم آخر.');
            redirect('categories.php');
        }

        // التحقق من وجود طلبات مرتبطة
        $ordersCount = db()->count('orders', 'category_id = ?', [$categoryId]);
        if ($ordersCount > 0) {
            setFlashMessage('danger', 'لا يمكن حذف الفئة لوجود طلبات مرتبطة بها. يمكنك تعطيلها بدلاً من ذلك.');
        } else {
            db()->delete('service_categories', 'id = ?', [$categoryId]);
            logActivity('delete_category', 'service_categories', $categoryId);
            setFlashMessage('success', 'تم حذف الفئة بنجاح');
        }
        redirect('categories.php');
    }
}

// جلب الفئات الرئيسية لاستخدامها في اختيار الأب
$mainCategories = db()->fetchAll("SELECT id, name_ar FROM service_categories WHERE parent_id IS NULL ORDER BY sort_order ASC, id ASC");

// عرض قائمة الفئات
$categories = db()->fetchAll("
    SELECT c.*, p.name_ar AS parent_name_ar,
           (SELECT COUNT(*) FROM orders o WHERE o.category_id = c.id) AS orders_count,
           (SELECT COUNT(*) FROM service_categories ch WHERE ch.parent_id = c.id) AS children_count
    FROM service_categories c
    LEFT JOIN service_categories p ON p.id = c.parent_id
    ORDER BY COALESCE(c.parent_id, c.id) ASC,
             CASE WHEN c.parent_id IS NULL THEN 0 ELSE 1 END ASC,
             c.sort_order ASC,
             c.id ASC
");

// عرض بيانات فئة للتعديل
if ($action === 'edit' && $id) {
    $category = db()->fetch("SELECT * FROM service_categories WHERE id = ?", [$id]);
    if (!$category) {
        setFlashMessage('danger', 'الفئة غير موجودة');
        redirect('categories.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- زر الإضافة -->
<div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
    <button onclick="showModal('add-modal')" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        إضافة فئة جديدة
    </button>
</div>

<!-- قائمة الفئات -->
<div class="card animate-slideUp">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-th-large" style="color: var(--primary-color);"></i>
            فئات الخدمات
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔧</div>
            <h3>لا توجد فئات</h3>
            <p>أضف فئات الخدمات لتبدأ في استقبال الطلبات</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th>القسم الرئيسي</th>
                        <th>الأيقونة</th>
                        <th>الصورة</th>
                        <th>الاسم بالعربي</th>
                        <th>الاسم بالإنجليزي</th>
                        <th>الاسم بالأردو</th>
                        <th>الترتيب</th>
                        <th>الضمان</th>
                        <th>الحالة</th>
                        <th>عدد الطلبات</th>
                        <th>الأقسام الفرعية</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <?php $isSubCategory = !empty($cat['parent_id']); ?>
                    <tr style="<?php echo $isSubCategory ? 'background-color: #fcfcfd;' : ''; ?>">
                        <td>
                            <span class="badge <?php echo $isSubCategory ? 'badge-primary' : 'badge-success'; ?>">
                                <?php echo $isSubCategory ? 'فرعي' : 'رئيسي'; ?>
                            </span>
                        </td>
                        <td><?php echo $cat['parent_name_ar'] ?: '-'; ?></td>
                        <td>
                            <?php if ($cat['icon']): ?>
                                <img src="<?php echo imageUrl($cat['icon']); ?>" alt="" style="width: 30px; height: 30px; object-fit: contain;">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cat['image']): ?>
                            <img src="<?php echo imageUrl($cat['image']); ?>" alt="" class="avatar avatar-sm">
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isSubCategory): ?>
                                <span style="color: #6b7280; margin-left: 4px;">↳</span>
                            <?php endif; ?>
                            <strong><?php echo $cat['name_ar']; ?></strong>
                        </td>
                        <td><?php echo $cat['name_en'] ?: '-'; ?></td>
                        <td><?php echo $cat['name_ur'] ?: '-'; ?></td>
                        <td><?php echo (int) $cat['sort_order']; ?></td>
                        <td><?php echo (int) ($cat['warranty_days'] ?? 14); ?> يوم</td>
                        <td>
                            <span class="badge <?php echo $cat['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $cat['is_active'] ? 'نشط' : 'غير نشط'; ?>
                            </span>
                        </td>
                        <td><?php echo (int) $cat['orders_count']; ?></td>
                        <td><?php echo (int) $cat['children_count']; ?></td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
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
            <h3 class="modal-title">إضافة فئة جديدة</h3>
            <button class="modal-close" onclick="hideModal('add-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="form-label">القسم الرئيسي (اختياري)</label>
                    <select name="parent_id" class="form-control">
                        <option value="0">-- قسم رئيسي --</option>
                        <?php foreach ($mainCategories as $mainCat): ?>
                        <option value="<?php echo (int) $mainCat['id']; ?>">
                            <?php echo $mainCat['name_ar']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6b7280;">اختر قسم رئيسي لإنشاء قسم فرعي تحته.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم بالعربي</label>
                    <input type="text" name="name_ar" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم بالإنجليزي</label>
                    <input type="text" name="name_en" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم بالأردو</label>
                    <input type="text" name="name_ur" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">الأيقونة (صورة)</label>
                    <input type="file" name="icon" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">صورة الغلاف</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">الترتيب</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">مدة الضمان (بالأيام)</label>
                    <input type="number" name="warranty_days" class="form-control" value="14" min="0" max="365">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && isset($category)): ?>
<!-- تعديل فئة -->
<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <a href="categories.php" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            العودة للقائمة
        </a>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">تعديل الفئة: <?php echo $category['name_ar']; ?></h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">

                <div class="form-group">
                    <label class="form-label">القسم الرئيسي (اختياري)</label>
                    <select name="parent_id" class="form-control">
                        <option value="0">-- قسم رئيسي --</option>
                        <?php foreach ($mainCategories as $mainCat): ?>
                            <?php if ((int) $mainCat['id'] === (int) $category['id']) { continue; } ?>
                            <option value="<?php echo (int) $mainCat['id']; ?>" <?php echo (int) ($category['parent_id'] ?? 0) === (int) $mainCat['id'] ? 'selected' : ''; ?>>
                                <?php echo $mainCat['name_ar']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم بالعربي</label>
                    <input type="text" name="name_ar" class="form-control" value="<?php echo $category['name_ar']; ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم بالإنجليزي</label>
                    <input type="text" name="name_en" class="form-control" value="<?php echo $category['name_en']; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم بالأردو</label>
                    <input type="text" name="name_ur" class="form-control" value="<?php echo htmlspecialchars((string) ($category['name_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">الأيقونة (صورة)</label>
                    <?php if ($category['icon']): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?php echo imageUrl($category['icon']); ?>" alt="" style="width: 40px; height: 40px; object-fit: contain;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="icon" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">صورة الغلاف</label>
                    <?php if ($category['image']): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?php echo imageUrl($category['image']); ?>" alt="" style="height: 100px; border-radius: 10px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">الترتيب</label>
                    <input type="number" name="sort_order" class="form-control" value="<?php echo $category['sort_order']; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">مدة الضمان (بالأيام)</label>
                    <input type="number" name="warranty_days" class="form-control" value="<?php echo (int) ($category['warranty_days'] ?? 14); ?>" min="0" max="365">
                </div>

                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" style="width: 20px; height: 20px;" <?php echo $category['is_active'] ? 'checked' : ''; ?>>
                        تفعيل الفئة
                    </label>
                </div>
            </div>
            <div class="card-footer" style="text-align: left;">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
