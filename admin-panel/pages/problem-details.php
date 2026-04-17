<?php
/**
 * صفحة إدارة تفاصيل المشكلة
 * Problem Detail Options Management
 */

require_once '../init.php';
requireLogin();

if (!hasPermission('services') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'تفاصيل المشكلة';
$pageSubtitle = 'إدارة خيارات تفاصيل المشكلة حسب الفئة ونوع الخدمة';

$action = get('action', 'list');
$id = (int) get('id');

function ensureProblemDetailsTable()
{
    db()->query("
        CREATE TABLE IF NOT EXISTS problem_detail_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            service_id INT NULL,
            title_ar VARCHAR(255) NOT NULL,
            title_en VARCHAR(255) NULL,
            title_ur VARCHAR(255) NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category_id (category_id),
            INDEX idx_service_id (service_id),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    try {
        $exists = db()->fetch("SHOW COLUMNS FROM `problem_detail_options` LIKE 'title_ur'");
        if (!$exists) {
            db()->query("ALTER TABLE `problem_detail_options` ADD COLUMN `title_ur` VARCHAR(255) NULL AFTER `title_en`");
        }
    } catch (Throwable $e) {
        // Ignore schema issues
    }
}

function normalizeNullableInt($value)
{
    $num = (int) $value;
    return $num > 0 ? $num : null;
}

ensureProblemDetailsTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $optionId = (int) post('id');
        $categoryId = (int) post('category_id');
        $serviceId = normalizeNullableInt(post('service_id'));
        $titleAr = trim(post('title_ar'));
        $titleEn = trim(post('title_en'));
        $titleUr = trim(post('title_ur'));
        $sortOrder = (int) post('sort_order');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($categoryId <= 0 || $titleAr === '') {
            setFlashMessage('danger', 'الفئة وعنوان التفاصيل مطلوبان');
            redirect('problem-details.php' . ($postAction === 'edit' ? '?action=edit&id=' . $optionId : ''));
        }

        if ($serviceId !== null) {
            $service = db()->fetch("SELECT id, category_id FROM services WHERE id = ?", [$serviceId]);
            if (!$service || (int) $service['category_id'] !== $categoryId) {
                setFlashMessage('danger', 'نوع الخدمة المحدد لا يتبع للفئة المختارة');
                redirect('problem-details.php' . ($postAction === 'edit' ? '?action=edit&id=' . $optionId : ''));
            }
        }

        $data = [
            'category_id' => $categoryId,
            'service_id' => $serviceId,
            'title_ar' => $titleAr,
            'title_en' => ($titleEn !== '' ? $titleEn : null),
            'title_ur' => ($titleUr !== '' ? $titleUr : null),
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if ($postAction === 'add') {
            $newId = db()->insert('problem_detail_options', $data);
            logActivity('add_problem_detail_option', 'problem_detail_options', $newId);
            setFlashMessage('success', 'تمت إضافة تفصيلة المشكلة بنجاح');
            redirect('problem-details.php');
        }

        db()->update('problem_detail_options', $data, 'id = ?', [$optionId]);
        logActivity('update_problem_detail_option', 'problem_detail_options', $optionId);
        setFlashMessage('success', 'تم تحديث تفصيلة المشكلة بنجاح');
        redirect('problem-details.php');
    }

    if ($postAction === 'delete') {
        $optionId = (int) post('id');
        db()->delete('problem_detail_options', 'id = ?', [$optionId]);
        logActivity('delete_problem_detail_option', 'problem_detail_options', $optionId);
        setFlashMessage('success', 'تم حذف التفصيلة');
        redirect('problem-details.php');
    }
}

$categories = getServiceCategoriesHierarchy(true);
$categoryDisplayMap = getServiceCategoryDisplayMap(true);

$services = db()->fetchAll("
    SELECT s.id, s.category_id, s.name_ar
    FROM services s
    ORDER BY s.category_id ASC, s.id ASC
");
foreach ($services as &$serviceRow) {
    $serviceCategoryId = (int) ($serviceRow['category_id'] ?? 0);
    $serviceRow['category_display_name'] = $categoryDisplayMap[$serviceCategoryId] ?? '';
}
unset($serviceRow);

$options = db()->fetchAll("
    SELECT p.*, c.name_ar AS category_name, s.name_ar AS service_name
    FROM problem_detail_options p
    LEFT JOIN service_categories c ON p.category_id = c.id
    LEFT JOIN services s ON p.service_id = s.id
    ORDER BY c.sort_order ASC, p.sort_order ASC, p.id DESC
");

if ($action === 'edit' && $id) {
    $option = db()->fetch("SELECT * FROM problem_detail_options WHERE id = ?", [$id]);
    if (!$option) {
        setFlashMessage('danger', 'التفصيلة غير موجودة');
        redirect('problem-details.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
    <button onclick="showModal('add-modal')" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        إضافة تفصيلة مشكلة
    </button>
</div>

<div class="card animate-slideUp">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list-check" style="color: var(--primary-color);"></i>
            خيارات تفاصيل المشكلة
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($options)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🧩</div>
            <h3>لا توجد تفاصيل مضافة</h3>
            <p>أضف تفاصيل مرتبطة بكل فئة ونوع خدمة لتظهر للمستخدم أثناء الطلب</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التفصيلة</th>
                        <th>الفئة</th>
                        <th>نوع الخدمة</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($options as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['id']; ?></td>
                        <td>
                            <strong><?php echo $row['title_ar']; ?></strong>
                            <?php if (!empty($row['title_en'])): ?>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo $row['title_en']; ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['title_ur'])): ?>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo $row['title_ur']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $categoryDisplayMap[(int) ($row['category_id'] ?? 0)] ?? ($row['category_name'] ?: '-'); ?></td>
                        <td><?php echo $row['service_name'] ?: '<span class="text-muted">عام لكل الأنواع</span>'; ?></td>
                        <td><?php echo (int) ($row['sort_order'] ?? 0); ?></td>
                        <td>
                            <span class="badge <?php echo !empty($row['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo !empty($row['is_active']) ? 'نشط' : 'غير نشط'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="?action=edit&id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
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
    <div class="modal" style="width: 720px; max-width: 95%;">
        <div class="modal-header">
            <h3 class="modal-title">إضافة تفصيلة مشكلة</h3>
            <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <?php include __DIR__ . '/problem-details_form.php'; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && isset($option)): ?>
<div style="max-width: 760px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <a href="problem-details.php" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            العودة للقائمة
        </a>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">تعديل تفصيلة المشكلة</h3>
        </div>
        <form method="POST">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo (int) $option['id']; ?>">
                <?php include __DIR__ . '/problem-details_form.php'; ?>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    (function () {
        function bindServiceFilter(categorySelector, serviceSelector) {
            const categorySelect = document.querySelector(categorySelector);
            const serviceSelect = document.querySelector(serviceSelector);
            if (!categorySelect || !serviceSelect) return;

            function refresh() {
                const catId = categorySelect.value;
                for (const option of serviceSelect.options) {
                    if (!option.value) {
                        option.hidden = false;
                        continue;
                    }
                    const optionCategory = option.getAttribute('data-category-id');
                    option.hidden = !!catId && optionCategory !== catId;
                }
                if (serviceSelect.selectedOptions.length && serviceSelect.selectedOptions[0].hidden) {
                    serviceSelect.value = '';
                }
            }

            categorySelect.addEventListener('change', refresh);
            refresh();
        }

        bindServiceFilter('#add-category-id', '#add-service-id');
        bindServiceFilter('#edit-category-id', '#edit-service-id');
    })();
</script>

<?php include '../includes/footer.php'; ?>
