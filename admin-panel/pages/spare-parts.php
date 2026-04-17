<?php
/**
 * صفحة إدارة قطع الغيار
 * Spare Parts Management Page
 */

require_once '../init.php';
require_once '../includes/store_accounting.php';
require_once '../includes/service_areas.php';
require_once '../includes/spare_parts_scope.php';
requireLogin();

ensureStoreSparePartsAccountingSchema();
serviceAreaEnsureSchema();
serviceAreaEnsureServiceLinksSchema();
sparePartScopeEnsureSchema();

// التحقق من الصلاحيات
if (!hasPermission('products') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'قطع الغيار';
$pageSubtitle = 'إدارة مخزون قطع الغيار وربطه بالخدمات والمناطق';

$action = get('action', 'list');
$id = (int) get('id');
$storeFilterId = (int) get('store_id');
$serviceFilterId = (int) get('service_id');
$areaFilterId = (int) get('service_area_id');
$selectedServiceIds = [];
$selectedServiceAreaIds = [];

function sparePartAreaDisplayName(array $area): string
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

function sparePartNormalizeIds($raw): array
{
    return sparePartScopeNormalizeIds($raw);
}

$stores = db()->fetchAll("SELECT id, name_ar FROM stores WHERE is_active = 1 ORDER BY name_ar");
$serviceCategories = getServiceCategoriesHierarchy(false);
$serviceCategoryDisplayMap = getServiceCategoryDisplayMap(false);

$activeServices = db()->fetchAll(
    "SELECT s.id, s.category_id, s.name_ar, s.name_en, c.name_ar AS category_name
     FROM services s
     LEFT JOIN service_categories c ON c.id = s.category_id
     WHERE s.is_active = 1
     ORDER BY s.id DESC"
);
$serviceOptions = [];
foreach ($activeServices as $serviceRow) {
    $serviceId = (int) ($serviceRow['id'] ?? 0);
    if ($serviceId <= 0) {
        continue;
    }

    $categoryId = (int) ($serviceRow['category_id'] ?? 0);
    $categoryLabel = trim((string) ($serviceCategoryDisplayMap[$categoryId] ?? ($serviceRow['category_name'] ?? '')));
    $serviceName = trim((string) ($serviceRow['name_ar'] ?? ''));
    if ($serviceName === '') {
        $serviceName = 'خدمة #' . $serviceId;
    }
    $label = $serviceName . ($categoryLabel !== '' ? (' - ' . $categoryLabel) : '');
    $serviceOptions[$serviceId] = $label;
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
    $serviceAreaOptions[$areaId] = sparePartAreaDisplayName($areaRow);
}

$serviceToAreaIds = [];
$areaToServiceIds = [];
if (sparePartScopeTableExists('service_area_services')) {
    $serviceAreaRows = db()->fetchAll(
        "SELECT sas.service_id, sas.service_area_id
         FROM service_area_services sas
         JOIN services s ON s.id = sas.service_id AND s.is_active = 1
         JOIN service_areas sa ON sa.id = sas.service_area_id AND sa.is_active = 1
         ORDER BY sas.service_id ASC, sas.service_area_id ASC"
    );
    foreach ($serviceAreaRows as $row) {
        $serviceId = (int) ($row['service_id'] ?? 0);
        $areaId = (int) ($row['service_area_id'] ?? 0);
        if ($serviceId <= 0 || $areaId <= 0) {
            continue;
        }
        if (!isset($serviceToAreaIds[$serviceId])) {
            $serviceToAreaIds[$serviceId] = [];
        }
        if (!isset($areaToServiceIds[$areaId])) {
            $areaToServiceIds[$areaId] = [];
        }
        if (!in_array($areaId, $serviceToAreaIds[$serviceId], true)) {
            $serviceToAreaIds[$serviceId][] = $areaId;
        }
        if (!in_array($serviceId, $areaToServiceIds[$areaId], true)) {
            $areaToServiceIds[$areaId][] = $serviceId;
        }
    }
}

// =========================================================
// معالجة الإجراءات (POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $storeId = (int) post('store_id');
        if ($storeId <= 0 || !db()->count('stores', 'id = ?', [$storeId])) {
            setFlashMessage('danger', 'يرجى اختيار متجر صحيح.');
            redirect('spare-parts.php');
        }

        $categoryId = (int) post('category_id');
        if ($categoryId <= 0 || !db()->count('service_categories', 'id = ?', [$categoryId])) {
            setFlashMessage('danger', 'يرجى اختيار قسم خدمة صحيح لقطعة الغيار.');
            if ($postAction === 'edit') {
                $partId = (int) post('id');
                redirect('spare-parts.php?action=edit&id=' . $partId);
            }
            redirect('spare-parts.php');
        }

        $selectedServiceIds = sparePartNormalizeIds($_POST['service_ids'] ?? []);
        if (empty($selectedServiceIds)) {
            setFlashMessage('danger', 'يرجى اختيار خدمة واحدة على الأقل.');
            redirect('spare-parts.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $selectedServiceAreaIds = sparePartNormalizeIds($_POST['service_area_ids'] ?? []);
        if (empty($selectedServiceAreaIds)) {
            setFlashMessage('danger', 'يرجى اختيار منطقة خدمة واحدة على الأقل.');
            redirect('spare-parts.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $servicePlaceholders = implode(',', array_fill(0, count($selectedServiceIds), '?'));
        $validServiceRows = db()->fetchAll(
            "SELECT id FROM services WHERE is_active = 1 AND id IN ({$servicePlaceholders})",
            $selectedServiceIds
        );
        if (count($validServiceRows) !== count($selectedServiceIds)) {
            setFlashMessage('danger', 'من فضلك اختر خدمات مفعلة وصحيحة فقط.');
            redirect('spare-parts.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $areaPlaceholders = implode(',', array_fill(0, count($selectedServiceAreaIds), '?'));
        $validAreaRows = db()->fetchAll(
            "SELECT id FROM service_areas WHERE is_active = 1 AND id IN ({$areaPlaceholders})",
            $selectedServiceAreaIds
        );
        if (count($validAreaRows) !== count($selectedServiceAreaIds)) {
            setFlashMessage('danger', 'من فضلك اختر مناطق خدمة مفعلة وصحيحة فقط.');
            redirect('spare-parts.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
        }

        $allowedAreaIdsFromServices = sparePartScopeResolveServiceLinkedAreaIds($selectedServiceIds, true);
        if (!empty($allowedAreaIdsFromServices)) {
            $allowedAreaSet = array_fill_keys($allowedAreaIdsFromServices, true);
            foreach ($selectedServiceAreaIds as $areaId) {
                if (empty($allowedAreaSet[$areaId])) {
                    setFlashMessage('danger', 'يوجد منطقة غير مرتبطة بالخدمات المحددة.');
                    redirect('spare-parts.php' . ($postAction === 'edit' ? '?action=edit&id=' . (int) post('id') : ''));
                }
            }
        }

        $priceWithInstallation = (float) post('price_with_installation', post('price'));
        $priceWithoutInstallationRaw = post('price_without_installation');
        $priceWithoutInstallation = $priceWithoutInstallationRaw !== ''
            ? (float) $priceWithoutInstallationRaw
            : $priceWithInstallation;

        $oldPriceWithInstallationRaw = post('old_price_with_installation', post('old_price'));
        $oldPriceWithInstallation = $oldPriceWithInstallationRaw !== ''
            ? (float) $oldPriceWithInstallationRaw
            : null;

        $oldPriceWithoutInstallationRaw = post('old_price_without_installation');
        $oldPriceWithoutInstallation = $oldPriceWithoutInstallationRaw !== ''
            ? (float) $oldPriceWithoutInstallationRaw
            : $oldPriceWithInstallation;

        $data = [
            'store_id' => $storeId,
            'category_id' => $categoryId,
            'name_ar' => clean(post('name_ar')),
            'name_en' => clean(post('name_en')),
            'name_ur' => clean(post('name_ur')),
            'description_ar' => clean(post('description_ar')),
            'description_en' => clean(post('description_en')),
            'description_ur' => clean(post('description_ur')),
            'warranty_duration' => clean(post('warranty_duration')),
            'warranty_terms' => clean(post('warranty_terms')),
            'price' => $priceWithInstallation,
            'old_price' => $oldPriceWithInstallation,
            'price_with_installation' => $priceWithInstallation,
            'price_without_installation' => $priceWithoutInstallation,
            'old_price_with_installation' => $oldPriceWithInstallation,
            'old_price_without_installation' => $oldPriceWithoutInstallation,
            'stock_quantity' => (int) post('stock_quantity'),
            'sort_order' => (int) post('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        // رفع الصورة
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'spare_parts');
            if ($upload['success']) {
                $data['image'] = $upload['path'];
            }
        }

        if ($postAction === 'add') {
            $partId = (int) db()->insert('spare_parts', $data);
            sparePartScopeSyncServices($partId, $selectedServiceIds);
            sparePartScopeSyncAreas($partId, $selectedServiceAreaIds);
            logActivity('add_spare_part', 'spare_parts', $partId);
            setFlashMessage('success', 'تم إضافة قطعة الغيار بنجاح');
        } else {
            $partId = (int) post('id');
            db()->update('spare_parts', $data, 'id = ?', [$partId]);
            sparePartScopeSyncServices($partId, $selectedServiceIds);
            sparePartScopeSyncAreas($partId, $selectedServiceAreaIds);
            logActivity('update_spare_part', 'spare_parts', $partId);
            setFlashMessage('success', 'تم تحديث البيانات بنجاح');
        }

        redirect('spare-parts.php');
    }

    if ($postAction === 'delete') {
        $partId = (int) post('id');
        db()->delete('spare_part_services', 'spare_part_id = ?', [$partId]);
        db()->delete('spare_part_service_areas', 'spare_part_id = ?', [$partId]);
        db()->delete('spare_parts', 'id = ?', [$partId]);
        db()->delete('store_spare_part_movements', 'spare_part_id = ?', [$partId]);
        logActivity('delete_spare_part', 'spare_parts', $partId);
        setFlashMessage('success', 'تم الحذف بنجاح');
        redirect('spare-parts.php');
    }
}

// عرض البيانات
$whereClauses = [];
$params = [];

if ($storeFilterId > 0) {
    $whereClauses[] = 'sp.store_id = ?';
    $params[] = $storeFilterId;
}
if ($serviceFilterId > 0) {
    $whereClauses[] = 'EXISTS (SELECT 1 FROM spare_part_services sps WHERE sps.spare_part_id = sp.id AND sps.service_id = ?)';
    $params[] = $serviceFilterId;
}
if ($areaFilterId > 0) {
    $whereClauses[] = 'EXISTS (SELECT 1 FROM spare_part_service_areas spsa WHERE spsa.spare_part_id = sp.id AND spsa.service_area_id = ?)';
    $params[] = $areaFilterId;
}

$whereSql = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

$parts = db()->fetchAll(
    "SELECT sp.*, s.name_ar AS store_name, c.name_ar AS category_name
     FROM spare_parts sp
     LEFT JOIN stores s ON sp.store_id = s.id
     LEFT JOIN service_categories c ON sp.category_id = c.id
     {$whereSql}
     ORDER BY sp.id DESC",
    $params
);

$partServiceNamesByPart = [];
$partAreaNamesByPart = [];
if (!empty($parts)) {
    $partIds = [];
    foreach ($parts as $partRow) {
        $partId = (int) ($partRow['id'] ?? 0);
        if ($partId > 0) {
            $partIds[] = $partId;
        }
    }
    $partIds = array_values(array_unique($partIds));
    if (!empty($partIds)) {
        $partPlaceholders = implode(',', array_fill(0, count($partIds), '?'));

        $serviceLinkRows = db()->fetchAll(
            "SELECT sps.spare_part_id, sps.service_id, s.name_ar, s.category_id, c.name_ar AS category_name
             FROM spare_part_services sps
             JOIN services s ON s.id = sps.service_id
             LEFT JOIN service_categories c ON c.id = s.category_id
             WHERE sps.spare_part_id IN ({$partPlaceholders})
             ORDER BY sps.id ASC",
            $partIds
        );
        foreach ($serviceLinkRows as $row) {
            $partId = (int) ($row['spare_part_id'] ?? 0);
            if ($partId <= 0) {
                continue;
            }
            if (!isset($partServiceNamesByPart[$partId])) {
                $partServiceNamesByPart[$partId] = [];
            }
            $serviceId = (int) ($row['service_id'] ?? 0);
            $serviceName = trim((string) ($row['name_ar'] ?? ''));
            if ($serviceName === '') {
                $serviceName = 'خدمة #' . $serviceId;
            }
            $categoryLabel = trim((string) ($serviceCategoryDisplayMap[(int) ($row['category_id'] ?? 0)] ?? ($row['category_name'] ?? '')));
            $label = $serviceName . ($categoryLabel !== '' ? (' - ' . $categoryLabel) : '');
            if (!in_array($label, $partServiceNamesByPart[$partId], true)) {
                $partServiceNamesByPart[$partId][] = $label;
            }
        }

        $areaLinkRows = db()->fetchAll(
            "SELECT spsa.spare_part_id, sa.id AS area_id, sa.name, sa.country_code, sa.city_name, sa.village_name
             FROM spare_part_service_areas spsa
             JOIN service_areas sa ON sa.id = spsa.service_area_id
             WHERE spsa.spare_part_id IN ({$partPlaceholders})
             ORDER BY spsa.id ASC",
            $partIds
        );
        foreach ($areaLinkRows as $row) {
            $partId = (int) ($row['spare_part_id'] ?? 0);
            if ($partId <= 0) {
                continue;
            }
            if (!isset($partAreaNamesByPart[$partId])) {
                $partAreaNamesByPart[$partId] = [];
            }
            $areaId = (int) ($row['area_id'] ?? 0);
            $areaLabel = $serviceAreaOptions[$areaId] ?? sparePartAreaDisplayName($row);
            if ($areaLabel !== '' && !in_array($areaLabel, $partAreaNamesByPart[$partId], true)) {
                $partAreaNamesByPart[$partId][] = $areaLabel;
            }
        }
    }
}

if ($action === 'edit' && $id) {
    $part = db()->fetch("SELECT * FROM spare_parts WHERE id = ?", [$id]);
    if (!$part) {
        redirect('spare-parts.php');
    }

    $selectedServiceRows = db()->fetchAll(
        "SELECT service_id FROM spare_part_services WHERE spare_part_id = ? ORDER BY service_id ASC",
        [$id]
    );
    $selectedServiceIds = [];
    foreach ($selectedServiceRows as $selectedServiceRow) {
        $serviceId = (int) ($selectedServiceRow['service_id'] ?? 0);
        if ($serviceId > 0) {
            $selectedServiceIds[] = $serviceId;
        }
    }
    $selectedServiceIds = array_values(array_unique($selectedServiceIds));

    $selectedAreaRows = db()->fetchAll(
        "SELECT service_area_id FROM spare_part_service_areas WHERE spare_part_id = ? ORDER BY service_area_id ASC",
        [$id]
    );
    $selectedServiceAreaIds = [];
    foreach ($selectedAreaRows as $selectedAreaRow) {
        $areaId = (int) ($selectedAreaRow['service_area_id'] ?? 0);
        if ($areaId > 0) {
            $selectedServiceAreaIds[] = $areaId;
        }
    }
    $selectedServiceAreaIds = array_values(array_unique($selectedServiceAreaIds));
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <?php if (empty($stores)): ?>
        <div class="card" style="margin-bottom: 20px; border-color: #fca5a5;">
            <div class="card-body">
                <strong style="color: #b91c1c;">لا توجد متاجر نشطة.</strong>
                <p style="margin: 8px 0 0; color: #7f1d1d;">أضف متجرًا أولًا من صفحة المتاجر ثم أضف قطع الغيار التابعة له.</p>
            </div>
        </div>
    <?php endif; ?>
    <?php if (empty($serviceCategories)): ?>
        <div class="card" style="margin-bottom: 20px; border-color: #fca5a5;">
            <div class="card-body">
                <strong style="color: #b91c1c;">لا توجد أقسام خدمات متاحة.</strong>
                <p style="margin: 8px 0 0; color: #7f1d1d;">أضف قسم خدمة أولاً من صفحة الأقسام ثم اربط قطع الغيار به.</p>
            </div>
        </div>
    <?php endif; ?>
    <?php if (empty($serviceOptions)): ?>
        <div class="card" style="margin-bottom: 20px; border-color: #fca5a5;">
            <div class="card-body">
                <strong style="color: #b91c1c;">لا توجد خدمات مفعلة.</strong>
                <p style="margin: 8px 0 0; color: #7f1d1d;">فعّل خدمات أولًا من صفحة الخدمات قبل إضافة قطع الغيار.</p>
            </div>
        </div>
    <?php endif; ?>
    <?php if (empty($serviceAreaOptions)): ?>
        <div class="card" style="margin-bottom: 20px; border-color: #fca5a5;">
            <div class="card-body">
                <strong style="color: #b91c1c;">لا توجد مناطق خدمة مفعلة.</strong>
                <p style="margin: 8px 0 0; color: #7f1d1d;">أضف مناطق من صفحة مناطق تقديم الخدمة قبل إضافة قطع الغيار.</p>
            </div>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
        <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select name="store_id" class="form-control" style="min-width: 200px;" onchange="this.form.submit()">
                <option value="">كل المتاجر</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?php echo $store['id']; ?>" <?php echo $storeFilterId === (int) $store['id'] ? 'selected' : ''; ?>>
                        <?php echo $store['name_ar']; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="service_id" class="form-control" style="min-width: 220px;" onchange="this.form.submit()">
                <option value="">كل الخدمات</option>
                <?php foreach ($serviceOptions as $serviceId => $serviceName): ?>
                    <option value="<?php echo (int) $serviceId; ?>" <?php echo $serviceFilterId === (int) $serviceId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="service_area_id" class="form-control" style="min-width: 220px;" onchange="this.form.submit()">
                <option value="">كل المناطق</option>
                <?php foreach ($serviceAreaOptions as $areaId => $areaName): ?>
                    <option value="<?php echo (int) $areaId; ?>" <?php echo $areaFilterId === (int) $areaId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($storeFilterId > 0 || $serviceFilterId > 0 || $areaFilterId > 0): ?>
                <a href="spare-parts.php" class="btn btn-outline">إلغاء الفلترة</a>
            <?php endif; ?>
        </form>

        <button onclick="showModal('add-modal')" class="btn btn-primary" <?php echo (empty($stores) || empty($serviceCategories) || empty($serviceOptions) || empty($serviceAreaOptions)) ? 'disabled' : ''; ?>>
            <i class="fas fa-plus"></i> إضافة قطعة جديدة
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-cogs"></i> قطع الغيار</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>الاسم</th>
                            <th>القسم</th>
                            <th>الخدمات</th>
                            <th>المناطق</th>
                            <th>المتجر</th>
                            <th>السعر مع التركيب</th>
                            <th>السعر بدون تركيب</th>
                            <th>الكمية</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parts as $p): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="<?php echo imageUrl($p['image']); ?>" alt=""
                                            style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $p['name_ar']; ?></strong><br>
                                    <small class="text-muted"><?php echo $p['name_en']; ?></small>
                                    <?php if (!empty($p['name_ur'])): ?>
                                        <br>
                                        <small class="text-muted"><?php echo $p['name_ur']; ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($p['warranty_duration'])): ?>
                                        <br>
                                        <small class="text-muted">الضمان: <?php echo htmlspecialchars((string) $p['warranty_duration'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $categoryId = (int) ($p['category_id'] ?? 0);
                                    if ($categoryId > 0 && !empty($serviceCategoryDisplayMap[$categoryId])) {
                                        echo htmlspecialchars($serviceCategoryDisplayMap[$categoryId], ENT_QUOTES, 'UTF-8');
                                    } elseif (!empty($p['category_name'])) {
                                        echo htmlspecialchars((string) $p['category_name'], ENT_QUOTES, 'UTF-8');
                                    } else {
                                        echo '<span class="text-muted">غير محدد</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php $partServiceNames = $partServiceNamesByPart[(int) ($p['id'] ?? 0)] ?? []; ?>
                                    <?php if (empty($partServiceNames)): ?>
                                        <span class="text-muted">كل الخدمات</span>
                                    <?php else: ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <?php foreach (array_slice($partServiceNames, 0, 2) as $serviceName): ?>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($partServiceNames) > 2): ?>
                                                <span class="badge badge-secondary">+<?php echo count($partServiceNames) - 2; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $partAreaNames = $partAreaNamesByPart[(int) ($p['id'] ?? 0)] ?? []; ?>
                                    <?php if (empty($partAreaNames)): ?>
                                        <span class="text-muted">كل المناطق</span>
                                    <?php else: ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <?php foreach (array_slice($partAreaNames, 0, 2) as $areaName): ?>
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($partAreaNames) > 2): ?>
                                                <span class="badge badge-info">+<?php echo count($partAreaNames) - 2; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $p['store_name'] ?: '<span class="text-muted">غير محدد</span>'; ?>
                                </td>
                                <td>
                                    <?php
                                    $priceWithInstallation = isset($p['price_with_installation']) && (float) $p['price_with_installation'] > 0
                                        ? (float) $p['price_with_installation']
                                        : (float) $p['price'];
                                    $oldPriceWithInstallation = $p['old_price_with_installation'] ?? $p['old_price'] ?? null;
                                    ?>
                                    <?php echo number_format($priceWithInstallation, 2); ?> ⃁
                                    <?php if (!empty($oldPriceWithInstallation) && (float) $oldPriceWithInstallation > $priceWithInstallation): ?>
                                        <div style="font-size: 12px; text-decoration: line-through; color: #9ca3af;">
                                            <?php echo number_format((float) $oldPriceWithInstallation, 2); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $priceWithoutInstallation = isset($p['price_without_installation']) && (float) $p['price_without_installation'] > 0
                                        ? (float) $p['price_without_installation']
                                        : $priceWithInstallation;
                                    $oldPriceWithoutInstallation = $p['old_price_without_installation'] ?? $oldPriceWithInstallation;
                                    ?>
                                    <?php echo number_format($priceWithoutInstallation, 2); ?> ⃁
                                    <?php if (!empty($oldPriceWithoutInstallation) && (float) $oldPriceWithoutInstallation > $priceWithoutInstallation): ?>
                                        <div style="font-size: 12px; text-decoration: line-through; color: #9ca3af;">
                                            <?php echo number_format((float) $oldPriceWithoutInstallation, 2); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $p['stock_quantity'] > 0 ? 'badge-info' : 'badge-danger'; ?>">
                                        <?php echo $p['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $p['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $p['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline"><i
                                                class="fas fa-edit"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i
                                                    class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($parts)): ?>
                            <tr>
                                <td colspan="11" class="text-center">لا توجد بيانات</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- مودال الإضافة -->
    <div class="modal-overlay" id="add-modal">
        <div class="modal" style="width: 700px; max-width: 95%;">
            <div class="modal-header">
                <h3 class="modal-title">إضافة قطعة غيار</h3>
                <button class="modal-close" onclick="hideModal('add-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="spare-part-add-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="form-group">
                        <label class="form-label">المتجر</label>
                        <select name="store_id" class="form-control" required>
                            <option value="">اختر المتجر</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store['id']; ?>"><?php echo $store['name_ar']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">القسم</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">اختر القسم</option>
                            <?php foreach ($serviceCategories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['display_name_ar'] ?? $category['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الخدمات المرتبطة *</label>
                        <select name="service_ids[]" class="form-control js-spare-services" multiple size="5" required>
                            <?php foreach ($serviceOptions as $serviceId => $serviceName): ?>
                                <option value="<?php echo (int) $serviceId; ?>">
                                    <?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">يمكن اختيار أكثر من خدمة.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">مناطق الخدمة *</label>
                        <select name="service_area_ids[]" class="form-control js-spare-areas" multiple size="6" required>
                            <?php foreach ($serviceAreaOptions as $areaId => $areaName): ?>
                                <?php $areaServiceIds = $areaToServiceIds[(int) $areaId] ?? []; ?>
                                <option
                                    value="<?php echo (int) $areaId; ?>"
                                    data-service-ids="<?php echo htmlspecialchars(implode(',', $areaServiceIds), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">المناطق ستُفلتر تلقائياً حسب الخدمات المختارة.</small>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">الاسم (عربي)</label>
                            <input type="text" name="name_ar" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الاسم (إنجليزي)</label>
                            <input type="text" name="name_en" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الاسم (أوردو)</label>
                        <input type="text" name="name_ur" class="form-control">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">السعر مع التركيب</label>
                            <input type="number" step="0.01" name="price_with_installation" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">السعر بدون تركيب</label>
                            <input type="number" step="0.01" name="price_without_installation" class="form-control"
                                placeholder="إذا فارغًا سيتم استخدام سعر مع التركيب">
                        </div>
                        <div class="form-group">
                            <label class="form-label">السعر القديم مع التركيب</label>
                            <input type="number" step="0.01" name="old_price_with_installation" class="form-control"
                                placeholder="اتركه فارغاً إذا لم يكن هناك خصم">
                        </div>
                        <div class="form-group">
                            <label class="form-label">السعر القديم بدون تركيب</label>
                            <input type="number" step="0.01" name="old_price_without_installation" class="form-control"
                                placeholder="اختياري">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكمية المتوفرة</label>
                            <input type="number" name="stock_quantity" class="form-control" value="0">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">مدة الضمان</label>
                            <input type="text" name="warranty_duration" class="form-control" placeholder="مثال: 30 يوم أو سنة">
                        </div>
                        <div class="form-group">
                            <label class="form-label">شروط الضمان</label>
                            <textarea name="warranty_terms" class="form-control" rows="2" placeholder="مثال: الضمان لا يشمل سوء الاستخدام"></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الوصف (عربي)</label>
                        <textarea name="description_ar" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف (إنجليزي)</label>
                        <textarea name="description_en" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف (أوردو)</label>
                        <textarea name="description_ur" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">صورة القطعة</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label class="form-label">تفعيل</label>
                        <input type="checkbox" name="is_active" checked>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'edit' && isset($part)): ?>

    <div class="card animate-slideUp" style="max-width: 860px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">تعديل: <?php echo $part['name_ar']; ?></h3>
        </div>
        <form method="POST" enctype="multipart/form-data" id="spare-part-edit-form">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $part['id']; ?>">

                <div class="form-group">
                    <label class="form-label">المتجر</label>
                    <select name="store_id" class="form-control" required>
                        <option value="">اختر المتجر</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>" <?php echo (int) $part['store_id'] === (int) $store['id'] ? 'selected' : ''; ?>>
                                <?php echo $store['name_ar']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">القسم</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">اختر القسم</option>
                        <?php foreach ($serviceCategories as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php echo (int) ($part['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['display_name_ar'] ?? $category['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">الخدمات المرتبطة *</label>
                    <select name="service_ids[]" class="form-control js-spare-services" multiple size="6" required>
                        <?php foreach ($serviceOptions as $serviceId => $serviceName): ?>
                            <option value="<?php echo (int) $serviceId; ?>" <?php echo in_array((int) $serviceId, $selectedServiceIds, true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6b7280;">يمكن اختيار أكثر من خدمة.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">مناطق الخدمة *</label>
                    <select name="service_area_ids[]" class="form-control js-spare-areas" multiple size="6" required>
                        <?php foreach ($serviceAreaOptions as $areaId => $areaName): ?>
                            <?php $areaServiceIds = $areaToServiceIds[(int) $areaId] ?? []; ?>
                            <option
                                value="<?php echo (int) $areaId; ?>"
                                data-service-ids="<?php echo htmlspecialchars(implode(',', $areaServiceIds), ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo in_array((int) $areaId, $selectedServiceAreaIds, true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6b7280;">المناطق ستُفلتر تلقائياً حسب الخدمات المختارة.</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الاسم (عربي)</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo $part['name_ar']; ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم (إنجليزي)</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo $part['name_en']; ?>"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم (أوردو)</label>
                    <input type="text" name="name_ur" class="form-control" value="<?php echo $part['name_ur'] ?? ''; ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">السعر مع التركيب</label>
                        <input type="number" step="0.01" name="price_with_installation" class="form-control"
                            value="<?php echo isset($part['price_with_installation']) && (float) $part['price_with_installation'] > 0
                                ? $part['price_with_installation']
                                : $part['price']; ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعر بدون تركيب</label>
                        <input type="number" step="0.01" name="price_without_installation" class="form-control"
                            value="<?php echo isset($part['price_without_installation']) && (float) $part['price_without_installation'] > 0
                                ? $part['price_without_installation']
                                : (isset($part['price_with_installation']) && (float) $part['price_with_installation'] > 0 ? $part['price_with_installation'] : $part['price']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعر القديم مع التركيب</label>
                        <input type="number" step="0.01" name="old_price_with_installation" class="form-control"
                            value="<?php echo $part['old_price_with_installation'] ?? $part['old_price'] ?? ''; ?>"
                            placeholder="اتركه فارغاً إذا لم يكن هناك خصم">
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعر القديم بدون تركيب</label>
                        <input type="number" step="0.01" name="old_price_without_installation" class="form-control"
                            value="<?php echo $part['old_price_without_installation'] ?? $part['old_price_with_installation'] ?? $part['old_price'] ?? ''; ?>"
                            placeholder="اختياري">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الكمية المتوفرة</label>
                        <input type="number" name="stock_quantity" class="form-control"
                            value="<?php echo $part['stock_quantity'] ?? 0; ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">مدة الضمان</label>
                        <input type="text" name="warranty_duration" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($part['warranty_duration'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="مثال: 30 يوم أو سنة">
                    </div>
                    <div class="form-group">
                        <label class="form-label">شروط الضمان</label>
                        <textarea name="warranty_terms" class="form-control"
                            rows="2"
                            placeholder="مثال: الضمان لا يشمل سوء الاستخدام"><?php echo htmlspecialchars((string) ($part['warranty_terms'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الترتيب</label>
                    <input type="number" name="sort_order" class="form-control"
                        value="<?php echo (int) ($part['sort_order'] ?? 0); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف (عربي)</label>
                    <textarea name="description_ar" class="form-control"
                        rows="3"><?php echo $part['description_ar']; ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف (إنجليزي)</label>
                    <textarea name="description_en" class="form-control"
                        rows="3"><?php echo $part['description_en']; ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف (أوردو)</label>
                    <textarea name="description_ur" class="form-control"
                        rows="3"><?php echo $part['description_ur'] ?? ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">صورة القطعة</label>
                    <?php if (!empty($part['image'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo imageUrl($part['image']); ?>" alt=""
                                style="width: 100px; object-fit: cover; border-radius: 5px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">تفعيل</label>
                    <input type="checkbox" name="is_active" <?php echo $part['is_active'] ? 'checked' : ''; ?>>
                </div>
            </div>
            <div class="card-footer">
                <a href="spare-parts.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>

<?php endif; ?>

<script>
    (function() {
        function initSparePartScopeForm(form) {
            if (!form) return;
            var servicesSelect = form.querySelector('.js-spare-services');
            var areasSelect = form.querySelector('.js-spare-areas');
            if (!servicesSelect || !areasSelect) return;

            function selectedServiceIds() {
                return Array.from(servicesSelect.selectedOptions).map(function(option) {
                    return String(option.value);
                });
            }

            function shouldShowArea(areaOption, activeServiceIds) {
                var linkedRaw = String(areaOption.getAttribute('data-service-ids') || '').trim();
                if (linkedRaw === '') {
                    return true;
                }
                if (activeServiceIds.length === 0) {
                    return true;
                }
                var linkedServiceIds = linkedRaw.split(',').map(function(value) {
                    return value.trim();
                }).filter(Boolean);
                var hasMatch = linkedServiceIds.some(function(serviceId) {
                    return activeServiceIds.indexOf(serviceId) !== -1;
                });
                if (hasMatch) return true;
                return areaOption.selected === true;
            }

            function refreshAreas() {
                var activeServiceIds = selectedServiceIds();
                Array.from(areasSelect.options).forEach(function(option) {
                    var visible = shouldShowArea(option, activeServiceIds);
                    option.hidden = !visible;
                    if (!visible) {
                        option.selected = false;
                    }
                });
            }

            servicesSelect.addEventListener('change', refreshAreas);
            refreshAreas();
        }

        document.addEventListener('DOMContentLoaded', function() {
            initSparePartScopeForm(document.getElementById('spare-part-add-form'));
            initSparePartScopeForm(document.getElementById('spare-part-edit-form'));
        });
    })();
</script>

<?php include '../includes/footer.php'; ?>
