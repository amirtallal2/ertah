<?php
/**
 * صفحة إدارة الخدمات
 * Services Management Page
 */

require_once '../init.php';
requireLogin();
require_once '../includes/service_areas.php';
require_once '../includes/inspection_pricing.php';

// التحقق من الصلاحيات
if (!hasPermission('services') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'الخدمات';
$pageSubtitle = 'إدارة الخدمات وربطها بالأقسام';

$action = get('action', 'list');
$id = (int) get('id');
$selectedServiceAreaIds = [];

function servicesAdminColumnExists($table, $column)
{
    static $cache = [];

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $cacheKey = $safeTable . '.' . $safeColumn;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $tableQuoted = db()->getConnection()->quote($safeTable);
    $tableExists = (bool) db()->fetch("SHOW TABLES LIKE {$tableQuoted}");
    if (!$tableExists) {
        $cache[$cacheKey] = false;
        return false;
    }

    $columnQuoted = db()->getConnection()->quote($safeColumn);
    $exists = (bool) db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE {$columnQuoted}");
    $cache[$cacheKey] = $exists;
    return $exists;
}

function ensureServicesMultilingualSchema()
{
    if (!servicesAdminColumnExists('services', 'name_ur')) {
        db()->query("ALTER TABLE `services` ADD COLUMN `name_ur` VARCHAR(100) NULL AFTER `name_en`");
    }
    if (!servicesAdminColumnExists('services', 'description_ur')) {
        db()->query("ALTER TABLE `services` ADD COLUMN `description_ur` TEXT NULL AFTER `description_en`");
    }
}

ensureServicesMultilingualSchema();
serviceAreaEnsureServiceLinksSchema();
inspectionPricingEnsureSchema();

function normalizeServiceAreaIds($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $item) {
        $id = (int) $item;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function serviceAreaDisplayName(array $area): string
{
    $parts = [];
    $name = trim((string) ($area['name'] ?? ''));
    $country = trim((string) ($area['country_code'] ?? ''));
    $city = trim((string) ($area['city_name'] ?? ''));
    $village = trim((string) ($area['village_name'] ?? ''));

    if ($name !== '') {
        $parts[] = $name;
    }
    if ($city !== '') {
        $parts[] = $city;
    }
    if ($village !== '') {
        $parts[] = $village;
    }
    if ($country !== '') {
        $parts[] = $country;
    }

    $parts = array_values(array_unique(array_filter($parts, static fn($part) => trim((string) $part) !== '')));
    return !empty($parts) ? implode(' - ', $parts) : ('منطقة #' . (int) ($area['id'] ?? 0));
}

function syncServiceAreaLinks(int $serviceId, array $areaIds): void
{
    if ($serviceId <= 0) {
        return;
    }

    db()->delete('service_area_services', 'service_id = ?', [$serviceId]);
    if (empty($areaIds)) {
        return;
    }

    foreach ($areaIds as $areaId) {
        db()->insert('service_area_services', [
            'service_area_id' => (int) $areaId,
            'service_id' => $serviceId,
        ]);
    }
}

// =========================================================
// معالجة الإجراءات (POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $categoryId = (int) post('category_id');
        $selectedAreaIds = normalizeServiceAreaIds($_POST['service_area_ids'] ?? []);
        $inspectionMode = inspectionPricingNormalizeMode(post('inspection_pricing_mode'), true);
        $inspectionFee = $inspectionMode === 'paid' ? inspectionPricingNormalizeFee(post('inspection_fee')) : 0.0;
        $data = [
            'name_ar' => clean(post('name_ar')),
            'name_en' => clean(post('name_en')),
            'name_ur' => clean(post('name_ur')),
            'description_ar' => clean(post('description_ar')),
            'description_en' => clean(post('description_en')),
            'description_ur' => clean(post('description_ur')),
            'price' => (float) post('price'),
            'category_id' => $categoryId,
            'inspection_pricing_mode' => $inspectionMode,
            'inspection_fee' => $inspectionFee,
            'inspection_details_ar' => clean(post('inspection_details_ar')),
            'inspection_details_en' => clean(post('inspection_details_en')),
            'inspection_details_ur' => clean(post('inspection_details_ur')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0
        ];

        if ($data['name_ur'] === '') {
            $data['name_ur'] = $data['name_en'] !== '' ? $data['name_en'] : $data['name_ar'];
        }
        if ($data['description_ur'] === '') {
            $data['description_ur'] = $data['description_en'] !== '' ? $data['description_en'] : $data['description_ar'];
        }

        if ($data['name_ar'] === '' || $data['name_en'] === '') {
            setFlashMessage('danger', 'اسم الخدمة بالعربي والإنجليزي مطلوبان');
            redirect('services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        if ($categoryId <= 0) {
            setFlashMessage('danger', 'يرجى اختيار القسم');
            redirect('services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        if ($inspectionMode === 'paid' && $inspectionFee <= 0) {
            setFlashMessage('danger', 'يرجى إدخال رسوم معاينة صحيحة أكبر من صفر أو اختيار مجانية/حسب القسم');
            redirect('services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $validCategory = db()->fetch(
            "SELECT id FROM service_categories WHERE id = ? AND is_active = 1",
            [$categoryId]
        );
        if (!$validCategory) {
            setFlashMessage('danger', 'القسم المختار غير صالح أو غير نشط');
            redirect('services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        if (empty($selectedAreaIds)) {
            setFlashMessage('danger', 'يرجى اختيار منطقة خدمة واحدة على الأقل');
            redirect('services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $areaPlaceholders = implode(',', array_fill(0, count($selectedAreaIds), '?'));
        $activeAreasCount = 0;
        if ($areaPlaceholders !== '') {
            $activeAreasRows = db()->fetchAll(
                "SELECT id FROM service_areas WHERE is_active = 1 AND id IN ({$areaPlaceholders})",
                $selectedAreaIds
            );
            $activeAreasCount = count($activeAreasRows);
        }

        if ($activeAreasCount !== count($selectedAreaIds)) {
            setFlashMessage('danger', 'يوجد منطقة غير صالحة أو غير مفعلة ضمن الاختيار');
            redirect('services.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        // رفع الصورة
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'services');
            if ($upload['success']) {
                $data['image'] = $upload['path'];
            }
        }

        if ($postAction === 'add') {
            $newServiceId = (int) db()->insert('services', $data);
            syncServiceAreaLinks($newServiceId, $selectedAreaIds);
            logActivity('add_service', 'services', $newServiceId);
            setFlashMessage('success', 'تم إضافة الخدمة بنجاح');
        } else {
            $serviceId = (int) post('id');
            db()->update('services', $data, 'id = ?', [$serviceId]);
            syncServiceAreaLinks($serviceId, $selectedAreaIds);
            logActivity('update_service', 'services', $serviceId);
            setFlashMessage('success', 'تم تحديث الخدمة بنجاح');
        }
        redirect('services.php');
    }

    if ($postAction === 'delete') {
        $serviceId = (int) post('id');
        // Check for orders before delete? Maybe just soft delete or check dependency.
        // For simplicity:
        db()->delete('service_area_services', 'service_id = ?', [$serviceId]);
        db()->delete('services', 'id = ?', [$serviceId]);
        logActivity('delete_service', 'services', $serviceId);
        setFlashMessage('success', 'تم حذف الخدمة بنجاح');
        redirect('services.php');
    }
}

// جلب الفئات للقائمة المنسدلة
$categories = getServiceCategoriesHierarchy(true);
$categoryDisplayMap = [];
$categoriesById = [];
foreach ($categories as $cat) {
    $catId = (int) $cat['id'];
    $categoryDisplayMap[$catId] = $cat['display_name_ar'] ?? ($cat['name_ar'] ?? '');
    $categoriesById[$catId] = $cat;
}

$activeServiceAreas = db()->fetchAll(
    "SELECT id, name, country_code, city_name, village_name
     FROM service_areas
     WHERE is_active = 1
     ORDER BY priority ASC, id ASC"
);
$serviceAreaOptions = [];
foreach ($activeServiceAreas as $areaRow) {
    $areaId = (int) ($areaRow['id'] ?? 0);
    if ($areaId <= 0) {
        continue;
    }
    $serviceAreaOptions[$areaId] = serviceAreaDisplayName($areaRow);
}

// جلب الخدمات
$query = "SELECT s.*, c.name_ar as category_name 
          FROM services s 
          LEFT JOIN service_categories c ON s.category_id = c.id 
          ORDER BY s.id DESC";
$services = db()->fetchAll($query);

$serviceAreaNamesByService = [];
if (!empty($services)) {
    $serviceIds = [];
    foreach ($services as $serviceRow) {
        $serviceId = (int) ($serviceRow['id'] ?? 0);
        if ($serviceId > 0) {
            $serviceIds[] = $serviceId;
        }
    }
    $serviceIds = array_values(array_unique($serviceIds));

    if (!empty($serviceIds)) {
        $servicePlaceholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $serviceAreaRows = db()->fetchAll(
            "SELECT sas.service_id, sa.id AS area_id, sa.name, sa.country_code, sa.city_name, sa.village_name
             FROM service_area_services sas
             JOIN service_areas sa ON sa.id = sas.service_area_id
             WHERE sas.service_id IN ({$servicePlaceholders})
             ORDER BY sa.priority ASC, sa.id ASC",
            $serviceIds
        );

        foreach ($serviceAreaRows as $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);
            if ($serviceId <= 0) {
                continue;
            }
            if (!isset($serviceAreaNamesByService[$serviceId])) {
                $serviceAreaNamesByService[$serviceId] = [];
            }

            $areaId = (int) ($row['area_id'] ?? 0);
            $label = $serviceAreaOptions[$areaId] ?? serviceAreaDisplayName($row);
            if ($label !== '' && !in_array($label, $serviceAreaNamesByService[$serviceId], true)) {
                $serviceAreaNamesByService[$serviceId][] = $label;
            }
        }
    }
}

if ($action === 'edit' && $id) {
    $service = db()->fetch("SELECT * FROM services WHERE id = ?", [$id]);
    if (!$service) {
        redirect('services.php');
    }

    $selectedServiceAreaRows = db()->fetchAll(
        "SELECT service_area_id FROM service_area_services WHERE service_id = ? ORDER BY service_area_id ASC",
        [$id]
    );
    $selectedServiceAreaIds = [];
    foreach ($selectedServiceAreaRows as $serviceAreaRow) {
        $areaId = (int) ($serviceAreaRow['service_area_id'] ?? 0);
        if ($areaId > 0) {
            $selectedServiceAreaIds[] = $areaId;
        }
    }
    $selectedServiceAreaIds = array_values(array_unique($selectedServiceAreaIds));
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة خدمة جديدة
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tools"></i> الخدمات</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الاسم</th>
                            <th>القسم</th>
                            <th>المناطق</th>
	                            <th>النوع</th>
	                            <th>السعر</th>
	                            <th>المعاينة</th>
	                            <th>الحالة</th>
                            <th>مميزة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td>
                                    <?php if ($s['image']): ?>
                                        <img src="<?php echo imageUrl($s['image']); ?>" alt=""
                                            style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>
                                        <?php echo $s['name_ar']; ?>
                                    </strong><br>
                                    <small class="text-muted">
                                        <?php echo $s['name_en']; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $s['name_ur'] ?? ''; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($s['category_name']): ?>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars(($categoryDisplayMap[(int) ($s['category_id'] ?? 0)] ?? $s['category_name']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">غير محدد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $serviceAreaNames = $serviceAreaNamesByService[(int) ($s['id'] ?? 0)] ?? [];
                                    ?>
                                    <?php if (empty($serviceAreaNames)): ?>
                                        <span class="text-muted">كل المناطق</span>
                                    <?php else: ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <?php foreach (array_slice($serviceAreaNames, 0, 2) as $areaName): ?>
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($serviceAreaNames) > 2): ?>
                                                <span class="badge badge-info">+<?php echo count($serviceAreaNames) - 2; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $serviceCategory = $categoriesById[(int) ($s['category_id'] ?? 0)] ?? null;
                                        $isSubCategory = !empty($serviceCategory['is_sub_category']);
                                    ?>
                                    <span class="badge <?php echo $isSubCategory ? 'badge-primary' : 'badge-success'; ?>">
                                        <?php echo $isSubCategory ? 'قسم فرعي' : 'قسم رئيسي'; ?>
                                    </span>
                                </td>
	                                <td>
	                                    <?php echo number_format($s['price'], 2); ?> ⃁
	                                </td>
	                                <td>
	                                    <?php
	                                        $serviceInspectionMode = inspectionPricingNormalizeMode($s['inspection_pricing_mode'] ?? 'inherit', true);
	                                        $serviceInspectionFee = inspectionPricingNormalizeFee($s['inspection_fee'] ?? 0);
	                                    ?>
	                                    <?php if ($serviceInspectionMode === 'inherit'): ?>
	                                        <span class="badge badge-secondary">حسب القسم</span>
	                                    <?php elseif ($serviceInspectionMode === 'paid' && $serviceInspectionFee > 0): ?>
	                                        <span class="badge badge-warning">مدفوعة: <?php echo number_format($serviceInspectionFee, 2); ?> ⃁</span>
	                                    <?php else: ?>
	                                        <span class="badge badge-success">مجانية</span>
	                                    <?php endif; ?>
	                                </td>
	                                <td>
                                    <span class="badge <?php echo $s['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $s['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($s['is_featured']): ?>
                                        <span class="badge badge-warning"><i class="fas fa-star"></i> مميزة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline"><i
                                                class="fas fa-edit"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i
                                                    class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($services)): ?>
                            <tr>
	                                <td colspan="10" class="text-center">لا توجد خدمات مضافة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- مودال الإضافة -->
    <div class="modal-overlay" id="add-modal">
        <div class="modal" style="width: 700px; max-width: 90%;">
            <div class="modal-header">
                <h3 class="modal-title">إضافة خدمة جديدة</h3>
                <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <?php if (empty($serviceAreaOptions)): ?>
                        <div class="alert alert-warning" style="margin-bottom: 14px;">
                            لا توجد مناطق خدمة نشطة. أضف منطقة من صفحة
                            <a href="service-areas.php">مناطق تقديم الخدمة</a>
                            أولاً قبل إضافة الخدمة.
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">الاسم (عربي)</label>
                            <input type="text" name="name_ar" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الاسم (إنجليزي)</label>
                            <input type="text" name="name_en" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الاسم (أردو)</label>
                            <input type="text" name="name_ur" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">القسم</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">اختر القسم</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['display_name_ar'] ?? $cat['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">السعر (⃁)</label>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">مناطق الخدمة *</label>
                        <select name="service_area_ids[]" class="form-control" multiple size="5" required>
                            <?php foreach ($serviceAreaOptions as $areaId => $areaName): ?>
                                <option value="<?php echo $areaId; ?>">
                                    <?php echo htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">يمكن اختيار أكثر من منطقة بالضغط مع Ctrl/Cmd.</small>
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
	                        <label class="form-label">صورة الخدمة (الغلاف)</label>
	                        <input type="file" name="image" class="form-control" accept="image/*">
	                    </div>

	                    <div style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; margin-bottom: 14px;">
	                        <h4 style="margin: 0 0 12px;">إعدادات المعاينة للخدمة</h4>
	                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
	                            <div class="form-group">
	                                <label class="form-label">طريقة احتساب المعاينة</label>
	                                <select name="inspection_pricing_mode" class="form-control">
	                                    <option value="inherit">حسب إعداد القسم</option>
	                                    <option value="free">معاينة مجانية لهذه الخدمة</option>
	                                    <option value="paid">معاينة برسوم لهذه الخدمة</option>
	                                </select>
	                            </div>
	                            <div class="form-group">
	                                <label class="form-label">رسوم المعاينة (⃁)</label>
	                                <input type="number" step="0.01" min="0" name="inspection_fee" class="form-control" value="0">
	                            </div>
	                        </div>
	                        <div class="form-group">
	                            <label class="form-label">تفاصيل المعاينة بالعربي</label>
	                            <textarea name="inspection_details_ar" class="form-control" rows="2" placeholder="تظهر للعميل عند طلب هذه الخدمة إذا كانت مدفوعة أو مخصصة."></textarea>
	                        </div>
	                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
	                            <div class="form-group">
	                                <label class="form-label">Details EN</label>
	                                <textarea name="inspection_details_en" class="form-control" rows="2"></textarea>
	                            </div>
	                            <div class="form-group">
	                                <label class="form-label">Details UR</label>
	                                <textarea name="inspection_details_ur" class="form-control" rows="2"></textarea>
	                            </div>
	                        </div>
	                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label" style="cursor: pointer;">
                                <input type="checkbox" name="is_active" checked> تفعيل الخدمة
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="cursor: pointer;">
                                <input type="checkbox" name="is_featured"> عرض في "الأكثر طلباً"
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary" <?php echo empty($serviceAreaOptions) ? 'disabled' : ''; ?>>حفظ</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'edit' && isset($service)): ?>

    <div class="card animate-slideUp" style="max-width: 800px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">تعديل الخدمة:
                <?php echo $service['name_ar']; ?>
            </h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                <?php if (empty($serviceAreaOptions)): ?>
                    <div class="alert alert-warning" style="margin-bottom: 14px;">
                        لا توجد مناطق خدمة نشطة. أضف منطقة من صفحة
                        <a href="service-areas.php">مناطق تقديم الخدمة</a>
                        أولاً.
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الاسم (عربي)</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo $service['name_ar']; ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم (إنجليزي)</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo $service['name_en']; ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم (أردو)</label>
                        <input type="text" name="name_ur" class="form-control" value="<?php echo $service['name_ur'] ?? ''; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">القسم</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">اختر القسم</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $service['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['display_name_ar'] ?? $cat['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعر (⃁)</label>
                        <input type="number" step="0.01" name="price" class="form-control"
                            value="<?php echo $service['price']; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">مناطق الخدمة *</label>
                    <select name="service_area_ids[]" class="form-control" multiple size="6" required>
                        <?php foreach ($serviceAreaOptions as $areaId => $areaName): ?>
                            <option value="<?php echo $areaId; ?>" <?php echo in_array((int) $areaId, $selectedServiceAreaIds, true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6b7280;">يمكن اختيار أكثر من منطقة بالضغط مع Ctrl/Cmd.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف (عربي)</label>
                    <textarea name="description_ar" class="form-control"
                        rows="3"><?php echo $service['description_ar']; ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف (إنجليزي)</label>
                    <textarea name="description_en" class="form-control"
                        rows="3"><?php echo $service['description_en']; ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف (أردو)</label>
                    <textarea name="description_ur" class="form-control"
                        rows="3"><?php echo $service['description_ur'] ?? ''; ?></textarea>
                </div>

	                <div class="form-group">
	                    <label class="form-label">صورة الخدمة</label>
                    <?php if ($service['image']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo imageUrl($service['image']); ?>" alt=""
                                style="width: 100px; object-fit: cover; border-radius: 5px;">
                        </div>
                    <?php endif; ?>
	                    <input type="file" name="image" class="form-control" accept="image/*">
	                </div>

	                <div style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; margin-bottom: 14px;">
	                    <h4 style="margin: 0 0 12px;">إعدادات المعاينة للخدمة</h4>
	                    <?php
	                        $editInspectionMode = inspectionPricingNormalizeMode($service['inspection_pricing_mode'] ?? 'inherit', true);
	                        $editInspectionFee = inspectionPricingNormalizeFee($service['inspection_fee'] ?? 0);
	                    ?>
	                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
	                        <div class="form-group">
	                            <label class="form-label">طريقة احتساب المعاينة</label>
	                            <select name="inspection_pricing_mode" class="form-control">
	                                <option value="inherit" <?php echo $editInspectionMode === 'inherit' ? 'selected' : ''; ?>>حسب إعداد القسم</option>
	                                <option value="free" <?php echo $editInspectionMode === 'free' ? 'selected' : ''; ?>>معاينة مجانية لهذه الخدمة</option>
	                                <option value="paid" <?php echo $editInspectionMode === 'paid' ? 'selected' : ''; ?>>معاينة برسوم لهذه الخدمة</option>
	                            </select>
	                        </div>
	                        <div class="form-group">
	                            <label class="form-label">رسوم المعاينة (⃁)</label>
	                            <input type="number" step="0.01" min="0" name="inspection_fee" class="form-control" value="<?php echo htmlspecialchars((string) $editInspectionFee, ENT_QUOTES, 'UTF-8'); ?>">
	                        </div>
	                    </div>
	                    <div class="form-group">
	                        <label class="form-label">تفاصيل المعاينة بالعربي</label>
	                        <textarea name="inspection_details_ar" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($service['inspection_details_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
	                    </div>
	                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
	                        <div class="form-group">
	                            <label class="form-label">Details EN</label>
	                            <textarea name="inspection_details_en" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($service['inspection_details_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
	                        </div>
	                        <div class="form-group">
	                            <label class="form-label">Details UR</label>
	                            <textarea name="inspection_details_ur" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($service['inspection_details_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
	                        </div>
	                    </div>
	                </div>

	                <div style="display: flex; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" style="cursor: pointer;">
                            <input type="checkbox" name="is_active" <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                            تفعيل الخدمة
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="cursor: pointer;">
                            <input type="checkbox" name="is_featured" <?php echo $service['is_featured'] ? 'checked' : ''; ?>>
                            عرض في "الأكثر طلباً"
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="services.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary" <?php echo empty($serviceAreaOptions) ? 'disabled' : ''; ?>>حفظ التغييرات</button>
            </div>
        </form>
    </div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
