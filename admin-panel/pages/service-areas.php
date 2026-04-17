<?php
/**
 * Service Areas Management
 * إدارة مناطق ونطاقات تقديم الخدمة
 */

require_once '../init.php';
requireLogin();
require_once '../includes/service_areas.php';

$admin = getCurrentAdmin();
if (
    !hasPermission('settings')
    && !hasPermission('services')
    && !hasPermission('orders')
    && ($admin['role'] ?? '') !== 'super_admin'
) {
    die('صلاحيات غير كافية للوصول إلى إدارة مناطق الخدمة.');
}

serviceAreaEnsureSchema();
serviceAreaEnsureServiceLinksSchema();

$pageTitle = 'مناطق تقديم الخدمة';
$pageSubtitle = 'تحديد نطاقات الخدمة حسب GPS والدولة والمدينة';

function serviceAreaCountryOptions(array $enabledCountries = []): array
{
    $default = [
        'SA' => 'السعودية',
        'AE' => 'الإمارات',
        'KW' => 'الكويت',
        'QA' => 'قطر',
        'BH' => 'البحرين',
        'OM' => 'عُمان',
        'EG' => 'مصر',
        'JO' => 'الأردن',
        'IQ' => 'العراق',
        'YE' => 'اليمن',
    ];

    $options = [];
    if (serviceAreaTableExists('countries')) {
        $rows = db()->fetchAll("SELECT code, name_ar, name_en FROM countries WHERE is_active = 1 ORDER BY name_ar ASC");
        foreach ($rows as $row) {
            $code = serviceAreaNormalizeCountryCode($row['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $label = trim((string) ($row['name_ar'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['name_en'] ?? ''));
            }
            if ($label === '') {
                $label = $code;
            }
            $options[$code] = $label;
        }
    }

    if (empty($options)) {
        $options = $default;
    }

    // Keep fallback labels available for any country code.
    foreach ($default as $code => $label) {
        if (!isset($options[$code])) {
            $options[$code] = $label;
        }
    }

    $enabledCountries = array_values(array_unique(array_filter(array_map(
        'serviceAreaNormalizeCountryCode',
        $enabledCountries
    ))));

    // If admin enabled countries in settings, show only these here.
    if (!empty($enabledCountries)) {
        $filtered = [];
        foreach ($enabledCountries as $code) {
            $filtered[$code] = $options[$code] ?? ($default[$code] ?? $code);
        }
        if (!empty($filtered)) {
            return $filtered;
        }
    }

    ksort($options);
    return $options;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save_area') {
        $editId = (int) ($_POST['edit_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $nameEn = trim((string) ($_POST['name_en'] ?? ''));
        $nameUr = trim((string) ($_POST['name_ur'] ?? ''));
        $countryCode = serviceAreaNormalizeCountryCode($_POST['country_code'] ?? '');
        $cityName = trim((string) ($_POST['city_name'] ?? ''));
        $cityNameEn = trim((string) ($_POST['city_name_en'] ?? ''));
        $cityNameUr = trim((string) ($_POST['city_name_ur'] ?? ''));
        $villageName = trim((string) ($_POST['village_name'] ?? ''));
        $villageNameEn = trim((string) ($_POST['village_name_en'] ?? ''));
        $villageNameUr = trim((string) ($_POST['village_name_ur'] ?? ''));
        $geometryType = strtolower(trim((string) ($_POST['geometry_type'] ?? 'circle')));
        $centerLat = is_numeric($_POST['center_lat'] ?? null) ? (float) $_POST['center_lat'] : null;
        $centerLng = is_numeric($_POST['center_lng'] ?? null) ? (float) $_POST['center_lng'] : null;
        $radiusKm = is_numeric($_POST['radius_km'] ?? null) ? (float) $_POST['radius_km'] : 0.0;
        $priority = (int) ($_POST['priority'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $polygonRaw = trim((string) ($_POST['polygon_json'] ?? ''));
        $polygonPoints = serviceAreaDecodePolygon($polygonRaw);

        if ($name === '') {
            setFlashMessage('danger', 'اسم المنطقة مطلوب');
            redirect('service-areas.php' . ($editId > 0 ? '?action=edit&id=' . $editId : ''));
        }

        if ($countryCode === '') {
            setFlashMessage('danger', 'يرجى اختيار الدولة');
            redirect('service-areas.php' . ($editId > 0 ? '?action=edit&id=' . $editId : ''));
        }

        if (!in_array($geometryType, ['circle', 'polygon'], true)) {
            $geometryType = 'circle';
        }

        if ($geometryType === 'polygon') {
            if (count($polygonPoints) < 3) {
                setFlashMessage('danger', 'يرجى رسم مضلع صالح (3 نقاط على الأقل)');
                redirect('service-areas.php' . ($editId > 0 ? '?action=edit&id=' . $editId : ''));
            }

            $polygonRaw = json_encode($polygonPoints, JSON_UNESCAPED_UNICODE);

            if ($centerLat === null || $centerLng === null) {
                $center = serviceAreaComputeCenter(['polygon_json' => $polygonRaw]);
                if ($center !== null) {
                    $centerLat = (float) $center['lat'];
                    $centerLng = (float) $center['lng'];
                }
            }

            if ($radiusKm <= 0 && $centerLat !== null && $centerLng !== null) {
                $maxDistance = 0.0;
                foreach ($polygonPoints as $point) {
                    $distance = serviceAreaDistanceKm(
                        $centerLat,
                        $centerLng,
                        (float) $point['lat'],
                        (float) $point['lng']
                    );
                    if ($distance > $maxDistance) {
                        $maxDistance = $distance;
                    }
                }
                $radiusKm = $maxDistance > 0 ? $maxDistance : 1.0;
            }
        } else {
            $polygonRaw = null;
            if ($centerLat === null || $centerLng === null) {
                setFlashMessage('danger', 'يرجى تحديد مركز المنطقة على الخريطة');
                redirect('service-areas.php' . ($editId > 0 ? '?action=edit&id=' . $editId : ''));
            }
            if ($radiusKm <= 0) {
                setFlashMessage('danger', 'يرجى إدخال نصف قطر صالح للمنطقة');
                redirect('service-areas.php' . ($editId > 0 ? '?action=edit&id=' . $editId : ''));
            }
        }

        if ($centerLat !== null && ($centerLat < -90 || $centerLat > 90)) {
            setFlashMessage('danger', 'خط العرض غير صالح');
            redirect('service-areas.php' . ($editId > 0 ? '?action=edit&id=' . $editId : ''));
        }
        if ($centerLng !== null && ($centerLng < -180 || $centerLng > 180)) {
            setFlashMessage('danger', 'خط الطول غير صالح');
            redirect('service-areas.php' . ($editId > 0 ? '?action=edit&id=' . $editId : ''));
        }

        $data = [
            'name' => $name,
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'name_ur' => $nameUr !== '' ? $nameUr : null,
            'country_code' => $countryCode,
            'city_name' => $cityName !== '' ? $cityName : null,
            'city_name_en' => $cityNameEn !== '' ? $cityNameEn : null,
            'city_name_ur' => $cityNameUr !== '' ? $cityNameUr : null,
            'village_name' => $villageName !== '' ? $villageName : null,
            'village_name_en' => $villageNameEn !== '' ? $villageNameEn : null,
            'village_name_ur' => $villageNameUr !== '' ? $villageNameUr : null,
            'geometry_type' => $geometryType,
            'center_lat' => $centerLat,
            'center_lng' => $centerLng,
            'radius_km' => $radiusKm > 0 ? $radiusKm : null,
            'polygon_json' => $polygonRaw,
            'priority' => $priority,
            'notes' => $notes !== '' ? $notes : null,
            'is_active' => $isActive,
            'updated_by' => (int) ($_SESSION['admin_id'] ?? 0),
        ];

        if ($editId > 0) {
            db()->update('service_areas', $data, 'id = :id', ['id' => $editId]);
            logActivity('update_service_area', 'service_areas', $editId);
            setFlashMessage('success', 'تم تحديث منطقة الخدمة بنجاح');
            redirect('service-areas.php');
        }

        $data['created_by'] = (int) ($_SESSION['admin_id'] ?? 0);
        $newId = (int) db()->insert('service_areas', $data);
        logActivity('create_service_area', 'service_areas', $newId);
        setFlashMessage('success', 'تم إضافة منطقة الخدمة بنجاح');
        redirect('service-areas.php');
    }

    if ($postAction === 'delete_area') {
        $areaId = (int) ($_POST['area_id'] ?? 0);
        if ($areaId > 0) {
            $conn = db()->getConnection();
            try {
                $conn->beginTransaction();

                db()->delete('service_area_services', 'service_area_id = ?', [$areaId]);
                if (serviceAreaTableExists('spare_part_service_areas')) {
                    db()->delete('spare_part_service_areas', 'service_area_id = ?', [$areaId]);
                }
                db()->delete('service_areas', 'id = ?', [$areaId]);

                $conn->commit();
                logActivity('delete_service_area', 'service_areas', $areaId);
                setFlashMessage('success', 'تم حذف منطقة الخدمة');
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                setFlashMessage('danger', 'تعذر حذف منطقة الخدمة. تأكد من عدم ارتباطها ببيانات أخرى ثم أعد المحاولة.');
            }
        } else {
            setFlashMessage('danger', 'المعرف غير صالح');
        }
        redirect('service-areas.php');
    }
}

$action = get('action', 'list');
$editId = (int) get('id');
$editArea = null;
if ($action === 'edit' && $editId > 0) {
    $editArea = db()->fetch("SELECT * FROM service_areas WHERE id = ?", [$editId]);
    if (!$editArea) {
        setFlashMessage('danger', 'المنطقة غير موجودة');
        redirect('service-areas.php');
    }
}

$areas = db()->fetchAll("SELECT * FROM service_areas ORDER BY is_active DESC, priority ASC, id DESC");
$supportedCountries = serviceAreaSupportedCountries();
$countryOptions = serviceAreaCountryOptions($supportedCountries);

// Keep currently edited area country selectable even if it was later disabled from settings.
if ($editArea) {
    $editCountryCode = serviceAreaNormalizeCountryCode($editArea['country_code'] ?? '');
    if ($editCountryCode !== '' && !isset($countryOptions[$editCountryCode])) {
        $countryOptions[$editCountryCode] = $editCountryCode;
    }
}

$areasForMap = [];
$areasNearestProviders = [];
$insideProvidersCount = [];
$allNearestRows = [];
foreach ($areas as $row) {
    $summary = serviceAreaSummarizeArea($row);
    $summary['polygon'] = serviceAreaDecodePolygon($row['polygon_json'] ?? null);
    $summary['is_active'] = (int) ($row['is_active'] ?? 0) === 1;
    $summary['priority'] = (int) ($row['priority'] ?? 0);
    $summary['notes'] = $row['notes'] ?? '';
    $areasForMap[] = $summary;

    $nearest = serviceAreaNearestProvidersForArea($row, 5);
    $areasNearestProviders[(int) $row['id']] = $nearest;
    $insideCount = 0;
    foreach ($nearest as $provider) {
        if (!empty($provider['inside_area'])) {
            $insideCount++;
        }
    }
    $insideProvidersCount[(int) $row['id']] = $insideCount;
    $allNearestRows = array_merge($allNearestRows, $nearest);
}

$providersWithGpsCount = count(serviceAreaFetchProviders());
$areasCount = count($areas);
$activeAreasCount = count(array_filter($areas, fn($row) => (int) ($row['is_active'] ?? 0) === 1));

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">

<div class="stats-grid" style="margin-bottom: 22px;">
    <div class="stat-card animate-slideUp">
        <div class="stat-icon primary"><i class="fas fa-draw-polygon"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($areasCount); ?></h3>
            <p>إجمالي المناطق</p>
        </div>
    </div>
    <div class="stat-card animate-slideUp" style="animation-delay: 0.08s;">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($activeAreasCount); ?></h3>
            <p>المناطق النشطة</p>
        </div>
    </div>
    <div class="stat-card animate-slideUp" style="animation-delay: 0.16s;">
        <div class="stat-icon secondary"><i class="fas fa-globe-asia"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format(count($supportedCountries)); ?></h3>
            <p>الدول المفعلة من الإعدادات</p>
        </div>
    </div>
    <div class="stat-card animate-slideUp" style="animation-delay: 0.24s;">
        <div class="stat-icon warning"><i class="fas fa-user-tie"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($providersWithGpsCount); ?></h3>
            <p>مقدمو خدمة بإحداثيات</p>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1.2fr 1fr; gap: 20px; align-items: start;">
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-map-marked-alt" style="color: var(--secondary-color);"></i>
                <?php echo $editArea ? 'تعديل منطقة خدمة' : 'إضافة منطقة خدمة جديدة'; ?>
            </h3>
            <?php if ($editArea): ?>
                <a href="service-areas.php" class="btn btn-outline btn-sm">إلغاء التعديل</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" id="service-area-form">
                <input type="hidden" name="action" value="save_area">
                <input type="hidden" name="edit_id" value="<?php echo (int) ($editArea['id'] ?? 0); ?>">
                <input type="hidden" name="center_lat" id="center_lat" value="<?php echo htmlspecialchars((string) ($editArea['center_lat'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="center_lng" id="center_lng" value="<?php echo htmlspecialchars((string) ($editArea['center_lng'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="polygon_json" id="polygon_json" value="<?php echo htmlspecialchars((string) ($editArea['polygon_json'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">اسم المنطقة *</label>
                        <input
                            type="text"
                            class="form-control"
                            name="name"
                            required
                            maxlength="150"
                            placeholder="مثال: شمال الرياض - حي الياسمين"
                            value="<?php echo htmlspecialchars((string) ($editArea['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">اسم المنطقة (إنجليزي)</label>
                        <input
                            type="text"
                            class="form-control"
                            name="name_en"
                            maxlength="150"
                            placeholder="Example: North Riyadh - Al Yasmin"
                            value="<?php echo htmlspecialchars((string) ($editArea['name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">اسم المنطقة (أوردو)</label>
                        <input
                            type="text"
                            class="form-control"
                            name="name_ur"
                            maxlength="150"
                            placeholder="مثال: شمال الرياض - الياسمين"
                            value="<?php echo htmlspecialchars((string) ($editArea['name_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الدولة *</label>
                        <select class="form-control" name="country_code" id="country_code" required>
                            <option value="">اختر الدولة</option>
                            <?php foreach ($countryOptions as $code => $label): ?>
                                <?php $selected = serviceAreaNormalizeCountryCode($editArea['country_code'] ?? '') === $code ? 'selected' : ''; ?>
                                <option value="<?php echo $code; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $code; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">المدينة</label>
                        <input
                            type="text"
                            class="form-control"
                            name="city_name"
                            maxlength="120"
                            placeholder="مثال: الرياض"
                            value="<?php echo htmlspecialchars((string) ($editArea['city_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">المدينة (إنجليزي)</label>
                        <input
                            type="text"
                            class="form-control"
                            name="city_name_en"
                            maxlength="120"
                            placeholder="Example: Riyadh"
                            value="<?php echo htmlspecialchars((string) ($editArea['city_name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">المدينة (أوردو)</label>
                        <input
                            type="text"
                            class="form-control"
                            name="city_name_ur"
                            maxlength="120"
                            placeholder="مثال: الرياض"
                            value="<?php echo htmlspecialchars((string) ($editArea['city_name_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الحي / القرية</label>
                        <input
                            type="text"
                            class="form-control"
                            name="village_name"
                            maxlength="120"
                            placeholder="مثال: قرية العليا"
                            value="<?php echo htmlspecialchars((string) ($editArea['village_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الحي / القرية (إنجليزي)</label>
                        <input
                            type="text"
                            class="form-control"
                            name="village_name_en"
                            maxlength="120"
                            placeholder="Example: Al Olaya"
                            value="<?php echo htmlspecialchars((string) ($editArea['village_name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الحي / القرية (أوردو)</label>
                        <input
                            type="text"
                            class="form-control"
                            name="village_name_ur"
                            maxlength="120"
                            placeholder="مثال: قرية العليا"
                            value="<?php echo htmlspecialchars((string) ($editArea['village_name_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوع النطاق</label>
                        <select class="form-control" name="geometry_type" id="geometry_type">
                            <?php $geometryType = strtolower(trim((string) ($editArea['geometry_type'] ?? 'circle'))); ?>
                            <option value="circle" <?php echo $geometryType === 'circle' ? 'selected' : ''; ?>>دائرة (مركز + نصف قطر)</option>
                            <option value="polygon" <?php echo $geometryType === 'polygon' ? 'selected' : ''; ?>>مضلع (رسم حر)</option>
                        </select>
                    </div>

                    <div class="form-group" id="radius-group">
                        <label class="form-label">نصف القطر (كم)</label>
                        <input
                            type="number"
                            class="form-control"
                            name="radius_km"
                            id="radius_km"
                            step="0.1"
                            min="0.1"
                            value="<?php echo htmlspecialchars((string) ($editArea['radius_km'] ?? '5'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الأولوية (الأصغر أولًا)</label>
                        <input
                            type="number"
                            class="form-control"
                            name="priority"
                            min="0"
                            value="<?php echo (int) ($editArea['priority'] ?? 0); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <input
                            type="text"
                            class="form-control"
                            name="notes"
                            maxlength="255"
                            placeholder="أي ملاحظات تشغيلية للمنطقة"
                            value="<?php echo htmlspecialchars((string) ($editArea['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الخريطة (حدّد النطاق هنا)</label>
                    <div id="service-area-map"></div>
                    <small class="hint-text">
                        استخدم أدوات الرسم أعلى الخريطة:
                        <strong>Circle</strong> لتحديد مركز + نصف قطر، أو <strong>Polygon</strong> لرسم حدود منطقة مخصصة.
                    </small>
                    <small class="hint-text" id="map-status-hint" style="display:none;"></small>
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap: 8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" <?php echo (int) ($editArea['is_active'] ?? 1) === 1 ? 'checked' : ''; ?>>
                        تفعيل المنطقة فورًا
                    </label>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editArea ? 'تحديث المنطقة' : 'إضافة المنطقة'; ?>
                    </button>
                    <a href="settings.php#service-country-settings" class="btn btn-outline">
                        تعديل الدول من الإعدادات
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card animate-slideUp" style="animation-delay: 0.08s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-broadcast-tower" style="color: var(--primary-color);"></i>
                ملخص التغطية
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($supportedCountries)): ?>
                <div class="alert alert-warning">لم يتم تحديد دول مدعومة من إعدادات النظام.</div>
            <?php else: ?>
                <div class="country-badges">
                    <?php foreach ($supportedCountries as $countryCode): ?>
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr style="margin: 14px 0;">
            <h4 style="margin: 0 0 10px; font-size: 14px;">المناطق الحالية على الخريطة</h4>
            <?php if (empty($areas)): ?>
                <p style="color:#64748b; margin:0;">لا توجد مناطق محفوظة بعد.</p>
            <?php else: ?>
                <div class="mini-areas-list">
                    <?php foreach ($areas as $row): ?>
                        <?php
                            $rowId = (int) $row['id'];
                            $rowType = (string) ($row['geometry_type'] ?? 'circle');
                            $rowCountry = serviceAreaNormalizeCountryCode($row['country_code'] ?? '');
                            $rowCity = trim((string) ($row['city_name'] ?? ''));
                            $rowVillage = trim((string) ($row['village_name'] ?? ''));
                            $rowNameEn = trim((string) ($row['name_en'] ?? ''));
                            $rowNameUr = trim((string) ($row['name_ur'] ?? ''));
                            $rowInsideCount = (int) ($insideProvidersCount[$rowId] ?? 0);
                        ?>
                        <div class="mini-area-item">
                            <div>
                                <strong><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($rowNameEn !== '' || $rowNameUr !== ''): ?>
                                    <div class="meta">
                                        <?php echo htmlspecialchars($rowNameEn, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($rowNameEn !== '' && $rowNameUr !== ''): ?>
                                            •
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($rowNameUr, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="meta">
                                    <?php echo htmlspecialchars($rowCountry, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($rowCity !== ''): ?> • <?php echo htmlspecialchars($rowCity, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                    <?php if ($rowVillage !== ''): ?> • <?php echo htmlspecialchars($rowVillage, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="mini-area-actions">
                                <span class="badge <?php echo $rowType === 'polygon' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo $rowType === 'polygon' ? 'مضلع' : 'دائرة'; ?>
                                </span>
                                <span class="badge <?php echo (int) ($row['is_active'] ?? 0) === 1 ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int) ($row['is_active'] ?? 0) === 1 ? 'نشطة' : 'متوقفة'; ?>
                                </span>
                                <span class="badge badge-secondary"><?php echo $rowInsideCount; ?> داخل النطاق</span>
                                <button type="button" class="btn btn-sm btn-outline js-focus-area" data-area-id="<?php echo $rowId; ?>">
                                    عرض بالخريطة
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card animate-slideUp" style="margin-top: 20px;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list-ul" style="color: var(--secondary-color);"></i>
            تفاصيل المناطق + أقرب مقدمي الخدمة
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($areas)): ?>
            <div class="empty-state" style="padding: 35px;">
                <div class="empty-state-icon">📍</div>
                <h3>لا توجد مناطق خدمة</h3>
                <p>ابدأ بإضافة منطقة على الخريطة لتفعيل تغطية GPS.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>المنطقة</th>
                            <th>الموقع</th>
                            <th>نوع التحديد</th>
                            <th>الحالة</th>
                            <th>أقرب مقدمي الخدمة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($areas as $area): ?>
                            <?php
                                $areaId = (int) $area['id'];
                                $country = serviceAreaNormalizeCountryCode($area['country_code'] ?? '');
                                $city = trim((string) ($area['city_name'] ?? ''));
                                $village = trim((string) ($area['village_name'] ?? ''));
                                $nearest = $areasNearestProviders[$areaId] ?? [];
                                $geometry = strtolower(trim((string) ($area['geometry_type'] ?? 'circle')));
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string) ($area['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php
                                        $areaNameEn = trim((string) ($area['name_en'] ?? ''));
                                        $areaNameUr = trim((string) ($area['name_ur'] ?? ''));
                                    ?>
                                    <?php if ($areaNameEn !== '' || $areaNameUr !== ''): ?>
                                        <div style="font-size: 11px; color:#64748b; margin-top:4px;">
                                            <?php echo htmlspecialchars($areaNameEn, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($areaNameEn !== '' && $areaNameUr !== ''): ?>
                                                •
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($areaNameUr, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($area['notes'])): ?>
                                        <div style="font-size: 11px; color:#64748b; margin-top:4px;">
                                            <?php echo htmlspecialchars((string) $area['notes'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div style="font-size: 12px; color:#64748b;">
                                        <?php echo $city !== '' ? htmlspecialchars($city, ENT_QUOTES, 'UTF-8') : '—'; ?>
                                        <?php if ($village !== ''): ?>
                                            • <?php echo htmlspecialchars($village, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($geometry === 'polygon'): ?>
                                        <span class="badge badge-info">مضلع</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">دائرة</span>
                                        <div style="font-size: 11px; color:#64748b; margin-top:4px;">
                                            نصف القطر: <?php echo number_format((float) ($area['radius_km'] ?? 0), 1); ?> كم
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo (int) ($area['is_active'] ?? 0) === 1 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo (int) ($area['is_active'] ?? 0) === 1 ? 'نشطة' : 'متوقفة'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (empty($nearest)): ?>
                                        <span style="font-size: 12px; color:#9ca3af;">لا يوجد مقدمو خدمة بإحداثيات</span>
                                    <?php else: ?>
                                        <div class="nearest-providers">
                                            <?php foreach ($nearest as $provider): ?>
                                                <div class="nearest-provider-item">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars((string) ($provider['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <span style="font-size:11px; color:#64748b;">
                                                            <?php echo htmlspecialchars((string) ($provider['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </div>
                                                    <div style="display:flex; gap:6px; align-items:center;">
                                                        <?php if (!empty($provider['inside_area'])): ?>
                                                            <span class="badge badge-success">داخل</span>
                                                        <?php endif; ?>
                                                        <span class="badge badge-secondary"><?php echo number_format((float) ($provider['distance_km'] ?? 0), 2); ?> كم</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap: 6px;">
                                        <a href="service-areas.php?action=edit&id=<?php echo $areaId; ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline js-focus-area" data-area-id="<?php echo $areaId; ?>">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('هل تريد حذف هذه المنطقة؟');">
                                            <input type="hidden" name="action" value="delete_area">
                                            <input type="hidden" name="area_id" value="<?php echo $areaId; ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">
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

<style>
    #service-area-map {
        width: 100%;
        height: 420px;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        overflow: hidden;
        margin-top: 8px;
    }
    .hint-text {
        color: #64748b;
        font-size: 12px;
        display: block;
        margin-top: 8px;
    }
    .country-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .mini-areas-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .mini-area-item {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
    }
    .mini-area-item .meta {
        font-size: 12px;
        color: #64748b;
        margin-top: 2px;
    }
    .mini-area-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .nearest-providers {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .nearest-provider-item {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
        padding: 6px 8px;
        background: #f8fafc;
    }
    @media (max-width: 1200px) {
        .main-content > div[style*="grid-template-columns: 1.2fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
(() => {
    const areas = <?php echo json_encode($areasForMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const editArea = <?php echo $editArea ? json_encode(serviceAreaSummarizeArea($editArea) + ['polygon' => serviceAreaDecodePolygon($editArea['polygon_json'] ?? null)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null'; ?>;

    const map = L.map('service-area-map', {
        zoomControl: true,
    });

    const tileProviders = [
        {
            id: 'osm',
            label: 'OpenStreetMap',
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: {
                maxZoom: 19,
                subdomains: 'abc',
            },
            attribution: '&copy; OpenStreetMap contributors',
        },
        {
            id: 'carto_light',
            label: 'Carto Light',
            url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            options: {
                maxZoom: 20,
                subdomains: 'abcd',
            },
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        },
        {
            id: 'esri_street',
            label: 'Esri Streets',
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
            options: {
                maxZoom: 19,
            },
            attribution: 'Tiles &copy; Esri',
        },
    ];
    const attemptedTileProviders = new Set();
    let activeTileLayer = null;
    let hasTileLoadedOnce = false;
    let tileErrorCount = 0;

    const drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    const areasLayer = new L.FeatureGroup();
    map.addLayer(areasLayer);

    let currentLayer = null;
    let currentCircleCenter = null;

    const geometryInput = document.getElementById('geometry_type');
    const radiusInput = document.getElementById('radius_km');
    const radiusGroup = document.getElementById('radius-group');
    const centerLatInput = document.getElementById('center_lat');
    const centerLngInput = document.getElementById('center_lng');
    const polygonInput = document.getElementById('polygon_json');
    const countryInput = document.getElementById('country_code');
    const mapStatusHint = document.getElementById('map-status-hint');
    const countryCenters = {
        SA: { lat: 24.7136, lng: 46.6753, zoom: 8 },
        AE: { lat: 24.4539, lng: 54.3773, zoom: 8 },
        KW: { lat: 29.3759, lng: 47.9774, zoom: 9 },
        QA: { lat: 25.2854, lng: 51.5310, zoom: 9 },
        BH: { lat: 26.2235, lng: 50.5876, zoom: 10 },
        OM: { lat: 23.5880, lng: 58.3829, zoom: 8 },
        EG: { lat: 30.0444, lng: 31.2357, zoom: 7 },
        JO: { lat: 31.9539, lng: 35.9106, zoom: 8 },
        IQ: { lat: 33.3152, lng: 44.3661, zoom: 7 },
        YE: { lat: 15.3694, lng: 44.1910, zoom: 7 },
    };

    function showMapStatus(message, kind = 'info') {
        if (!mapStatusHint) {
            return;
        }
        if (!message) {
            mapStatusHint.textContent = '';
            mapStatusHint.style.display = 'none';
            mapStatusHint.style.color = '#64748b';
            return;
        }

        mapStatusHint.textContent = message;
        mapStatusHint.style.display = 'block';
        mapStatusHint.style.color = kind === 'danger' ? '#dc2626' : '#64748b';
    }

    function createTileLayer(provider) {
        return L.tileLayer(provider.url, {
            ...provider.options,
            detectRetina: true,
            attribution: provider.attribution,
        });
    }

    function getNextTileProviderIndex() {
        for (let i = 0; i < tileProviders.length; i += 1) {
            if (!attemptedTileProviders.has(i)) {
                return i;
            }
        }
        return -1;
    }

    function activateTileProvider(providerIndex, reason = '') {
        if (providerIndex < 0 || providerIndex >= tileProviders.length) {
            return;
        }

        const provider = tileProviders[providerIndex];
        attemptedTileProviders.add(providerIndex);
        hasTileLoadedOnce = false;
        tileErrorCount = 0;

        if (activeTileLayer) {
            activeTileLayer.off();
            map.removeLayer(activeTileLayer);
        }

        activeTileLayer = createTileLayer(provider);

        activeTileLayer.on('tileload', () => {
            hasTileLoadedOnce = true;
            showMapStatus(`الخريطة تعمل عبر ${provider.label}`);
        });

        activeTileLayer.on('tileerror', () => {
            tileErrorCount += 1;
            if (hasTileLoadedOnce || tileErrorCount < 6) {
                return;
            }

            const nextIndex = getNextTileProviderIndex();
            if (nextIndex === -1) {
                showMapStatus('تعذر تحميل الخرائط. تحقق من اتصال الإنترنت أو من إتاحة مصادر الخرائط على الشبكة.', 'danger');
                return;
            }

            const nextProvider = tileProviders[nextIndex];
            showMapStatus(
                `تعذر تحميل ${provider.label}. جارٍ التحويل تلقائيًا إلى ${nextProvider.label}...`,
                'danger'
            );
            activateTileProvider(nextIndex, 'tile-error');
        });

        activeTileLayer.addTo(map);

        if (!reason) {
            showMapStatus(`جارٍ تحميل الخريطة عبر ${provider.label}...`);
        }
    }

    function setCenter(lat, lng) {
        centerLatInput.value = Number(lat).toFixed(8);
        centerLngInput.value = Number(lng).toFixed(8);
        currentCircleCenter = L.latLng(lat, lng);
    }

    function clearDrawnLayer() {
        drawnItems.clearLayers();
        currentLayer = null;
    }

    function updateGeometryVisibility() {
        const geometry = geometryInput.value;
        radiusGroup.style.display = geometry === 'circle' ? 'block' : 'none';
    }

    function polygonToPayload(latLngs) {
        return latLngs.map((point) => ({
            lat: Number(point.lat.toFixed(8)),
            lng: Number(point.lng.toFixed(8)),
        }));
    }

    function setPolygonPayload(latLngs) {
        polygonInput.value = JSON.stringify(polygonToPayload(latLngs));
    }

    function toFiniteNumber(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function normalizePolygonPoints(rawPoints) {
        if (!Array.isArray(rawPoints)) {
            return [];
        }
        return rawPoints
            .map((point) => ({
                lat: toFiniteNumber(point ? point.lat : null),
                lng: toFiniteNumber(point ? point.lng : null),
            }))
            .filter((point) => point.lat !== null && point.lng !== null);
    }

    function focusMapByCountry(force = false) {
        const code = (countryInput ? countryInput.value : '').trim().toUpperCase();
        const center = countryCenters[code];
        if (!center) {
            return false;
        }

        const hasDrawnGeometry =
            drawnItems.getLayers().length > 0 ||
            areasLayer.getLayers().length > 0 ||
            currentLayer !== null;
        if (hasDrawnGeometry && !force) {
            return false;
        }

        map.setView([center.lat, center.lng], center.zoom);
        return true;
    }

    function attachEditableLayer(layer, geometryType) {
        clearDrawnLayer();
        currentLayer = layer;
        drawnItems.addLayer(layer);
        geometryInput.value = geometryType;
        updateGeometryVisibility();

        if (geometryType === 'circle') {
            const center = layer.getLatLng();
            setCenter(center.lat, center.lng);
            const radiusKm = layer.getRadius() / 1000;
            radiusInput.value = Math.max(0.1, Number(radiusKm.toFixed(2)));
            polygonInput.value = '';
        } else if (geometryType === 'polygon') {
            const latLngs = layer.getLatLngs()[0] || [];
            if (latLngs.length > 0) {
                const avgLat = latLngs.reduce((sum, p) => sum + p.lat, 0) / latLngs.length;
                const avgLng = latLngs.reduce((sum, p) => sum + p.lng, 0) / latLngs.length;
                setCenter(avgLat, avgLng);
                setPolygonPayload(latLngs);
            }
        }
    }

    function drawExistingAreas() {
        areasLayer.clearLayers();
        areas.forEach((area) => {
            let layer = null;
            const color = area.is_active ? '#16a34a' : '#ef4444';
            const normalizedPolygon = normalizePolygonPoints(area.polygon);
            const centerLat = toFiniteNumber(area.center_lat);
            const centerLng = toFiniteNumber(area.center_lng);
            const radiusKm = toFiniteNumber(area.radius_km);

            if (area.geometry_type === 'polygon' && normalizedPolygon.length >= 3) {
                layer = L.polygon(
                    normalizedPolygon.map((p) => [p.lat, p.lng]),
                    {
                        color,
                        weight: 2,
                        fillOpacity: 0.12,
                    }
                );
            } else if (centerLat !== null && centerLng !== null && radiusKm !== null && radiusKm > 0) {
                layer = L.circle([centerLat, centerLng], {
                    radius: radiusKm * 1000,
                    color,
                    weight: 2,
                    fillOpacity: 0.12,
                });
            }
            if (!layer) return;

            layer.bindPopup(`
                <strong>${area.name || ''}</strong><br>
                ${area.country_code || ''}${area.city_name ? ' - ' + area.city_name : ''}${area.village_name ? ' - ' + area.village_name : ''}<br>
                ${area.geometry_type === 'polygon' ? 'مضلع' : 'دائرة'}
            `);
            layer.__areaId = area.id;
            areasLayer.addLayer(layer);
        });
    }

    function fitMapToData() {
        const bounds = L.latLngBounds([]);

        const extendFromLayer = (layer) => {
            if (!layer) {
                return;
            }
            if (typeof layer.getBounds === 'function') {
                const layerBounds = layer.getBounds();
                if (layerBounds && typeof layerBounds.isValid === 'function' && layerBounds.isValid()) {
                    bounds.extend(layerBounds);
                    return;
                }
            }
            if (typeof layer.getLatLng === 'function') {
                const point = layer.getLatLng();
                if (point) {
                    bounds.extend(point);
                }
            }
        };

        areasLayer.eachLayer(extendFromLayer);
        drawnItems.eachLayer(extendFromLayer);

        if (typeof bounds.isValid === 'function' && bounds.isValid()) {
            try {
                map.fitBounds(bounds.pad(0.1));
                return;
            } catch (error) {
                console.error('fitMapToData failed, fallback to default view:', error);
            }
        }
        map.setView([24.7136, 46.6753], 9);
    }

    activateTileProvider(0);

    const drawControl = new L.Control.Draw({
        edit: {
            featureGroup: drawnItems,
            remove: true,
        },
        draw: {
            polyline: false,
            rectangle: false,
            marker: false,
            circlemarker: false,
            polygon: {
                allowIntersection: false,
                showArea: true,
                shapeOptions: { color: '#0f766e', weight: 2 },
            },
            circle: {
                shapeOptions: { color: '#d97706', weight: 2 },
            },
        },
    });
    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, (event) => {
        const layer = event.layer;
        const type = event.layerType === 'circle' ? 'circle' : 'polygon';
        attachEditableLayer(layer, type);
    });

    map.on(L.Draw.Event.EDITED, (event) => {
        event.layers.eachLayer((layer) => {
            if (layer === currentLayer) {
                const type = layer instanceof L.Circle ? 'circle' : 'polygon';
                attachEditableLayer(layer, type);
            }
        });
    });

    map.on(L.Draw.Event.DELETED, () => {
        centerLatInput.value = '';
        centerLngInput.value = '';
        polygonInput.value = '';
        currentCircleCenter = null;
        currentLayer = null;
    });

    radiusInput.addEventListener('input', () => {
        if (geometryInput.value !== 'circle') return;
        if (!currentCircleCenter) return;
        const radiusKm = Number(radiusInput.value || 0);
        if (!(radiusKm > 0)) return;

        const circle = L.circle(currentCircleCenter, {
            radius: radiusKm * 1000,
            color: '#d97706',
            weight: 2,
            fillOpacity: 0.1,
        });
        attachEditableLayer(circle, 'circle');
    });

    geometryInput.addEventListener('change', () => {
        updateGeometryVisibility();
    });
    if (countryInput) {
        countryInput.addEventListener('change', () => {
            if (!currentLayer) {
                focusMapByCountry(true);
            }
        });
    }

    document.querySelectorAll('.js-focus-area').forEach((button) => {
        button.addEventListener('click', () => {
            const areaId = Number(button.getAttribute('data-area-id'));
            let targetLayer = null;
            areasLayer.eachLayer((layer) => {
                if (layer.__areaId === areaId) {
                    targetLayer = layer;
                }
            });
            if (!targetLayer) return;
            map.fitBounds(targetLayer.getBounds ? targetLayer.getBounds().pad(0.2) : L.latLngBounds(targetLayer.getLatLng(), targetLayer.getLatLng()).pad(0.2));
            if (targetLayer.openPopup) targetLayer.openPopup();
        });
    });

    drawExistingAreas();

    if (editArea) {
        const editPolygonPoints = normalizePolygonPoints(editArea.polygon);
        const editCenterLat = toFiniteNumber(editArea.center_lat);
        const editCenterLng = toFiniteNumber(editArea.center_lng);
        const editRadiusKm = toFiniteNumber(editArea.radius_km || radiusInput.value || 5);

        if (editArea.geometry_type === 'polygon' && editPolygonPoints.length >= 3) {
            const polygon = L.polygon(editPolygonPoints.map((p) => [p.lat, p.lng]), {
                color: '#0f766e',
                weight: 2,
                fillOpacity: 0.12,
            });
            attachEditableLayer(polygon, 'polygon');
            map.fitBounds(polygon.getBounds().pad(0.2));
        } else if (editCenterLat !== null && editCenterLng !== null && editRadiusKm !== null && editRadiusKm > 0) {
            const circle = L.circle([editCenterLat, editCenterLng], {
                radius: editRadiusKm * 1000,
                color: '#d97706',
                weight: 2,
                fillOpacity: 0.12,
            });
            attachEditableLayer(circle, 'circle');
            map.fitBounds(circle.getBounds().pad(0.2));
        } else {
            fitMapToData();
        }
    } else {
        fitMapToData();
        focusMapByCountry();
    }

    updateGeometryVisibility();
    map.whenReady(() => {
        setTimeout(() => map.invalidateSize(), 0);
        setTimeout(() => map.invalidateSize(), 250);
    });
    window.addEventListener('resize', () => map.invalidateSize());
})();
</script>

<?php include '../includes/footer.php'; ?>
