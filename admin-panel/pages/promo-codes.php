<?php
/**
 * صفحة إدارة أكواد الخصم
 * Promo Codes Management Page
 */

require_once '../init.php';
requireLogin();

if (!hasPermission('offers') && getCurrentAdmin()['role'] !== 'super_admin') {
    die('ليس لديك صلاحية الوصول لهذه الصفحة');
}

$pageTitle = 'أكواد الخصم';
$pageSubtitle = 'إدارة العروض وأكواد الخصم (موحّدة)';

$action = get('action', 'list');
$id = (int) get('id');

function promoTableExists($table)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($table === '') {
        return false;
    }

    $row = db()->fetch("SHOW TABLES LIKE '{$table}'");
    return !empty($row);
}

function promoTableColumnExists($table, $column)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($table === '' || $column === '') {
        return false;
    }

    $row = db()->fetch("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return !empty($row);
}

function ensurePromoCodesAdminMediaSchema()
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    try {
        if (!promoTableExists('promo_codes')) {
            return;
        }

        $columns = [
            'image' => "VARCHAR(255) NULL AFTER `description`",
            'title_ar' => "VARCHAR(255) NULL AFTER `code`",
            'title_en' => "VARCHAR(255) NULL AFTER `title_ar`",
            'title_ur' => "VARCHAR(255) NULL AFTER `title_en`",
            'description_ar' => "TEXT NULL AFTER `title_ur`",
            'description_en' => "TEXT NULL AFTER `description_ar`",
            'description_ur' => "TEXT NULL AFTER `description_en`",
            'usage_limit_per_user' => "INT NULL AFTER `usage_limit`",
        ];

        foreach ($columns as $column => $definition) {
            if (!promoTableColumnExists('promo_codes', $column)) {
                try {
                    db()->query("ALTER TABLE `promo_codes` ADD COLUMN `{$column}` {$definition}");
                } catch (Throwable $e) {
                    error_log('promo-codes schema warning: ' . $e->getMessage());
                }
            }
        }
    } catch (Throwable $e) {
        error_log('promo-codes ensure schema failed: ' . $e->getMessage());
    }
}

ensurePromoCodesAdminMediaSchema();

function normalizePromoCode($code)
{
    $normalized = strtoupper(trim((string) $code));
    $normalized = preg_replace('/\s+/', '', $normalized);
    return preg_replace('/[^A-Z0-9_-]/', '', $normalized);
}

function normalizeNullableDate($date)
{
    $date = trim((string) $date);
    return $date === '' ? null : $date;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'add' || $postAction === 'edit') {
        $couponId = (int) post('coupon_id');
        $code = normalizePromoCode(post('code'));
        $description = post('description');
        $discountType = post('discount_type');
        $discountValue = (float) post('discount_value');
        $minOrderAmount = max(0, (float) post('min_order_amount'));
        $maxDiscountAmountInput = post('max_discount_amount');
        $usageLimitInput = post('usage_limit');
        $usageLimitPerUserInput = post('usage_limit_per_user');
        $startDate = normalizeNullableDate(post('start_date'));
        $endDate = normalizeNullableDate(post('end_date'));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $maxDiscountAmount = $maxDiscountAmountInput === '' ? null : max(0, (float) $maxDiscountAmountInput);
        $usageLimit = $usageLimitInput === '' ? null : max(0, (int) $usageLimitInput);
        $usageLimitPerUser = $usageLimitPerUserInput === '' ? null : max(0, (int) $usageLimitPerUserInput);

        if ($usageLimit === 0) {
            $usageLimit = null;
        }
        if ($usageLimitPerUser === 0) {
            $usageLimitPerUser = null;
        }

        $errors = [];

        if ($code === '' || strlen($code) < 3) {
            $errors[] = 'كود الخصم يجب أن يكون 3 أحرف على الأقل (إنجليزي/أرقام)';
        }

        if (!in_array($discountType, ['percentage', 'fixed'], true)) {
            $errors[] = 'نوع الخصم غير صالح';
        }

        if ($discountValue <= 0) {
            $errors[] = 'قيمة الخصم يجب أن تكون أكبر من صفر';
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            $errors[] = 'قيمة الخصم بالنسبة لا يمكن أن تتجاوز 100%';
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $errors[] = 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية';
        }

        if ($postAction === 'edit') {
            if ($couponId <= 0) {
                $errors[] = 'الكوبون غير صالح للتعديل';
            } else {
                $currentCoupon = db()->fetch("SELECT id, used_count FROM promo_codes WHERE id = ?", [$couponId]);
                if (!$currentCoupon) {
                    $errors[] = 'الكوبون غير موجود';
                } elseif ($usageLimit !== null && $usageLimit < (int) $currentCoupon['used_count']) {
                    $errors[] = 'حد الاستخدام أقل من عدد الاستخدام الحالي';
                }
            }
        }

        if ($code !== '') {
            if ($postAction === 'add') {
                $exists = db()->fetch("SELECT id FROM promo_codes WHERE code = ? LIMIT 1", [$code]);
            } else {
                $exists = db()->fetch(
                    "SELECT id FROM promo_codes WHERE code = ? AND id != ? LIMIT 1",
                    [$code, $couponId]
                );
            }
            if ($exists) {
                $errors[] = 'كود الخصم مستخدم بالفعل';
            }
        }

        if (!empty($errors)) {
            setFlashMessage('danger', implode(' - ', $errors));
            if ($postAction === 'edit' && $couponId > 0) {
                redirect('promo-codes.php?action=edit&id=' . $couponId);
            }
            redirect('promo-codes.php');
        }

        if ($discountType === 'fixed') {
            $maxDiscountAmount = null;
        }

        $titleAr = trim((string) post('title_ar'));
        $titleEn = trim((string) post('title_en'));
        $titleUr = trim((string) post('title_ur'));
        $descriptionAr = trim((string) post('description_ar'));
        $descriptionEn = trim((string) post('description_en'));
        $descriptionUr = trim((string) post('description_ur'));

        $imagePath = null;
        if (
            isset($_FILES['image'])
            && is_array($_FILES['image'])
            && (int) (isset($_FILES['image']['error']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        ) {
            $upload = uploadFile($_FILES['image'], 'offers');
            if (!empty($upload['success'])) {
                $imagePath = isset($upload['path']) ? $upload['path'] : null;
            } else {
                setFlashMessage('danger', 'فشل رفع صورة العرض: ' . (isset($upload['message']) ? $upload['message'] : 'Unknown error'));
                if ($postAction === 'edit' && $couponId > 0) {
                    redirect('promo-codes.php?action=edit&id=' . $couponId);
                }
                redirect('promo-codes.php');
            }
        }

        $data = [
            'code' => $code,
            'description' => $description,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_order_amount' => $minOrderAmount,
            'max_discount_amount' => $maxDiscountAmount,
            'usage_limit' => $usageLimit,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        if (promoTableColumnExists('promo_codes', 'usage_limit_per_user')) {
            $data['usage_limit_per_user'] = $usageLimitPerUser;
        }

        if (promoTableColumnExists('promo_codes', 'title_ar')) {
            $data['title_ar'] = $titleAr !== '' ? $titleAr : null;
        }
        if (promoTableColumnExists('promo_codes', 'title_en')) {
            $data['title_en'] = $titleEn !== '' ? $titleEn : null;
        }
        if (promoTableColumnExists('promo_codes', 'title_ur')) {
            $data['title_ur'] = $titleUr !== '' ? $titleUr : ($titleEn !== '' ? $titleEn : ($titleAr !== '' ? $titleAr : null));
        }
        if (promoTableColumnExists('promo_codes', 'description_ar')) {
            $data['description_ar'] = $descriptionAr !== '' ? $descriptionAr : null;
        }
        if (promoTableColumnExists('promo_codes', 'description_en')) {
            $data['description_en'] = $descriptionEn !== '' ? $descriptionEn : null;
        }
        if (promoTableColumnExists('promo_codes', 'description_ur')) {
            $data['description_ur'] = $descriptionUr !== '' ? $descriptionUr : ($descriptionEn !== '' ? $descriptionEn : ($descriptionAr !== '' ? $descriptionAr : null));
        }

        if ($imagePath !== null && promoTableColumnExists('promo_codes', 'image')) {
            $data['image'] = $imagePath;
        }

        if ($postAction === 'add') {
            $data['is_active'] = 1;
            $newId = db()->insert('promo_codes', $data);
            logActivity('add_promo_code', 'promo_codes', $newId);
            setFlashMessage('success', 'تم إنشاء كود الخصم بنجاح');
        } else {
            $data['is_active'] = $isActive;
            db()->update('promo_codes', $data, 'id = :id', ['id' => $couponId]);
            logActivity('update_promo_code', 'promo_codes', $couponId);
            setFlashMessage('success', 'تم تحديث كود الخصم بنجاح');
        }

        redirect('promo-codes.php');
    }

    if ($postAction === 'delete') {
        $couponId = (int) post('coupon_id');
        $coupon = db()->fetch("SELECT id, used_count FROM promo_codes WHERE id = ?", [$couponId]);

        if (!$coupon) {
            setFlashMessage('danger', 'الكوبون غير موجود');
        } elseif ((int) $coupon['used_count'] > 0) {
            setFlashMessage('danger', 'لا يمكن حذف كوبون تم استخدامه. يمكنك تعطيله بدلاً من ذلك.');
        } else {
            db()->delete('promo_codes', 'id = ?', [$couponId]);
            logActivity('delete_promo_code', 'promo_codes', $couponId);
            setFlashMessage('success', 'تم حذف كود الخصم بنجاح');
        }

        redirect('promo-codes.php');
    }
}

$promoCodes = db()->fetchAll("
    SELECT pc.*,
           CASE
               WHEN pc.is_active = 0 THEN 'inactive'
               WHEN pc.end_date IS NOT NULL AND pc.end_date < CURDATE() THEN 'expired'
               WHEN pc.usage_limit IS NOT NULL AND pc.used_count >= pc.usage_limit THEN 'exhausted'
               WHEN pc.start_date IS NOT NULL AND pc.start_date > CURDATE() THEN 'scheduled'
               ELSE 'active'
           END AS state
    FROM promo_codes pc
    ORDER BY pc.created_at DESC
");

if ($action === 'edit' && $id > 0) {
    $promoCode = db()->fetch("SELECT * FROM promo_codes WHERE id = ?", [$id]);
    if (!$promoCode) {
        setFlashMessage('danger', 'كود الخصم غير موجود');
        redirect('promo-codes.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
    <div class="badge badge-primary" style="padding: 10px 14px;">
        <i class="fas fa-bullhorn"></i>
        العروض الآن تعتمد على أكواد الخصم فقط
    </div>
    <button onclick="showModal('add-modal')" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        إضافة عرض/كود خصم
    </button>
</div>

<div class="card animate-slideUp">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-ticket-alt" style="color: var(--primary-color);"></i>
            العروض وأكواد الخصم
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($promoCodes)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎟️</div>
            <h3>لا توجد أكواد خصم</h3>
            <p>أنشئ كود خصم جديد ليظهر مباشرة في التحقق داخل التطبيق</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>الكود</th>
                        <th>الخصم</th>
                        <th>الاستخدام</th>
                        <th>فترة الصلاحية</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stateLabels = [
                        'active' => 'نشط',
                        'scheduled' => 'مجدول',
                        'expired' => 'منتهي',
                        'exhausted' => 'مستهلك',
                        'inactive' => 'معطل'
                    ];
                    $stateClasses = [
                        'active' => 'badge-success',
                        'scheduled' => 'badge-primary',
                        'expired' => 'badge-danger',
                        'exhausted' => 'badge-warning',
                        'inactive' => 'badge-danger'
                    ];
                    ?>
                    <?php foreach ($promoCodes as $code): ?>
                    <tr>
                        <td>
                            <div style="width: 56px; height: 56px; border-radius: 10px; overflow: hidden; background: #f3f4f6; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($code['image'])): ?>
                                    <img src="<?php echo imageUrl($code['image']); ?>" alt="promo" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-ticket-alt" style="color: #9ca3af;"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <strong style="font-family: monospace; font-size: 15px; background: var(--gray-100); padding: 4px 10px; border-radius: 6px; color: var(--secondary-color);">
                                <?php echo $code['code']; ?>
                            </strong>
                            <?php if (!empty($code['title_ar']) || !empty($code['title_en'])): ?>
                            <div style="font-size: 12px; color: #111827; margin-top: 6px; font-weight: 600;">
                                <?php echo !empty($code['title_ar']) ? $code['title_ar'] : $code['title_en']; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($code['title_ur'])): ?>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                <?php echo $code['title_ur']; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($code['description'])): ?>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 6px;"><?php echo $code['description']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: var(--success-color);">
                                <?php echo number_format((float) $code['discount_value'], 2); ?>
                                <?php echo $code['discount_type'] === 'percentage' ? '%' : '⃁'; ?>
                            </strong>
                            <div style="font-size: 11px; color: #6b7280;">
                                حد أدنى: <?php echo number_format((float) $code['min_order_amount'], 2); ?> ⃁
                            </div>
                            <?php if ($code['discount_type'] === 'percentage' && $code['max_discount_amount'] !== null): ?>
                            <div style="font-size: 11px; color: #6b7280;">
                                أقصى خصم: <?php echo number_format((float) $code['max_discount_amount'], 2); ?> ⃁
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $usageLimit = $code['usage_limit'] ? (int) $code['usage_limit'] : null; ?>
                            <div>
                                <?php echo (int) $code['used_count']; ?>
                                <?php if ($usageLimit !== null): ?>
                                    / <?php echo $usageLimit; ?>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">/ غير محدود</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($code['usage_limit_per_user'])): ?>
                                <div style="font-size: 11px; color: #6b7280;">لكل عميل: <?php echo (int) $code['usage_limit_per_user']; ?></div>
                            <?php endif; ?>
                            <?php if ($usageLimit !== null && $usageLimit > 0): ?>
                                <?php $usagePercent = min(100, (int) round(((int) $code['used_count'] / $usageLimit) * 100)); ?>
                                <div style="margin-top: 6px; width: 110px; height: 6px; background: #e5e7eb; border-radius: 99px;">
                                    <div style="width: <?php echo $usagePercent; ?>%; height: 100%; border-radius: 99px; background: <?php echo $usagePercent >= 100 ? '#ef4444' : '#22c55e'; ?>;"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12px;">
                            <div>من: <?php echo $code['start_date'] ?: 'مباشر'; ?></div>
                            <div>إلى: <?php echo $code['end_date'] ?: 'بدون نهاية'; ?></div>
                        </td>
                        <td>
                            <?php $state = $code['state']; ?>
                            <span class="badge <?php echo isset($stateClasses[$state]) ? $stateClasses[$state] : 'badge-secondary'; ?>">
                                <?php echo isset($stateLabels[$state]) ? $stateLabels[$state] : 'غير معروف'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="?action=edit&id=<?php echo $code['id']; ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف الكود؟');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="coupon_id" value="<?php echo $code['id']; ?>">
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
    <div class="modal" style="max-width: 660px;">
        <div class="modal-header">
            <h3 class="modal-title">إضافة كود خصم جديد</h3>
            <button class="modal-close" onclick="hideModal('add-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="form-label">الكود (إنجليزي/أرقام)</label>
                    <input type="text" name="code" class="form-control" style="text-transform: uppercase;" placeholder="SAVE20" required>
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <input type="text" name="description" class="form-control" placeholder="خصم خاص لفترة العروض">
                </div>

                <div class="form-group">
                    <label class="form-label">صورة العرض (اختياري)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">عنوان العرض (عربي)</label>
                        <input type="text" name="title_ar" class="form-control" placeholder="مثال: خصم الأسبوع">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عنوان العرض (English)</label>
                        <input type="text" name="title_en" class="form-control" placeholder="Example: Weekly Discount">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">عنوان العرض (اردو)</label>
                    <input type="text" name="title_ur" class="form-control" placeholder="مثال: ہفتہ وار رعایت">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">وصف العرض (عربي)</label>
                        <textarea name="description_ar" class="form-control" rows="3" placeholder="وصف يظهر في التطبيق"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف العرض (English)</label>
                        <textarea name="description_en" class="form-control" rows="3" placeholder="Description shown in app"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">وصف العرض (اردو)</label>
                    <textarea name="description_ur" class="form-control" rows="3" placeholder="ایپ میں دکھانے کے لیے تفصیل"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">نوع الخصم</label>
                        <select name="discount_type" class="form-control">
                            <option value="percentage">نسبة مئوية (%)</option>
                            <option value="fixed">مبلغ ثابت (⃁)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">قيمة الخصم</label>
                        <input type="number" name="discount_value" class="form-control" step="0.01" min="0.01" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الحد الأدنى للطلب</label>
                        <input type="number" name="min_order_amount" class="form-control" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">أقصى مبلغ للخصم (للخصم النسبي)</label>
                        <input type="number" name="max_discount_amount" class="form-control" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">حد الاستخدام الكلي (فارغ = غير محدود)</label>
                    <input type="number" name="usage_limit" class="form-control" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">حد الاستخدام لكل عميل (فارغ = غير محدود)</label>
                    <input type="number" name="usage_limit_per_user" class="form-control" min="1">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">تاريخ البداية</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ النهاية</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ الكود</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && isset($promoCode)): ?>
<div style="max-width: 720px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <a href="promo-codes.php" class="btn btn-outline">
            <i class="fas fa-arrow-right"></i>
            العودة إلى أكواد الخصم
        </a>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">تعديل الكود: <?php echo $promoCode['code']; ?></h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="coupon_id" value="<?php echo $promoCode['id']; ?>">

                <div class="form-group">
                    <label class="form-label">الكود</label>
                    <input type="text" name="code" class="form-control" style="text-transform: uppercase;" value="<?php echo $promoCode['code']; ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <input type="text" name="description" class="form-control" value="<?php echo $promoCode['description']; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">صورة العرض (اختياري)</label>
                    <?php if (!empty($promoCode['image'])): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?php echo imageUrl($promoCode['image']); ?>" alt="promo" style="width: 120px; height: 80px; object-fit: cover; border-radius: 10px; border: 1px solid #e5e7eb;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">عنوان العرض (عربي)</label>
                        <input type="text" name="title_ar" class="form-control" value="<?php echo htmlspecialchars((string) (isset($promoCode['title_ar']) ? $promoCode['title_ar'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عنوان العرض (English)</label>
                        <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars((string) (isset($promoCode['title_en']) ? $promoCode['title_en'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">عنوان العرض (اردو)</label>
                    <input type="text" name="title_ur" class="form-control" value="<?php echo htmlspecialchars((string) (isset($promoCode['title_ur']) ? $promoCode['title_ur'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">وصف العرض (عربي)</label>
                        <textarea name="description_ar" class="form-control" rows="3"><?php echo htmlspecialchars((string) (isset($promoCode['description_ar']) ? $promoCode['description_ar'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف العرض (English)</label>
                        <textarea name="description_en" class="form-control" rows="3"><?php echo htmlspecialchars((string) (isset($promoCode['description_en']) ? $promoCode['description_en'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">وصف العرض (اردو)</label>
                    <textarea name="description_ur" class="form-control" rows="3"><?php echo htmlspecialchars((string) (isset($promoCode['description_ur']) ? $promoCode['description_ur'] : ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">نوع الخصم</label>
                        <select name="discount_type" class="form-control">
                            <option value="percentage" <?php echo $promoCode['discount_type'] === 'percentage' ? 'selected' : ''; ?>>نسبة مئوية (%)</option>
                            <option value="fixed" <?php echo $promoCode['discount_type'] === 'fixed' ? 'selected' : ''; ?>>مبلغ ثابت (⃁)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">قيمة الخصم</label>
                        <input type="number" name="discount_value" class="form-control" step="0.01" min="0.01" value="<?php echo $promoCode['discount_value']; ?>" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">الحد الأدنى للطلب</label>
                        <input type="number" name="min_order_amount" class="form-control" step="0.01" min="0" value="<?php echo $promoCode['min_order_amount']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">أقصى مبلغ للخصم (للخصم النسبي)</label>
                        <input type="number" name="max_discount_amount" class="form-control" step="0.01" min="0" value="<?php echo $promoCode['max_discount_amount']; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">حد الاستخدام الكلي</label>
                    <input type="number" name="usage_limit" class="form-control" min="1" value="<?php echo $promoCode['usage_limit']; ?>">
                    <small style="color: #6b7280;">الاستخدام الحالي: <?php echo (int) $promoCode['used_count']; ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label">حد الاستخدام لكل عميل</label>
                    <input type="number" name="usage_limit_per_user" class="form-control" min="1" value="<?php echo htmlspecialchars((string) ($promoCode['usage_limit_per_user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <small style="color: #6b7280;">عدد مرات استخدام الكود لكل عميل</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">تاريخ البداية</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $promoCode['start_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ النهاية</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $promoCode['end_date']; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" style="width: 20px; height: 20px;" <?php echo $promoCode['is_active'] ? 'checked' : ''; ?>>
                        تفعيل الكود
                    </label>
                </div>
            </div>
            <div class="card-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                <a href="promo-codes.php" class="btn btn-outline">إلغاء</a>
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
