<?php
/**
 * Mobile API - Offers (Promo Codes)
 * العروض (تعتمد على أكواد الخصم)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/service_areas.php';

serviceAreaEnsureSchema();
ensurePromoCodesMediaSchema();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getOffers();
        break;
    case 'detail':
        getOfferDetail();
        break;
    case 'validate-promo':
    case 'validate_promo':
        validatePromoCode($input);
        break;
    default:
        sendError('Invalid action', 400);
}

/**
 * Ensure promo_codes has image/title/description columns used by mobile UI.
 */
function ensurePromoCodesMediaSchema(): void
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'promo_codes'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
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
    ];

    foreach ($columns as $column => $definition) {
        $safeColumn = $conn->real_escape_string($column);
        $columnCheck = $conn->query("SHOW COLUMNS FROM `promo_codes` LIKE '{$safeColumn}'");
        if ($columnCheck && $columnCheck->num_rows > 0) {
            continue;
        }
        $conn->query("ALTER TABLE `promo_codes` ADD COLUMN `{$column}` {$definition}");
    }
}

function isDateOpenRange(?string $value): bool
{
    $date = trim((string) ($value ?? ''));
    return $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00';
}

function activeDateRangeSql(string $tableAlias = ''): string
{
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';

    return "{$prefix}is_active = 1
           AND ({$prefix}start_date IS NULL OR {$prefix}start_date = '' OR {$prefix}start_date = '0000-00-00' OR {$prefix}start_date <= CURDATE())
           AND ({$prefix}end_date IS NULL OR {$prefix}end_date = '' OR {$prefix}end_date = '0000-00-00' OR {$prefix}end_date >= CURDATE())";
}

function tableExistsOffers(string $table): bool
{
    global $conn;

    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        $cache[$table] = false;
        return false;
    }

    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    $cache[$table] = $res && $res->num_rows > 0;
    return $cache[$table];
}

function fetchLegacyOffersRows(?int $offerId = null): array
{
    global $conn;

    if (!tableExistsOffers('offers')) {
        return [];
    }

    $where = activeDateRangeSql();
    $types = '';
    $params = [];

    if ($offerId !== null && $offerId > 0) {
        $where .= ' AND id = ?';
        $types .= 'i';
        $params[] = $offerId;
    }

    $sql = "SELECT *
            FROM offers
            WHERE {$where}
            ORDER BY created_at DESC";

    if ($offerId !== null && $offerId > 0) {
        $sql .= ' LIMIT 1';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Get all active promo codes as offers.
 */
function getOffers(): void
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT *
         FROM promo_codes
         WHERE " . activeDateRangeSql() . "
         ORDER BY created_at DESC"
    );

    if (!$stmt) {
        sendError('Database error: ' . $conn->error, 500);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $offers = [];
    while ($row = $result->fetch_assoc()) {
        $offers[] = formatPromoOffer($row);
    }

    // Backward compatibility:
    // legacy admin data may still exist in `offers` while promo_codes is empty.
    if (empty($offers)) {
        $legacyRows = fetchLegacyOffersRows();
        foreach ($legacyRows as $legacyRow) {
            $offers[] = formatLegacyOffer($legacyRow);
        }
    }

    sendSuccess($offers);
}

/**
 * Get single promo code detail as offer payload.
 */
function getOfferDetail(): void
{
    global $conn;

    $offerId = (int) ($_GET['id'] ?? 0);
    if ($offerId <= 0) {
        sendError('Offer id is required', 422);
    }

    $stmt = $conn->prepare(
        "SELECT *
         FROM promo_codes
         WHERE id = ?
           AND " . activeDateRangeSql() . "
         LIMIT 1"
    );

    if (!$stmt) {
        sendError('Database error: ' . $conn->error, 500);
    }

    $stmt->bind_param("i", $offerId);
    $stmt->execute();
    $offer = $stmt->get_result()->fetch_assoc();

    if (!$offer) {
        $legacyRows = fetchLegacyOffersRows($offerId);
        if (empty($legacyRows)) {
            sendError('Offer not found', 404);
        }

        sendSuccess(formatLegacyOffer($legacyRows[0]));
    }

    sendSuccess(formatPromoOffer($offer));
}

/**
 * Validate promo code.
 */
function validatePromoCode(array $input): void
{
    global $conn;

    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $orderAmount = (float) ($input['order_amount'] ?? 0);

    if ($code === '') {
        sendError('Promo code is required', 422);
    }

    $stmt = $conn->prepare(
        "SELECT *
         FROM promo_codes
         WHERE code = ?
           AND " . activeDateRangeSql() . "
         LIMIT 1"
    );

    if (!$stmt) {
        sendError('Database error: ' . $conn->error, 500);
    }

    $stmt->bind_param("s", $code);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();

    if (!$promo) {
        sendError('كود الخصم غير صالح أو منتهي', 404);
    }

    if (!empty($promo['usage_limit']) && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
        sendError('تم استهلاك كود الخصم بالكامل', 422);
    }

    $minOrderAmount = (float) ($promo['min_order_amount'] ?? 0);
    if ($orderAmount < $minOrderAmount) {
        sendError('الحد الأدنى للطلب لاستخدام الكود هو ' . $minOrderAmount, 422);
    }

    $discountAmount = 0.0;
    if (($promo['discount_type'] ?? 'fixed') === 'percentage') {
        $discountAmount = $orderAmount * ((float) ($promo['discount_value'] ?? 0) / 100);
    } else {
        $discountAmount = (float) ($promo['discount_value'] ?? 0);
    }

    $maxDiscountAmount = $promo['max_discount_amount'] !== null ? (float) $promo['max_discount_amount'] : null;
    if ($maxDiscountAmount !== null && $discountAmount > $maxDiscountAmount) {
        $discountAmount = $maxDiscountAmount;
    }

    $discountAmount = round($discountAmount, 2);
    $finalAmount = round(max(0, $orderAmount - $discountAmount), 2);

    sendSuccess([
        'id' => (int) $promo['id'],
        'code' => (string) ($promo['code'] ?? ''),
        'description' => $promo['description'] ?? '',
        'discount_type' => $promo['discount_type'] ?? 'fixed',
        'discount_value' => (float) ($promo['discount_value'] ?? 0),
        'discount_amount' => $discountAmount,
        'order_amount' => $orderAmount,
        'final_amount' => $finalAmount,
        'min_order_amount' => $minOrderAmount,
        'max_discount_amount' => $maxDiscountAmount,
        'usage_limit' => !empty($promo['usage_limit']) ? (int) $promo['usage_limit'] : null,
        'used_count' => (int) ($promo['used_count'] ?? 0),
        'image' => !empty($promo['image']) ? imageUrl($promo['image']) : null,
    ], 'Promo code is valid');
}

/**
 * Format promo row into mobile offer structure.
 */
function formatPromoOffer(array $row): array
{
    $code = strtoupper(trim((string) ($row['code'] ?? '')));
    $fallbackTitleAr = $code !== '' ? ('كود خصم ' . $code) : 'كود خصم';
    $fallbackTitleEn = $code !== '' ? ('Promo Code ' . $code) : 'Promo Code';

    $titleAr = trim((string) ($row['title_ar'] ?? ''));
    if ($titleAr === '') {
        $titleAr = $fallbackTitleAr;
    }

    $titleEn = trim((string) ($row['title_en'] ?? ''));
    if ($titleEn === '') {
        $titleEn = $fallbackTitleEn;
    }

    $titleUr = trim((string) ($row['title_ur'] ?? ''));
    if ($titleUr === '') {
        $titleUr = $titleEn !== '' ? $titleEn : $titleAr;
    }

    $descriptionAr = trim((string) ($row['description_ar'] ?? ''));
    if ($descriptionAr === '') {
        $descriptionAr = trim((string) ($row['description'] ?? ''));
    }

    $descriptionEn = trim((string) ($row['description_en'] ?? ''));
    if ($descriptionEn === '') {
        $descriptionEn = $descriptionAr;
    }

    $descriptionUr = trim((string) ($row['description_ur'] ?? ''));
    if ($descriptionUr === '') {
        $descriptionUr = $descriptionEn !== '' ? $descriptionEn : $descriptionAr;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title_ar' => $titleAr,
        'title_en' => $titleEn,
        'title_ur' => $titleUr,
        'description_ar' => $descriptionAr,
        'description_en' => $descriptionEn,
        'description_ur' => $descriptionUr,
        'image' => !empty($row['image']) ? imageUrl($row['image']) : null,
        'discount_type' => $row['discount_type'] ?? 'fixed',
        'discount_value' => (float) ($row['discount_value'] ?? 0),
        'min_order_amount' => (float) ($row['min_order_amount'] ?? 0),
        'max_discount_amount' => $row['max_discount_amount'] !== null ? (float) $row['max_discount_amount'] : null,
        'category_id' => null,
        'category_name' => null,
        'target_audience' => 'all',
        'start_date' => $row['start_date'] ?? null,
        'end_date' => $row['end_date'] ?? null,
        'is_active' => !empty($row['is_active']),
        'usage_limit' => !empty($row['usage_limit']) ? (int) $row['usage_limit'] : null,
        'used_count' => (int) ($row['used_count'] ?? 0),
        'code' => $code,
        'promo_code' => $code,
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'source' => 'promo_codes',
    ];
}

function formatLegacyOffer(array $row): array
{
    $titleAr = trim((string) ($row['title_ar'] ?? ''));
    $titleEn = trim((string) ($row['title_en'] ?? ''));
    $titleUr = trim((string) ($row['title_ur'] ?? ''));
    $descriptionAr = trim((string) ($row['description_ar'] ?? ''));
    $descriptionEn = trim((string) ($row['description_en'] ?? ''));
    $descriptionUr = trim((string) ($row['description_ur'] ?? ''));
    $code = strtoupper(trim((string) ($row['code'] ?? '')));

    if ($titleAr === '' && $titleEn !== '') {
        $titleAr = $titleEn;
    }
    if ($titleEn === '' && $titleAr !== '') {
        $titleEn = $titleAr;
    }
    if ($descriptionAr === '' && $descriptionEn !== '') {
        $descriptionAr = $descriptionEn;
    }
    if ($descriptionEn === '' && $descriptionAr !== '') {
        $descriptionEn = $descriptionAr;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title_ar' => $titleAr !== '' ? $titleAr : 'عرض خاص',
        'title_en' => $titleEn !== '' ? $titleEn : ($titleAr !== '' ? $titleAr : 'Special Offer'),
        'title_ur' => $titleUr !== '' ? $titleUr : ($titleEn !== '' ? $titleEn : ($titleAr !== '' ? $titleAr : 'Special Offer')),
        'description_ar' => $descriptionAr,
        'description_en' => $descriptionEn !== '' ? $descriptionEn : $descriptionAr,
        'description_ur' => $descriptionUr !== '' ? $descriptionUr : ($descriptionEn !== '' ? $descriptionEn : $descriptionAr),
        'image' => !empty($row['image']) ? imageUrl($row['image']) : null,
        'discount_type' => $row['discount_type'] ?? 'fixed',
        'discount_value' => (float) ($row['discount_value'] ?? 0),
        'min_order_amount' => (float) ($row['min_order_amount'] ?? 0),
        'max_discount_amount' => $row['max_discount_amount'] !== null ? (float) $row['max_discount_amount'] : null,
        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'category_name' => null,
        'target_audience' => $row['target_audience'] ?? 'all',
        'start_date' => isDateOpenRange($row['start_date'] ?? null) ? null : $row['start_date'],
        'end_date' => isDateOpenRange($row['end_date'] ?? null) ? null : $row['end_date'],
        'is_active' => !empty($row['is_active']),
        'usage_limit' => !empty($row['usage_limit']) ? (int) $row['usage_limit'] : null,
        'used_count' => (int) ($row['used_count'] ?? 0),
        'code' => $code,
        'promo_code' => $code,
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'source' => 'offers',
    ];
}

function offersCoverage(): array
{
    static $coverage = null;
    if ($coverage !== null) {
        return $coverage;
    }

    $country = serviceAreaNormalizeCountryCode($_GET['country_code'] ?? '');
    $lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : null;
    $lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : null;
    $hasCoordinates = $lat !== null && $lng !== null;

    if ($hasCoordinates && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
        sendError('إحداثيات الموقع غير صحيحة', 422);
    }

    $coverage = serviceAreaEvaluateCoverage($country, $lat, $lng);
    return $coverage;
}

function guardOffersCoverageList(): void
{
    $coverage = offersCoverage();
    if (!($coverage['is_supported'] ?? true)) {
        $message = trim((string) ($coverage['message_ar'] ?? ''));
        if ($message === '') {
            $message = 'أنت خارج نطاق تقديم الخدمة';
        }
        sendSuccess([], $message);
    }
}

function guardOffersCoverageDetail(): void
{
    $coverage = offersCoverage();
    if (!($coverage['is_supported'] ?? true)) {
        $message = trim((string) ($coverage['message_ar'] ?? ''));
        if ($message === '') {
            $message = 'أنت خارج نطاق تقديم الخدمة';
        }
        sendError($message, 403);
    }
}
