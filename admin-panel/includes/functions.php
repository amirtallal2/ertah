<?php
/**
 * دوال مساعدة
 * Helper Functions
 */

/**
 * إعادة التوجيه
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * طباعة JSON
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * تنسيق التاريخ بالعربي
 */
function formatDateAr($date, $format = 'Y/m/d')
{
    if (empty($date))
        return '-';
    return date($format, strtotime($date));
}

/**
 * تنسيق التاريخ والوقت
 */
function formatDateTime($date)
{
    if (empty($date))
        return '-';
    return date('Y/m/d H:i', strtotime($date));
}

/**
 * الوقت المنقضي
 */
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'منذ لحظات';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "منذ {$mins} دقيقة";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "منذ {$hours} ساعة";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "منذ {$days} يوم";
    } else {
        return formatDateAr($datetime);
    }
}

/**
 * تنسيق المبلغ
 */
function formatMoney($amount, $currency = '⃁')
{
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * تنسيق رقم الهاتف
 */
function formatPhone($phone)
{
    return $phone;
}

/**
 * اختصار النص
 */
function truncate($text, $length = 100, $suffix = '...')
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * تنظيف المدخلات
 */
function clean($data)
{
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * الحصول على قيمة من POST
 */
function post($key, $default = '')
{
    return isset($_POST[$key]) ? clean($_POST[$key]) : $default;
}

/**
 * الحصول على قيمة من GET
 */
function get($key, $default = '')
{
    return isset($_GET[$key]) ? clean($_GET[$key]) : $default;
}

/**
 * رفع ملف
 */
function uploadFile($file, $folder = 'general', $allowedTypes = null)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف'];
    }

    $allowedTypes = $allowedTypes ?? ALLOWED_IMAGE_TYPES;
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => "نوع الملف غير مسموح ($extension)"];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً'];
    }

    $uploadDir = UPLOAD_DIR . $folder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $folder . '/' . $filename
        ];
    }

    return ['success' => false, 'message' => 'فشل في حفظ الملف'];
}

/**
 * حذف ملف
 */
function deleteFile($path)
{
    $fullPath = UPLOAD_DIR . $path;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * رابط الصورة
 */
function imageUrl($path, $default = 'assets/images/default.png')
{
    if (empty($path)) {
        // If default is already a full URL, return as-is
        if (filter_var($default, FILTER_VALIDATE_URL)) {
            return $default;
        }
        return APP_URL . '/' . $default;
    }
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    // Check if path already includes 'uploads/' to avoid duplication
    if (strpos($path, 'uploads/') === 0) {
        return APP_URL . '/' . $path;
    }
    // Return absolute URL
    return APP_URL . '/uploads/' . $path;
}

function mediaValueLooksLikeFile($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return true;
    }

    if (strpos($value, '/') !== false || strpos($value, '\\') !== false) {
        return true;
    }

    return (bool) preg_match('/\.(png|jpe?g|gif|webp|svg|avif)$/i', $value);
}

function mediaUrlOrNull($value)
{
    $value = trim((string) $value);
    if ($value === '' || !mediaValueLooksLikeFile($value)) {
        return null;
    }

    return imageUrl($value);
}

function normalizeServiceCategoryLabel($value)
{
    $value = trim((string) $value);
    $value = str_replace(['أ', 'إ', 'آ', 'ى', 'ة'], ['ا', 'ا', 'ا', 'ي', 'ه'], $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return strtolower($value);
}

function isOtherServiceCategoryLabel($nameAr, $nameEn = '')
{
    $ar = normalizeServiceCategoryLabel($nameAr);
    $en = normalizeServiceCategoryLabel($nameEn);

    if ($ar !== '' && strpos($ar, 'خدم') !== false && strpos($ar, 'اخر') !== false) {
        return true;
    }

    if (in_array($en, ['other', 'other service', 'other services'], true)) {
        return true;
    }

    return $en !== '' && strpos($en, 'other') !== false && strpos($en, 'service') !== false;
}

function serviceCategoryImageForApi($image)
{
    return mediaUrlOrNull($image);
}

function serviceCategoryIconForApi($icon, $nameAr = '', $nameEn = '')
{
    $icon = trim((string) $icon);
    if ($icon === '') {
        return null;
    }

    if (mediaValueLooksLikeFile($icon)) {
        return imageUrl($icon);
    }

    if (isOtherServiceCategoryLabel($nameAr, $nameEn)) {
        return null;
    }

    return $icon;
}

function serviceCategoryPrimaryMediaForApi($icon, $image, $nameAr = '', $nameEn = '')
{
    $imageUrl = serviceCategoryImageForApi($image);
    $iconValue = serviceCategoryIconForApi($icon, $nameAr, $nameEn);

    if (isOtherServiceCategoryLabel($nameAr, $nameEn) && $imageUrl !== null) {
        return $imageUrl;
    }

    return $iconValue ?? $imageUrl;
}

function serviceCategoryDedupeKeyForApi(array $category)
{
    $specialModule = trim((string) ($category['special_module'] ?? ''));
    if ($specialModule !== '') {
        return 'module:' . strtolower($specialModule);
    }

    $nameAr = $category['name_ar'] ?? '';
    $nameEn = $category['name_en'] ?? '';
    if (isOtherServiceCategoryLabel($nameAr, $nameEn)) {
        return 'category:other_service';
    }

    $label = normalizeServiceCategoryLabel($nameAr) . '|' . normalizeServiceCategoryLabel($nameEn);
    if ($label === '|') {
        return 'id:' . (int) ($category['id'] ?? 0);
    }

    $parentId = (int) ($category['parent_id'] ?? 0);
    return 'category:' . $parentId . ':' . $label;
}

function serviceCategoryApiRank(array $category)
{
    $rank = 0;
    if (!empty($category['image']) && mediaValueLooksLikeFile($category['image'])) {
        $rank += 100;
    }
    if (!empty($category['icon']) && mediaValueLooksLikeFile($category['icon'])) {
        $rank += 25;
    }
    if (!array_key_exists('is_active', $category) || (int) $category['is_active'] === 1 || $category['is_active'] === true) {
        $rank += 10;
    }

    $sortOrder = (int) ($category['sort_order'] ?? 0);
    $id = (int) ($category['id'] ?? 0);
    return [$rank, -$sortOrder, -$id];
}

function serviceCategoryApiCandidateIsBetter(array $candidate, array $current)
{
    $candidateRank = serviceCategoryApiRank($candidate);
    $currentRank = serviceCategoryApiRank($current);

    for ($i = 0; $i < count($candidateRank); $i++) {
        if ($candidateRank[$i] === $currentRank[$i]) {
            continue;
        }
        return $candidateRank[$i] > $currentRank[$i];
    }

    return false;
}

function deduplicateServiceCategoriesForApi(array $categories)
{
    $byKey = [];
    $order = [];

    foreach ($categories as $category) {
        if (!is_array($category)) {
            continue;
        }

        if (isset($category['sub_categories']) && is_array($category['sub_categories'])) {
            $category['sub_categories'] = deduplicateServiceCategoriesForApi($category['sub_categories']);
        }

        $key = serviceCategoryDedupeKeyForApi($category);
        if (!isset($byKey[$key])) {
            $byKey[$key] = $category;
            $order[] = $key;
            continue;
        }

        if (serviceCategoryApiCandidateIsBetter($category, $byKey[$key])) {
            $byKey[$key] = $category;
        }
    }

    $result = [];
    foreach ($order as $key) {
        if (isset($byKey[$key])) {
            $result[] = $byKey[$key];
        }
    }

    usort($result, fn($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
    return $result;
}

/**
 * توليد رمز عشوائي
 */
function generateCode($length = 8, $type = 'alphanumeric')
{
    $chars = match ($type) {
        'numeric' => '0123456789',
        'alpha' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        default => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
    };

    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * توليد رقم الطلب
 */
function generateOrderNumber()
{
    return 'RT' . date('ymd') . generateCode(4, 'numeric');
}

/**
 * توليد رقم التذكرة
 */
function generateTicketNumber()
{
    return 'TK' . date('ymd') . generateCode(4, 'numeric');
}

/**
 * حالة الطلب بالعربي
 */
function getOrderStatusAr($status)
{
    $statuses = [
        'pending' => 'قيد الانتظار',
        'accepted' => 'مقبول',
        'on_the_way' => 'في الطريق',
        'arrived' => 'وصل',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'rejected' => 'مرفوض'
    ];
    return $statuses[$status] ?? $status;
}

/**
 * لون حالة الطلب
 */
function getOrderStatusColor($status)
{
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'accepted' => 'bg-blue-100 text-blue-800',
        'on_the_way' => 'bg-indigo-100 text-indigo-800',
        'arrived' => 'bg-purple-100 text-purple-800',
        'in_progress' => 'bg-orange-100 text-orange-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'rejected' => 'bg-gray-100 text-gray-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * حالة مقدم الخدمة بالعربي
 */
function getProviderStatusAr($status)
{
    $statuses = [
        'pending' => 'قيد المراجعة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'suspended' => 'موقوف'
    ];
    return $statuses[$status] ?? $status;
}

/**
 * لون حالة مقدم الخدمة
 */
function getProviderStatusColor($status)
{
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'approved' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'suspended' => 'bg-gray-100 text-gray-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * حالة الدفع بالعربي
 */
function getPaymentStatusAr($status)
{
    $statuses = [
        'pending' => 'قيد الانتظار',
        'paid' => 'مدفوع',
        'refunded' => 'مسترد',
        'failed' => 'فشل'
    ];
    return $statuses[$status] ?? $status;
}

/**
 * أولوية الشكوى بالعربي
 */
function getComplaintPriorityAr($priority)
{
    $priorities = [
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
        'urgent' => 'عاجلة'
    ];
    return $priorities[$priority] ?? $priority;
}

/**
 * إظهار رسالة نجاح
 */
function setFlashMessage($type, $message)
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * الحصول على رسالة الفلاش
 */
function getFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * التصفح (Pagination)
 */
function paginate($total, $currentPage = 1, $perPage = ITEMS_PER_PAGE)
{
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * إعدادات التطبيق
 */
function getSetting($key, $default = null)
{
    static $settings = null;

    if ($settings === null) {
        $results = db()->fetchAll("SELECT key_name, value FROM app_settings");
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['key_name']] = $row['value'];
        }
    }

    return $settings[$key] ?? $default;
}

/**
 * تحديث إعداد
 */
function updateSetting($key, $value)
{
    return db()->update(
        'app_settings',
        ['value' => $value],
        'key_name = :key',
        ['key' => $key]
    );
}

/**
 * هل جدول فئات الخدمات يحتوي على parent_id؟
 */
function hasServiceCategoryParentColumn()
{
    static $hasParentColumn = null;

    if ($hasParentColumn !== null) {
        return $hasParentColumn;
    }

    try {
        $column = db()->fetch("SHOW COLUMNS FROM service_categories LIKE 'parent_id'");
        $hasParentColumn = !empty($column);
    } catch (Throwable $e) {
        $hasParentColumn = false;
    }

    return $hasParentColumn;
}

/**
 * جلب فئات الخدمات مع اسم عرض هرمي (الرئيسي > الفرعي).
 */
function getServiceCategoriesHierarchy($onlyActive = true)
{
    static $cache = [];
    $cacheKey = $onlyActive ? 'active' : 'all';

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $where = $onlyActive ? 'WHERE c.is_active = 1' : '';

    if (hasServiceCategoryParentColumn()) {
        $rows = db()->fetchAll("
            SELECT c.*, p.name_ar AS parent_name_ar, p.name_en AS parent_name_en
            FROM service_categories c
            LEFT JOIN service_categories p ON p.id = c.parent_id
            {$where}
            ORDER BY COALESCE(c.parent_id, c.id) ASC,
                     CASE WHEN c.parent_id IS NULL THEN 0 ELSE 1 END ASC,
                     c.sort_order ASC,
                     c.id ASC
        ");
    } else {
        $rows = db()->fetchAll("
            SELECT c.*, NULL AS parent_name_ar, NULL AS parent_name_en
            FROM service_categories c
            {$where}
            ORDER BY c.sort_order ASC, c.id ASC
        ");
    }

    foreach ($rows as &$row) {
        $parentAr = trim((string) ($row['parent_name_ar'] ?? ''));
        $parentEn = trim((string) ($row['parent_name_en'] ?? ''));
        $nameAr = trim((string) ($row['name_ar'] ?? ''));
        $nameEn = trim((string) ($row['name_en'] ?? ''));

        $row['is_sub_category'] = !empty($row['parent_id']);
        $row['display_name_ar'] = $parentAr !== '' ? ($parentAr . ' > ' . $nameAr) : $nameAr;
        $row['display_name_en'] = $parentEn !== '' ? ($parentEn . ' > ' . $nameEn) : $nameEn;
    }
    unset($row);

    $cache[$cacheKey] = $rows;
    return $rows;
}

/**
 * خريطة أسماء الفئات بالاسم الهرمي.
 */
function getServiceCategoryDisplayMap($onlyActive = true)
{
    $map = [];
    foreach (getServiceCategoriesHierarchy($onlyActive) as $category) {
        $map[(int) $category['id']] = $category['display_name_ar'] ?? ($category['name_ar'] ?? '');
    }
    return $map;
}
