<?php
/**
 * إعدادات طلبات نقل العفش
 */

require_once '../init.php';
require_once '../includes/special_services.php';
requireLogin();

ensureSpecialServicesSchema();

if (!hasPermission('services') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'إعدادات نقل العفش';
$pageSubtitle = 'إدارة المناطق والحقول المطلوبة في نموذج الطلب داخل التطبيق';

$action = get('action', 'list');
$id = (int) get('id');

function furnitureAllowedFieldTypes(): array
{
    return [
        'text' => 'نص قصير',
        'number' => 'رقم',
        'textarea' => 'نص طويل',
        'select' => 'قائمة اختيار',
        'checkbox' => 'صح / خطأ',
    ];
}

function furnitureBuildOptionsJson(string $rawOptions): ?string
{
    $lines = preg_split('/\r\n|\r|\n/', trim($rawOptions));
    $options = [];

    if (!$lines) {
        return null;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) >= 4) {
            $value = $parts[0] !== '' ? $parts[0] : $parts[1];
            $labelAr = $parts[1] !== '' ? $parts[1] : $value;
            $labelEn = $parts[2] !== '' ? $parts[2] : $labelAr;
            $labelUr = $parts[3] !== '' ? $parts[3] : $labelEn;
        } elseif (count($parts) >= 3) {
            $value = $parts[0] !== '' ? $parts[0] : $parts[1];
            $labelAr = $parts[1] !== '' ? $parts[1] : $value;
            $labelEn = $parts[2] !== '' ? $parts[2] : $labelAr;
            $labelUr = $labelEn;
        } else {
            $value = $parts[0];
            $labelAr = $parts[0];
            $labelEn = $parts[0];
            $labelUr = $parts[0];
        }

        if ($value === '') {
            continue;
        }

        $options[] = [
            'value' => $value,
            'label_ar' => $labelAr,
            'label_en' => $labelEn,
            'label_ur' => $labelUr,
        ];
    }

    if (empty($options)) {
        return null;
    }

    return json_encode($options, JSON_UNESCAPED_UNICODE);
}

function furnitureOptionsJsonToText(?string $optionsJson): string
{
    if (empty($optionsJson)) {
        return '';
    }

    $decoded = json_decode($optionsJson, true);
    if (!is_array($decoded)) {
        return '';
    }

    $lines = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $value = trim((string) ($item['value'] ?? ''));
        $labelAr = trim((string) ($item['label_ar'] ?? ''));
        $labelEn = trim((string) ($item['label_en'] ?? ''));
        $labelUr = trim((string) ($item['label_ur'] ?? ''));
        if ($value === '' && $labelAr === '' && $labelEn === '') {
            continue;
        }
        if ($labelUr !== '') {
            $lines[] = $value . '|' . $labelAr . '|' . $labelEn . '|' . $labelUr;
        } else {
            $lines[] = $value . '|' . $labelAr . '|' . $labelEn;
        }
    }

    return implode("\n", $lines);
}

function furnitureNormalizeFieldKey(string $rawKey, string $labelAr, int $ignoreId = 0): string
{
    $candidate = strtolower(trim($rawKey));
    if ($candidate !== '') {
        $candidate = preg_replace('/[^a-z0-9_]+/', '_', $candidate);
        $candidate = trim((string) $candidate, '_');
    }

    if ($candidate === '') {
        $candidate = 'field_' . substr(md5($labelAr . microtime(true)), 0, 8);
    }

    $base = $candidate;
    $index = 2;

    while (true) {
        if ($ignoreId > 0) {
            $exists = db()->fetch(
                'SELECT id FROM furniture_request_fields WHERE field_key = ? AND id != ? LIMIT 1',
                [$candidate, $ignoreId]
            );
        } else {
            $exists = db()->fetch(
                'SELECT id FROM furniture_request_fields WHERE field_key = ? LIMIT 1',
                [$candidate]
            );
        }

        if (!$exists) {
            return $candidate;
        }

        $candidate = $base . '_' . $index;
        $index++;
    }
}

$fieldTypes = furnitureAllowedFieldTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add_area' || $postAction === 'edit_area') {
        $nameAr = clean(post('name_ar'));
        $nameEn = clean(post('name_en'));
        $nameUr = clean(post('name_ur'));
        $sortOrder = (int) post('sort_order');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($nameAr === '') {
            setFlashMessage('danger', 'اسم المنطقة بالعربي مطلوب');
            redirect('furniture-settings.php' . ($postAction === 'edit_area' ? '?action=edit_area&id=' . (int) post('id') : ''));
        }

        $data = [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'name_ur' => $nameUr,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if ($postAction === 'add_area') {
            $newId = db()->insert('furniture_areas', $data);
            logActivity('add_furniture_area', 'furniture_areas', $newId);
            setFlashMessage('success', 'تمت إضافة المنطقة بنجاح');
        } else {
            $areaId = (int) post('id');
            db()->update('furniture_areas', $data, 'id = ?', [$areaId]);
            logActivity('update_furniture_area', 'furniture_areas', $areaId);
            setFlashMessage('success', 'تم تحديث المنطقة بنجاح');
        }

        redirect('furniture-settings.php');
    }

    if ($postAction === 'delete_area') {
        $areaId = (int) post('id');
        $area = db()->fetch('SELECT id, name_ar FROM furniture_areas WHERE id = ?', [$areaId]);
        if (!$area) {
            setFlashMessage('danger', 'المنطقة غير موجودة');
            redirect('furniture-settings.php');
        }

        $linkedRequests = (int) db()->count('furniture_requests', 'area_id = ?', [$areaId]);
        if ($linkedRequests > 0) {
            db()->query(
                'UPDATE furniture_requests SET area_name = ?, area_id = NULL WHERE area_id = ?',
                [$area['name_ar'] ?? null, $areaId]
            );
        }

        db()->delete('furniture_areas', 'id = ?', [$areaId]);
        logActivity('delete_furniture_area', 'furniture_areas', $areaId);
        setFlashMessage('success', 'تم حذف المنطقة بنجاح');
        redirect('furniture-settings.php');
    }

    if ($postAction === 'add_field' || $postAction === 'edit_field') {
        $labelAr = clean(post('label_ar'));
        $labelEn = clean(post('label_en'));
        $labelUr = clean(post('label_ur'));
        $fieldType = clean(post('field_type'));
        $sortOrder = (int) post('sort_order');
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $placeholderAr = clean(post('placeholder_ar'));
        $placeholderEn = clean(post('placeholder_en'));
        $placeholderUr = clean(post('placeholder_ur'));
        $rawOptions = (string) post('options_text');

        if ($labelAr === '') {
            setFlashMessage('danger', 'اسم الحقل بالعربي مطلوب');
            redirect('furniture-settings.php' . ($postAction === 'edit_field' ? '?action=edit_field&id=' . (int) post('id') : ''));
        }

        if (!isset($fieldTypes[$fieldType])) {
            setFlashMessage('danger', 'نوع الحقل غير صالح');
            redirect('furniture-settings.php' . ($postAction === 'edit_field' ? '?action=edit_field&id=' . (int) post('id') : ''));
        }

        $fieldId = $postAction === 'edit_field' ? (int) post('id') : 0;
        $fieldKey = furnitureNormalizeFieldKey(clean(post('field_key')), $labelAr, $fieldId);

        $optionsJson = null;
        if ($fieldType === 'select') {
            $optionsJson = furnitureBuildOptionsJson($rawOptions);
            if ($optionsJson === null) {
                setFlashMessage('danger', 'حقول القائمة تحتاج خيارات. أضف كل خيار في سطر مستقل.');
                redirect('furniture-settings.php' . ($postAction === 'edit_field' ? '?action=edit_field&id=' . (int) post('id') : ''));
            }
        }

        $data = [
            'field_key' => $fieldKey,
            'label_ar' => $labelAr,
            'label_en' => $labelEn,
            'label_ur' => $labelUr,
            'field_type' => $fieldType,
            'placeholder_ar' => $placeholderAr,
            'placeholder_en' => $placeholderEn,
            'placeholder_ur' => $placeholderUr,
            'options_json' => $optionsJson,
            'is_required' => $isRequired,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ];

        if ($postAction === 'add_field') {
            $newId = db()->insert('furniture_request_fields', $data);
            logActivity('add_furniture_request_field', 'furniture_request_fields', $newId);
            setFlashMessage('success', 'تمت إضافة الحقل بنجاح');
        } else {
            db()->update('furniture_request_fields', $data, 'id = ?', [$fieldId]);
            logActivity('update_furniture_request_field', 'furniture_request_fields', $fieldId);
            setFlashMessage('success', 'تم تحديث الحقل بنجاح');
        }

        redirect('furniture-settings.php');
    }

    if ($postAction === 'delete_field') {
        $fieldId = (int) post('id');
        db()->delete('furniture_request_fields', 'id = ?', [$fieldId]);
        logActivity('delete_furniture_request_field', 'furniture_request_fields', $fieldId);
        setFlashMessage('success', 'تم حذف الحقل بنجاح');
        redirect('furniture-settings.php');
    }
}

$areas = db()->fetchAll(
    "SELECT a.*, (SELECT COUNT(*) FROM furniture_requests r WHERE r.area_id = a.id) AS requests_count
     FROM furniture_areas a
     ORDER BY a.sort_order ASC, a.id DESC"
);

$fields = db()->fetchAll(
    "SELECT f.*,
            (SELECT COUNT(*) FROM furniture_requests r WHERE JSON_EXTRACT(r.details_json, CONCAT('$.', f.field_key)) IS NOT NULL) AS used_count
     FROM furniture_request_fields f
     ORDER BY f.sort_order ASC, f.id DESC"
);

$editArea = null;
if ($action === 'edit_area' && $id > 0) {
    $editArea = db()->fetch('SELECT * FROM furniture_areas WHERE id = ?', [$id]);
    if (!$editArea) {
        setFlashMessage('danger', 'المنطقة غير موجودة');
        redirect('furniture-settings.php');
    }
}

$editField = null;
if ($action === 'edit_field' && $id > 0) {
    $editField = db()->fetch('SELECT * FROM furniture_request_fields WHERE id = ?', [$id]);
    if (!$editField) {
        setFlashMessage('danger', 'الحقل غير موجود');
        redirect('furniture-settings.php');
    }
}

include '../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start;">
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><?php echo $editArea ? 'تعديل منطقة' : 'إضافة منطقة'; ?></h3>
            <?php if ($editArea): ?>
                <a href="furniture-settings.php" class="btn btn-sm btn-outline">إلغاء</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editArea ? 'edit_area' : 'add_area'; ?>">
                <?php if ($editArea): ?>
                    <input type="hidden" name="id" value="<?php echo $editArea['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">اسم المنطقة (عربي)</label>
                    <input type="text" name="name_ar" class="form-control" required
                        value="<?php echo $editArea['name_ar'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">اسم المنطقة (إنجليزي)</label>
                    <input type="text" name="name_en" class="form-control"
                        value="<?php echo $editArea['name_en'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">اسم المنطقة (أوردو)</label>
                    <input type="text" name="name_ur" class="form-control"
                        value="<?php echo $editArea['name_ur'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">الترتيب</label>
                    <input type="number" name="sort_order" class="form-control"
                        value="<?php echo (int) ($editArea['sort_order'] ?? 0); ?>">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" <?php echo $editArea ? (!empty($editArea['is_active']) ? 'checked' : '') : 'checked'; ?>>
                        منطقة نشطة
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo $editArea ? 'حفظ التعديلات' : 'إضافة المنطقة'; ?>
                </button>
            </form>
        </div>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">مناطق الخدمة</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>المنطقة</th>
                            <th>عدد الطلبات</th>
                            <th>الحالة</th>
                            <th>الترتيب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($areas as $area): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $area['name_ar']; ?></strong><br>
                                    <small class="text-muted"><?php echo $area['name_en'] ?: '-'; ?></small>
                                    <?php if (!empty($area['name_ur'])): ?>
                                        <br><small class="text-muted"><?php echo $area['name_ur']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-info"><?php echo (int) ($area['requests_count'] ?? 0); ?></span></td>
                                <td>
                                    <span class="badge <?php echo !empty($area['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo !empty($area['is_active']) ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td><?php echo (int) ($area['sort_order'] ?? 0); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?action=edit_area&id=<?php echo $area['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                            <input type="hidden" name="action" value="delete_area">
                                            <input type="hidden" name="id" value="<?php echo $area['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($areas)): ?>
                            <tr>
                                <td colspan="5" class="text-center">لا توجد مناطق مضافة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div style="height: 20px;"></div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start;">
    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title"><?php echo $editField ? 'تعديل حقل' : 'إضافة حقل'; ?></h3>
            <?php if ($editField): ?>
                <a href="furniture-settings.php" class="btn btn-sm btn-outline">إلغاء</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editField ? 'edit_field' : 'add_field'; ?>">
                <?php if ($editField): ?>
                    <input type="hidden" name="id" value="<?php echo $editField['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">مفتاح الحقل (اختياري - إنجليزي)</label>
                    <input type="text" name="field_key" class="form-control"
                        value="<?php echo $editField['field_key'] ?? ''; ?>"
                        placeholder="مثال: furniture_size">
                </div>

                <div class="form-group">
                    <label class="form-label">اسم الحقل (عربي)</label>
                    <input type="text" name="label_ar" class="form-control" required
                        value="<?php echo $editField['label_ar'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">اسم الحقل (إنجليزي)</label>
                    <input type="text" name="label_en" class="form-control"
                        value="<?php echo $editField['label_en'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">اسم الحقل (أوردو)</label>
                    <input type="text" name="label_ur" class="form-control"
                        value="<?php echo $editField['label_ur'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">نوع الحقل</label>
                    <select name="field_type" class="form-control">
                        <?php foreach ($fieldTypes as $typeKey => $typeLabel): ?>
                            <option value="<?php echo $typeKey; ?>" <?php echo (($editField['field_type'] ?? 'text') === $typeKey) ? 'selected' : ''; ?>>
                                <?php echo $typeLabel; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Placeholder عربي</label>
                    <input type="text" name="placeholder_ar" class="form-control"
                        value="<?php echo $editField['placeholder_ar'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Placeholder إنجليزي</label>
                    <input type="text" name="placeholder_en" class="form-control"
                        value="<?php echo $editField['placeholder_en'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Placeholder أوردو</label>
                    <input type="text" name="placeholder_ur" class="form-control"
                        value="<?php echo $editField['placeholder_ur'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">خيارات القائمة (للنوع قائمة فقط)</label>
                    <textarea name="options_text" class="form-control" rows="4"
                        placeholder="كل سطر بالشكل: value|Label AR|Label EN|Label UR"><?php echo $editField ? furnitureOptionsJsonToText($editField['options_json'] ?? null) : ''; ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control"
                            value="<?php echo (int) ($editField['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="form-group" style="display: flex; align-items: end; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="is_required" <?php echo $editField ? (!empty($editField['is_required']) ? 'checked' : '') : ''; ?>>
                            مطلوب
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="is_active" <?php echo $editField ? (!empty($editField['is_active']) ? 'checked' : '') : 'checked'; ?>>
                            نشط
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo $editField ? 'حفظ التعديلات' : 'إضافة الحقل'; ?>
                </button>
            </form>
        </div>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">حقول نموذج طلب نقل العفش</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الحقل</th>
                            <th>المفتاح</th>
                            <th>النوع</th>
                            <th>مطلوب</th>
                            <th>نشط</th>
                            <th>الترتيب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $field['label_ar']; ?></strong><br>
                                    <small class="text-muted"><?php echo $field['label_en'] ?: '-'; ?></small>
                                    <?php if (!empty($field['label_ur'])): ?>
                                        <br><small class="text-muted"><?php echo $field['label_ur']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo $field['field_key']; ?></code></td>
                                <td><?php echo $fieldTypes[$field['field_type']] ?? $field['field_type']; ?></td>
                                <td>
                                    <span class="badge <?php echo !empty($field['is_required']) ? 'badge-warning' : 'badge-secondary'; ?>">
                                        <?php echo !empty($field['is_required']) ? 'نعم' : 'لا'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo !empty($field['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo !empty($field['is_active']) ? 'نشط' : 'متوقف'; ?>
                                    </span>
                                </td>
                                <td><?php echo (int) ($field['sort_order'] ?? 0); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?action=edit_field&id=<?php echo $field['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                            <input type="hidden" name="action" value="delete_field">
                                            <input type="hidden" name="id" value="<?php echo $field['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($fields)): ?>
                            <tr>
                                <td colspan="7" class="text-center">لا توجد حقول مضافة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
