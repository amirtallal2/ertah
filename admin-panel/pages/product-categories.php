<?php
/**
 * صفحة إدارة فئات المنتجات
 * Product Categories Management Page
 */

require_once '../init.php';
requireLogin();

if (!hasPermission('products') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'فئات المنتجات';
$pageSubtitle = 'إدارة تصنيفات المنتجات وقطع الغيار';

$action = get('action', 'list');
$id = (int) get('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add') {
        $nameAr = post('name_ar');
        $nameEn = post('name_en');

        if ($nameAr === '') {
            setFlashMessage('danger', 'اسم الفئة بالعربي مطلوب');
            redirect('product-categories.php');
        }

        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'categories');
            if (!$upload['success']) {
                setFlashMessage('danger', $upload['message'] ?? 'تعذر رفع الصورة');
                redirect('product-categories.php');
            }
            $imagePath = $upload['path'];
        }

        $newId = db()->insert('product_categories', [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'image' => $imagePath,
            'is_active' => 1
        ]);

        logActivity('add_product_category', 'product_categories', $newId);
        setFlashMessage('success', 'تم إضافة فئة المنتج بنجاح');
        redirect('product-categories.php');
    }

    if ($postAction === 'edit') {
        $categoryId = (int) post('category_id');
        $nameAr = post('name_ar');
        $nameEn = post('name_en');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($categoryId <= 0 || $nameAr === '') {
            setFlashMessage('danger', 'بيانات التعديل غير مكتملة');
            redirect('product-categories.php');
        }

        $data = [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'is_active' => $isActive
        ];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'categories');
            if (!$upload['success']) {
                setFlashMessage('danger', $upload['message'] ?? 'تعذر رفع الصورة');
                redirect('product-categories.php?action=edit&id=' . $categoryId);
            }
            $data['image'] = $upload['path'];
        }

        db()->update('product_categories', $data, 'id = :id', ['id' => $categoryId]);
        logActivity('update_product_category', 'product_categories', $categoryId);
        setFlashMessage('success', 'تم تحديث فئة المنتج بنجاح');
        redirect('product-categories.php');
    }

    if ($postAction === 'delete') {
        $categoryId = (int) post('category_id');
        if ($categoryId > 0) {
            $productsCount = db()->count('products', 'category_id = ?', [$categoryId]);
            if ($productsCount > 0) {
                setFlashMessage('danger', 'لا يمكن حذف الفئة لوجود منتجات مرتبطة بها');
            } else {
                db()->delete('product_categories', 'id = ?', [$categoryId]);
                logActivity('delete_product_category', 'product_categories', $categoryId);
                setFlashMessage('success', 'تم حذف الفئة بنجاح');
            }
        }
        redirect('product-categories.php');
    }
}

$categories = db()->fetchAll("
    SELECT pc.*, COALESCE(pc_count.products_count, 0) AS products_count
    FROM product_categories pc
    LEFT JOIN (
        SELECT category_id, COUNT(*) AS products_count
        FROM products
        GROUP BY category_id
    ) pc_count ON pc_count.category_id = pc.id
    ORDER BY pc.id DESC
");

if ($action === 'edit' && $id > 0) {
    $category = db()->fetch("SELECT * FROM product_categories WHERE id = ?", [$id]);
    if (!$category) {
        setFlashMessage('danger', 'الفئة غير موجودة');
        redirect('product-categories.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            إضافة فئة منتجات
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-tags" style="color: var(--primary-color);"></i>
                فئات المنتجات
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏷️</div>
                    <h3>لا توجد فئات منتجات</h3>
                    <p>أضف الفئة الأولى لتنظيم منتجات المتجر</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الصورة</th>
                                <th>الاسم بالعربي</th>
                                <th>الاسم بالإنجليزي</th>
                                <th>عدد المنتجات</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($cat['image'])): ?>
                                            <img src="<?php echo imageUrl($cat['image']); ?>" alt="" class="avatar avatar-sm">
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $cat['name_ar']; ?></strong>
                                    </td>
                                    <td><?php echo $cat['name_en'] ?: '-'; ?></td>
                                    <td><?php echo (int) $cat['products_count']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $cat['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $cat['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                        </span>
                                    </td>
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

    <div class="modal-overlay" id="add-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">إضافة فئة منتجات</h3>
                <button class="modal-close" onclick="hideModal('add-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="form-label">الاسم بالعربي</label>
                        <input type="text" name="name_ar" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم بالإنجليزي</label>
                        <input type="text" name="name_en" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">صورة الفئة</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
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
    <div style="max-width: 640px; margin: 0 auto;">
        <div class="card animate-slideUp">
            <div class="card-header">
                <h3 class="card-title">تعديل فئة المنتج</h3>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="card-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                    <div class="form-group">
                        <label class="form-label">الاسم بالعربي</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo $category['name_ar']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم بالإنجليزي</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo $category['name_en']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">صورة الفئة</label>
                        <?php if (!empty($category['image'])): ?>
                            <img src="<?php echo imageUrl($category['image']); ?>" alt="" style="height: 60px; display: block; margin-bottom: 8px;">
                        <?php endif; ?>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo $category['is_active'] ? 'checked' : ''; ?>>
                            تفعيل الفئة
                        </label>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    <a href="product-categories.php" class="btn btn-outline">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
