<?php
/**
 * صفحة إدارة المنتجات
 * Products Management Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'المنتجات';
$pageSubtitle = 'إدارة المنتجات والمخزون';

$action = get('action', 'list');
$id = (int)get('id');

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');
    
    // إضافة منتج
    if ($postAction === 'add') {
        $store_id = (int)post('store_id');
        $category_id = (int)post('category_id');
        $name_ar = post('name_ar');
        $name_en = post('name_en');
        $price = (float)post('price');
        $original_price = post('original_price') ? (float)post('original_price') : null;
        $stock_quantity = (int)post('stock_quantity');
        $description_ar = post('description_ar');
        $discount_percent = 0;
        
        if ($original_price && $original_price > $price) {
            $discount_percent = round((($original_price - $price) / $original_price) * 100);
        }
        
        // رفع الصورة الرئيسية
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'products');
            if ($upload['success']) {
                $imagePath = $upload['path'];
            }
        }
        
        db()->insert('products', [
            'store_id' => $store_id,
            'category_id' => $category_id,
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'price' => $price,
            'original_price' => $original_price,
            'discount_percent' => $discount_percent,
            'stock_quantity' => $stock_quantity,
            'description_ar' => $description_ar,
            'image' => $imagePath,
            'is_active' => 1
        ]);
        
        logActivity('add_product', 'products', db()->getConnection()->lastInsertId());
        setFlashMessage('success', 'تم إضافة المنتج بنجاح');
        redirect('products.php');
    }
    
    // تعديل منتج
    if ($postAction === 'edit') {
        $productId = (int)post('product_id');
        $store_id = (int)post('store_id');
        $category_id = (int)post('category_id');
        $name_ar = post('name_ar');
        $name_en = post('name_en');
        $price = (float)post('price');
        $original_price = post('original_price') ? (float)post('original_price') : null;
        $stock_quantity = (int)post('stock_quantity');
        $description_ar = post('description_ar');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        $discount_percent = 0;
        if ($original_price && $original_price > $price) {
            $discount_percent = round((($original_price - $price) / $original_price) * 100);
        }
        
        $data = [
            'store_id' => $store_id,
            'category_id' => $category_id,
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'price' => $price,
            'original_price' => $original_price,
            'discount_percent' => $discount_percent,
            'stock_quantity' => $stock_quantity,
            'description_ar' => $description_ar,
            'is_active' => $is_active,
            'is_featured' => $is_featured
        ];
        
        // تحديث الصورة
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'products');
            if ($upload['success']) {
                $data['image'] = $upload['path'];
            }
        }
        
        db()->update('products', $data, 'id = :id', ['id' => $productId]);
        logActivity('update_product', 'products', $productId);
        setFlashMessage('success', 'تم تحديث المنتج بنجاح');
        redirect('products.php');
    }
    
    // حذف منتج
    if ($postAction === 'delete') {
        $productId = (int)post('product_id');
        db()->delete('products', 'id = ?', [$productId]);
        logActivity('delete_product', 'products', $productId);
        setFlashMessage('success', 'تم حذف المنتج بنجاح');
        redirect('products.php');
    }
}

// البحث والفلترة
$search = get('search');
$storeId = (int)get('store_id');
$categoryId = (int)get('category_id');
$page = max(1, (int)get('page', 1));

$where = '1=1';
$params = [];

if ($search) {
    $where .= " AND (p.name_ar LIKE ? OR p.name_en LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if ($storeId) {
    $where .= " AND p.store_id = ?";
    $params[] = $storeId;
}

if ($categoryId) {
    $where .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

$totalProducts = db()->count('products p', $where, $params);
$pagination = paginate($totalProducts, $page);

$products = db()->fetchAll("
    SELECT p.*, s.name_ar as store_name, c.name_ar as category_name
    FROM products p
    LEFT JOIN stores s ON p.store_id = s.id
    LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE {$where}
    ORDER BY p.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
", $params);

// البيانات المساعدة للقوائم المنسدلة
$stores = db()->fetchAll("SELECT id, name_ar FROM stores WHERE is_active = 1 ORDER BY name_ar");
$categories = db()->fetchAll("SELECT id, name_ar FROM product_categories WHERE is_active = 1 ORDER BY name_ar");

// عرض بيانات منتج للتعديل
if ($action === 'edit' && $id) {
    $product = db()->fetch("SELECT * FROM products WHERE id = ?", [$id]);
    if (!$product) {
        setFlashMessage('danger', 'المنتج غير موجود');
        redirect('products.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- زر الإضافة -->
<div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
    <button onclick="showModal('add-modal')" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        إضافة منتج جديد
    </button>
</div>

<!-- قائمة المنتجات -->
<div class="card animate-slideUp">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-box" style="color: var(--primary-color);"></i>
            المنتجات (<?php echo $totalProducts; ?>)
        </h3>
    </div>
    <div class="card-body">
        <!-- البحث والفلترة -->
        <form method="GET" style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <div class="search-input" style="flex: 1; min-width: 200px;">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="اسم المنتج..." 
                       value="<?php echo $search; ?>">
            </div>
            
            <select name="store_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">جميع المتاجر</option>
                <?php foreach ($stores as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $storeId == $s['id'] ? 'selected' : ''; ?>>
                    <?php echo $s['name_ar']; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="category_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">جميع الفئات</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $categoryId == $c['id'] ? 'selected' : ''; ?>>
                    <?php echo $c['name_ar']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <h3>لا توجد منتجات</h3>
            <p>لم يتم العثور على أي منتجات مطابقة</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>اسم المنتج</th>
                        <th>المتجر</th>
                        <th>السعر</th>
                        <th>المخزون</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod): ?>
                    <tr>
                        <td>
                            <img src="<?php echo imageUrl($prod['image']); ?>" alt="" class="avatar avatar-md" style="border-radius: 8px;">
                        </td>
                        <td>
                            <strong><?php echo $prod['name_ar']; ?></strong>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo $prod['category_name']; ?></div>
                        </td>
                        <td><?php echo $prod['store_name']; ?></td>
                        <td>
                            <div style="font-weight: 700; color: var(--primary-dark);">
                                <?php echo number_format($prod['price'], 2); ?> ⃁
                            </div>
                            <?php if ($prod['original_price']): ?>
                            <div style="font-size: 12px; text-decoration: line-through; color: #9ca3af;">
                                <?php echo number_format($prod['original_price'], 2); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $prod['stock_quantity'] > 10 ? 'badge-success' : ($prod['stock_quantity'] > 0 ? 'badge-warning' : 'badge-danger'); ?>">
                                <?php echo $prod['stock_quantity']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $prod['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $prod['is_active'] ? 'نشط' : 'غير نشط'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
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
        
        <!-- التصفح -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination" style="margin-top: 20px;">
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <a href="?page=<?php echo $i; ?>&store_id=<?php echo $storeId; ?>&category_id=<?php echo $categoryId; ?>&search=<?php echo $search; ?>" 
               class="page-link <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- مودال الإضافة -->
<div class="modal-overlay" id="add-modal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">إضافة منتج جديد</h3>
            <button class="modal-close" onclick="hideModal('add-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label">المتجر</label>
                    <select name="store_id" class="form-control" required>
                        <option value="">اختر المتجر</option>
                        <?php foreach ($stores as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo $s['name_ar']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">الفئة</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">اختر الفئة</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo $c['name_ar']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">اسم المنتج</label>
                    <input type="text" name="name_ar" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">السعر</label>
                        <input type="number" name="price" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعر الأصلي (للخصم)</label>
                        <input type="number" name="original_price" class="form-control" step="0.01">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">الكمية في المخزون</label>
                    <input type="number" name="stock_quantity" class="form-control" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">صورة المنتج</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <textarea name="description_ar" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && isset($product)): ?>
<!-- تعديل منتج -->
<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <a href="products.php" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            العودة للقائمة
        </a>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">تعديل المنتج: <?php echo $product['name_ar']; ?></h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">المتجر</label>
                        <select name="store_id" class="form-control" required>
                            <?php foreach ($stores as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $product['store_id'] == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo $s['name_ar']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الفئة</label>
                        <select name="category_id" class="form-control" required>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $product['category_id'] == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo $c['name_ar']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">اسم المنتج (عربي)</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo $product['name_ar']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">اسم المنتج (إنجليزي)</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo $product['name_en']; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">السعر</label>
                        <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $product['price']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعر الأصلي</label>
                        <input type="number" name="original_price" class="form-control" step="0.01" value="<?php echo $product['original_price']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الكمية</label>
                        <input type="number" name="stock_quantity" class="form-control" value="<?php echo $product['stock_quantity']; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">صورة المنتج</label>
                    <?php if ($product['image']): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?php echo imageUrl($product['image']); ?>" alt="" style="height: 100px; border-radius: 10px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <textarea name="description_ar" class="form-control" rows="4"><?php echo $product['description_ar']; ?></textarea>
                </div>
                
                <div style="display: flex; gap: 20px;">
                    <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_featured" style="width: 20px; height: 20px;" 
                               <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                        منتج مميز
                    </label>
                    
                    <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" style="width: 20px; height: 20px;" 
                               <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                        تفعيل المنتج
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
