<?php
/**
 * صفحة إدارة البانرات الإعلانية
 * Banners Management Page
 */

require_once '../init.php';
requireLogin();

$pageTitle = 'البانرات الإعلانية';
$pageSubtitle = 'إدارة اللافتات الإعلانية في التطبيق';

$action = get('action', 'list');
$id = (int) get('id');

/**
 * تجهيز مخطط جدول البانرات لدعم التصميم الجديد.
 */
function ensureBannersPresentationSchema(): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }
    $schemaChecked = true;

    $requiredColumns = [
        'title_en' => "VARCHAR(255) NULL AFTER `title`",
        'title_ur' => "VARCHAR(255) NULL AFTER `title_en`",
        'subtitle' => "VARCHAR(255) NULL AFTER `title_ur`",
        'subtitle_en' => "VARCHAR(255) NULL AFTER `subtitle`",
        'subtitle_ur' => "VARCHAR(255) NULL AFTER `subtitle_en`",
        'background_color' => "VARCHAR(20) NOT NULL DEFAULT '#FBCC26' AFTER `subtitle_ur`",
        'background_color_end' => "VARCHAR(20) NULL DEFAULT NULL AFTER `background_color`",
    ];

    foreach ($requiredColumns as $column => $definition) {
        $exists = db()->fetch("SHOW COLUMNS FROM `banners` LIKE '{$column}'");
        if (!$exists) {
            db()->query("ALTER TABLE `banners` ADD COLUMN `{$column}` {$definition}");
        }
    }

    $positionColumn = db()->fetch("SHOW COLUMNS FROM `banners` LIKE 'position'");
    if ($positionColumn) {
        $positionType = strtolower((string) ($positionColumn['Type'] ?? ''));
        if (strpos($positionType, "'home_middle'") === false) {
            db()->query("ALTER TABLE `banners` MODIFY COLUMN `position` ENUM('home_slider','home_middle','home_mid','home_popup','category','offer') DEFAULT 'home_slider'");
        }
    }
}

/**
 * تنظيف لون HEX.
 */
function normalizeHexColor($value, $default = '#FBCC26'): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return strtoupper($default);
    }

    if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $raw)) {
        return strtoupper($default);
    }

    if ($raw[0] !== '#') {
        $raw = '#' . $raw;
    }

    return strtoupper($raw);
}

/**
 * تحديد لون النص الأنسب على الخلفية.
 */
function getContrastTextColor($hexColor): string
{
    $hex = ltrim(normalizeHexColor($hexColor), '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    return $luminance >= 150 ? '#1F2937' : '#FFFFFF';
}

/**
 * بناء CSS للخلفية (لون عادي أو تدرج).
 */
function bannerGradientCss(array $banner): string
{
    $start = normalizeHexColor($banner['background_color'] ?? '#FBCC26', '#FBCC26');
    $rawEnd = trim((string) ($banner['background_color_end'] ?? ''));
    $end = $rawEnd !== '' ? normalizeHexColor($rawEnd, $start) : $start;

    if ($start === $end) {
        return $start;
    }

    return "linear-gradient(135deg, {$start} 0%, {$end} 100%)";
}

function isHomeSliderPosition($position): bool
{
    return trim((string) $position) === 'home_slider';
}

ensureBannersPresentationSchema();

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    // إضافة بانر
    if ($postAction === 'add') {
        $position = post('position');
        $isHomeSlider = isHomeSliderPosition($position);
        $title = trim(post('title'));
        $titleEn = trim(post('title_en'));
        $titleUr = trim(post('title_ur'));
        $subtitle = trim(post('subtitle'));
        $subtitleEn = trim(post('subtitle_en'));
        $subtitleUr = trim(post('subtitle_ur'));
        $link_type = post('link_type');
        $link_id = post('link_id') ? (int) post('link_id') : null;
        $external_link = post('external_link');
        $sort_order = (int) post('sort_order');
        $start_date = !empty(post('start_date')) ? post('start_date') : null;
        $end_date = !empty(post('end_date')) ? post('end_date') : null;
        $background_color = $isHomeSlider
            ? normalizeHexColor(post('background_color'), '#FBCC26')
            : '#FBCC26';
        $use_gradient = $isHomeSlider && isset($_POST['use_gradient']);
        $background_color_end = $use_gradient
            ? normalizeHexColor(post('background_color_end'), $background_color)
            : null;

        if ($isHomeSlider && $title === '') {
            setFlashMessage('danger', 'نص البانر مطلوب');
            redirect('banners.php');
        }

        $link = ($link_type === 'external') ? $external_link : null;

        // رفع الصورة
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'banners');
            if ($upload['success']) {
                $imagePath = $upload['path'];
            }
        }

        if (!$imagePath) {
            setFlashMessage('danger', 'فشل رفع الصورة. الرجاء التأكد من اختيار صورة صالحة.');
            redirect('banners.php');
        }

        db()->insert('banners', [
            'title' => $title !== '' ? $title : null,
            'title_en' => $titleEn !== '' ? $titleEn : null,
            'title_ur' => $titleUr !== '' ? $titleUr : null,
            'subtitle' => $isHomeSlider && $subtitle !== '' ? $subtitle : null,
            'subtitle_en' => $isHomeSlider && $subtitleEn !== '' ? $subtitleEn : null,
            'subtitle_ur' => $isHomeSlider && $subtitleUr !== '' ? $subtitleUr : null,
            'background_color' => $background_color,
            'background_color_end' => $isHomeSlider ? $background_color_end : null,
            'image' => $imagePath,
            'position' => $position,
            'link_type' => $link_type,
            'link_id' => $link_id,
            'link' => $link,
            'sort_order' => $sort_order,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'is_active' => 1
        ]);

        logActivity('add_banner', 'banners', db()->getConnection()->lastInsertId());
        setFlashMessage('success', 'تم إضافة البانر بنجاح');
        redirect('banners.php');
    }

    // تعديل بانر
    if ($postAction === 'edit') {
        $bannerId = (int) post('banner_id');
        $existingBanner = db()->fetch("SELECT title, title_en, title_ur, subtitle, subtitle_en, subtitle_ur, background_color, background_color_end, link_type, link_id, link FROM banners WHERE id = ?", [$bannerId]);
        if (!$existingBanner) {
            setFlashMessage('danger', 'البانر غير موجود');
            redirect('banners.php');
        }

        $position = post('position');
        $isHomeSlider = isHomeSliderPosition($position);
        $titleInput = trim(post('title'));
        $title = $titleInput !== '' ? $titleInput : trim((string) ($existingBanner['title'] ?? ''));
        $titleEn = trim(post('title_en'));
        $titleUr = trim(post('title_ur'));
        $subtitle = trim(post('subtitle'));
        $subtitleEn = trim(post('subtitle_en'));
        $subtitleUr = trim(post('subtitle_ur'));
        $linkTypeInput = trim((string) post('link_type'));
        $link_type = $linkTypeInput !== '' ? $linkTypeInput : (string) ($existingBanner['link_type'] ?? 'none');
        $link_id = post('link_id') ? (int) post('link_id') : ($existingBanner['link_id'] !== null ? (int) $existingBanner['link_id'] : null);
        $external_link = trim((string) post('external_link'));
        $sort_order = (int) post('sort_order');
        $start_date = !empty(post('start_date')) ? post('start_date') : null;
        $end_date = !empty(post('end_date')) ? post('end_date') : null;
        $background_color = $isHomeSlider
            ? normalizeHexColor(post('background_color'), (string) ($existingBanner['background_color'] ?? '#FBCC26'))
            : normalizeHexColor((string) ($existingBanner['background_color'] ?? '#FBCC26'), '#FBCC26');
        $use_gradient = $isHomeSlider && isset($_POST['use_gradient']);
        $background_color_end = $use_gradient
            ? normalizeHexColor(post('background_color_end'), $background_color)
            : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($isHomeSlider && $title === '') {
            setFlashMessage('danger', 'نص البانر مطلوب');
            redirect('banners.php?action=edit&id=' . $bannerId);
        }

        if ($linkTypeInput === '') {
            $link = $existingBanner['link'] ?? null;
        } else {
            $link = ($link_type === 'external') ? $external_link : null;
        }

        $data = [
            'title' => $title !== '' ? $title : null,
            'title_en' => $titleEn !== '' ? $titleEn : null,
            'title_ur' => $titleUr !== '' ? $titleUr : null,
            'subtitle' => $isHomeSlider && $subtitle !== '' ? $subtitle : null,
            'subtitle_en' => $isHomeSlider && $subtitleEn !== '' ? $subtitleEn : null,
            'subtitle_ur' => $isHomeSlider && $subtitleUr !== '' ? $subtitleUr : null,
            'position' => $position,
            'link_type' => $link_type,
            'link_id' => $link_id,
            'link' => $link,
            'sort_order' => $sort_order,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'background_color' => $background_color,
            'background_color_end' => $isHomeSlider ? $background_color_end : null,
            'is_active' => $is_active
        ];

        // تحديث الصورة
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['image'], 'banners');
            if ($upload['success']) {
                $data['image'] = $upload['path'];
            }
        }

        db()->update('banners', $data, 'id = :id', ['id' => $bannerId]);
        logActivity('update_banner', 'banners', $bannerId);
        setFlashMessage('success', 'تم تحديث البانر بنجاح');
        redirect('banners.php');
    }

    // حذف بانر
    if ($postAction === 'delete') {
        $bannerId = (int) post('banner_id');
        db()->delete('banners', 'id = ?', [$bannerId]);
        logActivity('delete_banner', 'banners', $bannerId);
        setFlashMessage('success', 'تم حذف البانر بنجاح');
        redirect('banners.php');
    }
}

// البيانات المساعدة
$categories = getServiceCategoriesHierarchy(true);
$offers = db()->fetchAll("SELECT id, title_ar as title FROM offers WHERE is_active = 1");
$products = db()->fetchAll("SELECT id, name_ar FROM products WHERE is_active = 1");

// عرض قائمة البانرات
$banners = db()->fetchAll("SELECT * FROM banners ORDER BY position, sort_order");

// عرض بيانات للتعديل
if ($action === 'edit' && $id) {
    $banner = db()->fetch("SELECT * FROM banners WHERE id = ?", [$id]);
    if (!$banner) {
        setFlashMessage('danger', 'البانر غير موجود');
        redirect('banners.php');
    }
}

include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
        <button onclick="showModal('add-modal')" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            إضافة بانر جديد
        </button>
    </div>

    <div class="card animate-slideUp">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-images" style="color: var(--primary-color);"></i>
                البانرات الإعلانية
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($banners)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🖼️</div>
                    <h3>لا توجد بانرات</h3>
                    <p>أضف بانر بتصميم مخصص للصفحة الرئيسية</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>المعاينة</th>
                                <th>النص</th>
                                <th>المكان</th>
                                <th>الترتيب</th>
                                <th>الرابط</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($banners as $b): ?>
                                <?php
                                $previewBackground = bannerGradientCss($b);
                                $textColor = getContrastTextColor($b['background_color'] ?? '#FBCC26');
                                $isSliderPreview = isHomeSliderPosition($b['position'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($isSliderPreview): ?>
                                            <div style="position: relative; width: 170px; height: 72px; border-radius: 12px; overflow: hidden; background: <?php echo htmlspecialchars($previewBackground, ENT_QUOTES, 'UTF-8'); ?>; padding: 10px;">
                                                <div style="position: absolute; inset-inline-end: 6px; bottom: 0; width: 54px; height: 54px; display: flex; align-items: flex-end; justify-content: center;">
                                                    <?php if (!empty($b['image'])): ?>
                                                        <img src="<?php echo imageUrl($b['image']); ?>" alt=""
                                                            style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                    <?php endif; ?>
                                                </div>
                                                <div style="color: <?php echo $textColor; ?>; max-width: 98px; line-height: 1.25;">
                                                    <div style="font-size: 12px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars((string) ($b['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                    <?php if (!empty($b['subtitle'])): ?>
                                                        <div style="font-size: 10px; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.85;">
                                                            <?php echo htmlspecialchars((string) $b['subtitle'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <img src="<?php echo imageUrl($b['image']); ?>" alt=""
                                                style="width: 170px; height: 72px; object-fit: cover; border-radius: 12px;">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars((string) ($b['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if (!empty($b['title_en']) || !empty($b['title_ur'])): ?>
                                            <div class="text-muted small" style="margin-top: 4px;">
                                                <?php echo htmlspecialchars((string) ($b['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (!empty($b['title_en']) && !empty($b['title_ur'])): ?>
                                                    •
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars((string) ($b['title_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($b['subtitle'])): ?>
                                            <div class="text-muted small" style="margin-top: 4px;">
                                                <?php echo htmlspecialchars((string) $b['subtitle'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $positions = [
                                            'home_slider' => 'سلايدر الرئيسية',
                                            'home_middle' => 'بنر وسط الصفحة',
                                            'home_mid' => 'بنر وسط الصفحة (قديم)',
                                            'home_popup' => 'نافذة منبثقة',
                                            'category' => 'صفحة الأقسام',
                                            'offer' => 'صفحة العروض'
                                        ];
                                        echo $positions[$b['position']] ?? $b['position'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo (int) $b['sort_order']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($b['link_type'] === 'none') {
                                            echo '<span class="text-muted">بدون رابط</span>';
                                        } elseif ($b['link_type'] === 'external') {
                                            echo '<a href="' . htmlspecialchars((string) $b['link'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="text-primary"><i class="fas fa-external-link-alt"></i> رابط خارجي</a>';
                                        } else {
                                            echo '<span class="badge badge-info">' . htmlspecialchars((string) $b['link_type'], ENT_QUOTES, 'UTF-8') . ': ' . (int) $b['link_id'] . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $b['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $b['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="?action=edit&id=<?php echo (int) $b['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display: inline;"
                                                onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="banner_id" value="<?php echo (int) $b['id']; ?>">
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
        <div class="modal" style="max-width: 640px;">
            <div class="modal-header">
                <h3 class="modal-title">إضافة بانر جديد</h3>
                <button class="modal-close" onclick="hideModal('add-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" data-banner-form="1">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="form-group">
                        <label class="form-label">النص الأساسي على البنر</label>
                        <input type="text" name="title" class="form-control">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">النص الأساسي (إنجليزي)</label>
                            <input type="text" name="title_en" class="form-control" placeholder="English title">
                        </div>
                        <div class="form-group">
                            <label class="form-label">النص الأساسي (أوردو)</label>
                            <input type="text" name="title_ur" class="form-control" placeholder="Urdu title">
                        </div>
                    </div>

                    <div data-slider-only>
                        <div class="form-group">
                            <label class="form-label">نص إضافي (اختياري)</label>
                            <input type="text" name="subtitle" class="form-control" placeholder="مثال: خصم حتى 30% لفترة محدودة">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label class="form-label">نص إضافي (إنجليزي)</label>
                                <input type="text" name="subtitle_en" class="form-control" placeholder="Optional subtitle in English">
                            </div>
                            <div class="form-group">
                                <label class="form-label">نص إضافي (أوردو)</label>
                                <input type="text" name="subtitle_ur" class="form-control" placeholder="Urdu subtitle">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">لون البنر</label>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <input type="color" name="background_color" class="form-control" value="#FBCC26"
                                    style="width: 60px; padding: 2px; height: 38px;">
                                <span class="text-muted small">لون بداية البنر والهيدر في التطبيق</span>
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="use_gradient" data-gradient-toggle checked>
                                <span>استخدام تدرج لوني</span>
                            </label>
                            <div data-gradient-target style="margin-top: 8px;">
                                <input type="color" name="background_color_end" class="form-control" value="#F5C01F"
                                    style="width: 60px; padding: 2px; height: 38px;">
                                <span class="text-muted small" style="margin-inline-start: 8px;">لون نهاية التدرج</span>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">مكان العرض</label>
                            <select name="position" class="form-control" data-banner-position onchange="toggleBannerFormMode(this)">
                                <option value="home_slider">سلايدر الرئيسية</option>
                                <option value="home_middle">بنر وسط الصفحة الرئيسية</option>
                                <option value="home_popup">نافذة منبثقة (Pop-up)</option>
                                <option value="category">صفحة الأقسام</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الترتيب</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" data-image-label>الصورة الجانبية (PNG بدون خلفية)</label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                        <div class="text-muted small" style="margin-top: 6px;" data-image-hint>ستظهر في يمين بنر سلايدر الرئيسية.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">نوع الرابط</label>
                        <select name="link_type" class="form-control" id="linkTypeSelect" onchange="toggleLinkInputs()">
                            <option value="none">بدون رابط</option>
                            <option value="category">قسم خدمات</option>
                            <option value="offer">عرض</option>
                            <option value="product">منتج</option>
                            <option value="external">رابط خارجي</option>
                        </select>
                    </div>

                    <div id="categoryInput" class="form-group link-input" style="display: none;">
                        <label class="form-label">اختر القسم</label>
                        <select name="link_id" class="form-control" disabled>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['display_name_ar'] ?? $c['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="offerInput" class="form-group link-input" style="display: none;">
                        <label class="form-label">اختر العرض</label>
                        <select name="link_id" class="form-control" disabled>
                            <?php foreach ($offers as $o): ?>
                                <option value="<?php echo (int) $o['id']; ?>">
                                    <?php echo htmlspecialchars((string) $o['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="productInput" class="form-group link-input" style="display: none;">
                        <label class="form-label">اختر المنتج</label>
                        <select name="link_id" class="form-control" disabled>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo (int) $p['id']; ?>">
                                    <?php echo htmlspecialchars((string) $p['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="externalInput" class="form-group link-input" style="display: none;">
                        <label class="form-label">الرابط الخارجي</label>
                        <input type="url" name="external_link" class="form-control" placeholder="https://example.com">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">تاريخ البداية (اختياري)</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">تاريخ النهاية (اختياري)</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideModal('add-modal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'edit' && isset($banner)): ?>
    <?php
    $editIsHomeSlider = isHomeSliderPosition($banner['position'] ?? '');
    $editGradientEnabled = $editIsHomeSlider && !empty($banner['background_color_end']);
    $editStartColor = normalizeHexColor($banner['background_color'] ?? '#FBCC26', '#FBCC26');
    $editEndColor = $editGradientEnabled
        ? normalizeHexColor($banner['background_color_end'], $editStartColor)
        : $editStartColor;
    ?>
    <div class="card animate-slideUp" style="max-width: 680px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title">تعديل البانر:
                <?php echo htmlspecialchars((string) $banner['title'], ENT_QUOTES, 'UTF-8'); ?>
            </h3>
        </div>
        <form method="POST" enctype="multipart/form-data" data-banner-form="1">
            <div class="card-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="banner_id" value="<?php echo (int) $banner['id']; ?>">

                <div class="form-group">
                    <label class="form-label">النص الأساسي على البنر</label>
                    <input type="text" name="title" class="form-control"
                        value="<?php echo htmlspecialchars((string) $banner['title'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $editIsHomeSlider ? 'required' : ''; ?>>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">النص الأساسي (إنجليزي)</label>
                        <input type="text" name="title_en" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($banner['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">النص الأساسي (أوردو)</label>
                        <input type="text" name="title_ur" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($banner['title_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div data-slider-only>
                    <div class="form-group">
                        <label class="form-label">نص إضافي (اختياري)</label>
                        <input type="text" name="subtitle" class="form-control"
                            value="<?php echo htmlspecialchars((string) ($banner['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="مثال: خصم حتى 30% لفترة محدودة">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">نص إضافي (إنجليزي)</label>
                            <input type="text" name="subtitle_en" class="form-control"
                                value="<?php echo htmlspecialchars((string) ($banner['subtitle_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Optional subtitle in English">
                        </div>
                        <div class="form-group">
                            <label class="form-label">نص إضافي (أوردو)</label>
                            <input type="text" name="subtitle_ur" class="form-control"
                                value="<?php echo htmlspecialchars((string) ($banner['subtitle_ur'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Urdu subtitle">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">لون البنر</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                            <input type="color" name="background_color" class="form-control"
                                value="<?php echo htmlspecialchars($editStartColor, ENT_QUOTES, 'UTF-8'); ?>"
                                style="width: 60px; padding: 2px; height: 38px;">
                            <span class="text-muted small">لون بداية البنر والهيدر في التطبيق</span>
                        </div>
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="use_gradient" data-gradient-toggle <?php echo $editGradientEnabled ? 'checked' : ''; ?>>
                            <span>استخدام تدرج لوني</span>
                        </label>
                        <div data-gradient-target style="margin-top: 8px;">
                            <input type="color" name="background_color_end" class="form-control"
                                value="<?php echo htmlspecialchars($editEndColor, ENT_QUOTES, 'UTF-8'); ?>"
                                style="width: 60px; padding: 2px; height: 38px;">
                            <span class="text-muted small" style="margin-inline-start: 8px;">لون نهاية التدرج</span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">مكان العرض</label>
                        <select name="position" class="form-control" data-banner-position onchange="toggleBannerFormMode(this)">
                            <option value="home_slider" <?php echo $banner['position'] == 'home_slider' ? 'selected' : ''; ?>>
                                سلايدر الرئيسية</option>
                            <option value="home_middle" <?php echo $banner['position'] == 'home_middle' ? 'selected' : ''; ?>>
                                بنر وسط الصفحة الرئيسية</option>
                            <option value="home_popup" <?php echo $banner['position'] == 'home_popup' ? 'selected' : ''; ?>>
                                نافذة منبثقة</option>
                            <option value="category" <?php echo $banner['position'] == 'category' ? 'selected' : ''; ?>>صفحة
                                الأقسام</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control"
                            value="<?php echo (int) $banner['sort_order']; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-image-label>الصورة الجانبية (PNG بدون خلفية)</label>
                    <?php if (!empty($banner['image'])): ?>
                        <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                            <img src="<?php echo imageUrl($banner['image']); ?>" alt=""
                                style="height: 72px; width: 72px; border-radius: 10px; object-fit: contain; background: #f8fafc; border: 1px solid #e5e7eb;">
                            <span class="text-muted small">الصورة الحالية</span>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="text-muted small" style="margin-top: 6px;" data-image-hint>ستظهر في يمين بنر سلايدر الرئيسية.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">نوع الرابط</label>
                    <select name="link_type" class="form-control" id="editLinkTypeSelect" onchange="toggleEditLinkInputs()">
                        <option value="none" <?php echo ($banner['link_type'] ?? '') === 'none' ? 'selected' : ''; ?>>بدون رابط</option>
                        <option value="category" <?php echo ($banner['link_type'] ?? '') === 'category' ? 'selected' : ''; ?>>قسم خدمات</option>
                        <option value="offer" <?php echo ($banner['link_type'] ?? '') === 'offer' ? 'selected' : ''; ?>>عرض</option>
                        <option value="product" <?php echo ($banner['link_type'] ?? '') === 'product' ? 'selected' : ''; ?>>منتج</option>
                        <option value="external" <?php echo ($banner['link_type'] ?? '') === 'external' ? 'selected' : ''; ?>>رابط خارجي</option>
                    </select>
                </div>

                <div id="editCategoryInput" class="form-group edit-link-input" style="display: none;">
                    <label class="form-label">اختر القسم</label>
                    <select name="link_id" class="form-control" disabled>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo ((int) ($banner['link_id'] ?? 0) === (int) $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['display_name_ar'] ?? $c['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="editOfferInput" class="form-group edit-link-input" style="display: none;">
                    <label class="form-label">اختر العرض</label>
                    <select name="link_id" class="form-control" disabled>
                        <?php foreach ($offers as $o): ?>
                            <option value="<?php echo (int) $o['id']; ?>" <?php echo ((int) ($banner['link_id'] ?? 0) === (int) $o['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $o['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="editProductInput" class="form-group edit-link-input" style="display: none;">
                    <label class="form-label">اختر المنتج</label>
                    <select name="link_id" class="form-control" disabled>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($banner['link_id'] ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $p['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="editExternalInput" class="form-group edit-link-input" style="display: none;">
                    <label class="form-label">الرابط الخارجي</label>
                    <input type="url" name="external_link" class="form-control" value="<?php echo htmlspecialchars((string) ($banner['link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://example.com">
                </div>

                <div class="form-group">
                    <label class="form-label">تفعيل البانر</label>
                    <input type="checkbox" name="is_active" <?php echo $banner['is_active'] ? 'checked' : ''; ?>>
                </div>

                <div class="alert alert-info">سيظهر زر "عرض المزيد" في سلايدر الرئيسية فقط عندما يكون نوع الرابط غير "بدون رابط".</div>

            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<script>
    function toggleLinkInputs() {
        const typeSelect = document.getElementById('linkTypeSelect');
        if (!typeSelect) return;

        const type = typeSelect.value;
        const inputs = document.querySelectorAll('.link-input');

        inputs.forEach(div => {
            div.style.display = 'none';
            const select = div.querySelector('select');
            if (select) {
                select.disabled = true;
                select.removeAttribute('name');
            }
        });

        let targetId = '';
        if (type === 'category') targetId = 'categoryInput';
        else if (type === 'offer') targetId = 'offerInput';
        else if (type === 'product') targetId = 'productInput';
        else if (type === 'external') targetId = 'externalInput';

        if (targetId) {
            const targetDiv = document.getElementById(targetId);
            if (!targetDiv) return;

            targetDiv.style.display = 'block';
            const select = targetDiv.querySelector('select');
            if (select) {
                select.disabled = false;
                select.setAttribute('name', 'link_id');
            }
        }
    }

    function toggleEditLinkInputs() {
        const typeSelect = document.getElementById('editLinkTypeSelect');
        if (!typeSelect) return;

        const type = typeSelect.value;
        const inputs = document.querySelectorAll('.edit-link-input');

        inputs.forEach(div => {
            div.style.display = 'none';
            const select = div.querySelector('select');
            if (select) {
                select.disabled = true;
                select.removeAttribute('name');
            }
        });

        let targetId = '';
        if (type === 'category') targetId = 'editCategoryInput';
        else if (type === 'offer') targetId = 'editOfferInput';
        else if (type === 'product') targetId = 'editProductInput';
        else if (type === 'external') targetId = 'editExternalInput';

        if (targetId) {
            const targetDiv = document.getElementById(targetId);
            if (!targetDiv) return;

            targetDiv.style.display = 'block';
            const select = targetDiv.querySelector('select');
            if (select) {
                select.disabled = false;
                select.setAttribute('name', 'link_id');
            }
        }
    }

    function toggleBannerFormMode(positionSelect) {
        if (!positionSelect) return;
        const form = positionSelect.closest('form');
        if (!form) return;

        const isHomeSlider = positionSelect.value === 'home_slider';
        const sliderOnlyBlocks = form.querySelectorAll('[data-slider-only]');
        sliderOnlyBlocks.forEach(block => {
            block.style.display = isHomeSlider ? 'block' : 'none';
            const inputs = block.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.disabled = !isHomeSlider;
            });
        });

        const titleInput = form.querySelector('input[name="title"]');
        if (titleInput) {
            titleInput.required = isHomeSlider;
        }

        const imageLabel = form.querySelector('[data-image-label]');
        if (imageLabel) {
            imageLabel.textContent = isHomeSlider
                ? 'الصورة الجانبية (PNG بدون خلفية)'
                : 'صورة البنر';
        }

        const imageHint = form.querySelector('[data-image-hint]');
        if (imageHint) {
            imageHint.textContent = isHomeSlider
                ? 'ستظهر في يمين بنر سلايدر الرئيسية.'
                : 'ستظهر بالشكل الطبيعي لهذا النوع من البنرات.';
        }
    }

    function setupGradientControls(form) {
        const toggle = form.querySelector('[data-gradient-toggle]');
        const target = form.querySelector('[data-gradient-target]');
        if (!toggle || !target) return;

        const syncEndWithStart = () => {
            const startInput = form.querySelector('input[name="background_color"]');
            const endInput = form.querySelector('input[name="background_color_end"]');
            if (!startInput || !endInput) return;
            if (!toggle.checked) {
                endInput.value = startInput.value;
            }
        };

        const updateVisibility = () => {
            const positionSelect = form.querySelector('select[name="position"]');
            const isHomeSlider = !positionSelect || positionSelect.value === 'home_slider';
            target.style.display = (isHomeSlider && toggle.checked) ? 'block' : 'none';
            syncEndWithStart();
        };

        toggle.addEventListener('change', updateVisibility);
        const startInput = form.querySelector('input[name="background_color"]');
        if (startInput) {
            startInput.addEventListener('change', () => {
                if (!toggle.checked) {
                    const endInput = form.querySelector('input[name="background_color_end"]');
                    if (endInput) {
                        endInput.value = startInput.value;
                    }
                }
            });
        }

        updateVisibility();
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleLinkInputs();
        toggleEditLinkInputs();
        const forms = document.querySelectorAll('form[data-banner-form]');
        forms.forEach(form => {
            setupGradientControls(form);
            const positionSelect = form.querySelector('select[data-banner-position]');
            if (positionSelect) {
                toggleBannerFormMode(positionSelect);
                positionSelect.addEventListener('change', () => {
                    toggleBannerFormMode(positionSelect);
                    const gradientTarget = form.querySelector('[data-gradient-target]');
                    const gradientToggle = form.querySelector('[data-gradient-toggle]');
                    if (gradientTarget && gradientToggle) {
                        const isHomeSlider = positionSelect.value === 'home_slider';
                        gradientTarget.style.display = (isHomeSlider && gradientToggle.checked) ? 'block' : 'none';
                    }
                });
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
