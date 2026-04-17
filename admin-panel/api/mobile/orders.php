<?php
/**
 * Mobile API - Orders
 * الطلبات
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/special_services.php';
require_once __DIR__ . '/../../includes/service_areas.php';
require_once __DIR__ . '/../../includes/spare_parts_scope.php';
require_once __DIR__ . '/../../includes/notification_service.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            getOrders();
            break;
        case 'detail':
            getOrderDetail();
            break;
        case 'provider_assignment_response':
            providerAssignmentResponse($input);
            break;
        case 'provider_update_status':
            providerUpdateOrderStatus($input);
            break;
        case 'provider_location_update':
            updateProviderLiveLocation($input);
            break;
        case 'create':
            createOrder($input);
            break;
        case 'cancel':
            cancelOrder($input);
            break;
        case 'rate':
            rateOrder($input);
            break;
        case 'pay':
            payOrder($input);
            break;
        case 'myfatoorah_execute':
            executeMyFatoorahPayment($input);
            break;
        case 'myfatoorah_status':
            getMyFatoorahPaymentStatus($input);
            break;
        case 'myfatoorah_callback':
            handleMyFatoorahCallback($input);
            break;
        case 'myfatoorah_error':
            handleMyFatoorahError($input);
            break;
        case 'start_job':
            startJob($input);
            break;
        case 'complete_job':
            completeJob($input);
            break;
        case 'submit_invoice':
            submitInvoice($input);
            break;
        case 'approve_invoice':
            approveInvoice($input);
            break;
        // For Demo/Dev purposes, let's allow setting estimate via mobile API if admin uses it or if we mock ops
        case 'set_estimate':
            setEstimate($input);
            break;
        default:
            sendError('Invalid action', 400);
    }
} catch (Throwable $e) {
    handleOrdersApiThrowable($e);
}

/**
 * Convert unexpected backend exceptions into user-facing API errors.
 */
function handleOrdersApiThrowable(Throwable $e)
{
    error_log('Orders API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $statusCode = 500;
    $exceptionCode = (int) $e->getCode();
    if ($exceptionCode >= 400 && $exceptionCode <= 599) {
        $statusCode = $exceptionCode;
    }

    if ($e instanceof mysqli_sql_exception) {
        $mysqlCode = (int) $e->getCode();
        if (in_array($mysqlCode, [1048, 1062, 1451, 1452], true)) {
            $statusCode = 422;
        }
    }

    sendError(buildOrdersApiThrowableMessage($e), $statusCode);
}

/**
 * Build a safe and actionable message from low-level exceptions.
 */
function buildOrdersApiThrowableMessage(Throwable $e)
{
    $rawMessage = trim((string) $e->getMessage());
    $normalized = strtolower($rawMessage);

    if (
        str_contains($normalized, 'unknown column')
        || str_contains($normalized, 'base table or view not found')
        || str_contains($normalized, "doesn't exist")
        || str_contains($normalized, 'can\'t find file')
    ) {
        return 'بيئة الطلبات غير مكتملة على الخادم حالياً. حاول مرة أخرى بعد قليل.';
    }

    if (
        str_contains($normalized, 'foreign key constraint fails')
        || str_contains($normalized, 'cannot add or update a child row')
        || str_contains($normalized, 'cannot delete or update a parent row')
    ) {
        return 'الخدمة أو البيانات المرتبطة بالطلب غير متاحة حالياً. يرجى تحديث البيانات والمحاولة مرة أخرى.';
    }

    if (str_contains($normalized, 'duplicate entry')) {
        return 'تم إنشاء طلب مشابه بالفعل. راجع قائمة الطلبات قبل إعادة الإرسال.';
    }

    if (str_contains($normalized, 'lock wait timeout') || str_contains($normalized, 'deadlock')) {
        return 'الخادم مشغول حالياً أثناء حفظ الطلب. يرجى المحاولة مرة أخرى بعد لحظات.';
    }

    if (str_contains($normalized, 'access denied')) {
        return 'تعذر الوصول إلى خدمة الطلبات حالياً. يرجى المحاولة لاحقاً.';
    }

    if (
        $rawMessage !== ''
        && strlen($rawMessage) <= 140
        && !str_contains($normalized, 'sql')
        && !str_contains($normalized, 'mysqli')
        && !str_contains($normalized, 'syntax error')
        && !str_contains($normalized, 'stack trace')
    ) {
        return $rawMessage;
    }

    return 'تعذر إتمام طلب الخدمة حالياً بسبب مشكلة تقنية مؤقتة.';
}

function ordersCoverageAreaIds(array $coverage): array
{
    $matchedArea = $coverage['matched_area'] ?? null;
    if (!is_array($matchedArea)) {
        return [];
    }

    $matchedAreaId = (int) ($matchedArea['id'] ?? 0);
    if ($matchedAreaId <= 0) {
        return [];
    }

    return [$matchedAreaId];
}

function ordersResolveAllowedServiceIds(array $serviceIds, array $areaIds): array
{
    global $conn;

    $serviceIds = normalizeIntegerIds($serviceIds);
    if (empty($serviceIds)) {
        return [];
    }

    if (!tableExists('services')) {
        return [];
    }

    $servicePlaceholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $types = str_repeat('i', count($serviceIds));
    $params = $serviceIds;
    $visibilitySql = '';

    $areaIds = normalizeIntegerIds($areaIds);
    if (!empty($areaIds) && tableExists('service_area_services')) {
        $areaPlaceholders = implode(',', array_fill(0, count($areaIds), '?'));
        $visibilitySql = " AND (
            NOT EXISTS (
                SELECT 1
                FROM service_area_services sas_any
                WHERE sas_any.service_id = s.id
            )
            OR EXISTS (
                SELECT 1
                FROM service_area_services sas_match
                WHERE sas_match.service_id = s.id
                  AND sas_match.service_area_id IN ({$areaPlaceholders})
            )
        )";
        $types .= str_repeat('i', count($areaIds));
        $params = array_merge($params, $areaIds);
    }

    $sql = "SELECT s.id
            FROM services s
            WHERE s.is_active = 1
              AND s.id IN ({$servicePlaceholders}){$visibilitySql}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $allowedSet = [];
    while ($row = $result->fetch_assoc()) {
        $serviceId = (int) ($row['id'] ?? 0);
        if ($serviceId > 0) {
            $allowedSet[$serviceId] = true;
        }
    }

    $orderedAllowedIds = [];
    foreach ($serviceIds as $serviceId) {
        if (!empty($allowedSet[$serviceId])) {
            $orderedAllowedIds[] = $serviceId;
        }
    }

    return $orderedAllowedIds;
}

function normalizePromoCodeValue($code): string
{
    $normalized = strtoupper(trim((string) $code));
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/\s+/', '', $normalized);
    return preg_replace('/[^A-Z0-9_-]/', '', $normalized);
}

/**
 * Resolve promo pricing for a given amount.
 *
 * @throws RuntimeException when promo is invalid.
 */
function resolvePromoPricingForAmount($promoCodeRaw, float $baseAmount, int $userId = 0): array
{
    global $conn;

    $baseAmount = round(max(0, $baseAmount), 2);
    $promoCode = normalizePromoCodeValue($promoCodeRaw);

    if ($promoCode === '') {
        return [
            'promo_id' => null,
            'promo_code' => null,
            'discount_amount' => 0.0,
            'base_amount' => $baseAmount,
            'final_amount' => $baseAmount,
            'discount_type' => null,
            'discount_value' => 0.0,
        ];
    }

    if ($baseAmount <= 0) {
        throw new RuntimeException('قيمة الطلب غير صالحة لتطبيق كود الخصم');
    }

    if (!tableExists('promo_codes')) {
        throw new RuntimeException('أكواد الخصم غير متاحة حالياً');
    }

    ensurePromoUsageSchema();

    $selectColumns = [
        'id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'used_count',
        'is_active',
        'start_date',
        'end_date',
    ];
    if (tableColumnExists('promo_codes', 'usage_limit_per_user')) {
        $selectColumns[] = 'usage_limit_per_user';
    }

    $stmt = $conn->prepare(
        "SELECT " . implode(', ', $selectColumns) . "
         FROM promo_codes
         WHERE code = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException('تعذر التحقق من كود الخصم حالياً');
    }

    $stmt->bind_param("s", $promoCode);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();

    if (!$promo) {
        throw new RuntimeException('كود الخصم غير صالح أو منتهي');
    }

    if ((int) ($promo['is_active'] ?? 0) !== 1) {
        throw new RuntimeException('كود الخصم غير مفعل');
    }

    $today = date('Y-m-d');
    $startDate = trim((string) ($promo['start_date'] ?? ''));
    $endDate = trim((string) ($promo['end_date'] ?? ''));
    if ($startDate === '0000-00-00' || $startDate === '0000-00-00 00:00:00') {
        $startDate = '';
    }
    if ($endDate === '0000-00-00' || $endDate === '0000-00-00 00:00:00') {
        $endDate = '';
    }

    if ($startDate !== '' && $startDate > $today) {
        throw new RuntimeException('كود الخصم غير متاح بعد');
    }
    if ($endDate !== '' && $endDate < $today) {
        throw new RuntimeException('كود الخصم منتهي');
    }

    $usageLimit = isset($promo['usage_limit']) ? (int) $promo['usage_limit'] : 0;
    $usedCount = isset($promo['used_count']) ? (int) $promo['used_count'] : 0;
    if ($usageLimit > 0 && $usedCount >= $usageLimit) {
        throw new RuntimeException('تم استهلاك كود الخصم بالكامل');
    }

    $usageLimitPerUser = isset($promo['usage_limit_per_user']) ? (int) $promo['usage_limit_per_user'] : 0;
    if ($usageLimitPerUser > 0 && $userId > 0 && tableExists('promo_code_usages')) {
        $usageStmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM promo_code_usages
             WHERE promo_code_id = ? AND user_id = ?"
        );
        if ($usageStmt) {
            $promoId = (int) ($promo['id'] ?? 0);
            $usageStmt->bind_param("ii", $promoId, $userId);
            $usageStmt->execute();
            $usageRow = $usageStmt->get_result()->fetch_assoc();
            $userUsageCount = (int) ($usageRow['total'] ?? 0);
            if ($userUsageCount >= $usageLimitPerUser) {
                throw new RuntimeException('تم الوصول للحد الأقصى لاستخدام الكود لهذا العميل');
            }
        }
    }

    $minOrderAmount = max(0, (float) ($promo['min_order_amount'] ?? 0));
    if ($baseAmount < $minOrderAmount) {
        throw new RuntimeException('الحد الأدنى لاستخدام الكود هو ' . number_format($minOrderAmount, 2));
    }

    $discountType = strtolower(trim((string) ($promo['discount_type'] ?? 'fixed')));
    $discountValue = max(0, (float) ($promo['discount_value'] ?? 0));
    $discountAmount = 0.0;

    if ($discountType === 'percentage') {
        $discountAmount = $baseAmount * ($discountValue / 100);
    } else {
        $discountAmount = $discountValue;
    }

    $maxDiscountAmount = $promo['max_discount_amount'] !== null
        ? max(0, (float) $promo['max_discount_amount'])
        : null;
    if ($maxDiscountAmount !== null && $discountAmount > $maxDiscountAmount) {
        $discountAmount = $maxDiscountAmount;
    }

    if ($discountAmount > $baseAmount) {
        $discountAmount = $baseAmount;
    }

    $discountAmount = round(max(0, $discountAmount), 2);
    $finalAmount = round(max(0, $baseAmount - $discountAmount), 2);

    return [
        'promo_id' => (int) ($promo['id'] ?? 0),
        'promo_code' => strtoupper(trim((string) ($promo['code'] ?? $promoCode))),
        'discount_amount' => $discountAmount,
        'base_amount' => $baseAmount,
        'final_amount' => $finalAmount,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
    ];
}

function resolveOrderBaseAmountForPayment(array $order, float $requestedAmount): float
{
    $subtotal = isset($order['subtotal_amount']) ? (float) ($order['subtotal_amount'] ?? 0) : 0;
    if ($subtotal > 0) {
        return $subtotal;
    }

    $orderTotal = isset($order['total_amount']) ? (float) ($order['total_amount'] ?? 0) : 0;
    $discountAmount = isset($order['discount_amount']) ? (float) ($order['discount_amount'] ?? 0) : 0;
    $promoCode = normalizePromoCodeValue($order['promo_code'] ?? '');

    if ($orderTotal > 0 && $discountAmount > 0 && $promoCode !== '') {
        return $orderTotal + $discountAmount;
    }

    if ($orderTotal > 0) {
        return $orderTotal;
    }

    return max(0, $requestedAmount);
}

function minSparePartsOrderWithInstallationAmount(): float
{
    $raw = appSettingValue('spare_parts_min_order_with_installation', '0');
    $value = (float) preg_replace('/[^0-9.\-]/', '', (string) $raw);
    return max(0, $value);
}

function enforceSparePartsMinOrderIfNeeded(array $requestedSpareParts, array $problemDetails = []): void
{
    global $conn;

    if (empty($requestedSpareParts)) {
        return;
    }

    $minimum = minSparePartsOrderWithInstallationAmount();
    if ($minimum <= 0) {
        return;
    }

    ensureSparePartsPricingSchema();

    $defaultMode = normalizeSparePricingMode($problemDetails['pricing_mode'] ?? null) ?? 'with_installation';
    $defaultRequiresInstallation = array_key_exists('requires_installation', $problemDetails)
        ? normalizeBooleanValue($problemDetails['requires_installation'])
        : ($defaultMode !== 'without_installation');

    $lookupStmt = $conn->prepare(
        "SELECT id, price, price_with_installation, price_without_installation
         FROM spare_parts
         WHERE id = ?"
    );

    $totalWithInstallation = 0.0;
    $hasInstallationItems = false;

    foreach ($requestedSpareParts as $requestedPart) {
        if (!is_array($requestedPart)) {
            continue;
        }

        $pricingMode = normalizeSparePricingMode($requestedPart['pricing_mode'] ?? null) ?? $defaultMode;
        $requiresInstallation = array_key_exists('requires_installation', $requestedPart)
            ? normalizeBooleanValue($requestedPart['requires_installation'])
            : $defaultRequiresInstallation;

        if ($pricingMode === 'without_installation' && !$requiresInstallation) {
            continue;
        }

        $hasInstallationItems = true;

        $quantity = max(1, (int) ($requestedPart['quantity'] ?? 1));
        $unitPrice = isset($requestedPart['unit_price']) ? (float) $requestedPart['unit_price'] : null;

        if (($unitPrice === null || $unitPrice <= 0) && $lookupStmt) {
            $sparePartId = (int) ($requestedPart['spare_part_id'] ?? 0);
            if ($sparePartId > 0) {
                $lookupStmt->bind_param("i", $sparePartId);
                $lookupStmt->execute();
                $row = $lookupStmt->get_result()->fetch_assoc();
                if ($row) {
                    $unitPrice = resolveSparePartUnitPrice($row, $pricingMode);
                }
            }
        }

        if ($unitPrice === null || $unitPrice <= 0) {
            $unitPrice = 0.0;
        }

        $totalWithInstallation += $unitPrice * $quantity;
    }

    if ($hasInstallationItems && $totalWithInstallation < $minimum) {
        sendError('الحد الأدنى لطلب قطع الغيار مع التركيب هو ' . number_format($minimum, 2) . ' ⃁', 422);
    }
}

function orderTableColumnExists(string $column): bool
{
    if (orderColumnExists($column)) {
        return true;
    }
    return tableColumnExists('orders', $column);
}

function persistOrderPromoPricing(int $orderId, int $userId, array $pricing): void
{
    global $conn;

    if ($orderId <= 0 || $userId <= 0 || !tableExists('orders')) {
        return;
    }

    $updates = [];
    $types = '';
    $values = [];

    if (orderTableColumnExists('subtotal_amount')) {
        $updates[] = 'subtotal_amount = ?';
        $types .= 'd';
        $values[] = (float) ($pricing['base_amount'] ?? 0);
    }

    if (orderTableColumnExists('discount_amount')) {
        $updates[] = 'discount_amount = ?';
        $types .= 'd';
        $values[] = (float) ($pricing['discount_amount'] ?? 0);
    }

    if (orderTableColumnExists('promo_code')) {
        $promoCode = trim((string) ($pricing['promo_code'] ?? ''));
        if ($promoCode !== '') {
            $updates[] = 'promo_code = ?';
            $types .= 's';
            $values[] = $promoCode;
        } else {
            $updates[] = 'promo_code = NULL';
        }
    }

    if (empty($updates)) {
        return;
    }

    $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    $types .= 'ii';
    $values[] = $orderId;
    $values[] = $userId;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
}

function incrementPromoUsageIfPresent(?string $promoCode, int $userId = 0, int $orderId = 0, ?int $promoId = null): void
{
    global $conn;

    $promoCode = normalizePromoCodeValue($promoCode);
    if ($promoCode === '' || !tableExists('promo_codes')) {
        return;
    }

    ensurePromoUsageSchema();

    $promoRow = null;
    $resolvedPromoId = $promoId ?? 0;
    if ($resolvedPromoId <= 0) {
        $promoStmt = $conn->prepare("SELECT id FROM promo_codes WHERE code = ? LIMIT 1");
        if ($promoStmt) {
            $promoStmt->bind_param("s", $promoCode);
            $promoStmt->execute();
            $promoRow = $promoStmt->get_result()->fetch_assoc();
            $resolvedPromoId = (int) ($promoRow['id'] ?? 0);
        }
    }

    if ($resolvedPromoId <= 0) {
        return;
    }

    $shouldIncrement = true;
    if ($userId > 0 && $orderId > 0 && tableExists('promo_code_usages')) {
        $existsStmt = $conn->prepare(
            "SELECT id FROM promo_code_usages
             WHERE promo_code_id = ? AND user_id = ? AND order_id = ?
             LIMIT 1"
        );
        if ($existsStmt) {
            $existsStmt->bind_param("iii", $resolvedPromoId, $userId, $orderId);
            $existsStmt->execute();
            $exists = $existsStmt->get_result()->fetch_assoc();
            if ($exists) {
                return;
            }
        }

        $insertUsage = $conn->prepare(
            "INSERT INTO promo_code_usages (promo_code_id, user_id, order_id, used_at)
             VALUES (?, ?, ?, NOW())"
        );
        if ($insertUsage) {
            $insertUsage->bind_param("iii", $resolvedPromoId, $userId, $orderId);
            if (!$insertUsage->execute()) {
                $shouldIncrement = false;
            }
        } else {
            $shouldIncrement = false;
        }
    }

    if (!$shouldIncrement) {
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE promo_codes
         SET used_count = used_count + 1
         WHERE code = ?
           AND is_active = 1
           AND (usage_limit IS NULL OR used_count < usage_limit)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("s", $promoCode);
    $stmt->execute();
}

function ensurePromoUsageSchema(): void
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!tableExists('promo_codes')) {
        return;
    }

    if (!tableColumnExists('promo_codes', 'usage_limit_per_user')) {
        $conn->query("ALTER TABLE `promo_codes` ADD COLUMN `usage_limit_per_user` INT NULL");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS `promo_code_usages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `promo_code_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `order_id` INT NOT NULL,
        `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_promo_user_order` (`promo_code_id`, `user_id`, `order_id`),
        INDEX `idx_promo_usage_user` (`user_id`),
        INDEX `idx_promo_usage_promo` (`promo_code_id`),
        INDEX `idx_promo_usage_order` (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function resolveOrderCoverageAreaIdsForSparePartsScope(int $orderId): array
{
    global $conn;

    if ($orderId <= 0 || !tableExists('orders')) {
        return [];
    }

    $stmt = $conn->prepare("SELECT lat, lng FROM orders WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return [];
    }

    $lat = isset($row['lat']) && $row['lat'] !== null ? (float) $row['lat'] : null;
    $lng = isset($row['lng']) && $row['lng'] !== null ? (float) $row['lng'] : null;
    if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return [];
    }

    $coverage = serviceAreaEvaluateCoverage('', $lat, $lng);
    if (!($coverage['is_supported'] ?? true)) {
        return [];
    }

    return ordersCoverageAreaIds($coverage);
}

function resolveOrderServiceIdsForSparePartsScope(int $orderId, array $problemDetails = []): array
{
    global $conn;

    $serviceIds = [];

    if ($orderId > 0 && tableExists('order_services')) {
        $rows = db()->fetchAll(
            "SELECT service_id
             FROM order_services
             WHERE order_id = ?
               AND service_id IS NOT NULL
             ORDER BY id ASC",
            [$orderId]
        );
        foreach ($rows as $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);
            if ($serviceId > 0) {
                $serviceIds[$serviceId] = $serviceId;
            }
        }
    }

    if ($orderId > 0 && tableExists('orders') && orderColumnExists('service_type_id')) {
        $stmt = $conn->prepare("SELECT service_type_id FROM orders WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $serviceTypeId = (int) ($row['service_type_id'] ?? 0);
            if ($serviceTypeId > 0) {
                $serviceIds[$serviceTypeId] = $serviceTypeId;
            }
        }
    }

    $problemServiceCandidates = [];
    if (!empty($problemDetails['service_type_id'])) {
        $problemServiceCandidates[] = $problemDetails['service_type_id'];
    }
    if (isset($problemDetails['service_type_ids'])) {
        $problemServiceCandidates[] = $problemDetails['service_type_ids'];
    }
    if (isset($problemDetails['sub_services'])) {
        $problemServiceCandidates[] = $problemDetails['sub_services'];
    }

    foreach ($problemServiceCandidates as $candidate) {
        foreach (sparePartScopeNormalizeIds($candidate) as $serviceId) {
            $serviceIds[$serviceId] = $serviceId;
        }
    }

    return array_values($serviceIds);
}

function sparePartMatchesOrderScope(int $sparePartId, array $orderAreaIds, array $orderServiceIds): bool
{
    global $conn;

    if ($sparePartId <= 0) {
        return false;
    }
    if (!tableExists('spare_parts')) {
        return false;
    }

    sparePartScopeEnsureSchema();
    $visibility = sparePartScopeBuildVisibilityFragment('sp', $orderAreaIds, $orderServiceIds);
    if ($visibility['sql'] === '1=1') {
        return true;
    }

    $sql = "SELECT sp.id
            FROM spare_parts sp
            WHERE sp.id = ?
              AND {$visibility['sql']}
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $bindTypes = 'i' . $visibility['types'];
    $bindParams = array_merge([$sparePartId], $visibility['params']);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return !empty($row['id']);
}

function decodeOrderProblemDetailsPayload($problemDetailsRaw): array
{
    if (is_array($problemDetailsRaw)) {
        return $problemDetailsRaw;
    }

    if (is_object($problemDetailsRaw)) {
        return (array) $problemDetailsRaw;
    }

    if (!is_string($problemDetailsRaw)) {
        return [];
    }

    $trimmed = trim($problemDetailsRaw);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    return is_array($decoded) ? $decoded : [];
}

function isSparePartsWithInstallationOrderPayload($problemDetailsRaw): bool
{
    $details = decodeOrderProblemDetailsPayload($problemDetailsRaw);
    if (empty($details)) {
        return false;
    }

    $module = strtolower(trim((string) ($details['module'] ?? '')));
    $type = strtolower(trim((string) ($details['type'] ?? '')));
    $signal = $type !== '' ? $type : $module;
    $signal = str_replace(['-', ' '], '_', $signal);

    if ($signal === 'spare_parts_with_installation') {
        return true;
    }

    $pricingMode = normalizeSparePricingMode($details['pricing_mode'] ?? null);
    if ($pricingMode === 'with_installation' && strpos($signal, 'spare_parts') !== false) {
        return true;
    }

    if (isset($details['requires_installation']) && normalizeBooleanValue($details['requires_installation'])) {
        return true;
    }

    return false;
}

function isContainerFlowOrderPayload($problemDetailsRaw): bool
{
    $details = decodeOrderProblemDetailsPayload($problemDetailsRaw);
    if (empty($details)) {
        return false;
    }

    $module = strtolower(trim((string) ($details['module'] ?? '')));
    $type = strtolower(trim((string) ($details['type'] ?? '')));

    return isset($details['container_request'])
        || $module === 'container_rental'
        || $type === 'container_rental'
        || strpos($module, 'container') !== false
        || strpos($type, 'container') !== false;
}

function denyContainerOrdersForProvider($problemDetailsRaw, $orderId = 0): void
{
    if (isContainerFlowOrderPayload($problemDetailsRaw)) {
        sendError('طلبات الحاويات لا يتم إسنادها لمقدمي الخدمات', 403);
    }

    $orderId = (int) $orderId;
    if (
        $orderId > 0
        && specialServiceTableExists('container_requests')
        && specialServiceColumnExists('container_requests', 'source_order_id')
    ) {
        $linked = db()->fetch(
            'SELECT id FROM container_requests WHERE source_order_id = ? LIMIT 1',
            [$orderId]
        );
        if (!empty($linked['id'])) {
            sendError('طلبات الحاويات لا يتم إسنادها لمقدمي الخدمات', 403);
        }
    }
}

/**
 * Get user/provider orders
 */
function getOrders()
{
    global $conn;

    $id = requireAuth();
    $role = getAuthRole();

    $status = $_GET['status'] ?? null;
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    $offset = ($page - 1) * $perPage;

    if ($role === 'provider') {
        ensureProviderCanViewOrders((int) $id);
        ensureOrderExtensionsSchema();
        if (tableExists('order_providers')) {
            $where = "WHERE (
                o.provider_id = ?
                OR EXISTS (
                    SELECT 1
                    FROM order_providers op
                    WHERE op.order_id = o.id
                      AND op.provider_id = ?
                      AND op.assignment_status IN ('assigned', 'accepted', 'in_progress', 'completed', 'cancelled')
                )
            )";
            $params = [$id, $id];
            $types = "ii";
        } else {
            $where = "WHERE o.provider_id = ?";
            $params = [$id];
            $types = "i";
        }
    } else {
        $where = "WHERE o.user_id = ?";
        $params = [$id];
        $types = "i";
    }

    if ($status) {
        if ($status === 'active') {
            $where .= " AND o.status IN ('assigned', 'accepted', 'on_the_way', 'arrived', 'in_progress')";
        } else {
            $where .= " AND o.status = ?";
            $params[] = $status;
            $types .= "s";
        }
    }

    if ($role === 'provider' && orderColumnExists('problem_details')) {
        $where .= " AND (
            o.problem_details IS NULL
            OR (
                o.problem_details NOT LIKE '%\"module\":\"container_rental\"%'
                AND o.problem_details NOT LIKE '%\"type\":\"container_rental\"%'
                AND o.problem_details NOT LIKE '%\"container_request\"%'
            )
        )";
    }
    if (
        $role === 'provider'
        && specialServiceTableExists('container_requests')
        && specialServiceColumnExists('container_requests', 'source_order_id')
    ) {
        $where .= " AND o.id NOT IN (
            SELECT cr.source_order_id
            FROM container_requests cr
            WHERE cr.source_order_id IS NOT NULL
        )";
    }

    // Get total
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders o $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Get orders
    $sql = "SELECT o.*, 
            c.name_ar as category_name, c.icon as category_icon,
            p.full_name as provider_name, p.avatar as provider_avatar, p.rating as provider_rating,
            p.phone as provider_phone, p.email as provider_email, p.status as provider_status,
            p.is_available as provider_is_available
            FROM orders o
            LEFT JOIN service_categories c ON o.category_id = c.id
            LEFT JOIN providers p ON o.provider_id = p.id
            $where
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?";

    $params[] = $perPage;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $context = [
            'role' => $role,
            'provider_id' => $role === 'provider' ? (int) $id : null,
        ];
        $orders[] = formatOrder($row, $context);
    }

    sendPaginated($orders, $page, $perPage, $total);
}

function ensureProviderCanViewOrders($providerId)
{
    global $conn;

    if ($providerId <= 0) {
        sendError('Unauthorized', 403);
    }
    if (!tableExists('providers')) {
        sendError('مزودو الخدمة غير متاحين حالياً', 500);
    }

    $columns = ['id', 'full_name', 'avatar', 'city', 'bio', 'experience_years', 'status'];
    if (tableColumnExists('providers', 'profile_completed')) {
        $columns[] = 'profile_completed';
    }
    $sql = "SELECT " . implode(', ', $columns) . " FROM providers WHERE id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendError('Failed to validate provider profile', 500);
    }
    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();

    if (!$provider) {
        sendError('حساب مقدم الخدمة غير موجود', 404);
    }

    $status = strtolower(trim((string) ($provider['status'] ?? '')));
    if ($status === 'pending') {
        sendError('حساب مقدم الخدمة قيد المراجعة من الإدارة', 403);
    }
    if (in_array($status, ['rejected', 'suspended'], true)) {
        sendError('حساب مقدم الخدمة غير مفعل حالياً', 403);
    }

    $categoryCount = 0;
    if (tableExists('provider_services')) {
        $catStmt = $conn->prepare("SELECT COUNT(*) AS total FROM provider_services WHERE provider_id = ?");
        if ($catStmt) {
            $catStmt->bind_param("i", $providerId);
            $catStmt->execute();
            $categoryCount = (int) ($catStmt->get_result()->fetch_assoc()['total'] ?? 0);
        }
    }

    $isCompleted = isset($provider['profile_completed']) && (int) $provider['profile_completed'] === 1;
    if (!$isCompleted) {
        $fullName = trim((string) ($provider['full_name'] ?? ''));
        $city = trim((string) ($provider['city'] ?? ''));
        $bio = trim((string) ($provider['bio'] ?? ''));
        $avatar = trim((string) ($provider['avatar'] ?? ''));
        $experienceYears = (int) ($provider['experience_years'] ?? 0);

        $isCompleted = $fullName !== ''
            && $fullName !== 'مقدم خدمة جديد'
            && $city !== ''
            && $bio !== ''
            && $avatar !== ''
            && $avatar !== 'default-provider.png'
            && $experienceYears > 0
            && $categoryCount > 0;
    }

    if (!$isCompleted) {
        sendError('يرجى استكمال الملف الشخصي قبل عرض الطلبات', 403);
    }
}

/**
 * Get order detail
 */
function getOrderDetail()
{
    global $conn;

    $id = requireAuth();
    $role = getAuthRole();
    $orderId = (int) ($_GET['id'] ?? 0);

    if ($role === 'provider') {
        ensureProviderCanViewOrders((int) $id);
    }

    $sql = "SELECT o.*,
            c.name_ar as category_name, c.icon as category_icon,
            u.full_name as user_name, u.phone as user_phone, u.avatar as user_avatar,
            p.full_name as provider_name, p.avatar as provider_avatar, p.rating as provider_rating,
            p.phone as provider_phone, p.email as provider_email, p.status as provider_status,
            p.is_available as provider_is_available
            FROM orders o
            LEFT JOIN service_categories c ON o.category_id = c.id
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN providers p ON o.provider_id = p.id
            WHERE o.id = ?";

    if ($role === 'provider') {
        ensureOrderExtensionsSchema();
        if (tableExists('order_providers')) {
            $sql .= " AND (
                o.provider_id = ?
                OR EXISTS (
                    SELECT 1
                    FROM order_providers op
                    WHERE op.order_id = o.id
                      AND op.provider_id = ?
                      AND op.assignment_status IN ('assigned', 'accepted', 'in_progress', 'completed', 'cancelled')
                )
            )";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $orderId, $id, $id);
        } else {
            $sql .= " AND o.provider_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $orderId, $id);
        }
    } else {
        $sql .= " AND o.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $orderId, $id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        sendError('Order not found', 404);
    }

    if ($role === 'provider') {
        denyContainerOrdersForProvider($order['problem_details'] ?? null, $orderId);
    }

    $context = [
        'role' => $role,
        'provider_id' => $role === 'provider' ? (int) $id : null,
    ];

    sendSuccess(formatOrder($order, $context));
}

/**
 * Provider accepts/rejects assigned order
 */
function providerAssignmentResponse($input)
{
    global $conn;

    $providerId = requireAuth();
    $role = getAuthRole();
    if ($role !== 'provider') {
        sendError('Unauthorized', 403);
    }
    ensureProviderCanViewOrders((int) $providerId);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }

    ensureOrderExtensionsSchema();

    $orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;
    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }

    $decision = strtolower(trim((string) ($input['decision'] ?? $input['assignment_action'] ?? '')));
    if (!in_array($decision, ['accept', 'reject'], true)) {
        sendError('decision must be accept or reject', 422);
    }

    $stmt = $conn->prepare(
        "SELECT o.id, o.status, o.provider_id, o.user_id, o.problem_details,
                op.assignment_status AS current_assignment_status
         FROM orders o
         LEFT JOIN order_providers op ON op.order_id = o.id AND op.provider_id = ?
         WHERE o.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        sendError('Failed to prepare assignment query', 500);
    }
    $stmt->bind_param("ii", $providerId, $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        sendError('Order not found', 404);
    }
    denyContainerOrdersForProvider($order['problem_details'] ?? null, $orderId);

    $currentAssignment = strtolower(trim((string) ($order['current_assignment_status'] ?? '')));
    $isLegacyPrimary = (int) ($order['provider_id'] ?? 0) === $providerId;
    if ($currentAssignment === '' && !$isLegacyPrimary) {
        sendError('هذا الطلب غير معيّن لك', 403);
    }

    if ($decision === 'accept') {
        if (!in_array($currentAssignment, ['', 'assigned', 'accepted'], true) && !$isLegacyPrimary) {
            sendError('لا يمكن قبول هذا الطلب حالياً', 422);
        }
    } else {
        $currentOrderStatus = strtolower(trim((string) ($order['status'] ?? '')));
        if (in_array($currentOrderStatus, ['in_progress', 'completed', 'cancelled'], true)) {
            sendError('لا يمكن رفض هذا الطلب بعد بدء التنفيذ', 422);
        }
        if (!in_array($currentAssignment, ['', 'assigned', 'accepted'], true) && !$isLegacyPrimary) {
            sendError('لا يمكن رفض هذا الطلب حالياً', 422);
        }
    }

    $nextAssignedProviderId = 0;
    $autoRejectedProviderIds = [];

    $conn->begin_transaction();
    try {
        if ($decision === 'accept') {
            setProviderAssignmentStatus($orderId, $providerId, 'accepted');

            if (tableExists('order_providers')) {
                $otherProvidersStmt = $conn->prepare(
                    "SELECT provider_id
                     FROM order_providers
                     WHERE order_id = ?
                       AND provider_id <> ?
                       AND assignment_status IN ('assigned', 'accepted')"
                );
                if ($otherProvidersStmt) {
                    $otherProvidersStmt->bind_param("ii", $orderId, $providerId);
                    $otherProvidersStmt->execute();
                    $otherProvidersResult = $otherProvidersStmt->get_result();
                    while ($otherProviderRow = $otherProvidersResult->fetch_assoc()) {
                        $otherProviderId = (int) ($otherProviderRow['provider_id'] ?? 0);
                        if ($otherProviderId > 0) {
                            $autoRejectedProviderIds[$otherProviderId] = $otherProviderId;
                        }
                    }
                }

                $stmt = $conn->prepare(
                    "UPDATE order_providers
                     SET assignment_status = 'rejected'
                     WHERE order_id = ?
                       AND provider_id <> ?
                       AND assignment_status IN ('assigned', 'accepted')"
                );
                if ($stmt) {
                    $stmt->bind_param("ii", $orderId, $providerId);
                    $stmt->execute();
                }
            }

            $stmt = $conn->prepare(
                "UPDATE orders
                 SET provider_id = ?,
                     status = CASE
                        WHEN status IN ('pending', 'assigned', 'accepted') THEN 'accepted'
                        ELSE status
                     END
                 WHERE id = ?"
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to update order status');
            }
            $stmt->bind_param("ii", $providerId, $orderId);
            $stmt->execute();
        } else {
            setProviderAssignmentStatus($orderId, $providerId, 'rejected');

            $nextProviderId = null;
            $nextStatus = null;
            if (tableExists('order_providers')) {
                $stmt = $conn->prepare(
                    "SELECT provider_id, assignment_status
                     FROM order_providers
                     WHERE order_id = ?
                       AND assignment_status IN ('in_progress', 'completed', 'accepted')
                     ORDER BY FIELD(assignment_status, 'in_progress', 'completed', 'accepted'), id ASC
                     LIMIT 1"
                );
                if ($stmt) {
                    $stmt->bind_param("i", $orderId);
                    $stmt->execute();
                    $preferred = $stmt->get_result()->fetch_assoc();
                    if ($preferred) {
                        $nextProviderId = (int) ($preferred['provider_id'] ?? 0);
                        $assignmentStatus = (string) ($preferred['assignment_status'] ?? '');
                        if ($assignmentStatus === 'in_progress') {
                            $nextStatus = 'in_progress';
                        } elseif ($assignmentStatus === 'completed') {
                            $nextStatus = 'completed';
                        } else {
                            $nextStatus = 'accepted';
                        }
                    }
                }
            }

            if ($nextProviderId !== null && $nextProviderId > 0) {
                $stmt = $conn->prepare(
                    "UPDATE orders
                     SET provider_id = ?,
                         status = CASE
                            WHEN status IN ('pending', 'assigned', 'accepted', 'in_progress', 'completed') THEN ?
                            ELSE status
                         END
                     WHERE id = ?"
                );
                if (!$stmt) {
                    throw new RuntimeException('Failed to update order provider');
                }
                $stmt->bind_param("isi", $nextProviderId, $nextStatus, $orderId);
                $stmt->execute();
                $nextAssignedProviderId = (int) $nextProviderId;
            } else {
                $remainingAssigned = 0;
                if (tableExists('order_providers')) {
                    $stmt = $conn->prepare(
                        "SELECT COUNT(*) AS cnt
                         FROM order_providers
                         WHERE order_id = ?
                           AND assignment_status = 'assigned'"
                    );
                    if ($stmt) {
                        $stmt->bind_param("i", $orderId);
                        $stmt->execute();
                        $countRow = $stmt->get_result()->fetch_assoc();
                        $remainingAssigned = (int) ($countRow['cnt'] ?? 0);
                    }
                }

                $fallbackStatus = $remainingAssigned > 0 ? 'assigned' : 'pending';
                $stmt = $conn->prepare(
                    "UPDATE orders
                     SET provider_id = CASE WHEN provider_id = ? THEN NULL ELSE provider_id END,
                         status = CASE
                            WHEN status IN ('pending', 'assigned', 'accepted') THEN ?
                            ELSE status
                         END
                     WHERE id = ?"
                );
                if (!$stmt) {
                    throw new RuntimeException('Failed to reset order assignment');
                }
                $stmt->bind_param("isi", $providerId, $fallbackStatus, $orderId);
                $stmt->execute();
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        sendError($e->getMessage(), 500);
    }

    $stmt = $conn->prepare(
        "SELECT o.*,
                c.name_ar AS category_name, c.icon AS category_icon,
                p.full_name AS provider_name, p.avatar AS provider_avatar, p.rating AS provider_rating,
                p.phone AS provider_phone, p.email AS provider_email, p.status AS provider_status,
                p.is_available AS provider_is_available
         FROM orders o
         LEFT JOIN service_categories c ON o.category_id = c.id
         LEFT JOIN providers p ON o.provider_id = p.id
         WHERE o.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        $decisionTitle = $decision === 'accept' ? 'تم تأكيد مقدم الخدمة' : 'تحديث على طلبك';
        $decisionBody = $decision === 'accept'
            ? 'تم قبول الطلب من مقدم الخدمة وجاري البدء في التنفيذ.'
            : 'اعتذر مقدم الخدمة عن الطلب وجاري إعادة التعيين من العمليات.';
        notifyUserOrderEvent((int) ($order['user_id'] ?? 0), $orderId, $decisionTitle, $decisionBody, 'order', [
            'event' => $decision === 'accept' ? 'provider_assignment_accepted' : 'provider_assignment_rejected',
            'status' => $decision === 'accept' ? 'accepted' : 'assigned',
        ]);
        if ($decision === 'accept' && !empty($autoRejectedProviderIds)) {
            notifyOrderProvidersEvent(
                array_values($autoRejectedProviderIds),
                $orderId,
                'تم إسناد الطلب لمقدم خدمة آخر',
                'تم اختيار مقدم خدمة آخر لهذا الطلب، ولن يظهر ضمن الطلبات الجديدة لديك.',
                'order',
                ['event' => 'provider_assignment_taken', 'status' => 'rejected']
            );
        }
        if ($decision === 'reject' && $nextAssignedProviderId > 0 && $nextAssignedProviderId !== $providerId) {
            notifyProviderOrderEvent(
                $nextAssignedProviderId,
                $orderId,
                'طلب جديد بانتظار قبولك',
                'تم تحويل طلب خدمة إليك بعد اعتذار مقدم خدمة آخر. يرجى مراجعة الطلب.',
                'order',
                ['event' => 'provider_assignment_received', 'status' => 'assigned', 'source' => 'reassigned']
            );
        }
        sendSuccess(
            [
                'order_id' => $orderId,
                'decision' => $decision,
            ],
            $decision === 'accept' ? 'تم قبول الطلب بنجاح' : 'تم رفض الطلب بنجاح'
        );
    }

    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $latestOrder = $stmt->get_result()->fetch_assoc();

    if (!$latestOrder) {
        $decisionTitle = $decision === 'accept' ? 'تم تأكيد مقدم الخدمة' : 'تحديث على طلبك';
        $decisionBody = $decision === 'accept'
            ? 'تم قبول الطلب من مقدم الخدمة، وسيتم تحديثك عند التحرك إلى موقعك.'
            : 'اعتذر مقدم الخدمة عن الطلب وجاري إعادة التعيين من العمليات.';
        notifyUserOrderEvent((int) ($order['user_id'] ?? 0), $orderId, $decisionTitle, $decisionBody, 'order', [
            'event' => $decision === 'accept' ? 'provider_assignment_accepted' : 'provider_assignment_rejected',
            'status' => $decision === 'accept' ? 'accepted' : 'assigned',
        ]);
        if ($decision === 'accept' && !empty($autoRejectedProviderIds)) {
            notifyOrderProvidersEvent(
                array_values($autoRejectedProviderIds),
                $orderId,
                'تم إسناد الطلب لمقدم خدمة آخر',
                'تم اختيار مقدم خدمة آخر لهذا الطلب، ولن يظهر ضمن الطلبات الجديدة لديك.',
                'order',
                ['event' => 'provider_assignment_taken', 'status' => 'rejected']
            );
        }
        if ($decision === 'reject' && $nextAssignedProviderId > 0 && $nextAssignedProviderId !== $providerId) {
            notifyProviderOrderEvent(
                $nextAssignedProviderId,
                $orderId,
                'طلب جديد بانتظار قبولك',
                'تم تحويل طلب خدمة إليك بعد اعتذار مقدم خدمة آخر. يرجى مراجعة الطلب.',
                'order',
                ['event' => 'provider_assignment_received', 'status' => 'assigned', 'source' => 'reassigned']
            );
        }
        sendSuccess(
            [
                'order_id' => $orderId,
                'decision' => $decision,
            ],
            $decision === 'accept' ? 'تم قبول الطلب بنجاح' : 'تم رفض الطلب بنجاح'
        );
    }

    $context = ['role' => 'provider', 'provider_id' => $providerId];
    $decisionTitle = $decision === 'accept' ? 'تم تأكيد مقدم الخدمة' : 'تحديث على طلبك';
    $decisionBody = $decision === 'accept'
        ? 'تم قبول الطلب من مقدم الخدمة، وسيتم تحديثك عند التحرك إلى موقعك.'
        : 'اعتذر مقدم الخدمة عن الطلب وجاري إعادة التعيين من العمليات.';
    notifyUserOrderEvent((int) ($latestOrder['user_id'] ?? $order['user_id'] ?? 0), $orderId, $decisionTitle, $decisionBody, 'order', [
        'event' => $decision === 'accept' ? 'provider_assignment_accepted' : 'provider_assignment_rejected',
        'status' => $decision === 'accept' ? 'accepted' : 'assigned',
    ]);
    if ($decision === 'accept' && !empty($autoRejectedProviderIds)) {
        notifyOrderProvidersEvent(
            array_values($autoRejectedProviderIds),
            $orderId,
            'تم إسناد الطلب لمقدم خدمة آخر',
            'تم اختيار مقدم خدمة آخر لهذا الطلب، ولن يظهر ضمن الطلبات الجديدة لديك.',
            'order',
            ['event' => 'provider_assignment_taken', 'status' => 'rejected']
        );
    }
    if ($decision === 'reject' && $nextAssignedProviderId > 0 && $nextAssignedProviderId !== $providerId) {
        notifyProviderOrderEvent(
            $nextAssignedProviderId,
            $orderId,
            'طلب جديد بانتظار قبولك',
            'تم تحويل طلب خدمة إليك بعد اعتذار مقدم خدمة آخر. يرجى مراجعة الطلب.',
            'order',
            ['event' => 'provider_assignment_received', 'status' => 'assigned', 'source' => 'reassigned']
        );
    }
    sendSuccess(
        formatOrder($latestOrder, $context),
        $decision === 'accept' ? 'تم قبول الطلب بنجاح' : 'تم رفض الطلب بنجاح'
    );
}

/**
 * Provider updates operational order status (on_the_way / arrived / in_progress).
 */
function providerUpdateOrderStatus($input)
{
    global $conn;

    $providerId = requireAuth();
    $role = getAuthRole();
    if ($role !== 'provider') {
        sendError('Unauthorized', 403);
    }
    ensureProviderCanViewOrders((int) $providerId);

    ensureOrderExtensionsSchema();
    ensureOrderLiveLocationSchema();

    $orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;
    $targetStatus = strtolower(trim((string) ($input['status'] ?? $input['target_status'] ?? '')));
    $allowedStatuses = ['on_the_way', 'arrived', 'in_progress'];
    $lat = isset($input['lat']) ? (float) $input['lat'] : null;
    $lng = isset($input['lng']) ? (float) $input['lng'] : null;
    $accuracy = isset($input['accuracy']) ? max(0, (float) $input['accuracy']) : null;
    $speed = isset($input['speed']) ? max(0, (float) $input['speed']) : null;
    $heading = isset($input['heading']) ? (float) $input['heading'] : null;
    $hasLocationPayload = ($lat !== null && $lng !== null);

    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }
    if (!in_array($targetStatus, $allowedStatuses, true)) {
        sendError('status must be one of: on_the_way, arrived, in_progress', 422);
    }
    if (($lat !== null && $lng === null) || ($lat === null && $lng !== null)) {
        sendError('lat و lng يجب إرسالهما معًا', 422);
    }
    if ($hasLocationPayload && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
        sendError('إحداثيات الموقع غير صحيحة', 422);
    }

    $stmt = $conn->prepare(
        "SELECT o.id, o.status, o.provider_id, o.user_id, o.problem_details,
                op.assignment_status AS current_assignment_status
         FROM orders o
         LEFT JOIN order_providers op ON op.order_id = o.id AND op.provider_id = ?
         WHERE o.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        sendError('Failed to prepare status query', 500);
    }
    $stmt->bind_param("ii", $providerId, $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        sendError('Order not found', 404);
    }
    denyContainerOrdersForProvider($order['problem_details'] ?? null, $orderId);

    $currentStatus = strtolower(trim((string) ($order['status'] ?? '')));
    $currentAssignment = strtolower(trim((string) ($order['current_assignment_status'] ?? '')));
    $isLegacyPrimary = (int) ($order['provider_id'] ?? 0) === $providerId;
    if ($currentAssignment === '' && !$isLegacyPrimary) {
        sendError('هذا الطلب غير معيّن لك', 403);
    }

    if (in_array($currentStatus, ['completed', 'cancelled'], true)) {
        sendError('لا يمكن تعديل حالة الطلب بعد الإغلاق', 422);
    }

    $allowedCurrent = ['assigned', 'accepted', 'on_the_way', 'arrived', 'in_progress'];
    if (!in_array($currentStatus, $allowedCurrent, true)) {
        sendError('لا يمكن تحديث الحالة في الوضع الحالي', 422);
    }

    $allowedTransitions = [
        'on_the_way' => ['assigned', 'accepted', 'on_the_way'],
        'arrived' => ['on_the_way', 'arrived'],
        'in_progress' => ['arrived', 'in_progress'],
    ];
    $validFromStatuses = $allowedTransitions[$targetStatus] ?? [];
    if (!in_array($currentStatus, $validFromStatuses, true)) {
        sendError('تسلسل مراحل الطلب غير صحيح', 422);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "UPDATE orders
             SET provider_id = ?,
                 status = ?
             WHERE id = ?"
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare order update');
        }
        $stmt->bind_param("isi", $providerId, $targetStatus, $orderId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update order status');
        }

        if ($targetStatus === 'in_progress') {
            setProviderAssignmentStatus($orderId, $providerId, 'in_progress');
            if (orderColumnExists('started_at')) {
                $stmt = $conn->prepare(
                    "UPDATE orders
                     SET started_at = COALESCE(started_at, NOW())
                     WHERE id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param("i", $orderId);
                    $stmt->execute();
                }
            }
        } else {
            setProviderAssignmentStatus($orderId, $providerId, 'accepted');
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        sendError($e->getMessage(), 500);
    }

    if ($hasLocationPayload) {
        $saved = saveProviderLiveLocationPoint(
            $orderId,
            $providerId,
            $lat,
            $lng,
            $accuracy,
            $speed,
            $heading
        );
        if (!$saved) {
            sendError('تم تحديث الحالة لكن فشل حفظ الموقع الحي', 500);
        }
    }

    $stmt = $conn->prepare(
        "SELECT o.*,
                c.name_ar AS category_name, c.icon AS category_icon,
                p.full_name AS provider_name, p.avatar AS provider_avatar, p.rating AS provider_rating,
                p.phone AS provider_phone, p.email AS provider_email, p.status AS provider_status,
                p.is_available AS provider_is_available
         FROM orders o
         LEFT JOIN service_categories c ON o.category_id = c.id
         LEFT JOIN providers p ON o.provider_id = p.id
         WHERE o.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        $statusTitles = [
            'on_the_way' => 'الفني في الطريق',
            'arrived' => 'وصل الفني إلى موقعك',
            'in_progress' => 'بدأ تنفيذ الخدمة',
        ];
        $statusBodies = [
            'on_the_way' => 'مقدم الخدمة في الطريق إليك الآن.',
            'arrived' => 'مقدم الخدمة وصل إلى العنوان المحدد.',
            'in_progress' => 'تم بدء تنفيذ الخدمة في موقعك.',
        ];
        notifyUserOrderEvent(
            (int) ($order['user_id'] ?? 0),
            $orderId,
            $statusTitles[$targetStatus] ?? 'تحديث حالة الطلب',
            $statusBodies[$targetStatus] ?? 'تم تحديث حالة طلبك.',
            'order',
            ['event' => 'order_status_updated', 'status' => $targetStatus]
        );
        sendSuccess(null, 'تم تحديث الحالة');
    }

    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $latestOrder = $stmt->get_result()->fetch_assoc();

    $context = ['role' => 'provider', 'provider_id' => $providerId];
    $statusTitles = [
        'on_the_way' => 'الفني في الطريق',
        'arrived' => 'وصل الفني إلى موقعك',
        'in_progress' => 'بدأ تنفيذ الخدمة',
    ];
    $statusBodies = [
        'on_the_way' => 'مقدم الخدمة في الطريق إليك الآن.',
        'arrived' => 'مقدم الخدمة وصل إلى العنوان المحدد.',
        'in_progress' => 'تم بدء تنفيذ الخدمة في موقعك.',
    ];
    notifyUserOrderEvent(
        (int) ($latestOrder['user_id'] ?? $order['user_id'] ?? 0),
        $orderId,
        $statusTitles[$targetStatus] ?? 'تحديث حالة الطلب',
        $statusBodies[$targetStatus] ?? 'تم تحديث حالة طلبك.',
        'order',
        ['event' => 'order_status_updated', 'status' => $targetStatus]
    );
    sendSuccess(formatOrder($latestOrder, $context), 'تم تحديث حالة الطلب');
}

/**
 * Provider sends live location update for an active order.
 */
function updateProviderLiveLocation($input)
{
    global $conn;

    $providerId = requireAuth();
    $role = getAuthRole();
    if ($role !== 'provider') {
        sendError('Unauthorized', 403);
    }
    ensureProviderCanViewOrders((int) $providerId);

    ensureOrderExtensionsSchema();
    ensureOrderLiveLocationSchema();

    $orderId = isset($input['order_id']) ? (int) $input['order_id'] : 0;
    $lat = isset($input['lat']) ? (float) $input['lat'] : null;
    $lng = isset($input['lng']) ? (float) $input['lng'] : null;
    $accuracy = isset($input['accuracy']) ? max(0, (float) $input['accuracy']) : null;
    $speed = isset($input['speed']) ? max(0, (float) $input['speed']) : null;
    $heading = isset($input['heading']) ? (float) $input['heading'] : null;

    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }
    if ($lat === null || $lng === null) {
        sendError('lat و lng مطلوبان', 422);
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        sendError('إحداثيات الموقع غير صحيحة', 422);
    }

    $stmt = $conn->prepare(
        "SELECT o.id, o.status, o.provider_id, o.problem_details,
                op.assignment_status AS current_assignment_status
         FROM orders o
         LEFT JOIN order_providers op ON op.order_id = o.id AND op.provider_id = ?
         WHERE o.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        sendError('Failed to prepare location query', 500);
    }
    $stmt->bind_param("ii", $providerId, $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        sendError('Order not found', 404);
    }
    denyContainerOrdersForProvider($order['problem_details'] ?? null, $orderId);

    $currentAssignment = strtolower(trim((string) ($order['current_assignment_status'] ?? '')));
    $isLegacyPrimary = (int) ($order['provider_id'] ?? 0) === $providerId;
    if ($currentAssignment === '' && !$isLegacyPrimary) {
        sendError('هذا الطلب غير معيّن لك', 403);
    }

    $orderStatus = strtolower(trim((string) ($order['status'] ?? '')));
    if (in_array($orderStatus, ['completed', 'cancelled'], true)) {
        sendError('لا يمكن إرسال موقع لطلب مغلق', 422);
    }
    if ($orderStatus !== 'on_the_way') {
        sendError('يُسمح بالتتبع الحي أثناء حالة "في الطريق" فقط', 422);
    }

    $saved = saveProviderLiveLocationPoint(
        $orderId,
        $providerId,
        $lat,
        $lng,
        $accuracy,
        $speed,
        $heading
    );
    if (!$saved) {
        sendError('فشل حفظ الموقع الحي', 500);
    }

    $latestLocation = fetchLatestOrderProviderLocation($orderId, $providerId);
    sendSuccess([
        'order_id' => $orderId,
        'provider_id' => $providerId,
        'live_location' => $latestLocation,
    ], 'تم تحديث الموقع الحي');
}

/**
 * Persist provider live location point and mirror latest coordinates on providers table.
 */
function saveProviderLiveLocationPoint($orderId, $providerId, $lat, $lng, $accuracy = null, $speed = null, $heading = null)
{
    global $conn;

    ensureOrderLiveLocationSchema();
    $orderId = (int) $orderId;
    $providerId = (int) $providerId;
    if ($orderId <= 0 || $providerId <= 0) {
        return false;
    }

    $lat = (float) $lat;
    $lng = (float) $lng;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO order_live_locations
        (order_id, provider_id, lat, lng, accuracy, speed, heading, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        return false;
    }

    $accuracyValue = $accuracy !== null ? max(0, (float) $accuracy) : 0.0;
    $speedValue = $speed !== null ? max(0, (float) $speed) : 0.0;
    $headingValue = $heading !== null ? (float) $heading : 0.0;
    $stmt->bind_param(
        "iiddddd",
        $orderId,
        $providerId,
        $lat,
        $lng,
        $accuracyValue,
        $speedValue,
        $headingValue
    );
    if (!$stmt->execute()) {
        return false;
    }

    if (tableExists('providers')) {
        if (!tableColumnExists('providers', 'current_lat')) {
            $conn->query("ALTER TABLE `providers` ADD COLUMN `current_lat` DECIMAL(10,8) NULL");
        }
        if (!tableColumnExists('providers', 'current_lng')) {
            $conn->query("ALTER TABLE `providers` ADD COLUMN `current_lng` DECIMAL(11,8) NULL");
        }
        if (!tableColumnExists('providers', 'location_updated_at')) {
            $conn->query("ALTER TABLE `providers` ADD COLUMN `location_updated_at` DATETIME NULL");
        }

        $updates = [];
        if (tableColumnExists('providers', 'current_lat')) {
            $updates[] = "current_lat = " . (float) $lat;
        }
        if (tableColumnExists('providers', 'current_lng')) {
            $updates[] = "current_lng = " . (float) $lng;
        }
        if (tableColumnExists('providers', 'location_updated_at')) {
            $updates[] = "location_updated_at = '" . $conn->real_escape_string(date('Y-m-d H:i:s')) . "'";
        }
        if (!empty($updates)) {
            $sql = "UPDATE providers SET " . implode(', ', $updates) . " WHERE id = " . (int) $providerId;
            $conn->query($sql);
        }
    }

    return true;
}

/**
 * Start Job (Provider)
 */
function startJob($input)
{
    global $conn;
    $providerId = requireAuth();
    $role = getAuthRole();

    if ($role !== 'provider')
        sendError('Unauthorized', 403);
    ensureProviderCanViewOrders((int) $providerId);

    ensureOrderExtensionsSchema();
    ensureOrderSparePartsSchema();
    ensureSparePartsPricingSchema();
    sparePartScopeEnsureSchema();

    $orderId = (int) ($input['order_id'] ?? 0);
    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }

    $stmt = $conn->prepare(
        "SELECT o.status, o.user_id, o.problem_details
         FROM orders o
         WHERE o.id = ?
           AND (
               o.provider_id = ?
               OR EXISTS (
                   SELECT 1
                   FROM order_providers op
                   WHERE op.order_id = o.id
                     AND op.provider_id = ?
                     AND op.assignment_status IN ('assigned', 'accepted', 'in_progress', 'completed')
               )
           )"
    );
    $stmt->bind_param("iii", $orderId, $providerId, $providerId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res)
        sendError('Order not found', 404);
    denyContainerOrdersForProvider($res['problem_details'] ?? null, $orderId);
    if (!in_array($res['status'], ['assigned', 'accepted', 'on_the_way', 'in_progress'], true)) {
        sendError('Cannot start job in current status', 422);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = 'in_progress', provider_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $providerId, $orderId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to start job');
        }

        if (orderColumnExists('started_at')) {
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET started_at = COALESCE(started_at, NOW())
                 WHERE id = ?"
            );
            if ($stmt) {
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
            }
        }

        setProviderAssignmentStatus($orderId, $providerId, 'in_progress');

        $conn->commit();
        notifyUserOrderEvent(
            (int) ($res['user_id'] ?? 0),
            $orderId,
            'بدأ تنفيذ طلبك',
            'تم بدء تنفيذ الخدمة فعليًا من مقدم الخدمة.',
            'order',
            ['event' => 'job_started', 'status' => 'in_progress']
        );
        sendSuccess(null, 'Job started');
    } catch (Throwable $e) {
        $conn->rollback();
        sendError($e->getMessage(), 500);
    }
}

/**
 * Complete Job (Provider)
 */
function completeJob($input)
{
    global $conn;
    $providerId = requireAuth();
    $role = getAuthRole();

    if ($role !== 'provider')
        sendError('Unauthorized', 403);
    ensureProviderCanViewOrders((int) $providerId);

    ensureOrderExtensionsSchema();
    ensureOrderCreationSchema();

    $orderId = (int) ($input['order_id'] ?? 0);
    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }

    $completionImagesPayload = $input['completion_images']
        ?? ($input['completion_proof_images'] ?? ($input['inspection_images'] ?? []));
    $completionImages = normalizeMediaListPayload($completionImagesPayload);

    $completionUploadKey = null;
    $completionFileKeys = [
        'completion_media_files',
        'completion_media_files[]',
        'completion_proof_files',
        'completion_proof_files[]',
        // Backward compatibility alias.
        'inspection_media_files',
        'inspection_media_files[]',
    ];
    foreach ($completionFileKeys as $fileKey) {
        if (!isset($_FILES[$fileKey])) {
            continue;
        }
        $fileRef = $_FILES[$fileKey];
        $hasName = is_array($fileRef['name'] ?? null)
            ? !empty(array_filter($fileRef['name'], static function ($item) {
                return trim((string) $item) !== '';
            }))
            : trim((string) ($fileRef['name'] ?? '')) !== '';
        if ($hasName) {
            $completionUploadKey = $fileKey;
            break;
        }
    }

    $uploadedCompletionFiles = [];
    $completionUploadErrors = [];
    if ($completionUploadKey !== null) {
        $files = $_FILES[$completionUploadKey];
        if (is_array($files['name'] ?? null)) {
            $total = count($files['name']);
            for ($i = 0; $i < $total; $i++) {
                if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $fileData = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];
                    $uploadResult = uploadFile($fileData, 'orders');
                    if (!empty($uploadResult['success'])) {
                        $uploadedCompletionFiles[] = (string) $uploadResult['path'];
                    } else {
                        $completionUploadErrors[] = trim((string) ($uploadResult['message'] ?? 'فشل رفع صورة دليل التنفيذ'));
                    }
                } elseif ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $completionUploadErrors[] = 'فشل رفع صورة دليل التنفيذ (code ' . (int) $files['error'][$i] . ')';
                }
            }
        } else {
            if ((int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($files, 'orders');
                if (!empty($uploadResult['success'])) {
                    $uploadedCompletionFiles[] = (string) $uploadResult['path'];
                } else {
                    $completionUploadErrors[] = trim((string) ($uploadResult['message'] ?? 'فشل رفع صورة دليل التنفيذ'));
                }
            } elseif ((int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $completionUploadErrors[] = 'فشل رفع صورة دليل التنفيذ (code ' . (int) $files['error'] . ')';
            }
        }
    }

    if ($completionUploadKey !== null && empty($uploadedCompletionFiles) && !empty($completionUploadErrors)) {
        sendError('تعذر رفع صور دليل التنفيذ: ' . $completionUploadErrors[0], 422);
    }

    if (!empty($uploadedCompletionFiles)) {
        $completionImages = array_values(array_unique(array_merge($completionImages, $uploadedCompletionFiles)));
    }

    if (empty($completionImages)) {
        sendError('يرجى رفع صورة دليل التنفيذ قبل إنهاء الطلب', 422);
    }

    $extraColumns = '';
    if (orderColumnExists('invoice_status')) {
        $extraColumns .= ', o.invoice_status';
    }
    if (orderColumnExists('inspection_images')) {
        $extraColumns .= ', o.inspection_images';
    }

    $stmt = $conn->prepare(
        "SELECT o.status, o.user_id, o.problem_details{$extraColumns}
         FROM orders o
         WHERE o.id = ?
           AND (
               o.provider_id = ?
               OR EXISTS (
                   SELECT 1
                   FROM order_providers op
                   WHERE op.order_id = o.id
                     AND op.provider_id = ?
                     AND op.assignment_status IN ('assigned', 'accepted', 'in_progress', 'completed')
               )
           )"
    );
    $stmt->bind_param("iii", $orderId, $providerId, $providerId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res)
        sendError('Order not found', 404);
    denyContainerOrdersForProvider($res['problem_details'] ?? null, $orderId);
    if ($res['status'] !== 'in_progress') {
        sendError('Job must be in progress to complete', 422);
    }
    if (isset($res['invoice_status']) && ($res['invoice_status'] ?? 'none') !== 'approved') {
        sendError('لا يمكن إنهاء الطلب قبل موافقة العميل على الفاتورة', 422);
    }

    if (!empty($res['inspection_images'])) {
        $existingInspectionImages = normalizeMediaListPayload($res['inspection_images']);
        if (!empty($existingInspectionImages)) {
            $completionImages = array_values(array_unique(array_merge($existingInspectionImages, $completionImages)));
        }
    }
    $completionImagesJson = json_encode($completionImages, JSON_UNESCAPED_UNICODE);

    $conn->begin_transaction();
    try {
        // Usually checks for payment here, but let's just mark complete for now.
        $updateColumns = ["status = 'completed'", 'provider_id = ?'];
        $updateTypes = 'i';
        $updateValues = [$providerId];

        if (orderColumnExists('inspection_images')) {
            $updateColumns[] = 'inspection_images = ?';
            $updateTypes .= 's';
            $updateValues[] = $completionImagesJson;
        }

        if (orderColumnExists('completed_at')) {
            $updateColumns[] = 'completed_at = NOW()';
        }

        $updateSql = "UPDATE orders SET " . implode(', ', $updateColumns) . " WHERE id = ?";
        $updateTypes .= 'i';
        $updateValues[] = $orderId;

        $stmt = $conn->prepare($updateSql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare complete job query');
        }
        $stmt->bind_param($updateTypes, ...$updateValues);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to complete job');
        }

        setProviderAssignmentStatus($orderId, $providerId, 'completed');

        $conn->commit();
        notifyUserOrderEvent(
            (int) ($res['user_id'] ?? 0),
            $orderId,
            'تم إكمال الخدمة',
            'تم تنفيذ طلبك بنجاح. يمكنك الآن الدفع وتقييم الخدمة.',
            'order',
            [
                'event' => 'job_completed',
                'status' => 'completed',
                'inspection_images_count' => count($completionImages),
            ]
        );
        sendSuccess([
            'inspection_images_count' => count($completionImages),
        ], 'Job completed');
    } catch (Throwable $e) {
        $conn->rollback();
        sendError($e->getMessage(), 500);
    }
}

/**
 * Create new order
 */
function createOrder($input)
{
    global $conn;

    $userId = requireAuth();
    $role = getAuthRole();
    if ($role === 'provider') {
        sendError('Providers cannot create orders', 403);
    }

    if (userColumnExists('is_blacklisted') || userColumnExists('no_show_count')) {
        $userColumns = [];
        if (userColumnExists('is_blacklisted')) {
            $userColumns[] = 'is_blacklisted';
        }
        if (userColumnExists('blacklist_reason')) {
            $userColumns[] = 'blacklist_reason';
        }
        if (userColumnExists('no_show_count')) {
            $userColumns[] = 'no_show_count';
        }

        $stmt = $conn->prepare("SELECT " . implode(', ', $userColumns) . " FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userStatus = $stmt->get_result()->fetch_assoc();

        if (!empty($userStatus['is_blacklisted'])) {
            $reason = trim((string) ($userStatus['blacklist_reason'] ?? ''));
            $msg = 'تم إيقاف حسابك مؤقتًا من حجز خدمات جديدة';
            if ($reason !== '') {
                $msg .= ' - السبب: ' . $reason;
            }
            sendError($msg, 403);
        }

        if (isset($userStatus['no_show_count'])) {
            $threshold = getNoShowThreshold();
            $noShowCount = (int) ($userStatus['no_show_count'] ?? 0);
            if ($noShowCount >= $threshold) {
                sendError('تم تعليق الحجز مؤقتًا بسبب تكرار عدم الالتزام بالمواعيد. تواصل مع الدعم لإعادة التفعيل', 403);
            }
        }
    }

    if (orderColumnExists('is_rated')) {
        $completedOrderSort = orderColumnExists('completed_at')
            ? 'completed_at DESC, id DESC'
            : 'created_at DESC, id DESC';
        $stmt = $conn->prepare(
            "SELECT id, order_number
             FROM orders
             WHERE user_id = ?
               AND status = 'completed'
               AND (is_rated IS NULL OR is_rated = 0)
             ORDER BY {$completedOrderSort}
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $pendingReviewOrder = $stmt->get_result()->fetch_assoc();
            if (!empty($pendingReviewOrder['id'])) {
                $pendingOrderNumber = trim((string) ($pendingReviewOrder['order_number'] ?? ''));
                $suffix = $pendingOrderNumber !== '' ? (' #' . $pendingOrderNumber) : '';
                sendError('يرجى تقييم طلبك المكتمل' . $suffix . ' قبل إنشاء طلب جديد', 422);
            }
        }
    }

    ensureOrderExtensionsSchema();
    ensureOrderCreationSchema();
    ensureSpecialServicesSchema();
    serviceAreaEnsureServiceLinksSchema();
    sparePartScopeEnsureSchema();

    $categoryId = (int) ($input['category_id'] ?? 0);
    $address = trim((string) ($input['address'] ?? ''));
    // Handle lat/lng conversion from string if coming from multipart form
    $lat = isset($input['lat']) ? (float) $input['lat'] : null;
    $lng = isset($input['lng']) ? (float) $input['lng'] : null;
    $notes = isset($input['notes']) ? trim((string) $input['notes']) : null;
    if ($notes === '') {
        $notes = null;
    }
    $scheduledDate = $input['scheduled_date'] ?? null;
    $scheduledTime = $input['scheduled_time'] ?? null;
    $problemDetailsRaw = $input['problem_details'] ?? null;
    $serviceIds = normalizeIntegerIds($input['service_ids'] ?? []);
    $isCustomService = normalizeBooleanValue($input['is_custom_service'] ?? false);
    $customServiceTitle = trim((string) ($input['custom_service_title'] ?? ''));
    $customServiceDescription = trim((string) ($input['custom_service_description'] ?? ''));

    if (
        !$isCustomService
        && $categoryId <= 0
        && ($customServiceTitle !== '' || $customServiceDescription !== '')
    ) {
        $isCustomService = true;
    }

    if ($isCustomService && $customServiceTitle === '') {
        $customServiceTitle = 'خدمة أخرى';
    }

    if ($isCustomService && $categoryId <= 0) {
        $categoryId = ensureOtherServiceCategory();
    }

    if (!$categoryId) {
        sendError('الخدمة مطلوبة', 422);
    }

    if (empty($address)) {
        sendError('العنوان مطلوب', 422);
    }

    $countryCode = serviceAreaNormalizeCountryCode(
        $input['country_code'] ?? ($input['address_country_code'] ?? '')
    );
    $coverage = serviceAreaEvaluateCoverage($countryCode, $lat, $lng);
    if (!($coverage['is_supported'] ?? true)) {
        $message = trim((string) ($coverage['message_ar'] ?? ''));
        if ($message === '') {
            $message = 'أنت خارج نطاق تقديم الخدمة';
        }
        sendError($message, 422);
    }

    // Handle File Uploads
    $uploadedFiles = [];
    $uploadErrors = [];
    $mediaFilesKey = null;
    if (isset($_FILES['media_files']) && !empty($_FILES['media_files']['name'])) {
        $mediaFilesKey = 'media_files';
    } elseif (isset($_FILES['media_files[]']) && !empty($_FILES['media_files[]']['name'])) {
        // Defensive fallback if multipart field was sent as media_files[] literally.
        $mediaFilesKey = 'media_files[]';
    }
    $hasMediaUploadInput = $mediaFilesKey !== null;
    if ($hasMediaUploadInput) {
        // If single file uploaded as array or multiple files
        $files = $_FILES[$mediaFilesKey];
        if (is_array($files['name'])) {
            $total = count($files['name']);
            for ($i = 0; $i < $total; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $fileData = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    $uploadResult = uploadFile($fileData, 'orders');
                    if ($uploadResult['success']) {
                        $uploadedFiles[] = $uploadResult['path'];
                    } else {
                        $uploadErrors[] = trim((string) ($uploadResult['message'] ?? 'فشل رفع المرفق'));
                    }
                } elseif ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $uploadErrors[] = 'فشل رفع المرفق (code ' . (int) $files['error'][$i] . ')';
                }
            }
        } else {
            // Single file without array syntax
            if ($files['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($files, 'orders');
                if ($uploadResult['success']) {
                    $uploadedFiles[] = $uploadResult['path'];
                } else {
                    $uploadErrors[] = trim((string) ($uploadResult['message'] ?? 'فشل رفع المرفق'));
                }
            } elseif ($files['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadErrors[] = 'فشل رفع المرفق (code ' . (int) $files['error'] . ')';
            }
        }
    }

    if ($hasMediaUploadInput && empty($uploadedFiles) && !empty($uploadErrors)) {
        sendError('تعذر رفع المرفقات: ' . $uploadErrors[0], 422);
    }

    $attachmentsJson = !empty($uploadedFiles) ? json_encode($uploadedFiles) : null;

    $problemDetails = normalizeProblemDetailsPayload($problemDetailsRaw);
    $requestedSpareParts = normalizeOrderRequestedSparePartsPayload($problemDetails['spare_parts'] ?? []);
    if (!empty($requestedSpareParts)) {
        $problemDetails['spare_parts'] = $requestedSpareParts;
    }

    $moduleSignal = strtolower(trim((string) ($problemDetails['module'] ?? '')));
    $typeSignal = strtolower(trim((string) ($problemDetails['type'] ?? '')));
    $isContainerModule = !empty($problemDetails['container_request'])
        || $moduleSignal === 'container_rental'
        || $typeSignal === 'container_rental'
        || strpos($moduleSignal, 'container') !== false
        || strpos($typeSignal, 'container') !== false;
    $isFurnitureModule = !empty($problemDetails['furniture_request'])
        || $moduleSignal === 'furniture_moving'
        || $typeSignal === 'furniture_moving'
        || strpos($moduleSignal, 'furniture') !== false
        || strpos($typeSignal, 'furniture') !== false;

    if ($isFurnitureModule) {
        $specialCategoryId = specialEnsureFurnitureCategoryId();
        if (($specialCategoryId ?? 0) > 0) {
            $categoryId = (int) $specialCategoryId;
        }
    } elseif ($isContainerModule) {
        $specialCategoryId = specialEnsureContainerCategoryId();
        if (($specialCategoryId ?? 0) > 0) {
            $categoryId = (int) $specialCategoryId;
        }
    }
    if (empty($serviceIds)) {
        $serviceIds = normalizeIntegerIds(
            $problemDetails['service_type_ids'] ?? ($problemDetails['sub_services'] ?? [])
        );
    }

    if (!empty($serviceIds)) {
        $coverageAreaIds = ordersCoverageAreaIds($coverage);
        $allowedServiceIds = ordersResolveAllowedServiceIds($serviceIds, $coverageAreaIds);
        if (count($allowedServiceIds) !== count($serviceIds)) {
            sendError('بعض الخدمات المختارة غير متاحة في نطاقك الحالي', 422);
        }
        $serviceIds = $allowedServiceIds;
    }

    if (!empty($serviceIds)) {
        $problemDetails['service_type_ids'] = $serviceIds;
        $problemDetails['sub_services'] = $serviceIds;
    }

    if ($isCustomService) {
        $problemDetails['is_custom_service'] = true;
        $problemDetails['custom_service'] = [
            'title' => $customServiceTitle,
            'description' => $customServiceDescription,
        ];
        if ($notes === null && $customServiceDescription !== '') {
            $notes = $customServiceDescription;
        }
    }

    $problemType = strtolower(trim((string) ($problemDetails['type'] ?? '')));
    $isSparePartsRequest = !empty($requestedSpareParts)
        || in_array($problemType, ['spare_parts_with_installation', 'spare_parts_order', 'spare_parts'], true);

    if ($isSparePartsRequest) {
        enforceSparePartsMinOrderIfNeeded($requestedSpareParts, $problemDetails);
    }

    if ($isCustomService && !$isSparePartsRequest && $notes === null) {
        sendError('يرجى كتابة وصف المشكلة للخدمة المخصصة', 422);
    }

    if ($isCustomService && !$isSparePartsRequest && empty($uploadedFiles)) {
        sendError('يرجى إرفاق صورة للخدمة المخصصة', 422);
    }

    if ($notes === null && !empty($problemDetails['user_desc'])) {
        $notes = trim((string) $problemDetails['user_desc']);
    }

    $problemDetailsJson = !empty($problemDetails) ? json_encode($problemDetails, JSON_UNESCAPED_UNICODE) : null;

    // Generate order number
    $orderNumber = 'RT' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

    $insertData = [
        'order_number' => $orderNumber,
        'user_id' => $userId,
        'category_id' => $categoryId,
        'address' => $address,
        'lat' => $lat,
        'lng' => $lng,
        'notes' => $notes,
        'scheduled_date' => $scheduledDate,
        'scheduled_time' => $scheduledTime,
    ];

    if (orderColumnExists('service_type_id')) {
        $primaryServiceTypeId = !empty($problemDetails['service_type_id'])
            ? (int) $problemDetails['service_type_id']
            : (!empty($serviceIds) ? (int) $serviceIds[0] : 0);
        if ($primaryServiceTypeId > 0) {
            $insertData['service_type_id'] = $primaryServiceTypeId;
        }
    }

    if (orderColumnExists('type_option_id') && !empty($problemDetails['type_option_id'])) {
        $typeOptionId = (int) $problemDetails['type_option_id'];
        if ($typeOptionId > 0) {
            $insertData['type_option_id'] = $typeOptionId;
        }
    }

    if (orderColumnExists('attachments')) {
        $insertData['attachments'] = $attachmentsJson;
    }
    if (orderColumnExists('problem_details')) {
        $insertData['problem_details'] = $problemDetailsJson;
    }
    if (orderColumnExists('problem_description')) {
        $insertData['problem_description'] = $notes ?? '';
    }
    if (orderColumnExists('problem_images')) {
        $insertData['problem_images'] = $attachmentsJson;
    }

    $columns = array_keys($insertData);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $types = '';
    $values = [];

    foreach ($columns as $column) {
        $value = $insertData[$column];
        $types .= inferBindTypeByColumn($column, $value);
        $values[] = $value;
    }

    $sql = "INSERT INTO orders (" . implode(', ', $columns) . ") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('تعذر تجهيز حفظ الطلب');
    }
    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        throw new RuntimeException('تعذر حفظ الطلب حالياً');
    }

    $orderId = $conn->insert_id;
    applyOrderLifecycleDefaults($orderId, $scheduledDate, $scheduledTime);
    persistOrderServiceItems($orderId, $serviceIds, $isCustomService ? $customServiceTitle : '', $customServiceDescription);
    persistOrderRequestedSpareParts($orderId, $requestedSpareParts, $problemDetails, $categoryId);
    syncSpecialServiceRequestFromOrder($orderId, $userId, $problemDetails, $uploadedFiles, $notes, $address);

    // Get the created order
    $sql = "SELECT o.*, c.name_ar as category_name, c.icon as category_icon,
            p.full_name as provider_name, p.avatar as provider_avatar, p.rating as provider_rating,
            p.phone as provider_phone, p.email as provider_email, p.status as provider_status,
            p.is_available as provider_is_available
            FROM orders o
            LEFT JOIN service_categories c ON o.category_id = c.id
            LEFT JOIN providers p ON o.provider_id = p.id
            WHERE o.id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $fallbackOrder = db()->fetch("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);
        sendSuccess(formatOrder($fallbackOrder ?: ['id' => $orderId]), 'تم إنشاء الطلب بنجاح');
    }
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        $fallbackOrder = db()->fetch("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);
        sendSuccess(formatOrder($fallbackOrder ?: ['id' => $orderId]), 'تم إنشاء الطلب بنجاح');
    }

    notifyUserOrderEvent(
        $userId,
        $orderId,
        'تم استلام طلبك',
        'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.',
        'order',
        ['event' => 'order_created', 'status' => 'pending']
    );

    $providerIdsToNotify = resolveOrderProviderIdsForNotification($orderId, ['assigned', 'accepted', 'in_progress']);
    if (!empty($providerIdsToNotify)) {
        notifyOrderProvidersEvent(
            $providerIdsToNotify,
            $orderId,
            'طلب جديد بانتظار قبولك',
            'تم تعيين طلب خدمة جديد لك. يرجى مراجعة التفاصيل والرد.',
            'order',
            ['event' => 'provider_assignment_received', 'status' => (string) ($order['status'] ?? 'assigned')]
        );
    }

    // Notify admins/operations about new order (email + WhatsApp)
    try {
        ensureNotificationSchema();
        $userInfo = db()->fetch('SELECT full_name, phone FROM users WHERE id = ? LIMIT 1', [$userId]);
        notifyAdminNewOrder($orderId, [
            'order_number'  => $orderNumber,
            'user_name'     => $userInfo['full_name'] ?? 'غير معروف',
            'phone'         => $userInfo['phone'] ?? '',
            'category_name' => $order['category_name'] ?? '',
            'address'       => $address,
            'total_amount'  => $order['total_amount'] ?? 0,
            'notes'         => $notes ?? '',
        ]);
    } catch (Throwable $notifErr) {
        error_log('Order notification error: ' . $notifErr->getMessage());
    }

    sendSuccess(formatOrder($order), 'تم إنشاء الطلب بنجاح');
}

/**
 * Cancel order
 */
function cancelOrder($input)
{
    global $conn;

    $userId = requireAuth();
    $orderId = $input['order_id'] ?? $_GET['id'] ?? 0;
    $reason = $input['reason'] ?? null;

    // Verify ownership and status
    $stmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        sendError('Order not found', 404);
    }

    $orderStatus = strtolower(trim((string) ($order['status'] ?? '')));
    if (!in_array($orderStatus, ['pending', 'assigned'], true)) {
        sendError('لا يمكن إلغاء هذا الطلب', 422);
    }

    if ($orderStatus === 'assigned') {
        $hasAdvancedProviderState = false;

        if (tableExists('order_providers')) {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt
                 FROM order_providers
                 WHERE order_id = ?
                   AND assignment_status IN ('accepted', 'in_progress', 'completed')"
            );
            if ($stmt) {
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $hasAdvancedProviderState = ((int) ($row['cnt'] ?? 0)) > 0;
            }
        }

        if (!$hasAdvancedProviderState && tableExists('order_live_locations')) {
            $stmt = $conn->prepare(
                "SELECT id
                 FROM order_live_locations
                 WHERE order_id = ?
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
                $hasAdvancedProviderState = (bool) $stmt->get_result()->fetch_assoc();
            }
        }

        if ($hasAdvancedProviderState) {
            sendError('لا يمكن إلغاء الطلب بعد تحرك مقدم الخدمة', 422);
        }
    }

    $providerIdsForCancelNotification = resolveOrderProviderIdsForNotification((int) $orderId, ['assigned', 'accepted', 'in_progress', 'completed']);

    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    ensureOrderExtensionsSchema();
    if (tableExists('order_providers')) {
        $stmt = $conn->prepare(
            "UPDATE order_providers
             SET assignment_status = 'cancelled'
             WHERE order_id = ?
               AND assignment_status <> 'completed'"
        );
        if ($stmt) {
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
        }
    }

    notifyUserOrderEvent(
        $userId,
        (int) $orderId,
        'تم إلغاء الطلب',
        'تم تأكيد إلغاء طلبك بنجاح.',
        'order',
        ['event' => 'order_cancelled', 'status' => 'cancelled']
    );

    if (!empty($providerIdsForCancelNotification)) {
        notifyOrderProvidersEvent(
            $providerIdsForCancelNotification,
            (int) $orderId,
            'تم إلغاء الطلب',
            'تم إلغاء الطلب من قبل العميل. لا حاجة للمتابعة على هذا الطلب.',
            'order',
            ['event' => 'order_cancelled_by_client', 'status' => 'cancelled']
        );
    }

    sendSuccess(null, 'تم إلغاء الطلب');
}

/**
 * Rate completed order
 */
function rateOrder($input)
{
    global $conn;

    $userId = requireAuth();
    $orderId = $input['order_id'] ?? 0;
    $rating = (int) ($input['rating'] ?? 0);
    $comment = $input['comment'] ?? null;
    $qualityRating = isset($input['quality_rating']) ? (int) $input['quality_rating'] : null;
    $speedRating = isset($input['speed_rating']) ? (int) $input['speed_rating'] : null;
    $priceRating = isset($input['price_rating']) ? (int) $input['price_rating'] : null;
    $behaviorRating = isset($input['behavior_rating']) ? (int) $input['behavior_rating'] : null;
    $tags = $input['tags'] ?? null;

    if ($rating < 1 || $rating > 5) {
        sendError('التقييم يجب أن يكون بين 1 و 5', 422);
    }

    foreach (
        [
            'quality_rating' => $qualityRating,
            'speed_rating' => $speedRating,
            'price_rating' => $priceRating,
            'behavior_rating' => $behaviorRating
        ] as $field => $value
    ) {
        if ($value !== null && ($value < 1 || $value > 5)) {
            sendError("قيمة $field يجب أن تكون بين 1 و 5", 422);
        }
    }

    // Verify order
    $stmt = $conn->prepare("SELECT id, provider_id, status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        sendError('Order not found', 404);
    }

    if ($order['status'] !== 'completed') {
        sendError('يمكن تقييم الطلبات المكتملة فقط', 422);
    }

    $reviewProviderId = !empty($order['provider_id']) ? (int) $order['provider_id'] : resolveOrderReviewProviderId($orderId);
    if ($reviewProviderId <= 0) {
        sendError('لا يمكن إضافة تقييم قبل تعيين مقدم الخدمة', 422);
    }

    // Create review with optional detailed fields if schema supports it.
    $columns = ['user_id', 'provider_id', 'order_id', 'rating', 'comment'];
    $placeholders = ['?', '?', '?', '?', '?'];
    $types = 'iiiis';
    $values = [$userId, $reviewProviderId, $orderId, $rating, $comment];

    if (reviewColumnExists('quality_rating') && $qualityRating !== null) {
        $columns[] = 'quality_rating';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $qualityRating;
    }
    if (reviewColumnExists('speed_rating') && $speedRating !== null) {
        $columns[] = 'speed_rating';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $speedRating;
    }
    if (reviewColumnExists('price_rating') && $priceRating !== null) {
        $columns[] = 'price_rating';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $priceRating;
    }
    if (reviewColumnExists('behavior_rating') && $behaviorRating !== null) {
        $columns[] = 'behavior_rating';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $behaviorRating;
    }
    if (reviewColumnExists('tags') && $tags !== null) {
        $tagsJson = is_array($tags) ? json_encode(array_values($tags), JSON_UNESCAPED_UNICODE) : (string) $tags;
        $columns[] = 'tags';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $tagsJson;
    }

    $sql = "INSERT INTO reviews (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    // Update provider rating
    if ($reviewProviderId > 0) {
        $stmt = $conn->prepare("UPDATE providers SET rating = (SELECT AVG(rating) FROM reviews WHERE provider_id = ?) WHERE id = ?");
        $stmt->bind_param("ii", $reviewProviderId, $reviewProviderId);
        $stmt->execute();
    }

    // Mark order as rated
    $stmt = $conn->prepare("UPDATE orders SET is_rated = 1 WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    sendSuccess(null, 'شكراً لتقييمك');
}

function getOrderForClientPayment($orderId, $userId, $allowAlreadyPaid = false)
{
    global $conn;

    $selectColumns = ['id', 'status'];
    if (orderColumnExists('total_amount')) {
        $selectColumns[] = 'total_amount';
    }
    if (orderColumnExists('payment_status')) {
        $selectColumns[] = 'payment_status';
    }
    if (orderColumnExists('invoice_status')) {
        $selectColumns[] = 'invoice_status';
    }
    if (orderColumnExists('problem_details')) {
        $selectColumns[] = 'problem_details';
    }
    if (orderTableColumnExists('subtotal_amount')) {
        $selectColumns[] = 'subtotal_amount';
    }
    if (orderTableColumnExists('discount_amount')) {
        $selectColumns[] = 'discount_amount';
    }
    if (orderTableColumnExists('promo_code')) {
        $selectColumns[] = 'promo_code';
    }

    $stmt = $conn->prepare("SELECT " . implode(', ', $selectColumns) . " FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        sendError('Order not found', 404);
    }

    if (($order['status'] ?? '') === 'cancelled') {
        sendError('لا يمكن دفع طلب ملغي', 422);
    }

    if (
        isset($order['payment_status'])
        && $order['payment_status'] === 'paid'
        && !$allowAlreadyPaid
    ) {
        sendError('تم دفع هذا الطلب مسبقًا', 422);
    }

    if (isset($order['invoice_status'])) {
        if ($order['invoice_status'] === 'pending') {
            $allowPending = false;
            if (isset($order['problem_details'])) {
                $allowPending = isSparePartsWithInstallationOrderPayload($order['problem_details']);
            }
            if (!$allowPending) {
                sendError('لا يمكن الدفع قبل اعتماد الفاتورة', 422);
            }
        }
        if ($order['invoice_status'] === 'rejected') {
            sendError('تم رفض الفاتورة الحالية، يرجى انتظار تحديث جديد', 422);
        }
    }

    return $order;
}

function getMyFatoorahConfig()
{
    global $conn;

    $baseUrl = defined('MYFATOORAH_BASE_URL')
        ? rtrim((string) MYFATOORAH_BASE_URL, '/')
        : 'https://api-sa.myfatoorah.com';
    $token = defined('MYFATOORAH_TOKEN') ? trim((string) MYFATOORAH_TOKEN) : '';
    $enabled = true;

    if (tableExists('app_settings')) {
        $keys = ['myfatoorah_enabled', 'myfatoorah_base_url', 'myfatoorah_token'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$keys);
            if ($stmt->execute()) {
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach ($rows as $row) {
                    $key = (string) ($row['setting_key'] ?? '');
                    $value = trim((string) ($row['setting_value'] ?? ''));
                    if ($key === 'myfatoorah_enabled' && $value !== '') {
                        $enabled = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
                    } elseif ($key === 'myfatoorah_base_url' && $value !== '') {
                        $baseUrl = rtrim($value, '/');
                    } elseif ($key === 'myfatoorah_token' && $value !== '') {
                        $token = $value;
                    }
                }
            }
        }
    }

    if (!$enabled) {
        sendError('بوابة الدفع غير مفعلة من لوحة الإدارة', 503);
    }

    if ($token === '') {
        sendError('لم يتم ضبط MyFatoorah Token في الإعدادات', 503);
    }

    return [
        'base_url' => $baseUrl,
        'token' => $token,
        'enabled' => $enabled,
    ];
}

function myFatoorahRequest($endpoint, array $payload)
{
    $config = getMyFatoorahConfig();
    $url = $config['base_url'] . '/' . ltrim($endpoint, '/');
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return [
            'success' => false,
            'status_code' => 500,
            'message' => 'Invalid request payload',
            'data' => null,
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($rawResponse === false) {
        return [
            'success' => false,
            'status_code' => 0,
            'message' => $curlError !== '' ? $curlError : 'Payment gateway connection failed',
            'data' => null,
        ];
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => 'Invalid payment gateway response',
            'data' => null,
        ];
    }

    $isSuccess = (bool) ($decoded['IsSuccess'] ?? false);
    if (!$isSuccess) {
        $message = trim((string) ($decoded['Message'] ?? 'Payment gateway request failed'));
        $hasValidationErrors = !empty($decoded['ValidationErrors']) && is_array($decoded['ValidationErrors']);
        $isGenericMessage = in_array(strtolower($message), ['invalid data', 'payment gateway request failed', ''], true);

        if (($message === '' || $isGenericMessage) && $hasValidationErrors) {
            $firstError = $decoded['ValidationErrors'][0] ?? [];
            if (is_array($firstError)) {
                $message = trim((string) ($firstError['Error'] ?? 'Payment validation failed'));
            } else {
                $message = trim((string) $firstError);
            }
        }

        if ($message === '') {
            $message = 'Payment gateway request failed';
        }

        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => $message,
            'data' => $decoded['Data'] ?? null,
        ];
    }

    return [
        'success' => true,
        'status_code' => $statusCode,
        'message' => trim((string) ($decoded['Message'] ?? '')),
        'data' => $decoded['Data'] ?? null,
    ];
}

function chooseMyFatoorahMethodId($methods, $preferredCode = '')
{
    if (!is_array($methods) || empty($methods)) {
        return null;
    }

    $preferredCode = strtolower(trim((string) $preferredCode));
    $preferredCodes = [];
    if ($preferredCode !== '') {
        $preferredCodes[] = $preferredCode;
    }
    $preferredCodes = array_values(array_unique(array_merge(
        $preferredCodes,
        ['md', 'vm', 'ap', 'stcpay', 'knet']
    )));

    foreach ($preferredCodes as $code) {
        foreach ($methods as $method) {
            if (!is_array($method)) {
                continue;
            }
            $methodCode = strtolower(trim((string) ($method['PaymentMethodCode'] ?? '')));
            if ($methodCode !== $code) {
                continue;
            }

            $methodId = (int) ($method['PaymentMethodId'] ?? 0);
            if ($methodId > 0) {
                return $methodId;
            }
        }
    }

    foreach ($methods as $method) {
        if (!is_array($method)) {
            continue;
        }
        $methodId = (int) ($method['PaymentMethodId'] ?? 0);
        if ($methodId > 0) {
            return $methodId;
        }
    }

    return null;
}

function buildMyFatoorahCallbackUrls($orderId)
{
    $orderId = (int) $orderId;
    $baseAppUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    if ($baseAppUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseAppUrl = $scheme . '://' . $host . '/admin-panel';
    }

    $callbackBase = $baseAppUrl . '/api/mobile/orders.php';
    return [
        'callback_url' => $callbackBase . '?action=myfatoorah_callback&order_id=' . $orderId,
        'error_url' => $callbackBase . '?action=myfatoorah_error&order_id=' . $orderId,
    ];
}

function normalizeMyFatoorahCustomerMobile($rawPhone)
{
    $digits = preg_replace('/\D+/', '', (string) $rawPhone);
    if ($digits === '') {
        return '0500000000';
    }

    // Convert Saudi international format (9665xxxxxxxx) to local format (05xxxxxxxx)
    if (strpos($digits, '966') === 0) {
        $digits = '0' . ltrim(substr($digits, 3), '0');
    } elseif (strpos($digits, '00966') === 0) {
        $digits = '0' . ltrim(substr($digits, 5), '0');
    }

    // Keep at most 11 chars as required by MyFatoorah.
    if (strlen($digits) > 11) {
        $digits = substr($digits, -11);
    }

    // Keep a reasonable minimum length.
    if (strlen($digits) < 8) {
        $digits = str_pad($digits, 8, '0', STR_PAD_LEFT);
    }

    return $digits;
}

function updateOrderMyFatoorahMeta($orderId, $userId, array $meta)
{
    global $conn;

    $updates = [];
    $types = '';
    $values = [];

    if (orderColumnExists('myfatoorah_invoice_id') && isset($meta['invoice_id'])) {
        $updates[] = 'myfatoorah_invoice_id = ?';
        $types .= 's';
        $values[] = trim((string) $meta['invoice_id']);
    }

    if (orderColumnExists('myfatoorah_payment_url') && isset($meta['payment_url'])) {
        $updates[] = 'myfatoorah_payment_url = ?';
        $types .= 's';
        $values[] = trim((string) $meta['payment_url']);
    }

    if (orderColumnExists('myfatoorah_payment_method_id') && isset($meta['payment_method_id'])) {
        $updates[] = 'myfatoorah_payment_method_id = ?';
        $types .= 'i';
        $values[] = (int) $meta['payment_method_id'];
    }

    if (orderColumnExists('myfatoorah_payment_id') && isset($meta['payment_id'])) {
        $updates[] = 'myfatoorah_payment_id = ?';
        $types .= 's';
        $values[] = trim((string) $meta['payment_id']);
    }

    if (orderColumnExists('myfatoorah_invoice_status') && isset($meta['invoice_status'])) {
        $updates[] = 'myfatoorah_invoice_status = ?';
        $types .= 's';
        $values[] = trim((string) $meta['invoice_status']);
    }

    if (orderColumnExists('myfatoorah_last_status_at')) {
        $updates[] = 'myfatoorah_last_status_at = ?';
        $types .= 's';
        $values[] = date('Y-m-d H:i:s');
    }

    if (empty($updates)) {
        return;
    }

    $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    $types .= 'ii';
    $values[] = $orderId;
    $values[] = $userId;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
}

function executeMyFatoorahPayment($input)
{
    global $conn;

    ensureOrderExtensionsSchema();
    ensureOrderCreationSchema();

    $userId = requireAuth();
    $orderId = (int) ($input['order_id'] ?? 0);
    $requestedAmount = (float) ($input['amount'] ?? 0);
    $inputPromoCode = normalizePromoCodeValue($input['promo_code'] ?? '');

    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }

    $order = getOrderForClientPayment($orderId, $userId);
    $baseAmount = resolveOrderBaseAmountForPayment($order, $requestedAmount);
    $storedPromoCode = normalizePromoCodeValue($order['promo_code'] ?? '');
    $promoCodeForPricing = $inputPromoCode !== '' ? $inputPromoCode : $storedPromoCode;
    $autoApproveInvoice = false;
    if (
        isset($order['invoice_status']) &&
        $order['invoice_status'] === 'pending' &&
        isset($order['problem_details']) &&
        isSparePartsWithInstallationOrderPayload($order['problem_details'])
    ) {
        $autoApproveInvoice = true;
    }
    if ($baseAmount <= 0) {
        sendError('قيمة الدفع غير صالحة', 422);
    }

    try {
        $pricing = resolvePromoPricingForAmount($promoCodeForPricing, $baseAmount, $userId);
    } catch (Throwable $e) {
        sendError($e->getMessage(), 422);
    }

    $payableAmount = (float) ($pricing['final_amount'] ?? 0);
    if ($payableAmount < 0) {
        $payableAmount = 0;
    }
    $pricing['final_amount'] = $payableAmount;

    if ($payableAmount <= 0) {
        $conn->begin_transaction();
        try {
            $updates = [];
            $types = '';
            $values = [];

            if (orderColumnExists('total_amount')) {
                $updates[] = 'total_amount = ?';
                $types .= 'd';
                $values[] = 0;
            }
            if (orderColumnExists('payment_method')) {
                $updates[] = 'payment_method = ?';
                $types .= 's';
                $values[] = 'card';
            }
            if (orderColumnExists('payment_status')) {
                $updates[] = "payment_status = 'paid'";
            }

            if (!empty($updates)) {
                $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $types .= 'ii';
                $values[] = $orderId;
                $values[] = $userId;
                $stmt->bind_param($types, ...$values);
                if (!$stmt->execute()) {
                    throw new RuntimeException('فشل تحديث الطلب بعد الخصم');
                }
            }

            persistOrderPromoPricing($orderId, $userId, $pricing);
            incrementPromoUsageIfPresent($pricing['promo_code'] ?? null, $userId, $orderId, (int) ($pricing['promo_id'] ?? 0));
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            sendError($e->getMessage(), 422);
        }

        notifyUserOrderEvent(
            $userId,
            $orderId,
            'تم تطبيق الخصم',
            'تم تطبيق كود الخصم ولا يوجد مبلغ متبقٍ للدفع.',
            'order',
            [
                'event' => 'payment_recorded',
                'status' => (string) ($order['status'] ?? ''),
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'promo_code' => $pricing['promo_code'] ?? null,
                'discount_amount' => (float) ($pricing['discount_amount'] ?? 0),
            ]
        );

        $providerIdsAfterFreePayment = resolveOrderProviderIdsForNotification($orderId, ['assigned', 'accepted', 'in_progress', 'completed']);
        if (!empty($providerIdsAfterFreePayment)) {
            notifyOrderProvidersEvent(
                $providerIdsAfterFreePayment,
                $orderId,
                'تم سداد قيمة الطلب',
                'تم تسجيل حالة الدفع للطلب بنجاح (بعد تطبيق الخصم).',
                'order',
                [
                    'event' => 'payment_recorded',
                    'payment_status' => 'paid',
                    'payment_method' => 'card',
                    'promo_code' => $pricing['promo_code'] ?? null,
                ]
            );
        }

        sendSuccess([
            'order_id' => $orderId,
            'amount' => 0,
            'discount_amount' => (float) ($pricing['discount_amount'] ?? 0),
            'promo_code' => $pricing['promo_code'] ?? null,
            'is_paid' => true,
            'skip_gateway' => true,
        ], 'تم تطبيق الخصم ولا يوجد مبلغ مستحق');
    }

    $stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: [];

    $customerName = trim((string) ($user['full_name'] ?? 'عميل Darfix'));
    if ($customerName === '') {
        $customerName = 'عميل Darfix';
    }

    $customerEmail = trim((string) ($user['email'] ?? 'no-reply@ertah.app'));
    if ($customerEmail === '' || strpos($customerEmail, '@') === false) {
        $customerEmail = 'no-reply@ertah.app';
    }

    $customerPhone = normalizeMyFatoorahCustomerMobile($user['phone'] ?? '');

    $initiate = myFatoorahRequest('/v2/InitiatePayment', [
        'InvoiceAmount' => round($payableAmount, 2),
        'CurrencyIso' => 'SAR',
    ]);

    if (!$initiate['success']) {
        sendError($initiate['message'] ?: 'تعذر تهيئة بوابة الدفع', 422);
    }

    $methods = [];
    if (is_array($initiate['data'])) {
        $methods = $initiate['data']['PaymentMethods'] ?? [];
    }
    $preferredCode = strtolower(trim((string) ($input['payment_method_code'] ?? '')));
    $allowedCodes = ['md', 'vm', 'ap', 'stcpay', 'knet'];
    if (!in_array($preferredCode, $allowedCodes, true)) {
        $preferredCode = '';
    }
    $paymentMethodId = chooseMyFatoorahMethodId($methods, $preferredCode);
    if ($paymentMethodId === null) {
        sendError('لا توجد طريقة دفع متاحة حالياً', 422);
    }

    $callbackUrls = buildMyFatoorahCallbackUrls($orderId);
    $execute = myFatoorahRequest('/v2/ExecutePayment', [
        'PaymentMethodId' => $paymentMethodId,
        'InvoiceValue' => round($payableAmount, 2),
        'DisplayCurrencyIso' => 'SAR',
        'CustomerName' => $customerName,
        'CustomerEmail' => $customerEmail,
        'CustomerMobile' => $customerPhone,
        'Language' => 'AR',
        'CustomerReference' => 'order-' . $orderId,
        'UserDefinedField' => 'order:' . $orderId,
        'SourceInfo' => 'ERTAH-App',
        'CallBackUrl' => $callbackUrls['callback_url'],
        'ErrorUrl' => $callbackUrls['error_url'],
    ]);

    if (!$execute['success']) {
        sendError($execute['message'] ?: 'تعذر إنشاء رابط الدفع', 422);
    }

    $resultData = is_array($execute['data']) ? $execute['data'] : [];
    $invoiceId = (string) ($resultData['InvoiceId'] ?? '');
    $paymentUrl = (string) ($resultData['PaymentURL'] ?? $resultData['InvoiceURL'] ?? '');

    if ($invoiceId === '' || $paymentUrl === '') {
        sendError('استجابة بوابة الدفع غير مكتملة', 422);
    }

    if (orderColumnExists('total_amount')) {
        $amountStmt = $conn->prepare("UPDATE orders SET total_amount = ? WHERE id = ? AND user_id = ?");
        if ($amountStmt) {
            $amountStmt->bind_param("dii", $payableAmount, $orderId, $userId);
            $amountStmt->execute();
        }
    }
    persistOrderPromoPricing($orderId, $userId, $pricing);

    updateOrderMyFatoorahMeta($orderId, $userId, [
        'invoice_id' => $invoiceId,
        'payment_url' => $paymentUrl,
        'payment_method_id' => $paymentMethodId,
        'invoice_status' => 'Pending',
    ]);

    notifyUserOrderEvent(
        $userId,
        $orderId,
        'تم إصدار رابط الدفع',
        'تم إصدار رابط دفع لطلبك. أكمل الدفع لتأكيد الطلب.',
        'order',
        [
            'event' => 'payment_link_created',
            'status' => (string) ($order['status'] ?? ''),
            'invoice_id' => $invoiceId,
            'payment_required' => true,
        ]
    );

    sendSuccess([
        'order_id' => $orderId,
        'amount' => round($payableAmount, 2),
        'subtotal_amount' => (float) ($pricing['base_amount'] ?? $payableAmount),
        'discount_amount' => (float) ($pricing['discount_amount'] ?? 0),
        'promo_code' => $pricing['promo_code'] ?? null,
        'invoice_id' => $invoiceId,
        'payment_url' => $paymentUrl,
        'payment_method_id' => $paymentMethodId,
    ], 'تم إنشاء رابط الدفع');
}

function markOrderAsPaidFromGateway($orderId, $userId, $amount, $reference)
{
    global $conn;

    $amount = (float) $amount;
    if ($amount <= 0) {
        return;
    }

    $orderStatus = '';
    $wasPaid = false;
    $orderPromoCode = '';
    $stateColumns = ['status', 'payment_status'];
    if (orderTableColumnExists('promo_code')) {
        $stateColumns[] = 'promo_code';
    }
    $stateStmt = $conn->prepare(
        "SELECT " . implode(', ', $stateColumns) . " FROM orders WHERE id = ? AND user_id = ? LIMIT 1"
    );
    if ($stateStmt) {
        $stateStmt->bind_param("ii", $orderId, $userId);
        $stateStmt->execute();
        $state = $stateStmt->get_result()->fetch_assoc();
        if ($state) {
            $orderStatus = trim((string) ($state['status'] ?? ''));
            $wasPaid = strtolower(trim((string) ($state['payment_status'] ?? ''))) === 'paid';
            $orderPromoCode = normalizePromoCodeValue($state['promo_code'] ?? '');
        }
    }

    $conn->begin_transaction();
    try {
        if (tableExists('transactions')) {
            $existsStmt = $conn->prepare("SELECT id FROM transactions WHERE order_id = ? AND reference_number = ? LIMIT 1");
            $existsStmt->bind_param("is", $orderId, $reference);
            $existsStmt->execute();
            $alreadyLogged = $existsStmt->get_result()->fetch_assoc();

            if (!$alreadyLogged) {
                $description = 'دفع طلب #' . $orderId . ' عبر MyFatoorah';
                $insertTx = $conn->prepare("INSERT INTO transactions (user_id, order_id, type, amount, description, reference_number, status) VALUES (?, ?, 'payment', ?, ?, ?, 'completed')");
                $insertTx->bind_param("iidss", $userId, $orderId, $amount, $description, $reference);
                if (!$insertTx->execute()) {
                    throw new RuntimeException('فشل تسجيل عملية الدفع');
                }
            }
        }

        $updates = [];
        $types = '';
        $values = [];

        if (orderColumnExists('total_amount')) {
            $updates[] = 'total_amount = ?';
            $types .= 'd';
            $values[] = $amount;
        }

        if (orderColumnExists('payment_method')) {
            $updates[] = 'payment_method = ?';
            $types .= 's';
            $values[] = 'card';
        }

        if (orderColumnExists('payment_status')) {
            $updates[] = "payment_status = 'paid'";
        }

        if (!empty($updates)) {
            $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $values[] = $orderId;
            $values[] = $userId;
            $types .= 'ii';
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                throw new RuntimeException('فشل تحديث الطلب بعد الدفع');
            }
        }

        if (!$wasPaid && $orderPromoCode !== '') {
            incrementPromoUsageIfPresent($orderPromoCode, (int) $userId, (int) $orderId);
        }

        $conn->commit();

        if (!$wasPaid) {
            notifyUserOrderEvent(
                (int) $userId,
                (int) $orderId,
                'تم تأكيد الدفع',
                'تم استلام الدفعة بنجاح، وسيتم استكمال إجراءات الطلب.',
                'order',
                [
                    'event' => 'payment_confirmed',
                    'status' => $orderStatus,
                    'payment_status' => 'paid',
                    'payment_reference' => (string) $reference,
                ]
            );

            $providerIdsAfterGatewayPayment = resolveOrderProviderIdsForNotification((int) $orderId, ['assigned', 'accepted', 'in_progress', 'completed']);
            if (!empty($providerIdsAfterGatewayPayment)) {
                notifyOrderProvidersEvent(
                    $providerIdsAfterGatewayPayment,
                    (int) $orderId,
                    'تم سداد قيمة الطلب',
                    'تم تأكيد الدفع عبر بوابة الدفع ويمكن متابعة إجراءات الطلب.',
                    'order',
                    [
                        'event' => 'payment_confirmed',
                        'payment_status' => 'paid',
                        'payment_reference' => (string) $reference,
                    ]
                );
            }
        }
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function getMyFatoorahPaymentStatus($input)
{
    global $conn;

    ensureOrderExtensionsSchema();

    $userId = requireAuth();
    $orderId = (int) ($input['order_id'] ?? 0);
    $invoiceId = trim((string) ($input['invoice_id'] ?? ''));
    $paymentId = trim((string) ($input['payment_id'] ?? ''));

    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }
    if ($invoiceId === '' && $paymentId === '') {
        sendError('invoice_id أو payment_id مطلوب', 422);
    }

    $order = getOrderForClientPayment($orderId, $userId, true);
    $orderTotal = isset($order['total_amount']) ? (float) ($order['total_amount'] ?? 0) : 0;
    $alreadyPaid = isset($order['payment_status']) && $order['payment_status'] === 'paid';

    if ($alreadyPaid) {
        $fallbackAmount = $orderTotal > 0 ? $orderTotal : (float) ($input['amount'] ?? 0);
        $resolvedPaymentId = $paymentId !== ''
            ? $paymentId
            : ($invoiceId !== '' ? ('INV-' . $invoiceId) : 'PAID-ORDER-' . $orderId);

        updateOrderMyFatoorahMeta($orderId, $userId, [
            'invoice_id' => $invoiceId,
            'payment_id' => $resolvedPaymentId,
            'invoice_status' => 'Paid',
        ]);

        sendSuccess([
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'invoice_status' => 'Paid',
            'payment_id' => $resolvedPaymentId,
            'amount' => $fallbackAmount,
            'is_paid' => true,
        ], 'تم جلب حالة الدفع');
    }

    $statusResponse = myFatoorahRequest('/v2/GetPaymentStatus', [
        'Key' => $invoiceId !== '' ? $invoiceId : $paymentId,
        'KeyType' => $invoiceId !== '' ? 'InvoiceId' : 'PaymentId',
    ]);

    if (!$statusResponse['success']) {
        sendError($statusResponse['message'] ?: 'تعذر التحقق من حالة الدفع', 422);
    }

    $data = is_array($statusResponse['data']) ? $statusResponse['data'] : [];
    $invoiceStatus = trim((string) ($data['InvoiceStatus'] ?? ''));
    $isPaid = strtolower($invoiceStatus) === 'paid';
    $invoiceValue = (float) ($data['InvoiceValue'] ?? 0);
    $paidAmount = $orderTotal > 0 ? $orderTotal : $invoiceValue;

    $invoiceTransactions = $data['InvoiceTransactions'] ?? [];
    $gatewayPaymentId = '';
    if (is_array($invoiceTransactions) && !empty($invoiceTransactions)) {
        $firstTx = $invoiceTransactions[0];
        if (is_array($firstTx)) {
            $gatewayPaymentId = trim((string) ($firstTx['PaymentId'] ?? ''));
        }
    }
    if ($gatewayPaymentId === '') {
        $gatewayPaymentId = $invoiceId !== '' ? ('INV-' . $invoiceId) : ('PAY-' . $paymentId);
    }

    $metaUpdate = [
        'invoice_id' => (string) ($data['InvoiceId'] ?? $invoiceId),
        'payment_id' => $gatewayPaymentId,
        'invoice_status' => $invoiceStatus !== '' ? $invoiceStatus : 'Unknown',
    ];
    $invoiceUrlFromGateway = trim((string) ($data['InvoiceURL'] ?? ''));
    if ($invoiceUrlFromGateway !== '') {
        $metaUpdate['payment_url'] = $invoiceUrlFromGateway;
    }
    updateOrderMyFatoorahMeta($orderId, $userId, $metaUpdate);

    if ($isPaid && (!isset($order['payment_status']) || $order['payment_status'] !== 'paid')) {
        try {
            markOrderAsPaidFromGateway($orderId, $userId, $paidAmount, $gatewayPaymentId);
        } catch (Throwable $e) {
            sendError($e->getMessage(), 422);
        }
    }

    sendSuccess([
        'order_id' => $orderId,
        'invoice_id' => (string) ($data['InvoiceId'] ?? $invoiceId),
        'invoice_status' => $invoiceStatus,
        'payment_id' => $gatewayPaymentId,
        'amount' => $paidAmount,
        'is_paid' => $isPaid,
    ], 'تم جلب حالة الدفع');
}

function handleMyFatoorahCallback($input)
{
    global $conn;

    ensureOrderExtensionsSchema();

    $orderId = (int) ($_GET['order_id'] ?? ($input['order_id'] ?? 0));
    $invoiceId = trim((string) ($_GET['invoice_id'] ?? ($_GET['invoiceId'] ?? ($input['invoice_id'] ?? ($input['invoiceId'] ?? '')))));
    $paymentId = trim((string) ($_GET['payment_id'] ?? ($_GET['paymentId'] ?? ($input['payment_id'] ?? ($input['paymentId'] ?? '')))));

    if ($invoiceId === '' && $paymentId === '') {
        sendError('invoice_id أو payment_id مطلوب', 422);
    }

    $statusResponse = myFatoorahRequest('/v2/GetPaymentStatus', [
        'Key' => $invoiceId !== '' ? $invoiceId : $paymentId,
        'KeyType' => $invoiceId !== '' ? 'InvoiceId' : 'PaymentId',
    ]);

    if (!$statusResponse['success']) {
        sendError($statusResponse['message'] ?: 'تعذر التحقق من حالة الدفع', 422);
    }

    $data = is_array($statusResponse['data']) ? $statusResponse['data'] : [];
    $resolvedInvoiceId = trim((string) ($data['InvoiceId'] ?? $invoiceId));
    $invoiceStatus = trim((string) ($data['InvoiceStatus'] ?? ''));
    $isPaid = strtolower($invoiceStatus) === 'paid';
    $invoiceValue = (float) ($data['InvoiceValue'] ?? 0);

    $invoiceTransactions = $data['InvoiceTransactions'] ?? [];
    $gatewayPaymentId = $paymentId;
    if (is_array($invoiceTransactions) && !empty($invoiceTransactions)) {
        $firstTx = $invoiceTransactions[0];
        if (is_array($firstTx)) {
            $gatewayPaymentId = trim((string) ($firstTx['PaymentId'] ?? $gatewayPaymentId));
        }
    }
    if ($gatewayPaymentId === '') {
        $gatewayPaymentId = $resolvedInvoiceId !== '' ? ('INV-' . $resolvedInvoiceId) : ('PAY-' . $orderId);
    }

    if ($orderId <= 0 && $resolvedInvoiceId !== '' && orderColumnExists('myfatoorah_invoice_id')) {
        $stmt = $conn->prepare("SELECT id FROM orders WHERE myfatoorah_invoice_id = ? ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $resolvedInvoiceId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $orderId = (int) ($row['id'] ?? 0);
        }
    }

    if ($orderId <= 0) {
        sendSuccess([
            'invoice_id' => $resolvedInvoiceId,
            'invoice_status' => $invoiceStatus,
            'payment_id' => $gatewayPaymentId,
            'is_paid' => $isPaid,
        ], 'تم استقبال callback لكن لم يتم ربطه بطلب');
    }

    $stmt = $conn->prepare("SELECT id, user_id, total_amount, payment_status FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (empty($order)) {
        sendError('الطلب غير موجود', 404);
    }

    $userId = (int) ($order['user_id'] ?? 0);
    $orderTotal = (float) ($order['total_amount'] ?? 0);
    $paidAmount = $orderTotal > 0 ? $orderTotal : $invoiceValue;

    if ($userId > 0) {
        $metaUpdate = [
            'invoice_id' => $resolvedInvoiceId,
            'payment_id' => $gatewayPaymentId,
            'invoice_status' => $invoiceStatus !== '' ? $invoiceStatus : 'Unknown',
        ];
        $invoiceUrlFromGateway = trim((string) ($data['InvoiceURL'] ?? ''));
        if ($invoiceUrlFromGateway !== '') {
            $metaUpdate['payment_url'] = $invoiceUrlFromGateway;
        }
        updateOrderMyFatoorahMeta($orderId, $userId, $metaUpdate);
    }

    if ($isPaid && ($order['payment_status'] ?? '') !== 'paid' && $userId > 0) {
        try {
            markOrderAsPaidFromGateway($orderId, $userId, $paidAmount, $gatewayPaymentId);
        } catch (Throwable $e) {
            sendError($e->getMessage(), 422);
        }
    }

    sendSuccess([
        'order_id' => $orderId,
        'invoice_id' => $resolvedInvoiceId,
        'invoice_status' => $invoiceStatus,
        'payment_id' => $gatewayPaymentId,
        'is_paid' => $isPaid,
        'amount' => $paidAmount,
    ], 'تمت معالجة callback بنجاح');
}

function handleMyFatoorahError($input)
{
    global $conn;

    $orderId = (int) ($_GET['order_id'] ?? ($input['order_id'] ?? 0));
    $paymentId = trim((string) ($_GET['paymentId'] ?? ($_GET['payment_id'] ?? ($input['payment_id'] ?? ''))));
    $targetUserId = 0;
    $orderStatus = '';

    if ($orderId > 0 && tableExists('orders') && orderColumnExists('myfatoorah_invoice_status')) {
        $updates = [];
        $types = '';
        $values = [];

        if (orderColumnExists('myfatoorah_invoice_status')) {
            $updates[] = 'myfatoorah_invoice_status = ?';
            $types .= 's';
            $values[] = 'Failed';
        }
        if (orderColumnExists('myfatoorah_last_status_at')) {
            $updates[] = 'myfatoorah_last_status_at = ?';
            $types .= 's';
            $values[] = date('Y-m-d H:i:s');
        }

        if (!empty($updates)) {
            $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types .= 'i';
                $values[] = $orderId;
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
            }
        }

        $ownerStmt = $conn->prepare("SELECT user_id, status FROM orders WHERE id = ? LIMIT 1");
        if ($ownerStmt) {
            $ownerStmt->bind_param("i", $orderId);
            $ownerStmt->execute();
            $owner = $ownerStmt->get_result()->fetch_assoc();
            $targetUserId = (int) ($owner['user_id'] ?? 0);
            $orderStatus = trim((string) ($owner['status'] ?? ''));
        }
    }

    if ($targetUserId > 0 && $orderId > 0) {
        notifyUserOrderEvent(
            $targetUserId,
            $orderId,
            'تعذر إتمام الدفع',
            'تعذر إتمام عملية الدفع. يمكنك إعادة المحاولة من صفحة الطلب.',
            'order',
            [
                'event' => 'payment_failed',
                'status' => $orderStatus,
                'payment_status' => 'failed',
                'payment_id' => $paymentId,
            ]
        );
    }

    sendSuccess([
        'order_id' => $orderId,
        'payment_id' => $paymentId,
    ], 'تم استقبال إشعار فشل الدفع');
}

/**
 * Pay order (wallet/card/cash)
 */
function payOrder($input)
{
    global $conn;

    ensureOrderCreationSchema();

    $userId = requireAuth();
    $orderId = (int) ($input['order_id'] ?? 0);
    $paymentMethod = $input['payment_method'] ?? 'card';
    $requestedAmount = (float) ($input['amount'] ?? 0);
    $inputPromoCode = normalizePromoCodeValue($input['promo_code'] ?? '');
    $transactionId = trim((string) ($input['transaction_id'] ?? ''));

    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }

    if ($requestedAmount <= 0) {
        sendError('قيمة الدفع غير صالحة', 422);
    }

    $allowedMethods = ['wallet', 'card', 'cash', 'apple_pay'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        sendError('طريقة الدفع غير مدعومة', 422);
    }

    $order = getOrderForClientPayment($orderId, $userId);
    $baseAmount = resolveOrderBaseAmountForPayment($order, $requestedAmount);
    $storedPromoCode = normalizePromoCodeValue($order['promo_code'] ?? '');
    $promoCodeForPricing = $inputPromoCode !== '' ? $inputPromoCode : $storedPromoCode;
    $autoApproveInvoice = false;
    if (
        isset($order['invoice_status']) &&
        $order['invoice_status'] === 'pending' &&
        isset($order['problem_details']) &&
        isSparePartsWithInstallationOrderPayload($order['problem_details'])
    ) {
        $autoApproveInvoice = true;
    }

    try {
        $pricing = resolvePromoPricingForAmount($promoCodeForPricing, $baseAmount, $userId);
    } catch (Throwable $e) {
        sendError($e->getMessage(), 422);
    }

    $payableAmount = (float) ($pricing['final_amount'] ?? $baseAmount);
    if ($payableAmount < 0) {
        $payableAmount = 0.0;
    }
    $pricing['final_amount'] = $payableAmount;

    $conn->begin_transaction();

    try {
        if ($paymentMethod === 'wallet' && $payableAmount > 0) {
            $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $walletBalance = (float) ($user['wallet_balance'] ?? 0);

            if ($walletBalance < $payableAmount) {
                throw new RuntimeException('رصيد المحفظة غير كافٍ');
            }

            $newBalance = $walletBalance - $payableAmount;
            $stmt = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
            $stmt->bind_param("di", $newBalance, $userId);
            if (!$stmt->execute()) {
                throw new RuntimeException('فشل تحديث رصيد المحفظة');
            }

            $description = 'دفع طلب #' . $orderId . ' من المحفظة';
            $reference = $transactionId !== '' ? $transactionId : null;
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, order_id, type, amount, balance_after, description, reference_number, status) VALUES (?, ?, 'withdrawal', ?, ?, ?, ?, 'completed')");
            $stmt->bind_param("iiddss", $userId, $orderId, $payableAmount, $newBalance, $description, $reference);
            if (!$stmt->execute()) {
                throw new RuntimeException('فشل تسجيل معاملة المحفظة');
            }
        } elseif ($payableAmount > 0) {
            $description = 'دفع طلب #' . $orderId . ' عبر ' . $paymentMethod;
            $reference = $transactionId !== '' ? $transactionId : null;
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, order_id, type, amount, description, reference_number, status) VALUES (?, ?, 'payment', ?, ?, ?, 'completed')");
            $stmt->bind_param("iidss", $userId, $orderId, $payableAmount, $description, $reference);
            if (!$stmt->execute()) {
                throw new RuntimeException('فشل تسجيل عملية الدفع');
            }
        }

        $updates = [];
        $types = '';
        $values = [];

        if (orderColumnExists('total_amount')) {
            $updates[] = 'total_amount = ?';
            $types .= 'd';
            $values[] = $payableAmount;
        }

        if (orderColumnExists('payment_method')) {
            $updates[] = 'payment_method = ?';
            $types .= 's';
            $values[] = $paymentMethod;
        }

        if (orderColumnExists('payment_status')) {
            $updates[] = "payment_status = 'paid'";
        }

        if ($autoApproveInvoice && orderColumnExists('invoice_status')) {
            $updates[] = "invoice_status = 'approved'";
        }

        if (!empty($updates)) {
            $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $values[] = $orderId;
            $values[] = $userId;
            $types .= 'ii';
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                throw new RuntimeException('فشل تحديث الطلب بعد الدفع');
            }
        }

        if ($autoApproveInvoice) {
            commitRequestedSparePartsForOrder($orderId);
        }

        persistOrderPromoPricing($orderId, $userId, $pricing);
        incrementPromoUsageIfPresent($pricing['promo_code'] ?? null, $userId, $orderId, (int) ($pricing['promo_id'] ?? 0));

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        sendError($e->getMessage(), 422);
    }

    notifyUserOrderEvent(
        $userId,
        $orderId,
        'تم تسجيل الدفعة',
        'تم تسجيل عملية الدفع بنجاح على طلبك.',
        'order',
        [
            'event' => 'payment_recorded',
            'status' => (string) ($order['status'] ?? ''),
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'discount_amount' => (float) ($pricing['discount_amount'] ?? 0),
            'promo_code' => $pricing['promo_code'] ?? null,
        ]
    );

    $providerIdsAfterPayment = resolveOrderProviderIdsForNotification($orderId, ['assigned', 'accepted', 'in_progress', 'completed']);
    if (!empty($providerIdsAfterPayment)) {
        notifyOrderProvidersEvent(
            $providerIdsAfterPayment,
            $orderId,
            'تم سداد قيمة الطلب',
            'تم تسجيل عملية الدفع للطلب بنجاح من العميل.',
            'order',
            [
                'event' => 'payment_recorded',
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'amount' => (float) $payableAmount,
                'promo_code' => $pricing['promo_code'] ?? null,
            ]
        );
    }

    sendSuccess([
        'order_id' => $orderId,
        'payment_method' => $paymentMethod,
        'amount' => $payableAmount,
        'subtotal_amount' => (float) ($pricing['base_amount'] ?? 0),
        'discount_amount' => (float) ($pricing['discount_amount'] ?? 0),
        'promo_code' => $pricing['promo_code'] ?? null,
    ], 'تم الدفع بنجاح');
}

/**
 * Submit Final Invoice (Provider)
 */
function submitInvoice($input)
{
    global $conn;
    $providerId = requireAuth();
    $role = getAuthRole();

    if ($role !== 'provider')
        sendError('Unauthorized', 403);
    ensureProviderCanViewOrders((int) $providerId);

    ensureOrderSparePartsSchema();
    ensureOrderExtensionsSchema();
    ensureSparePartsPricingSchema();
    ensureOrderCreationSchema();
    sparePartScopeEnsureSchema();

    $orderId = (int) ($input['order_id'] ?? 0);
    $laborCost = (float) ($input['labor_cost'] ?? 0);
    $partsCostInput = (float) ($input['parts_cost'] ?? 0);
    $notes = isset($input['notes']) ? trim((string) $input['notes']) : null;
    $sparePartsPayload = $input['spare_parts'] ?? [];
    $legacyInvoiceItemsPayload = $input['invoice_items'] ?? [];

    if ($orderId <= 0) {
        sendError('order_id مطلوب', 422);
    }

    if ($laborCost < 0 || $partsCostInput < 0) {
        sendError('تكاليف الفاتورة غير صالحة', 422);
    }

    if ($notes === '') {
        $notes = null;
    }

    $spareParts = normalizeInvoiceSparePartsPayload($sparePartsPayload);
    $legacyInvoiceItems = normalizeInvoiceItemsPayload($legacyInvoiceItemsPayload);

    // Check order
    $stmt = $conn->prepare(
        "SELECT o.status, o.invoice_status, o.user_id, o.category_id, o.problem_details
         FROM orders o
         WHERE o.id = ?
           AND (
               o.provider_id = ?
               OR EXISTS (
                   SELECT 1
                   FROM order_providers op
                   WHERE op.order_id = o.id
                     AND op.provider_id = ?
                     AND op.assignment_status IN ('assigned', 'accepted', 'in_progress', 'completed')
               )
           )"
    );
    $stmt->bind_param("iii", $orderId, $providerId, $providerId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {
        sendError('Order not found', 404);
    }
    denyContainerOrdersForProvider($res['problem_details'] ?? null, $orderId);
    if ($res['status'] !== 'in_progress') {
        sendError('Job must be in progress to submit invoice', 422);
    }
    if (($res['invoice_status'] ?? 'none') === 'pending') {
        sendError('تم إرسال فاتورة بالفعل، بانتظار اعتماد العميل', 422);
    }
    if (($res['invoice_status'] ?? 'none') === 'approved') {
        sendError('لا يمكن تعديل فاتورة تم اعتمادها', 422);
    }
    $orderCategoryId = (int) ($res['category_id'] ?? 0);
    $allowedCategoryIds = resolveRelatedSparePartCategoryIds($orderCategoryId);
    $orderScopeAreaIds = resolveOrderCoverageAreaIdsForSparePartsScope($orderId);
    $orderScopeServiceIds = resolveOrderServiceIdsForSparePartsScope($orderId);

    $conn->begin_transaction();
    try {
        $invoiceItems = [];
        $partsCost = 0.0;

        // Reset non-committed requested spare parts for this order (resubmission flow).
        if (tableExists('order_spare_parts')) {
            $stmt = $conn->prepare("DELETE FROM order_spare_parts WHERE order_id = ? AND is_committed = 0");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
        }

        if (!empty($spareParts)) {
            foreach ($spareParts as $requestedPart) {
                $sparePartId = (int) ($requestedPart['spare_part_id'] ?? 0);
                $quantity = (int) ($requestedPart['quantity'] ?? 0);
                $customUnitPrice = isset($requestedPart['unit_price']) ? (float) $requestedPart['unit_price'] : null;
                $pricingMode = normalizeSparePricingMode($requestedPart['pricing_mode'] ?? null) ?? 'without_installation';
                $requiresInstallation = array_key_exists('requires_installation', $requestedPart)
                    ? normalizeBooleanValue($requestedPart['requires_installation'])
                    : ($pricingMode !== 'without_installation');
                $itemNotes = trim((string) ($requestedPart['notes'] ?? ''));

                if ($sparePartId <= 0 || $quantity <= 0) {
                    continue;
                }

                $stmt = $conn->prepare(
                    "SELECT id, store_id, category_id, name_ar, price, price_with_installation, price_without_installation, stock_quantity, is_active
                     FROM spare_parts
                     WHERE id = ?"
                );
                $stmt->bind_param("i", $sparePartId);
                $stmt->execute();
                $part = $stmt->get_result()->fetch_assoc();

                if (!$part) {
                    throw new RuntimeException('إحدى قطع الغيار غير موجودة');
                }
                if ((int) ($part['is_active'] ?? 0) !== 1) {
                    throw new RuntimeException('إحدى قطع الغيار غير مفعلة');
                }
                if (!sparePartBelongsToOrderCategory($part['category_id'] ?? null, $orderCategoryId, $allowedCategoryIds)) {
                    throw new RuntimeException('إحدى قطع الغيار لا تتبع قسم الطلب');
                }
                if (!sparePartMatchesOrderScope($sparePartId, $orderScopeAreaIds, $orderScopeServiceIds)) {
                    throw new RuntimeException('إحدى قطع الغيار غير متاحة للخدمة أو المنطقة الحالية');
                }

                $availableQty = (int) ($part['stock_quantity'] ?? 0);
                if ($quantity > $availableQty) {
                    throw new RuntimeException('الكمية المطلوبة من "' . $part['name_ar'] . '" أكبر من المخزون المتاح');
                }

                $unitPrice = $customUnitPrice !== null && $customUnitPrice > 0
                    ? $customUnitPrice
                    : resolveSparePartUnitPrice($part, $pricingMode);
                $lineTotal = $unitPrice * $quantity;
                $partsCost += $lineTotal;

                $storeId = !empty($part['store_id']) ? (int) $part['store_id'] : null;
                $partName = (string) ($part['name_ar'] ?? ('قطعة #' . $sparePartId));

                if (tableExists('order_spare_parts')) {
                    $insert = $conn->prepare(
                        "INSERT INTO order_spare_parts
                        (order_id, provider_id, store_id, spare_part_id, spare_part_name, quantity, pricing_mode, requires_installation, unit_price, total_price, notes, is_committed)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                    );
                    $requiresInstallationValue = $requiresInstallation ? 1 : 0;
                    $insert->bind_param(
                        "iiiisisidds",
                        $orderId,
                        $providerId,
                        $storeId,
                        $sparePartId,
                        $partName,
                        $quantity,
                        $pricingMode,
                        $requiresInstallationValue,
                        $unitPrice,
                        $lineTotal,
                        $itemNotes
                    );
                    if (!$insert->execute()) {
                        throw new RuntimeException('فشل حفظ قطع الغيار المطلوبة');
                    }
                }

                $invoiceItems[] = [
                    'spare_part_id' => $sparePartId,
                    'store_id' => $storeId,
                    'name' => $partName,
                    'quantity' => $quantity,
                    'pricing_mode' => $pricingMode,
                    'requires_installation' => $requiresInstallation,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'notes' => $itemNotes,
                    'source' => 'provider_requested'
                ];
            }
        }

        // Backward compatibility for older provider app versions.
        if (empty($invoiceItems) && !empty($legacyInvoiceItems)) {
            foreach ($legacyInvoiceItems as $item) {
                $itemName = trim((string) ($item['name'] ?? $item['part_name'] ?? ''));
                if ($itemName === '') {
                    continue;
                }

                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
                $lineTotal = isset($item['total_price']) ? (float) $item['total_price'] : null;

                if ($lineTotal === null) {
                    if ($unitPrice !== null) {
                        $lineTotal = $unitPrice * $quantity;
                    } else {
                        $lineTotal = 0.0;
                    }
                }

                if ($unitPrice === null && $quantity > 0) {
                    $unitPrice = $lineTotal / $quantity;
                }

                $partsCost += max(0, $lineTotal);
                $invoiceItems[] = [
                    'name' => $itemName,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice ?? 0.0,
                    'total_price' => max(0, $lineTotal),
                    'source' => 'provider_requested'
                ];
            }
        }

        if (empty($invoiceItems) && $partsCostInput > 0) {
            $partsCost = $partsCostInput;
            $invoiceItems[] = [
                'name' => 'قطع غيار',
                'quantity' => 1,
                'unit_price' => $partsCostInput,
                'total_price' => $partsCostInput,
                'source' => 'manual_parts_cost'
            ];
        } elseif (!empty($invoiceItems) && $partsCost <= 0 && $partsCostInput > 0) {
            $partsCost = $partsCostInput;
        }

        if ($laborCost <= 0 && $partsCost <= 0) {
            throw new RuntimeException('يجب إدخال تكلفة اليد العاملة أو قطع الغيار');
        }

        $totalAmount = $laborCost + $partsCost;
        $invoiceItemsJson = !empty($invoiceItems)
            ? json_encode($invoiceItems, JSON_UNESCAPED_UNICODE)
            : null;

        $updateColumns = [
            'labor_cost = ?',
            'parts_cost = ?',
            'total_amount = ?',
            'invoice_items = ?',
            "invoice_status = 'pending'",
            'inspection_notes = ?',
        ];
        $updateTypes = 'dddss';
        $updateValues = [$laborCost, $partsCost, $totalAmount, $invoiceItemsJson, $notes];

        $updateSql = "UPDATE orders SET " . implode(', ', $updateColumns) . " WHERE id = ?";
        $updateTypes .= 'i';
        $updateValues[] = $orderId;

        $stmt = $conn->prepare($updateSql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare invoice update');
        }
        $stmt->bind_param($updateTypes, ...$updateValues);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to submit invoice');
        }

        $conn->commit();
        notifyUserOrderEvent(
            (int) ($res['user_id'] ?? 0),
            $orderId,
            'تم إصدار الفاتورة',
            'الفاتورة جاهزة للمراجعة. يمكنك الموافقة أو طلب تعديل قبل الدفع.',
            'order',
            ['event' => 'invoice_submitted', 'invoice_status' => 'pending']
        );

        sendSuccess([
            'labor_cost' => $laborCost,
            'parts_cost' => $partsCost,
            'total_amount' => $totalAmount,
            'requested_spare_parts_count' => count($invoiceItems),
            'inspection_images_count' => 0,
        ], 'Invoice submitted, waiting for client approval');
    } catch (Throwable $e) {
        $conn->rollback();
        sendError($e->getMessage(), 422);
    }
}

/**
 * Approve Invoice (Client)
 */
function approveInvoice($input)
{
    global $conn;
    $userId = requireAuth();
    $role = getAuthRole();

    if ($role !== 'user') {
        sendError('Only clients can approve invoices', 403);
    }

    $orderId = $input['order_id'] ?? 0;
    $action = $input['approval_action'] ?? 'approve'; // approve or reject

    // Check order
    $stmt = $conn->prepare("SELECT id, status, invoice_status, total_amount, payment_status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res)
        sendError('Order not found', 404);
    if ($res['invoice_status'] !== 'pending') {
        sendError('No pending invoice to approve', 422);
    }

    if (!in_array($action, ['approve', 'reject'], true)) {
        sendError('approval_action must be approve or reject', 422);
    }

    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $providerIdsForInvoiceDecision = resolveOrderProviderIdsForNotification((int) $orderId, ['assigned', 'accepted', 'in_progress', 'completed']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE orders SET invoice_status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update invoice status');
        }

        if ($newStatus === 'approved') {
            commitRequestedSparePartsForOrder($orderId);
        } else {
            notifyAdminInvoiceRejected($orderId);
        }

        $conn->commit();

        if ($newStatus === 'approved') {
            notifyOrderProvidersEvent(
                $providerIdsForInvoiceDecision,
                (int) $orderId,
                'تم اعتماد الفاتورة',
                'وافق العميل على الفاتورة. يمكنك متابعة الطلب واستكمال التنفيذ.',
                'order',
                ['event' => 'invoice_approved', 'invoice_status' => 'approved']
            );
        } else {
            notifyOrderProvidersEvent(
                $providerIdsForInvoiceDecision,
                (int) $orderId,
                'تم رفض الفاتورة',
                'قام العميل برفض الفاتورة الحالية. يرجى مراجعة الفاتورة وإعادة الإرسال.',
                'order',
                ['event' => 'invoice_rejected_by_client', 'invoice_status' => 'rejected']
            );
        }

        sendSuccess([
            'order_id' => (int) $orderId,
            'invoice_status' => $newStatus,
            'total_amount' => (float) ($res['total_amount'] ?? 0),
            'payment_status' => $res['payment_status'] ?? 'pending',
            'payment_required' => $newStatus === 'approved',
        ], 'Invoice ' . $newStatus);
    } catch (Throwable $e) {
        $conn->rollback();
        sendError($e->getMessage(), 422);
    }
}

/**
 * Notify admin team when client rejects invoice and refuses to proceed to payment.
 */
function notifyAdminInvoiceRejected($orderId)
{
    global $conn;

    if (!tableExists('notifications')) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT o.id, o.order_number, o.total_amount,
                u.full_name AS user_name, u.phone AS user_phone,
                p.full_name AS provider_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN providers p ON p.id = o.provider_id
         WHERE o.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        return;
    }

    $orderNumber = trim((string) ($order['order_number'] ?? ''));
    if ($orderNumber === '') {
        $orderNumber = (string) $orderId;
    }

    $clientName = trim((string) ($order['user_name'] ?? 'عميل'));
    $clientPhone = trim((string) ($order['user_phone'] ?? 'غير متوفر'));
    $providerName = trim((string) ($order['provider_name'] ?? 'غير محدد'));
    $amount = (float) ($order['total_amount'] ?? 0);

    $title = 'تنبيه إداري: رفض فاتورة طلب';
    $body = 'العميل "' . $clientName . '" رفض فاتورة الطلب #' . $orderNumber
        . ' (القيمة ' . number_format($amount, 2) . ' ر.س)'
        . '. رقم التواصل: ' . $clientPhone
        . '. مقدم الخدمة: ' . $providerName
        . '. يرجى متابعة الحالة هاتفيًا.';
    $type = 'system';
    $payload = json_encode([
        'event' => 'invoice_rejected',
        'order_id' => (int) ($order['id'] ?? $orderId),
        'order_number' => $orderNumber,
        'client_name' => $clientName,
        'client_phone' => $clientPhone,
        'provider_name' => $providerName,
    ], JSON_UNESCAPED_UNICODE);

    if (tableColumnExists('notifications', 'data')) {
        $insert = $conn->prepare(
            "INSERT INTO notifications
            (title, body, type, data, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())"
        );
        if ($insert) {
            $insert->bind_param("ssss", $title, $body, $type, $payload);
            $insert->execute();
            return;
        }
    }

    $insert = $conn->prepare(
        "INSERT INTO notifications
        (title, body, type, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())"
    );
    if ($insert) {
        $insert->bind_param("sss", $title, $body, $type);
        $insert->execute();
    }
}

function appSettingValue($key, $default = '')
{
    global $conn;

    if (!tableExists('app_settings')) {
        return $default;
    }

    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !array_key_exists('setting_value', $row)) {
        return $default;
    }

    $value = trim((string) $row['setting_value']);
    return $value === '' ? $default : $value;
}

function getOneSignalAppId()
{
    $fromEnv = trim((string) (getenv('ONESIGNAL_APP_ID') ?: getenv('ONE_SIGNAL_APP_ID') ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    $fromConfig = trim((string) (defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : ''));
    if ($fromConfig !== '') {
        return $fromConfig;
    }

    $fromSettings = appSettingValue('onesignal_app_id', '');
    if ($fromSettings !== '') {
        return $fromSettings;
    }

    return appSettingValue('one_signal_app_id', '');
}

function getOneSignalRestApiKey()
{
    $fromEnv = trim((string) (getenv('ONESIGNAL_REST_API_KEY') ?: getenv('ONE_SIGNAL_REST_API_KEY') ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    $fromConfig = trim((string) (defined('ONESIGNAL_REST_API_KEY') ? ONESIGNAL_REST_API_KEY : ''));
    if ($fromConfig !== '') {
        return $fromConfig;
    }

    $fromSettings = appSettingValue('onesignal_rest_api_key', '');
    if ($fromSettings !== '') {
        return $fromSettings;
    }

    return appSettingValue('one_signal_rest_api_key', '');
}

function createUserNotification($userId, $title, $body, $type = 'order', $data = [])
{
    global $conn;

    if ($userId <= 0 || !tableExists('notifications')) {
        return;
    }

    $allowedTypes = ['order', 'promotion', 'system', 'wallet', 'review'];
    $normalizedType = in_array($type, $allowedTypes, true) ? $type : 'order';
    $payloadJson = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    if ($payloadJson !== null && tableColumnExists('notifications', 'data')) {
        $stmt = $conn->prepare(
            "INSERT INTO notifications (user_id, title, body, type, data, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        if ($stmt) {
            $stmt->bind_param("issss", $userId, $title, $body, $normalizedType, $payloadJson);
            $stmt->execute();
            return;
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, body, type, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    if ($stmt) {
        $stmt->bind_param("isss", $userId, $title, $body, $normalizedType);
        $stmt->execute();
    }
}

function createProviderNotification($providerId, $title, $body, $type = 'order', $data = [])
{
    global $conn;

    if ($providerId <= 0 || !tableExists('notifications') || !tableColumnExists('notifications', 'provider_id')) {
        return;
    }

    $allowedTypes = ['order', 'promotion', 'system', 'wallet', 'review'];
    $normalizedType = in_array($type, $allowedTypes, true) ? $type : 'order';
    $payloadJson = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    if ($payloadJson !== null && tableColumnExists('notifications', 'data')) {
        $stmt = $conn->prepare(
            "INSERT INTO notifications (provider_id, title, body, type, data, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        if ($stmt) {
            $stmt->bind_param("issss", $providerId, $title, $body, $normalizedType, $payloadJson);
            $stmt->execute();
            return;
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO notifications (provider_id, title, body, type, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    if ($stmt) {
        $stmt->bind_param("isss", $providerId, $title, $body, $normalizedType);
        $stmt->execute();
    }
}

function sendOneSignalToExternalUser($externalUserId, $title, $body, $data = [])
{
    $appId = getOneSignalAppId();
    $restApiKey = getOneSignalRestApiKey();

    if ($appId === '' || $restApiKey === '' || trim((string) $externalUserId) === '') {
        return false;
    }

    $payload = [
        'app_id' => $appId,
        'target_channel' => 'push',
        'include_aliases' => [
            'external_id' => [trim((string) $externalUserId)],
        ],
        'headings' => [
            'en' => $title,
            'ar' => $title,
        ],
        'contents' => [
            'en' => $body,
            'ar' => $body,
        ],
        'data' => is_array($data) ? $data : [],
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($jsonPayload === false) {
        return false;
    }

    if (!function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Key ' . $restApiKey,
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return false;
    }

    return $statusCode >= 200 && $statusCode < 300;
}

function notifyUserOrderEvent($userId, $orderId, $title, $body, $type = 'order', $extraData = [])
{
    $userId = (int) $userId;
    $orderId = (int) $orderId;

    if ($userId <= 0 || $orderId <= 0) {
        return;
    }

    $data = array_merge(
        [
            'event' => 'order_update',
            'order_id' => $orderId,
            'deep_link' => 'order:' . $orderId,
        ],
        is_array($extraData) ? $extraData : []
    );

    createUserNotification($userId, $title, $body, $type, $data);
    sendOneSignalToExternalUser((string) $userId, $title, $body, $data);
}

function notifyProviderOrderEvent($providerId, $orderId, $title, $body, $type = 'order', $extraData = [])
{
    $providerId = (int) $providerId;
    $orderId = (int) $orderId;

    if ($providerId <= 0 || $orderId <= 0) {
        return;
    }

    $data = array_merge(
        [
            'event' => 'order_update',
            'target' => 'provider',
            'order_id' => $orderId,
            'deep_link' => 'order:' . $orderId,
        ],
        is_array($extraData) ? $extraData : []
    );

    createProviderNotification($providerId, $title, $body, $type, $data);
    sendOneSignalToExternalUser('provider_' . $providerId, $title, $body, $data);
}

function resolveOrderProviderIdsForNotification($orderId, array $assignmentStatuses = ['assigned', 'accepted', 'in_progress', 'completed'])
{
    global $conn;

    ensureOrderExtensionsSchema();

    $orderId = (int) $orderId;
    if ($orderId <= 0) {
        return [];
    }

    $allowedStatuses = ['assigned', 'accepted', 'in_progress', 'completed', 'cancelled', 'rejected'];
    $normalizedStatuses = [];
    foreach ($assignmentStatuses as $status) {
        $normalized = strtolower(trim((string) $status));
        if (in_array($normalized, $allowedStatuses, true)) {
            $normalizedStatuses[$normalized] = $normalized;
        }
    }
    if (empty($normalizedStatuses)) {
        $normalizedStatuses = [
            'assigned' => 'assigned',
            'accepted' => 'accepted',
            'in_progress' => 'in_progress',
            'completed' => 'completed',
        ];
    }

    $ids = [];

    if (tableExists('order_providers')) {
        $statuses = array_values($normalizedStatuses);
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $types = 'i' . str_repeat('s', count($statuses));
        $params = array_merge([$orderId], $statuses);

        $stmt = $conn->prepare(
            "SELECT provider_id
             FROM order_providers
             WHERE order_id = ?
               AND assignment_status IN ({$placeholders})"
        );
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $providerId = (int) ($row['provider_id'] ?? 0);
                if ($providerId > 0) {
                    $ids[$providerId] = $providerId;
                }
            }
        }
    }

    if (tableExists('orders')) {
        $stmt = $conn->prepare("SELECT provider_id FROM orders WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $primaryProviderId = (int) ($row['provider_id'] ?? 0);
            if ($primaryProviderId > 0) {
                $ids[$primaryProviderId] = $primaryProviderId;
            }
        }
    }

    ksort($ids);
    return array_values($ids);
}

function notifyOrderProvidersEvent($providerIds, $orderId, $title, $body, $type = 'order', $extraData = [])
{
    $orderId = (int) $orderId;
    if ($orderId <= 0) {
        return;
    }

    $ids = normalizeIntegerIds($providerIds);
    if (empty($ids)) {
        return;
    }

    foreach ($ids as $providerId) {
        notifyProviderOrderEvent((int) $providerId, $orderId, $title, $body, $type, $extraData);
    }
}

function ensureProviderWhatsAppSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!tableExists('providers')) {
        return;
    }

    if (!tableColumnExists('providers', 'whatsapp_number')) {
        $conn->query("ALTER TABLE `providers` ADD COLUMN `whatsapp_number` VARCHAR(32) NULL");
    }
}

function fetchProviderWhatsAppNumber($providerId, $fallbackPhone = null)
{
    global $conn;

    $providerId = (int) $providerId;
    $fallback = trim((string) ($fallbackPhone ?? ''));

    if ($providerId <= 0) {
        return $fallback !== '' ? $fallback : null;
    }

    ensureProviderWhatsAppSchema();
    if (!tableExists('providers') || !tableColumnExists('providers', 'whatsapp_number')) {
        return $fallback !== '' ? $fallback : null;
    }

    $stmt = $conn->prepare("SELECT whatsapp_number, phone FROM providers WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return $fallback !== '' ? $fallback : null;
    }

    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    if (!$provider) {
        return $fallback !== '' ? $fallback : null;
    }

    $whatsapp = trim((string) ($provider['whatsapp_number'] ?? ''));
    if ($whatsapp !== '') {
        return $whatsapp;
    }

    $phone = trim((string) ($provider['phone'] ?? ''));
    if ($phone !== '') {
        return $phone;
    }

    return $fallback !== '' ? $fallback : null;
}

/**
 * Set Initial Estimate (Ops/Admin)
 */
function setEstimate($input)
{
    global $conn;
    // In real app, check for admin/ops role. For now accept any auth or specific user
    $userId = requireAuth();
    $role = getAuthRole();
    if ($role === 'provider') {
        ensureProviderCanViewOrders((int) $userId);
    }
    // TODO: Verify if user is admin/ops

    ensureOrderExtensionsSchema();

    $orderId = (int) ($input['order_id'] ?? 0);
    $min = isset($input['min_estimate']) ? (float) $input['min_estimate'] : (float) ($input['min'] ?? 0);
    $max = isset($input['max_estimate']) ? (float) $input['max_estimate'] : (float) ($input['max'] ?? 0);
    $providerId = isset($input['provider_id']) ? (int) $input['provider_id'] : 0;

    if ($orderId <= 0) {
        sendError('Invalid order id', 422);
    }

    if ($min <= 0 || $max <= 0 || $max < $min) {
        sendError('Invalid estimate range', 422);
    }

    $sql = "UPDATE orders SET min_estimate = ?, max_estimate = ?";
    $types = "dd";
    $params = [$min, $max];

    if ($providerId > 0) {
        $sql .= ", provider_id = ?, status = 'assigned'";
        $types .= "i";
        $params[] = $providerId;
    }

    $sql .= " WHERE id = ?";
    $types .= "i";
    $params[] = $orderId;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        if ($providerId > 0) {
            assignProvidersToOrder((int) $orderId, [$providerId], true);
        }

        $userStmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ? LIMIT 1");
        if ($userStmt) {
            $userStmt->bind_param("i", $orderId);
            $userStmt->execute();
            $row = $userStmt->get_result()->fetch_assoc();
            $targetUserId = (int) ($row['user_id'] ?? 0);
            if ($targetUserId > 0) {
                notifyUserOrderEvent(
                    $targetUserId,
                    (int) $orderId,
                    'تم تحديث التقدير المبدئي',
                    'تم تحديث تقدير التكلفة المبدئي لطلبك من العمليات.',
                    'order',
                    ['event' => 'estimate_updated']
                );
            }
        }

        if ($providerId > 0) {
            notifyProviderOrderEvent(
                $providerId,
                (int) $orderId,
                'تم إسناد طلب جديد إليك',
                'تم تعيينك على طلب جديد من العمليات. راجع تفاصيل الطلب وابدأ التنفيذ.',
                'order',
                ['event' => 'provider_assignment_received', 'status' => 'assigned', 'source' => 'ops']
            );
        }

        sendSuccess(null, 'Estimate updated');
    } else {
        sendError('Failed to update estimate', 500);
    }
}

function isGenericOrderServiceLabel($value): bool
{
    $label = trim((string) $value);
    if ($label === '') {
        return true;
    }

    $normalized = strtolower(preg_replace('/\s+/', ' ', $label));
    if ($normalized === '') {
        return true;
    }

    if ($label === 'خدمة أخرى' || $label === 'خدمة اخري') {
        return true;
    }

    if (in_array($normalized, ['other service', 'other'], true)) {
        return true;
    }

    if (strpos($normalized, 'service #') === 0 || strpos($label, 'خدمة #') === 0) {
        return true;
    }

    return false;
}

function resolveOrderDisplayNameFromProblemDetails(array $problemDetails): string
{
    if (empty($problemDetails)) {
        return '';
    }

    $customService = $problemDetails['custom_service'] ?? null;
    if ($customService instanceof stdClass) {
        $customService = (array) $customService;
    }
    if (is_array($customService)) {
        $customTitle = trim((string) ($customService['title'] ?? ''));
        if ($customTitle !== '') {
            return $customTitle;
        }
    }

    $containerRequest = $problemDetails['container_request'] ?? null;
    if ($containerRequest instanceof stdClass) {
        $containerRequest = (array) $containerRequest;
    }
    if (is_array($containerRequest)) {
        $containerServiceName = trim((string) ($containerRequest['container_service_name'] ?? ''));
        if ($containerServiceName !== '') {
            return $containerServiceName;
        }
    }

    $selectedServices = $problemDetails['selected_services'] ?? [];
    if ($selectedServices instanceof stdClass) {
        $selectedServices = (array) $selectedServices;
    }
    if (is_array($selectedServices)) {
        foreach ($selectedServices as $selectedService) {
            if ($selectedService instanceof stdClass) {
                $selectedService = (array) $selectedService;
            }
            if (!is_array($selectedService)) {
                continue;
            }
            $selectedName = trim((string) ($selectedService['name_ar'] ?? $selectedService['name_en'] ?? ''));
            if ($selectedName !== '') {
                return $selectedName;
            }
        }
    }

    $module = strtolower(trim((string) ($problemDetails['module'] ?? '')));
    $type = strtolower(trim((string) ($problemDetails['type'] ?? '')));
    $signal = $type !== '' ? $type : $module;

    if (in_array($signal, ['spare_parts_with_installation', 'spare_parts_order', 'spare_parts'], true)) {
        return 'طلب قطع غيار';
    }

    if ($signal === 'container_rental' || strpos($signal, 'container') !== false) {
        return 'طلب خدمة الحاويات';
    }

    if ($signal === 'furniture_moving' || strpos($signal, 'furniture') !== false) {
        return 'طلب نقل العفش';
    }

    return '';
}

function resolveOrderDisplayServiceName($categoryName, array $serviceItems, array $problemDetails): string
{
    $firstCandidate = '';

    foreach ($serviceItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['service_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        if ($firstCandidate === '') {
            $firstCandidate = $name;
        }
        if (!isGenericOrderServiceLabel($name)) {
            return $name;
        }
    }

    if ($firstCandidate !== '') {
        return $firstCandidate;
    }

    $fromProblemDetails = resolveOrderDisplayNameFromProblemDetails($problemDetails);
    if ($fromProblemDetails !== '') {
        return $fromProblemDetails;
    }

    $fallback = trim((string) $categoryName);
    return $fallback;
}

/**
 * Format order for response
 */
function formatOrder($row, array $context = [])
{
    $orderId = (int) ($row['id'] ?? 0);
    $categoryId = (int) ($row['category_id'] ?? 0);
    $categoryName = getOrderCategoryDisplayName($categoryId, $row['category_name'] ?? null);
    $problemDetailsArray = decodeOrderProblemDetailsPayload($row['problem_details'] ?? null);
    $problemDetailsObject = !empty($problemDetailsArray) ? (object) $problemDetailsArray : null;
    $inspectionFee = isset($row['inspection_fee']) ? (float) $row['inspection_fee'] : 0.0;
    $invoiceItems = normalizeInvoiceItemsPayload($row['invoice_items'] ?? null);
    $requestedSpareParts = [];
    $serviceItems = fetchOrderServiceItems($orderId, $row);
    $assignedProviders = fetchOrderAssignedProviders($orderId);
    $serviceIds = [];
    $customServiceRequest = null;

    foreach ($serviceItems as $item) {
        if (!empty($item['service_id'])) {
            $serviceIds[] = (int) $item['service_id'];
        }
        if (!empty($item['is_custom']) && $customServiceRequest === null) {
            $customServiceRequest = [
                'title' => $item['service_name'] ?? 'خدمة أخرى',
                'description' => $item['notes'] ?? '',
            ];
        }
    }
    $serviceIds = array_values(array_unique($serviceIds));

    $displayServiceName = resolveOrderDisplayServiceName($categoryName, $serviceItems, $problemDetailsArray);
    if (
        $displayServiceName !== ''
        && ($categoryId <= 0 || isGenericOrderServiceLabel($categoryName))
    ) {
        $categoryName = $displayServiceName;
    }

    if (empty($assignedProviders) && !empty($row['provider_id'])) {
        $assignedProviders[] = [
            'provider_id' => (int) $row['provider_id'],
            'provider_name' => $row['provider_name'] ?? null,
            'provider_phone' => $row['provider_phone'] ?? null,
            'provider_email' => $row['provider_email'] ?? null,
            'provider_avatar' => !empty($row['provider_avatar']) ? imageUrl((string) $row['provider_avatar']) : null,
            'provider_rating' => !empty($row['provider_rating']) ? (float) $row['provider_rating'] : null,
            'provider_status' => $row['provider_status'] ?? null,
            'provider_is_available' => isset($row['provider_is_available']) ? ((int) $row['provider_is_available'] === 1) : null,
            'assignment_status' => $row['status'] ?? 'assigned',
            'assigned_at' => $row['created_at'] ?? null,
            'is_primary' => true,
        ];
    }

    $selectedProvider = null;
    if (!empty($row['provider_id'])) {
        $selectedProvider = [
            'provider_id' => (int) $row['provider_id'],
            'provider_name' => $row['provider_name'] ?? null,
            'provider_phone' => $row['provider_phone'] ?? null,
            'provider_email' => $row['provider_email'] ?? null,
            'provider_avatar' => !empty($row['provider_avatar']) ? imageUrl((string) $row['provider_avatar']) : null,
            'provider_rating' => !empty($row['provider_rating']) ? (float) $row['provider_rating'] : null,
            'provider_status' => $row['provider_status'] ?? null,
            'provider_is_available' => isset($row['provider_is_available']) ? ((int) $row['provider_is_available'] === 1) : null,
            'assignment_status' => $row['status'] ?? 'assigned',
            'is_primary' => true,
        ];
    } elseif (!empty($assignedProviders)) {
        $candidateProviders = array_values(array_filter($assignedProviders, function ($provider) {
            $status = strtolower((string) ($provider['assignment_status'] ?? ''));
            return in_array($status, ['accepted', 'in_progress', 'completed', 'assigned'], true);
        }));

        if (empty($candidateProviders)) {
            $candidateProviders = [];
        }

        $priorityMap = [
            'in_progress' => 1,
            'completed' => 2,
            'accepted' => 3,
            'assigned' => 4,
            'pending' => 5,
            'cancelled' => 6,
            'rejected' => 7,
        ];

        usort($candidateProviders, function ($a, $b) use ($priorityMap) {
            $aPrimary = !empty($a['is_primary']) ? 1 : 0;
            $bPrimary = !empty($b['is_primary']) ? 1 : 0;
            if ($aPrimary !== $bPrimary) {
                return $bPrimary <=> $aPrimary;
            }

            $aStatus = strtolower((string) ($a['assignment_status'] ?? 'assigned'));
            $bStatus = strtolower((string) ($b['assignment_status'] ?? 'assigned'));
            $aPriority = $priorityMap[$aStatus] ?? 999;
            $bPriority = $priorityMap[$bStatus] ?? 999;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            return ((int) ($a['provider_id'] ?? 0)) <=> ((int) ($b['provider_id'] ?? 0));
        });

        if (!empty($candidateProviders)) {
            $selectedProvider = $candidateProviders[0];
        }
    }

    $providerId = !empty($row['provider_id']) ? (int) $row['provider_id'] : null;
    $providerName = $selectedProvider['provider_name'] ?? ($row['provider_name'] ?? null);
    $providerPhone = $selectedProvider['provider_phone'] ?? ($row['provider_phone'] ?? null);
    $providerEmail = $selectedProvider['provider_email'] ?? ($row['provider_email'] ?? null);
    $providerAvatar = $selectedProvider['provider_avatar']
        ?? (!empty($row['provider_avatar']) ? imageUrl((string) $row['provider_avatar']) : null);
    $providerRating = isset($selectedProvider['provider_rating'])
        ? (float) $selectedProvider['provider_rating']
        : (!empty($row['provider_rating']) ? (float) $row['provider_rating'] : null);
    $providerStatus = $selectedProvider['provider_status'] ?? ($row['provider_status'] ?? null);
    $providerIsAvailable = array_key_exists('provider_is_available', (array) $selectedProvider)
        ? (bool) $selectedProvider['provider_is_available']
        : (isset($row['provider_is_available']) ? ((int) $row['provider_is_available'] === 1) : null);
    $providerWhatsapp = fetchProviderWhatsAppNumber($providerId, $providerPhone);

    $contextRole = (string) ($context['role'] ?? '');
    $contextProviderId = (int) ($context['provider_id'] ?? 0);
    $currentProviderAssignmentStatus = null;
    if ($contextRole === 'provider' && $contextProviderId > 0) {
        foreach ($assignedProviders as $assignedProvider) {
            if ((int) ($assignedProvider['provider_id'] ?? 0) === $contextProviderId) {
                $currentProviderAssignmentStatus = strtolower((string) ($assignedProvider['assignment_status'] ?? ''));
                break;
            }
        }
        if (($currentProviderAssignmentStatus === null || $currentProviderAssignmentStatus === '')
            && !empty($row['provider_id'])
            && (int) $row['provider_id'] === $contextProviderId
        ) {
            $currentProviderAssignmentStatus = strtolower((string) ($row['status'] ?? 'assigned'));
        }
    }

    $normalizedOrderStatus = strtolower((string) ($row['status'] ?? ''));
    $canProviderAccept = $contextRole === 'provider'
        && in_array($currentProviderAssignmentStatus, ['assigned', 'pending', ''], true)
        && in_array($normalizedOrderStatus, ['pending', 'assigned'], true);
    $canProviderReject = $contextRole === 'provider'
        && in_array($currentProviderAssignmentStatus, ['assigned', 'accepted', 'pending', ''], true)
        && !in_array($normalizedOrderStatus, ['in_progress', 'completed', 'cancelled'], true);
    $canProviderMarkOnTheWay = $contextRole === 'provider'
        && in_array($currentProviderAssignmentStatus, ['assigned', 'accepted', 'in_progress', 'pending', ''], true)
        && in_array($normalizedOrderStatus, ['assigned', 'accepted', 'arrived', 'in_progress', 'on_the_way'], true)
        && !in_array($normalizedOrderStatus, ['completed', 'cancelled'], true);
    $canProviderMarkInspection = $contextRole === 'provider'
        && in_array($currentProviderAssignmentStatus, ['assigned', 'accepted', 'in_progress', 'pending', ''], true)
        && in_array($normalizedOrderStatus, ['assigned', 'accepted', 'arrived', 'in_progress', 'on_the_way'], true)
        && !in_array($normalizedOrderStatus, ['completed', 'cancelled'], true);

    $requestedSpareParts = fetchOrderRequestedSpareParts($orderId);
    $providerLiveLocation = fetchLatestOrderProviderLocation($orderId, $providerId);

    if (empty($invoiceItems) && !empty($requestedSpareParts)) {
        foreach ($requestedSpareParts as $part) {
            $invoiceItems[] = [
                'spare_part_id' => $part['spare_part_id'],
                'store_id' => $part['store_id'],
                'name' => $part['name'],
                'quantity' => $part['quantity'],
                'unit_price' => $part['unit_price'],
                'total_price' => $part['total_price'],
                'notes' => $part['notes'],
                'pricing_mode' => $part['pricing_mode'] ?? null,
                'requires_installation' => $part['requires_installation'] ?? true,
                'source' => 'provider_requested'
            ];
        }
    }

    return [
        'id' => $orderId,
        'order_number' => $row['order_number'],
        'user_id' => (int) $row['user_id'],
        'user_name' => $row['user_name'] ?? null,
        'user_phone' => $row['user_phone'] ?? null,
        'user_avatar' => !empty($row['user_avatar']) ? imageUrl((string) $row['user_avatar']) : null,
        'provider_id' => $providerId,
        'category_id' => $categoryId,
        'status' => $row['status'],
        'total_amount' => (float) ($row['total_amount'] ?? 0),
        'subtotal_amount' => isset($row['subtotal_amount']) ? (float) ($row['subtotal_amount'] ?? 0) : null,
        'discount_amount' => isset($row['discount_amount']) ? (float) ($row['discount_amount'] ?? 0) : 0.0,
        'promo_code' => !empty($row['promo_code']) ? (string) $row['promo_code'] : null,
        'address' => $row['address'] ?? null,
        'lat' => !empty($row['lat']) ? (float) $row['lat'] : null,
        'lng' => !empty($row['lng']) ? (float) $row['lng'] : null,
        'notes' => $row['notes'] ?? null,
        'problem_description' => $row['problem_description'] ?? ($row['notes'] ?? null),
        'scheduled_date' => $row['scheduled_date'] ?? null,
        'scheduled_time' => $row['scheduled_time'] ?? null,
        'category_name' => $categoryName,
        'display_service_name' => $displayServiceName !== '' ? $displayServiceName : $categoryName,
        'category_icon' => $row['category_icon'] ?? null,
        'provider_name' => $providerName,
        'provider_avatar' => $providerAvatar,
        'provider_rating' => $providerRating,
        'provider_phone' => $providerPhone,
        'provider_whatsapp' => $providerWhatsapp,
        'provider_email' => $providerEmail,
        'provider_status' => $providerStatus,
        'provider_is_available' => $providerIsAvailable,
        'selected_provider' => $selectedProvider,
        'created_at' => $row['created_at'],
        'attachments' => normalizeMediaListPayload($row['attachments'] ?? null),
        'problem_images' => normalizeMediaListPayload($row['problem_images'] ?? null),
        'inspection_images' => normalizeMediaListPayload($row['inspection_images'] ?? null),
        'problem_details' => $problemDetailsObject,
        'service_ids' => $serviceIds,
        'service_items' => $serviceItems,
        'is_custom_service_request' => $customServiceRequest !== null,
        'custom_service_request' => $customServiceRequest,
        'assigned_providers' => $assignedProviders,
        'providers_count' => count($assignedProviders),
        'min_estimate' => !empty($row['min_estimate']) ? (float) $row['min_estimate'] : null,
        'max_estimate' => !empty($row['max_estimate']) ? (float) $row['max_estimate'] : null,
        'inspection_fee' => $inspectionFee,
        'labor_cost' => !empty($row['labor_cost']) ? (float) $row['labor_cost'] : null,
        'parts_cost' => !empty($row['parts_cost']) ? (float) $row['parts_cost'] : null,
        'payment_method' => $row['payment_method'] ?? null,
        'payment_status' => $row['payment_status'] ?? null,
        'payment_url' => isset($row['myfatoorah_payment_url']) ? $row['myfatoorah_payment_url'] : null,
        'payment_invoice_id' => isset($row['myfatoorah_invoice_id']) ? $row['myfatoorah_invoice_id'] : null,
        'payment_invoice_status' => isset($row['myfatoorah_invoice_status']) ? $row['myfatoorah_invoice_status'] : null,
        'payment_gateway_id' => isset($row['myfatoorah_payment_id']) ? $row['myfatoorah_payment_id'] : null,
        'payment_gateway_method_id' => isset($row['myfatoorah_payment_method_id']) ? $row['myfatoorah_payment_method_id'] : null,
        'payment_gateway_last_status_at' => isset($row['myfatoorah_last_status_at']) ? $row['myfatoorah_last_status_at'] : null,
        'invoice_status' => $row['invoice_status'] ?? 'none',
        'invoice_items' => $invoiceItems,
        'requested_spare_parts' => $requestedSpareParts,
        'has_requested_spare_parts' => !empty($requestedSpareParts),
        'admin_notes' => $row['admin_notes'] ?? null,
        'inspection_notes' => $row['inspection_notes'] ?? null,
        'confirmation_status' => $row['confirmation_status'] ?? null,
        'confirmation_due_at' => $row['confirmation_due_at'] ?? null,
        'confirmation_attempts' => isset($row['confirmation_attempts']) ? (int) $row['confirmation_attempts'] : 0,
        'confirmation_notes' => $row['confirmation_notes'] ?? null,
        'confirmed_at' => $row['confirmed_at'] ?? null,
        'is_free_inspection' => $inspectionFee <= 0,
        'is_rated' => (bool) ($row['is_rated'] ?? false),
        'current_provider_assignment_status' => $currentProviderAssignmentStatus,
        'can_provider_accept' => $canProviderAccept,
        'can_provider_reject' => $canProviderReject,
        'can_provider_mark_on_the_way' => $canProviderMarkOnTheWay,
        'can_provider_mark_inspection' => $canProviderMarkInspection,
        'provider_live_location' => $providerLiveLocation,
    ];
}

function getOrderCategoryDisplayName($categoryId, $fallbackName = null)
{
    if ($categoryId <= 0) {
        return $fallbackName;
    }

    $map = getOrderCategoryDisplayMap();
    return $map[$categoryId] ?? $fallbackName;
}

function getOrderCategoryDisplayMap()
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    global $conn;
    $map = [];

    $hasParentColumn = false;
    $parentColumnCheck = $conn->query("SHOW COLUMNS FROM service_categories LIKE 'parent_id'");
    if ($parentColumnCheck && $parentColumnCheck->num_rows > 0) {
        $hasParentColumn = true;
    }

    if ($hasParentColumn) {
        $query = "SELECT c.id, c.name_ar, p.name_ar AS parent_name_ar
                  FROM service_categories c
                  LEFT JOIN service_categories p ON p.id = c.parent_id";
    } else {
        $query = "SELECT c.id, c.name_ar, NULL AS parent_name_ar
                  FROM service_categories c";
    }

    $result = $conn->query($query);
    if (!$result) {
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $name = $row['name_ar'] ?? '';
        if (!empty($row['parent_name_ar'])) {
            $name = $row['parent_name_ar'] . ' > ' . $name;
        }
        $map[(int) $row['id']] = $name;
    }

    return $map;
}

/**
 * Check whether a column exists in orders table.
 */
function orderColumnExists($column)
{
    global $conn;

    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM orders");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
        }
    }

    return !empty($columns[$column]);
}

/**
 * Check whether a column exists in users table.
 */
function userColumnExists($column)
{
    global $conn;

    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM users");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
        }
    }

    return !empty($columns[$column]);
}

/**
 * Check whether a column exists in reviews table.
 */
function reviewColumnExists($column)
{
    global $conn;

    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM reviews");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
        }
    }

    return !empty($columns[$column]);
}

/**
 * Normalize problem_details payload from JSON body or multipart/form-data.
 */
function normalizeProblemDetailsPayload($payload)
{
    if ($payload === null) {
        return [];
    }

    if (is_string($payload)) {
        $trimmed = trim($payload);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payload = $decoded;
        } else {
            return [
                'type' => $trimmed,
            ];
        }
    }

    if (is_object($payload)) {
        $payload = (array) $payload;
    }

    if (!is_array($payload)) {
        return [];
    }

    $normalized = [];

    foreach (['type', 'user_desc'] as $key) {
        if (isset($payload[$key])) {
            $value = trim((string) $payload[$key]);
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }
    }

    if (isset($payload['module'])) {
        $module = trim((string) $payload['module']);
        if ($module !== '') {
            $normalized['module'] = $module;
        }
    }

    if (isset($payload['container_request'])) {
        $containerRequest = normalizeContainerRequestPayload($payload['container_request']);
        if (!empty($containerRequest)) {
            $normalized['container_request'] = $containerRequest;
        }
    }

    if (array_key_exists('is_custom_service', $payload)) {
        $normalized['is_custom_service'] = normalizeBooleanValue($payload['is_custom_service']);
    }

    if (isset($payload['custom_service'])) {
        $custom = $payload['custom_service'];
        if (is_object($custom)) {
            $custom = (array) $custom;
        }
        if (is_array($custom)) {
            $customTitle = trim((string) ($custom['title'] ?? ''));
            $customDescription = trim((string) ($custom['description'] ?? ''));
            if ($customTitle !== '' || $customDescription !== '') {
                if ($customTitle === '') {
                    $customTitle = 'خدمة أخرى';
                }
                $normalized['custom_service'] = [
                    'title' => $customTitle,
                    'description' => $customDescription,
                ];
            }
        }
    }

    if (isset($payload['selected_services']) && is_array($payload['selected_services'])) {
        $selectedServices = [];
        foreach ($payload['selected_services'] as $selectedService) {
            if ($selectedService instanceof stdClass) {
                $selectedService = (array) $selectedService;
            }
            if (!is_array($selectedService)) {
                continue;
            }
            $serviceId = isset($selectedService['id']) ? (int) $selectedService['id'] : 0;
            $nameAr = trim((string) ($selectedService['name_ar'] ?? ''));
            $nameEn = trim((string) ($selectedService['name_en'] ?? ''));
            if ($serviceId <= 0 && $nameAr === '' && $nameEn === '') {
                continue;
            }
            $entry = [];
            if ($serviceId > 0) {
                $entry['id'] = $serviceId;
            }
            if ($nameAr !== '') {
                $entry['name_ar'] = $nameAr;
            }
            if ($nameEn !== '') {
                $entry['name_en'] = $nameEn;
            }
            $selectedServices[] = $entry;
        }
        if (!empty($selectedServices)) {
            $normalized['selected_services'] = $selectedServices;
        }
    }

    $subServicesInput = $payload['service_type_ids'] ?? $payload['sub_services'] ?? [];
    $subServices = normalizeIntegerIds($subServicesInput);

    if (!empty($subServices)) {
        $normalized['service_type_ids'] = $subServices;
        $normalized['sub_services'] = $subServices; // backward compatibility for current apps
    }

    foreach (['category_id', 'service_type_id', 'type_option_id'] as $numericKey) {
        if (isset($payload[$numericKey]) && $payload[$numericKey] !== '') {
            $value = (int) $payload[$numericKey];
            if ($value > 0) {
                $normalized[$numericKey] = $value;
            }
        }
    }

    $pricingMode = normalizeSparePricingMode($payload['pricing_mode'] ?? null);
    if ($pricingMode !== null) {
        $normalized['pricing_mode'] = $pricingMode;
    }

    if (array_key_exists('requires_installation', $payload)) {
        $normalized['requires_installation'] = normalizeBooleanValue($payload['requires_installation']);
    } elseif ($pricingMode !== null) {
        $normalized['requires_installation'] = $pricingMode !== 'without_installation';
    }

    $spareParts = normalizeOrderRequestedSparePartsPayload($payload['spare_parts'] ?? []);
    if (!empty($spareParts)) {
        $normalized['spare_parts'] = $spareParts;
    }

    return $normalized;
}

function normalizeContainerRequestPayload($payload)
{
    if ($payload instanceof stdClass) {
        $payload = (array) $payload;
    }
    if (!is_array($payload)) {
        return [];
    }

    $normalized = [];

    foreach ([
        'container_service_name',
        'container_size',
        'site_city',
        'site_address',
        'start_date',
        'end_date',
        'purpose',
        'notes',
    ] as $textKey) {
        if (!isset($payload[$textKey])) {
            continue;
        }
        $value = trim((string) $payload[$textKey]);
        if ($value !== '') {
            $normalized[$textKey] = $value;
        }
    }

    foreach ([
        'container_service_id',
        'container_store_id',
        'duration_days',
        'quantity',
    ] as $intKey) {
        if (!isset($payload[$intKey]) || $payload[$intKey] === '') {
            continue;
        }
        $value = (int) $payload[$intKey];
        if ($value > 0) {
            $normalized[$intKey] = $value;
        }
    }

    foreach ([
        'capacity_ton',
        'daily_price',
        'weekly_price',
        'monthly_price',
        'delivery_fee',
        'price_per_kg',
        'price_per_meter',
        'minimum_charge',
        'estimated_weight_kg',
        'estimated_distance_meters',
    ] as $floatKey) {
        if (!isset($payload[$floatKey]) || $payload[$floatKey] === '') {
            continue;
        }
        $value = (float) $payload[$floatKey];
        if (is_finite($value) && $value >= 0) {
            $normalized[$floatKey] = $value;
        }
    }

    foreach (['needs_loading_help', 'needs_operator'] as $boolKey) {
        if (array_key_exists($boolKey, $payload)) {
            $normalized[$boolKey] = normalizeBooleanValue($payload[$boolKey]);
        }
    }

    return $normalized;
}

function normalizeMediaListPayload($payload)
{
    if ($payload === null) {
        return [];
    }

    if (is_string($payload)) {
        $trimmed = trim($payload);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload = $decoded;
        } else {
            $payload = [$trimmed];
        }
    }

    if (is_object($payload)) {
        $payload = (array) $payload;
    }

    if (!is_array($payload)) {
        return [];
    }

    $normalized = [];
    foreach ($payload as $item) {
        $path = trim((string) $item);
        if ($path !== '' && !in_array($path, $normalized, true)) {
            $normalized[] = $path;
        }
    }

    return $normalized;
}

/**
 * Normalize invoice_items payload into a clean list.
 */
function normalizeInvoiceItemsPayload($payload)
{
    if ($payload === null) {
        return [];
    }

    if (is_string($payload)) {
        $trimmed = trim($payload);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload = $decoded;
        } else {
            return [];
        }
    }

    if (is_object($payload)) {
        $payload = (array) $payload;
    }

    if (!is_array($payload)) {
        return [];
    }

    $items = [];
    foreach ($payload as $item) {
        if (is_object($item)) {
            $item = (array) $item;
        }
        if (!is_array($item)) {
            continue;
        }

        $name = trim((string) ($item['name'] ?? $item['part_name'] ?? $item['spare_part_name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
        $totalPrice = isset($item['total_price']) ? (float) $item['total_price'] : null;

        if ($totalPrice === null) {
            $totalPrice = $unitPrice !== null ? $unitPrice * $quantity : 0.0;
        }
        if ($unitPrice === null) {
            $unitPrice = $quantity > 0 ? ($totalPrice / $quantity) : 0.0;
        }

        $items[] = [
            'spare_part_id' => isset($item['spare_part_id']) ? (int) $item['spare_part_id'] : null,
            'store_id' => isset($item['store_id']) ? (int) $item['store_id'] : null,
            'name' => $name,
            'quantity' => $quantity,
            'pricing_mode' => normalizeSparePricingMode($item['pricing_mode'] ?? null),
            'requires_installation' => array_key_exists('requires_installation', $item)
                ? normalizeBooleanValue($item['requires_installation'])
                : null,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'notes' => isset($item['notes']) ? trim((string) $item['notes']) : '',
            'source' => isset($item['source']) ? (string) $item['source'] : 'provider_requested'
        ];
    }

    return $items;
}

/**
 * Normalize provider spare_parts payload from submit invoice.
 */
function normalizeInvoiceSparePartsPayload($payload)
{
    if ($payload === null) {
        return [];
    }

    if (is_string($payload)) {
        $trimmed = trim($payload);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload = $decoded;
        } else {
            return [];
        }
    }

    if (is_object($payload)) {
        $payload = (array) $payload;
    }

    if (!is_array($payload)) {
        return [];
    }

    $normalized = [];
    foreach ($payload as $item) {
        if (is_object($item)) {
            $item = (array) $item;
        }
        if (!is_array($item)) {
            continue;
        }

        $sparePartId = (int) ($item['spare_part_id'] ?? $item['id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
        $pricingMode = normalizeSparePricingMode($item['pricing_mode'] ?? null);
        $requiresInstallation = array_key_exists('requires_installation', $item)
            ? normalizeBooleanValue($item['requires_installation'])
            : ($pricingMode !== 'without_installation');
        $notes = isset($item['notes']) ? trim((string) $item['notes']) : '';

        if ($sparePartId <= 0 || $quantity <= 0) {
            continue;
        }

        $normalized[] = [
            'spare_part_id' => $sparePartId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'pricing_mode' => $pricingMode ?? ($requiresInstallation ? 'with_installation' : 'without_installation'),
            'requires_installation' => $requiresInstallation,
            'notes' => $notes
        ];
    }

    return $normalized;
}

function normalizeSparePricingMode($value)
{
    if ($value === null) {
        return null;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return null;
    }

    if (in_array($normalized, ['with_installation', 'with_install', 'with_installing', 'installation', 'installed'], true)) {
        return 'with_installation';
    }

    if (in_array($normalized, ['without_installation', 'without_install', 'part_only', 'without'], true)) {
        return 'without_installation';
    }

    return null;
}

function normalizeOrderRequestedSparePartsPayload($payload)
{
    if ($payload === null) {
        return [];
    }

    if (is_string($payload)) {
        $trimmed = trim($payload);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $payload = $decoded;
        } else {
            return [];
        }
    }

    if (is_object($payload)) {
        $payload = (array) $payload;
    }

    if (!is_array($payload)) {
        return [];
    }

    $normalized = [];
    foreach ($payload as $item) {
        if (is_object($item)) {
            $item = (array) $item;
        }
        if (!is_array($item)) {
            continue;
        }

        $sparePartId = (int) ($item['spare_part_id'] ?? $item['id'] ?? 0);
        $name = trim((string) ($item['name'] ?? $item['spare_part_name'] ?? ''));
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
        if ($unitPrice === null && isset($item['price'])) {
            $unitPrice = (float) $item['price'];
        }
        $pricingMode = normalizeSparePricingMode($item['pricing_mode'] ?? null);
        $requiresInstallation = array_key_exists('requires_installation', $item)
            ? normalizeBooleanValue($item['requires_installation'])
            : ($pricingMode !== 'without_installation');
        $notes = trim((string) ($item['notes'] ?? ''));

        if ($sparePartId <= 0 && $name === '') {
            continue;
        }

        $entry = [
            'spare_part_id' => $sparePartId > 0 ? $sparePartId : null,
            'name' => $name,
            'quantity' => $quantity,
            'pricing_mode' => $pricingMode ?? ($requiresInstallation ? 'with_installation' : 'without_installation'),
            'requires_installation' => $requiresInstallation,
            'notes' => $notes
        ];

        if ($unitPrice !== null && $unitPrice > 0) {
            $entry['unit_price'] = $unitPrice;
        }

        $normalized[] = $entry;
    }

    return $normalized;
}

/**
 * Check whether a specific column exists in any table.
 */
function tableColumnExists($table, $column)
{
    global $conn;

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

/**
 * Check whether an index exists in a table.
 */
function tableIndexExists($table, $indexName)
{
    global $conn;

    $safeTable = $conn->real_escape_string($table);
    $safeIndex = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    return $result && $result->num_rows > 0;
}

function normalizeBooleanValue($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int) $value === 1;
    }
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
    return false;
}

function normalizeIntegerIds($value)
{
    $ids = [];
    $push = function ($raw) use (&$ids) {
        $id = (int) $raw;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    };

    if (is_array($value)) {
        foreach ($value as $item) {
            $push($item);
        }
        return array_values($ids);
    }

    if (is_object($value)) {
        foreach ((array) $value as $item) {
            $push($item);
        }
        return array_values($ids);
    }

    if (is_numeric($value)) {
        $push($value);
        return array_values($ids);
    }

    if (!is_string($value)) {
        return [];
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $item) {
            $push($item);
        }
        return array_values($ids);
    }

    foreach (explode(',', $trimmed) as $item) {
        $push(trim($item));
    }

    return array_values($ids);
}

function ensureOrderExtensionsSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `order_services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `service_id` INT NULL,
        `service_name` VARCHAR(255) NULL,
        `is_custom` TINYINT(1) NOT NULL DEFAULT 0,
        `notes` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_order_services_order` (`order_id`),
        INDEX `idx_order_services_service` (`service_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `order_providers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `provider_id` INT NOT NULL,
        `assignment_status` VARCHAR(32) NOT NULL DEFAULT 'assigned',
        `assigned_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_order_provider` (`order_id`, `provider_id`),
        INDEX `idx_order_providers_order` (`order_id`),
        INDEX `idx_order_providers_provider` (`provider_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (tableExists('order_services')) {
        if (!tableColumnExists('order_services', 'service_name')) {
            $conn->query("ALTER TABLE `order_services` ADD COLUMN `service_name` VARCHAR(255) NULL");
        }
        if (!tableColumnExists('order_services', 'is_custom')) {
            $conn->query("ALTER TABLE `order_services` ADD COLUMN `is_custom` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!tableColumnExists('order_services', 'notes')) {
            $conn->query("ALTER TABLE `order_services` ADD COLUMN `notes` TEXT NULL");
        }
        if (!tableIndexExists('order_services', 'idx_order_services_order')) {
            $conn->query("ALTER TABLE `order_services` ADD INDEX `idx_order_services_order` (`order_id`)");
        }
        if (!tableIndexExists('order_services', 'idx_order_services_service')) {
            $conn->query("ALTER TABLE `order_services` ADD INDEX `idx_order_services_service` (`service_id`)");
        }
    }

    if (tableExists('order_providers')) {
        if (!tableColumnExists('order_providers', 'assignment_status')) {
            $conn->query("ALTER TABLE `order_providers` ADD COLUMN `assignment_status` VARCHAR(32) NOT NULL DEFAULT 'assigned'");
        }
        if (!tableColumnExists('order_providers', 'assigned_at')) {
            $conn->query("ALTER TABLE `order_providers` ADD COLUMN `assigned_at` DATETIME NULL");
        }
        if (!tableIndexExists('order_providers', 'uniq_order_provider')) {
            $conn->query("ALTER TABLE `order_providers` ADD UNIQUE KEY `uniq_order_provider` (`order_id`, `provider_id`)");
        }
        if (!tableIndexExists('order_providers', 'idx_order_providers_order')) {
            $conn->query("ALTER TABLE `order_providers` ADD INDEX `idx_order_providers_order` (`order_id`)");
        }
        if (!tableIndexExists('order_providers', 'idx_order_providers_provider')) {
            $conn->query("ALTER TABLE `order_providers` ADD INDEX `idx_order_providers_provider` (`provider_id`)");
        }
    }

    if (tableExists('notifications')) {
        if (!tableColumnExists('notifications', 'provider_id')) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `provider_id` INT NULL");
        }
        if (!tableColumnExists('notifications', 'data')) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `data` LONGTEXT NULL");
        }
        if (tableColumnExists('notifications', 'provider_id') && !tableIndexExists('notifications', 'idx_notifications_provider')) {
            $conn->query("ALTER TABLE `notifications` ADD INDEX `idx_notifications_provider` (`provider_id`)");
        }
    }

    if (tableExists('orders')) {
        if (!tableColumnExists('orders', 'myfatoorah_invoice_id')) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `myfatoorah_invoice_id` VARCHAR(100) NULL");
        }
        if (!tableColumnExists('orders', 'myfatoorah_payment_url')) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `myfatoorah_payment_url` TEXT NULL");
        }
        if (!tableColumnExists('orders', 'myfatoorah_payment_method_id')) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `myfatoorah_payment_method_id` INT NULL");
        }
        if (!tableColumnExists('orders', 'myfatoorah_payment_id')) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `myfatoorah_payment_id` VARCHAR(150) NULL");
        }
        if (!tableColumnExists('orders', 'myfatoorah_invoice_status')) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `myfatoorah_invoice_status` VARCHAR(50) NULL");
        }
        if (!tableColumnExists('orders', 'myfatoorah_last_status_at')) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `myfatoorah_last_status_at` DATETIME NULL");
        }
    }
}

/**
 * Ensure legacy databases contain the minimum order columns required by mobile create-order flow.
 */
function ensureOrderCreationSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!tableExists('orders')) {
        throw new RuntimeException('جدول الطلبات غير موجود');
    }

    $requiredColumns = [
        'address' => "VARCHAR(255) NULL",
        'lat' => "DECIMAL(10,8) NULL",
        'lng' => "DECIMAL(11,8) NULL",
        'notes' => "TEXT NULL",
        'scheduled_date' => "DATE NULL",
        'scheduled_time' => "TIME NULL",
        'attachments' => "LONGTEXT NULL",
        'problem_details' => "LONGTEXT NULL",
        'problem_description' => "TEXT NULL",
        'problem_images' => "LONGTEXT NULL",
        'inspection_images' => "LONGTEXT NULL",
        'subtotal_amount' => "DECIMAL(10,2) NULL",
        'discount_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'promo_code' => "VARCHAR(64) NULL",
    ];

    foreach ($requiredColumns as $column => $definition) {
        if (!orderColumnExists($column)) {
            $sql = "ALTER TABLE `orders` ADD COLUMN `{$column}` {$definition}";
            if (!$conn->query($sql)) {
                throw new RuntimeException('تعذر تجهيز قاعدة البيانات لحفظ الطلب (' . $column . ')');
            }
        }
    }
}

function ensureOtherServiceCategory()
{
    global $conn;

    $stmt = $conn->prepare("SELECT id FROM service_categories WHERE name_ar = ? LIMIT 1");
    $nameAr = 'خدمة أخرى';
    if ($stmt) {
        $stmt->bind_param("s", $nameAr);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if (!empty($existing['id'])) {
            return (int) $existing['id'];
        }
    }

    $insertData = [
        'name_ar' => $nameAr,
    ];
    if (tableColumnExists('service_categories', 'name_en')) {
        $insertData['name_en'] = 'Other Service';
    }
    if (tableColumnExists('service_categories', 'icon')) {
        $insertData['icon'] = '🔧';
    }
    if (tableColumnExists('service_categories', 'is_active')) {
        $insertData['is_active'] = 1;
    }
    if (tableColumnExists('service_categories', 'sort_order')) {
        $insertData['sort_order'] = 999;
    }
    if (tableColumnExists('service_categories', 'warranty_days')) {
        $insertData['warranty_days'] = 0;
    }

    $columns = array_keys($insertData);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $types = '';
    $values = [];
    foreach ($columns as $column) {
        $value = $insertData[$column];
        if (in_array($column, ['is_active', 'sort_order', 'warranty_days'], true)) {
            $types .= 'i';
        } else {
            $types .= 's';
        }
        $values[] = $value;
    }

    $sql = "INSERT INTO service_categories (" . implode(', ', $columns) . ") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) {
            return (int) $conn->insert_id;
        }
    }

    $stmt = $conn->prepare("SELECT id FROM service_categories WHERE name_ar = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $nameAr);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if (!empty($existing['id'])) {
            return (int) $existing['id'];
        }
    }

    return 0;
}

function persistOrderServiceItems($orderId, array $serviceIds, $customServiceTitle = '', $customServiceDescription = '')
{
    global $conn;

    ensureOrderExtensionsSchema();
    if (!tableExists('order_services')) {
        return;
    }

    $serviceIds = normalizeIntegerIds($serviceIds);
    $customServiceTitle = trim((string) $customServiceTitle);
    $customServiceDescription = trim((string) $customServiceDescription);

    $knownNames = [];
    if (!empty($serviceIds)) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $types = str_repeat('i', count($serviceIds));
        $sql = "SELECT id, name_ar FROM services WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$serviceIds);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $knownNames[(int) $row['id']] = trim((string) ($row['name_ar'] ?? ''));
            }
        }
    }

    if (!empty($serviceIds)) {
        $insertStmt = $conn->prepare(
            "INSERT INTO order_services (order_id, service_id, service_name, is_custom, notes)
             VALUES (?, ?, ?, 0, NULL)"
        );
        if ($insertStmt) {
            foreach ($serviceIds as $serviceId) {
                $serviceName = $knownNames[$serviceId] ?? ('خدمة #' . $serviceId);
                $insertStmt->bind_param("iis", $orderId, $serviceId, $serviceName);
                $insertStmt->execute();
            }
        }
    }

    if ($customServiceTitle !== '' || $customServiceDescription !== '') {
        if ($customServiceTitle === '') {
            $customServiceTitle = 'خدمة أخرى';
        }
        $insertCustom = $conn->prepare(
            "INSERT INTO order_services (order_id, service_id, service_name, is_custom, notes)
             VALUES (?, NULL, ?, 1, ?)"
        );
        if ($insertCustom) {
            $insertCustom->bind_param("iss", $orderId, $customServiceTitle, $customServiceDescription);
            $insertCustom->execute();
        }
    }
}

function fetchOrderServiceItems($orderId, $orderRow = null)
{
    global $conn;

    ensureOrderExtensionsSchema();
    $items = [];

    if (tableExists('order_services')) {
        $stmt = $conn->prepare(
            "SELECT os.id, os.service_id, os.service_name, os.is_custom, os.notes,
                    s.name_ar AS service_name_ar
             FROM order_services os
             LEFT JOIN services s ON s.id = os.service_id
             WHERE os.order_id = ?
             ORDER BY os.id ASC"
        );
        if ($stmt) {
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $serviceName = trim((string) ($row['service_name'] ?? ''));
                if ($serviceName === '') {
                    $serviceName = trim((string) ($row['service_name_ar'] ?? ''));
                }
                $items[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'service_id' => !empty($row['service_id']) ? (int) $row['service_id'] : null,
                    'service_name' => $serviceName,
                    'is_custom' => (int) ($row['is_custom'] ?? 0) === 1,
                    'notes' => $row['notes'] ?? '',
                ];
            }
        }
    }

    if (!empty($items)) {
        return $items;
    }

    $problemDetailsRaw = null;
    if (is_array($orderRow) && array_key_exists('problem_details', $orderRow)) {
        $problemDetailsRaw = $orderRow['problem_details'];
    }

    $problemDetails = [];
    if (is_array($problemDetailsRaw)) {
        $problemDetails = $problemDetailsRaw;
    } elseif (is_object($problemDetailsRaw)) {
        $problemDetails = (array) $problemDetailsRaw;
    } elseif (is_string($problemDetailsRaw)) {
        $decoded = json_decode(trim($problemDetailsRaw), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $problemDetails = $decoded;
        }
    }

    $serviceIds = normalizeIntegerIds($problemDetails['service_type_ids'] ?? ($problemDetails['sub_services'] ?? []));
    if (!empty($serviceIds)) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $types = str_repeat('i', count($serviceIds));
        $stmt = $conn->prepare("SELECT id, name_ar FROM services WHERE id IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$serviceIds);
            $stmt->execute();
            $result = $stmt->get_result();
            $nameMap = [];
            while ($row = $result->fetch_assoc()) {
                $nameMap[(int) $row['id']] = trim((string) ($row['name_ar'] ?? ''));
            }
            foreach ($serviceIds as $serviceId) {
                $items[] = [
                    'id' => 0,
                    'service_id' => $serviceId,
                    'service_name' => $nameMap[$serviceId] ?? ('خدمة #' . $serviceId),
                    'is_custom' => false,
                    'notes' => '',
                ];
            }
        }
    }

    $customService = $problemDetails['custom_service'] ?? null;
    if (is_array($customService)) {
        $customTitle = trim((string) ($customService['title'] ?? ''));
        $customDescription = trim((string) ($customService['description'] ?? ''));
        if ($customTitle !== '' || $customDescription !== '') {
            if ($customTitle === '') {
                $customTitle = 'خدمة أخرى';
            }
            $items[] = [
                'id' => 0,
                'service_id' => null,
                'service_name' => $customTitle,
                'is_custom' => true,
                'notes' => $customDescription,
            ];
        }
    }

    return $items;
}

function fetchOrderAssignedProviders($orderId)
{
    global $conn;

    ensureOrderExtensionsSchema();
    if (!tableExists('order_providers')) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT op.provider_id, op.assignment_status, op.assigned_at,
                p.full_name AS provider_name, p.phone AS provider_phone, p.email AS provider_email,
                p.avatar AS provider_avatar, p.rating AS provider_rating, p.status AS provider_status,
                p.is_available AS provider_is_available,
                o.provider_id AS primary_provider_id
         FROM order_providers op
         LEFT JOIN providers p ON p.id = op.provider_id
         LEFT JOIN orders o ON o.id = op.order_id
         WHERE op.order_id = ?
         ORDER BY op.id ASC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $providerId = (int) ($row['provider_id'] ?? 0);
        if ($providerId <= 0) {
            continue;
        }
        $items[] = [
            'provider_id' => $providerId,
            'provider_name' => $row['provider_name'] ?? null,
            'provider_phone' => $row['provider_phone'] ?? null,
            'provider_email' => $row['provider_email'] ?? null,
            'provider_avatar' => !empty($row['provider_avatar']) ? imageUrl((string) $row['provider_avatar']) : null,
            'provider_rating' => isset($row['provider_rating']) ? (float) $row['provider_rating'] : null,
            'provider_status' => $row['provider_status'] ?? null,
            'provider_is_available' => isset($row['provider_is_available']) ? ((int) $row['provider_is_available'] === 1) : null,
            'assignment_status' => $row['assignment_status'] ?? 'assigned',
            'assigned_at' => $row['assigned_at'] ?? null,
            'is_primary' => !empty($row['primary_provider_id']) && (int) $row['primary_provider_id'] === $providerId,
        ];
    }

    return $items;
}

function assignProvidersToOrder($orderId, $providerIds, $setPrimary = true)
{
    global $conn;

    ensureOrderExtensionsSchema();
    if (!tableExists('order_providers')) {
        return 0;
    }

    $providerIds = normalizeIntegerIds($providerIds);
    if (empty($providerIds)) {
        return 0;
    }

    $existingProviderIds = [];
    $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
    $types = str_repeat('i', count($providerIds));
    $stmt = $conn->prepare(
        "SELECT id
         FROM providers
         WHERE id IN ($placeholders)
           AND status = 'approved'"
    );
    if ($stmt) {
        $stmt->bind_param($types, ...$providerIds);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existingProviderIds[] = (int) $row['id'];
        }
    }

    if (empty($existingProviderIds)) {
        return 0;
    }

    $insertCount = 0;
    $insertStmt = $conn->prepare(
        "INSERT INTO order_providers (order_id, provider_id, assignment_status, assigned_at)
         VALUES (?, ?, 'assigned', NOW())
         ON DUPLICATE KEY UPDATE
             assignment_status = IF(assignment_status = 'completed', assignment_status, 'assigned'),
             assigned_at = COALESCE(assigned_at, NOW())"
    );
    if ($insertStmt) {
        foreach ($existingProviderIds as $providerId) {
            $insertStmt->bind_param("ii", $orderId, $providerId);
            if ($insertStmt->execute()) {
                $insertCount++;
            }
        }
    }

    if ($setPrimary && !empty($existingProviderIds)) {
        $primaryProviderId = $existingProviderIds[0];
        $updateStmt = $conn->prepare(
            "UPDATE orders
             SET provider_id = COALESCE(provider_id, ?),
                 status = CASE WHEN status = 'pending' THEN 'assigned' ELSE status END
             WHERE id = ?"
        );
        if ($updateStmt) {
            $updateStmt->bind_param("ii", $primaryProviderId, $orderId);
            $updateStmt->execute();
        }
    }

    return $insertCount;
}

function setProviderAssignmentStatus($orderId, $providerId, $status)
{
    global $conn;

    ensureOrderExtensionsSchema();
    if (!tableExists('order_providers')) {
        return;
    }

    $allowedStatuses = ['assigned', 'accepted', 'in_progress', 'completed', 'cancelled', 'rejected'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'assigned';
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO order_providers (order_id, provider_id, assignment_status, assigned_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE assignment_status = ?"
    );
    if ($insertStmt) {
        $insertStmt->bind_param("iiss", $orderId, $providerId, $status, $status);
        $insertStmt->execute();
    }

    if ($status === 'in_progress') {
        $resetStmt = $conn->prepare(
            "UPDATE order_providers
             SET assignment_status = 'assigned'
             WHERE order_id = ?
               AND provider_id <> ?
               AND assignment_status IN ('in_progress', 'accepted')"
        );
        if ($resetStmt) {
            $resetStmt->bind_param("ii", $orderId, $providerId);
            $resetStmt->execute();
        }
    }
}

function resolveOrderReviewProviderId($orderId)
{
    global $conn;

    ensureOrderExtensionsSchema();
    if (!tableExists('order_providers')) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT provider_id
         FROM order_providers
         WHERE order_id = ?
         ORDER BY FIELD(assignment_status, 'completed', 'in_progress', 'accepted', 'assigned', 'cancelled', 'rejected'), id ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (empty($row['provider_id'])) {
        return 0;
    }
    return (int) $row['provider_id'];
}

/**
 * Ensure schema for live tracking points of providers by order.
 */
function ensureOrderLiveLocationSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `order_live_locations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `provider_id` INT NOT NULL,
        `lat` DECIMAL(10,8) NOT NULL,
        `lng` DECIMAL(11,8) NOT NULL,
        `accuracy` DECIMAL(10,2) NULL,
        `speed` DECIMAL(10,2) NULL,
        `heading` DECIMAL(10,2) NULL,
        `created_at` DATETIME NOT NULL,
        INDEX `idx_oll_order` (`order_id`),
        INDEX `idx_oll_provider` (`provider_id`),
        INDEX `idx_oll_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (tableExists('order_live_locations')) {
        if (!tableColumnExists('order_live_locations', 'accuracy')) {
            $conn->query("ALTER TABLE `order_live_locations` ADD COLUMN `accuracy` DECIMAL(10,2) NULL");
        }
        if (!tableColumnExists('order_live_locations', 'speed')) {
            $conn->query("ALTER TABLE `order_live_locations` ADD COLUMN `speed` DECIMAL(10,2) NULL");
        }
        if (!tableColumnExists('order_live_locations', 'heading')) {
            $conn->query("ALTER TABLE `order_live_locations` ADD COLUMN `heading` DECIMAL(10,2) NULL");
        }
        if (!tableColumnExists('order_live_locations', 'created_at')) {
            $conn->query("ALTER TABLE `order_live_locations` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!tableIndexExists('order_live_locations', 'idx_oll_order')) {
            $conn->query("ALTER TABLE `order_live_locations` ADD INDEX `idx_oll_order` (`order_id`)");
        }
        if (!tableIndexExists('order_live_locations', 'idx_oll_provider')) {
            $conn->query("ALTER TABLE `order_live_locations` ADD INDEX `idx_oll_provider` (`provider_id`)");
        }
        if (!tableIndexExists('order_live_locations', 'idx_oll_created_at')) {
            $conn->query("ALTER TABLE `order_live_locations` ADD INDEX `idx_oll_created_at` (`created_at`)");
        }
    }
}

/**
 * Fetch latest live location point for a given order/provider.
 */
function fetchLatestOrderProviderLocation($orderId, $providerId = null)
{
    global $conn;

    ensureOrderLiveLocationSchema();
    if (!tableExists('order_live_locations')) {
        return null;
    }

    $orderId = (int) $orderId;
    if ($orderId <= 0) {
        return null;
    }

    $providerId = $providerId !== null ? (int) $providerId : 0;
    if ($providerId > 0) {
        $stmt = $conn->prepare(
            "SELECT oll.order_id, oll.provider_id, oll.lat, oll.lng, oll.accuracy, oll.speed, oll.heading, oll.created_at,
                    p.full_name AS provider_name, p.phone AS provider_phone
             FROM order_live_locations oll
             LEFT JOIN providers p ON p.id = oll.provider_id
             WHERE oll.order_id = ? AND oll.provider_id = ?
             ORDER BY oll.created_at DESC, oll.id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("ii", $orderId, $providerId);
    } else {
        $stmt = $conn->prepare(
            "SELECT oll.order_id, oll.provider_id, oll.lat, oll.lng, oll.accuracy, oll.speed, oll.heading, oll.created_at,
                    p.full_name AS provider_name, p.phone AS provider_phone
             FROM order_live_locations oll
             LEFT JOIN providers p ON p.id = oll.provider_id
             WHERE oll.order_id = ?
             ORDER BY oll.created_at DESC, oll.id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $orderId);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }

    return [
        'order_id' => (int) ($row['order_id'] ?? 0),
        'provider_id' => (int) ($row['provider_id'] ?? 0),
        'provider_name' => $row['provider_name'] ?? null,
        'provider_phone' => $row['provider_phone'] ?? null,
        'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
        'lng' => isset($row['lng']) ? (float) $row['lng'] : null,
        'accuracy' => isset($row['accuracy']) ? (float) $row['accuracy'] : null,
        'speed' => isset($row['speed']) ? (float) $row['speed'] : null,
        'heading' => isset($row['heading']) ? (float) $row['heading'] : null,
        'captured_at' => $row['created_at'] ?? null,
    ];
}

/**
 * Ensure schema for provider-requested order spare parts.
 */
function ensureOrderSparePartsSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `order_spare_parts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `provider_id` INT NULL,
        `store_id` INT NULL,
        `spare_part_id` INT NULL,
        `spare_part_name` VARCHAR(255) NOT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `pricing_mode` VARCHAR(32) NULL,
        `requires_installation` TINYINT(1) NOT NULL DEFAULT 1,
        `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `notes` VARCHAR(255) NULL,
        `is_committed` TINYINT(1) NOT NULL DEFAULT 0,
        `committed_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_osp_order` (`order_id`),
        INDEX `idx_osp_spare_part` (`spare_part_id`),
        INDEX `idx_osp_store` (`store_id`),
        INDEX `idx_osp_committed` (`is_committed`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (tableExists('order_spare_parts')) {
        if (!tableColumnExists('order_spare_parts', 'is_committed')) {
            $conn->query("ALTER TABLE `order_spare_parts` ADD COLUMN `is_committed` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!tableColumnExists('order_spare_parts', 'committed_at')) {
            $conn->query("ALTER TABLE `order_spare_parts` ADD COLUMN `committed_at` DATETIME NULL");
        }
        if (!tableColumnExists('order_spare_parts', 'notes')) {
            $conn->query("ALTER TABLE `order_spare_parts` ADD COLUMN `notes` VARCHAR(255) NULL");
        }
        if (!tableColumnExists('order_spare_parts', 'pricing_mode')) {
            $conn->query("ALTER TABLE `order_spare_parts` ADD COLUMN `pricing_mode` VARCHAR(32) NULL");
        }
        if (!tableColumnExists('order_spare_parts', 'requires_installation')) {
            $conn->query("ALTER TABLE `order_spare_parts` ADD COLUMN `requires_installation` TINYINT(1) NOT NULL DEFAULT 1");
        }
        if (!tableIndexExists('order_spare_parts', 'idx_osp_committed')) {
            $conn->query("ALTER TABLE `order_spare_parts` ADD INDEX `idx_osp_committed` (`is_committed`)");
        }
    }
}

function ensureSparePartsPricingSchema()
{
    global $conn;

    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (!tableExists('spare_parts')) {
        return;
    }

    if (!tableColumnExists('spare_parts', 'price_with_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `price_with_installation` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    if (!tableColumnExists('spare_parts', 'price_without_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `price_without_installation` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    if (!tableColumnExists('spare_parts', 'old_price_with_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `old_price_with_installation` DECIMAL(10,2) NULL");
    }
    if (!tableColumnExists('spare_parts', 'old_price_without_installation')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `old_price_without_installation` DECIMAL(10,2) NULL");
    }
    if (!tableColumnExists('spare_parts', 'category_id')) {
        $conn->query("ALTER TABLE `spare_parts` ADD COLUMN `category_id` INT NULL");
    }
    if (!tableIndexExists('spare_parts', 'idx_spare_parts_category_id')) {
        $conn->query("ALTER TABLE `spare_parts` ADD INDEX `idx_spare_parts_category_id` (`category_id`)");
    }

    if (tableColumnExists('spare_parts', 'price')) {
        $conn->query("UPDATE `spare_parts` SET `price_with_installation` = `price` WHERE `price_with_installation` <= 0 OR `price_with_installation` IS NULL");
        $conn->query("UPDATE `spare_parts` SET `price_without_installation` = `price` WHERE `price_without_installation` <= 0 OR `price_without_installation` IS NULL");
    }

    if (tableColumnExists('spare_parts', 'old_price')) {
        $conn->query("UPDATE `spare_parts` SET `old_price_with_installation` = `old_price` WHERE `old_price_with_installation` IS NULL AND `old_price` IS NOT NULL");
        $conn->query("UPDATE `spare_parts` SET `old_price_without_installation` = `old_price` WHERE `old_price_without_installation` IS NULL AND `old_price` IS NOT NULL");
    }
}

function resolveSparePartUnitPrice($sparePartRow, $pricingMode)
{
    $mode = normalizeSparePricingMode($pricingMode) ?? 'with_installation';
    $fallback = isset($sparePartRow['price']) ? (float) $sparePartRow['price'] : 0.0;
    $withInstallation = isset($sparePartRow['price_with_installation']) && (float) $sparePartRow['price_with_installation'] > 0
        ? (float) $sparePartRow['price_with_installation']
        : $fallback;
    $withoutInstallation = isset($sparePartRow['price_without_installation']) && (float) $sparePartRow['price_without_installation'] > 0
        ? (float) $sparePartRow['price_without_installation']
        : $withInstallation;

    return $mode === 'without_installation' ? $withoutInstallation : $withInstallation;
}

function serviceCategoriesHasParentColumnForOrders()
{
    static $hasParent = null;
    if ($hasParent !== null) {
        return $hasParent;
    }

    global $conn;
    $result = $conn->query("SHOW COLUMNS FROM service_categories LIKE 'parent_id'");
    $hasParent = $result && $result->num_rows > 0;
    return $hasParent;
}

function resolveRelatedSparePartCategoryIds($categoryId)
{
    global $conn;

    $categoryId = (int) $categoryId;
    if ($categoryId <= 0) {
        return [];
    }
    if (!tableExists('service_categories')) {
        return [$categoryId];
    }

    $ids = [$categoryId => $categoryId];

    if (!serviceCategoriesHasParentColumnForOrders()) {
        return array_values($ids);
    }

    $stmt = $conn->prepare("SELECT parent_id FROM service_categories WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return [];
        }
        $parentId = (int) ($row['parent_id'] ?? 0);
        if ($parentId > 0) {
            $ids[$parentId] = $parentId;
        }
    }

    $childStmt = $conn->prepare("SELECT id FROM service_categories WHERE parent_id = ?");
    if ($childStmt) {
        $childStmt->bind_param("i", $categoryId);
        $childStmt->execute();
        $result = $childStmt->get_result();
        while ($child = $result->fetch_assoc()) {
            $childId = (int) ($child['id'] ?? 0);
            if ($childId > 0) {
                $ids[$childId] = $childId;
            }
        }
    }

    return array_values($ids);
}

function sparePartBelongsToOrderCategory($sparePartCategoryId, $orderCategoryId, array $allowedCategoryIds = [])
{
    $orderCategoryId = (int) $orderCategoryId;
    if ($orderCategoryId <= 0) {
        return true;
    }

    $sparePartCategoryId = (int) $sparePartCategoryId;
    if ($sparePartCategoryId <= 0) {
        return false;
    }

    if (!empty($allowedCategoryIds)) {
        return in_array($sparePartCategoryId, $allowedCategoryIds, true);
    }

    return $sparePartCategoryId === $orderCategoryId;
}

function persistOrderRequestedSpareParts($orderId, array $requestedSpareParts, array $problemDetails = [], $orderCategoryId = 0)
{
    global $conn;

    if ($orderId <= 0 || empty($requestedSpareParts)) {
        return;
    }

    ensureOrderSparePartsSchema();
    ensureSparePartsPricingSchema();

    if (!tableExists('order_spare_parts')) {
        return;
    }

    $defaultMode = normalizeSparePricingMode($problemDetails['pricing_mode'] ?? null) ?? 'with_installation';
    $defaultRequiresInstallation = array_key_exists('requires_installation', $problemDetails)
        ? normalizeBooleanValue($problemDetails['requires_installation'])
        : ($defaultMode !== 'without_installation');
    $orderCategoryId = (int) $orderCategoryId;
    if ($orderCategoryId <= 0 && tableExists('orders')) {
        $orderStmt = $conn->prepare("SELECT category_id FROM orders WHERE id = ? LIMIT 1");
        if ($orderStmt) {
            $orderStmt->bind_param("i", $orderId);
            $orderStmt->execute();
            $orderRow = $orderStmt->get_result()->fetch_assoc();
            $orderCategoryId = (int) ($orderRow['category_id'] ?? 0);
        }
    }
    $allowedCategoryIds = resolveRelatedSparePartCategoryIds($orderCategoryId);
    $orderScopeAreaIds = resolveOrderCoverageAreaIdsForSparePartsScope((int) $orderId);
    $orderScopeServiceIds = resolveOrderServiceIdsForSparePartsScope((int) $orderId, $problemDetails);

    $deleteStmt = $conn->prepare(
        "DELETE FROM order_spare_parts
         WHERE order_id = ?
           AND provider_id IS NULL
           AND is_committed = 0"
    );
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $orderId);
        $deleteStmt->execute();
    }

    $lookupStmt = $conn->prepare(
        "SELECT id, store_id, category_id, name_ar, price, price_with_installation, price_without_installation, stock_quantity, is_active
         FROM spare_parts
         WHERE id = ?"
    );
    $insertStmt = $conn->prepare(
        "INSERT INTO order_spare_parts
        (order_id, provider_id, store_id, spare_part_id, spare_part_name, quantity, pricing_mode, requires_installation, unit_price, total_price, notes, is_committed)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
    );

    if (!$insertStmt) {
        return;
    }

    foreach ($requestedSpareParts as $requestedPart) {
        if (!is_array($requestedPart)) {
            continue;
        }

        $sparePartId = (int) ($requestedPart['spare_part_id'] ?? 0);
        $partName = trim((string) ($requestedPart['name'] ?? ''));
        $quantity = max(1, (int) ($requestedPart['quantity'] ?? 1));
        $pricingMode = normalizeSparePricingMode($requestedPart['pricing_mode'] ?? null) ?? $defaultMode;
        $requiresInstallation = array_key_exists('requires_installation', $requestedPart)
            ? normalizeBooleanValue($requestedPart['requires_installation'])
            : $defaultRequiresInstallation;
        $unitPrice = isset($requestedPart['unit_price']) ? (float) $requestedPart['unit_price'] : null;
        $notes = trim((string) ($requestedPart['notes'] ?? ''));
        $storeId = 0;

        if ($sparePartId > 0 && $lookupStmt) {
            $lookupStmt->bind_param("i", $sparePartId);
            $lookupStmt->execute();
            $dbPart = $lookupStmt->get_result()->fetch_assoc();

            if ($dbPart) {
                if (!sparePartBelongsToOrderCategory($dbPart['category_id'] ?? null, $orderCategoryId, $allowedCategoryIds)) {
                    continue;
                }
                if (!sparePartMatchesOrderScope($sparePartId, $orderScopeAreaIds, $orderScopeServiceIds)) {
                    continue;
                }
                $storeId = !empty($dbPart['store_id']) ? (int) $dbPart['store_id'] : 0;
                if ($partName === '') {
                    $partName = (string) ($dbPart['name_ar'] ?? ('قطعة #' . $sparePartId));
                }
                if ($unitPrice === null || $unitPrice <= 0) {
                    $unitPrice = resolveSparePartUnitPrice($dbPart, $pricingMode);
                }
            }
        }

        if ($partName === '') {
            $partName = 'قطعة غيار';
        }

        if ($unitPrice === null || $unitPrice <= 0) {
            $unitPrice = 0.0;
        }

        $totalPrice = $unitPrice * $quantity;
        $requiresInstallationValue = $requiresInstallation ? 1 : 0;

        $insertStmt->bind_param(
            "iiisisidds",
            $orderId,
            $storeId,
            $sparePartId,
            $partName,
            $quantity,
            $pricingMode,
            $requiresInstallationValue,
            $unitPrice,
            $totalPrice,
            $notes
        );
        $insertStmt->execute();
    }
}

/**
 * Sync special-service orders into dedicated special modules tables.
 * يدعم:
 * - نقل الحاويات -> container_requests
 * - نقل العفش -> furniture_requests
 */
function syncSpecialServiceRequestFromOrder($orderId, $userId, array $problemDetails, array $uploadedFiles, $notes, $address)
{
    if ($orderId <= 0 || $userId <= 0) {
        return;
    }

    $module = strtolower(trim((string) ($problemDetails['module'] ?? '')));
    $type = strtolower(trim((string) ($problemDetails['type'] ?? '')));
    $containerRequest = $problemDetails['container_request'] ?? [];
    if ($containerRequest instanceof stdClass) {
        $containerRequest = (array) $containerRequest;
    }
    if (!is_array($containerRequest)) {
        $containerRequest = [];
    }
    $furnitureRequest = $problemDetails['furniture_request'] ?? [];
    if ($furnitureRequest instanceof stdClass) {
        $furnitureRequest = (array) $furnitureRequest;
    }
    if (!is_array($furnitureRequest)) {
        $furnitureRequest = [];
    }

    $isContainerFlow = !empty($containerRequest)
        || $module === 'container_rental'
        || $type === 'container_rental'
        || strpos($module, 'container') !== false
        || strpos($type, 'container') !== false;
    $isFurnitureFlow = !empty($furnitureRequest)
        || $module === 'furniture_moving'
        || $type === 'furniture_moving'
        || strpos($module, 'furniture') !== false
        || strpos($type, 'furniture') !== false;

    if (!$isContainerFlow && !$isFurnitureFlow) {
        return;
    }

    ensureSpecialServicesSchema();
    $orderRow = db()->fetch('SELECT * FROM orders WHERE id = ? LIMIT 1', [$orderId]) ?: [];
    $specialStatus = normalizeSpecialRequestStatus(
        specialMapOrderStatusToRequestStatus($orderRow['status'] ?? '')
    );
    $orderTotalAmount = specialToPositiveFloat($orderRow['total_amount'] ?? 0);

    $user = db()->fetch('SELECT full_name, phone FROM users WHERE id = ? LIMIT 1', [$userId]) ?: [];
    $customerName = trim((string) ($user['full_name'] ?? ''));
    if ($customerName === '') {
        $customerName = 'عميل #' . $userId;
    }
    $customerPhone = trim((string) ($user['phone'] ?? ''));

    if ($isContainerFlow && specialServiceTableExists('container_requests')) {
        if (specialServiceColumnExists('container_requests', 'source_order_id')) {
            $existing = db()->fetch(
                'SELECT id FROM container_requests WHERE source_order_id = ? LIMIT 1',
                [$orderId]
            );
            if (!empty($existing['id'])) {
                return;
            }
        }

        $serviceId = (int) ($containerRequest['container_service_id'] ?? 0);
        if ($serviceId <= 0) {
            $serviceTypeIds = normalizeIntegerIds($problemDetails['service_type_ids'] ?? ($problemDetails['sub_services'] ?? []));
            if (!empty($serviceTypeIds)) {
                $serviceId = (int) $serviceTypeIds[0];
            }
        }

        $serviceRow = null;
        if ($serviceId > 0 && specialServiceTableExists('container_services')) {
            $serviceRow = db()->fetch(
                'SELECT id, name_ar, container_size, store_id, daily_price, weekly_price, monthly_price, delivery_fee, price_per_kg, price_per_meter, minimum_charge
                 FROM container_services
                 WHERE id = ? LIMIT 1',
                [$serviceId]
            );
        }

        $containerStoreId = (int) ($containerRequest['container_store_id'] ?? 0);
        if ($containerStoreId <= 0 && is_array($serviceRow)) {
            $containerStoreId = (int) ($serviceRow['store_id'] ?? 0);
        }
        if ($containerStoreId > 0 && specialServiceTableExists('container_stores')) {
            $storeRow = db()->fetch('SELECT id FROM container_stores WHERE id = ? LIMIT 1', [$containerStoreId]);
            if (!$storeRow) {
                $containerStoreId = 0;
            }
        }

        $durationDays = max(1, (int) ($containerRequest['duration_days'] ?? 1));
        $quantity = max(1, (int) ($containerRequest['quantity'] ?? 1));
        $weightKg = specialToPositiveFloat($containerRequest['estimated_weight_kg'] ?? 0);
        $distanceMeters = specialToPositiveFloat($containerRequest['estimated_distance_meters'] ?? 0);

        $basePrice = 0.0;
        $pricePerKg = 0.0;
        $pricePerMeter = 0.0;
        $minimumCharge = 0.0;

        if (is_array($serviceRow) && !empty($serviceRow)) {
            $basePrice = specialCalculateContainerRentalBase($serviceRow, $durationDays, $quantity);
            $pricePerKg = (float) ($serviceRow['price_per_kg'] ?? 0);
            $pricePerMeter = (float) ($serviceRow['price_per_meter'] ?? 0);
            $minimumCharge = (float) ($serviceRow['minimum_charge'] ?? 0);
        } else {
            $dailyPrice = specialToPositiveFloat($containerRequest['daily_price'] ?? 0);
            $basePrice = ($dailyPrice * $durationDays * $quantity) + specialToPositiveFloat($containerRequest['delivery_fee'] ?? 0);
            $pricePerKg = specialToPositiveFloat($containerRequest['price_per_kg'] ?? 0);
            $pricePerMeter = specialToPositiveFloat($containerRequest['price_per_meter'] ?? 0);
            $minimumCharge = specialToPositiveFloat($containerRequest['minimum_charge'] ?? 0);
        }

        $estimatedPrice = specialToPositiveFloat($containerRequest['estimated_price'] ?? 0);
        if ($estimatedPrice <= 0) {
            $estimatedPrice = specialCalculateFlexiblePrice(
                $basePrice,
                $pricePerKg,
                $pricePerMeter,
                $minimumCharge,
                $weightKg,
                $distanceMeters
            );
        }
        if ($estimatedPrice <= 0 && $orderTotalAmount > 0) {
            $estimatedPrice = $orderTotalAmount;
        }

        $finalPrice = null;
        $containerFinalPrice = specialToPositiveFloat($containerRequest['final_price'] ?? 0);
        if ($specialStatus === 'completed') {
            if ($containerFinalPrice > 0) {
                $finalPrice = $containerFinalPrice;
            } elseif ($orderTotalAmount > 0) {
                $finalPrice = $orderTotalAmount;
            }
        }

        $detailsPayload = [
            'source' => 'mobile_order',
            'source_order_id' => (int) $orderId,
            'module' => $module !== '' ? $module : 'container_rental',
            'problem_details' => $problemDetails,
            'pricing' => [
                'base_price' => $basePrice,
                'price_per_kg' => $pricePerKg,
                'price_per_meter' => $pricePerMeter,
                'minimum_charge' => $minimumCharge,
                'calculated_estimated_price' => $estimatedPrice,
            ],
        ];
        $detailsJson = json_encode($detailsPayload, JSON_UNESCAPED_UNICODE);
        $mediaJson = !empty($uploadedFiles) ? json_encode(array_values($uploadedFiles), JSON_UNESCAPED_UNICODE) : null;

        $insertData = [
            'request_number' => specialGenerateRequestNumber('CT', 'container_requests'),
            'user_id' => $userId,
            'container_service_id' => $serviceId > 0 ? $serviceId : null,
            'container_store_id' => $containerStoreId > 0 ? $containerStoreId : null,
            'customer_name' => $customerName,
            'phone' => $customerPhone,
            'site_city' => trim((string) ($containerRequest['site_city'] ?? '')),
            'site_address' => trim((string) ($containerRequest['site_address'] ?? $address ?? '')),
            'start_date' => specialNormalizeDateValue($containerRequest['start_date'] ?? ($orderRow['scheduled_date'] ?? null)),
            'end_date' => specialNormalizeDateValue($containerRequest['end_date'] ?? null),
            'duration_days' => $durationDays,
            'quantity' => $quantity,
            'needs_loading_help' => !empty($containerRequest['needs_loading_help']) ? 1 : 0,
            'needs_operator' => !empty($containerRequest['needs_operator']) ? 1 : 0,
            'purpose' => trim((string) ($containerRequest['purpose'] ?? '')),
            'notes' => trim((string) ($containerRequest['notes'] ?? $notes ?? '')),
            'status' => $specialStatus,
            'estimated_price' => $estimatedPrice > 0 ? $estimatedPrice : null,
            'final_price' => $finalPrice,
            'estimated_weight_kg' => $weightKg > 0 ? $weightKg : null,
            'estimated_distance_meters' => $distanceMeters > 0 ? $distanceMeters : null,
            'details_json' => $detailsJson,
            'media_json' => $mediaJson,
            'source_order_id' => $orderId,
        ];

        db()->insert('container_requests', $insertData);
        return;
    }

    if (!$isFurnitureFlow || !specialServiceTableExists('furniture_requests')) {
        return;
    }

    if (specialServiceColumnExists('furniture_requests', 'source_order_id')) {
        $existing = db()->fetch(
            'SELECT id FROM furniture_requests WHERE source_order_id = ? LIMIT 1',
            [$orderId]
        );
        if (!empty($existing['id'])) {
            return;
        }
    }

    $furnitureServiceId = (int) ($furnitureRequest['service_id'] ?? 0);
    if ($furnitureServiceId <= 0) {
        $serviceTypeIds = normalizeIntegerIds($problemDetails['service_type_ids'] ?? ($problemDetails['sub_services'] ?? []));
        if (!empty($serviceTypeIds)) {
            $furnitureServiceId = (int) $serviceTypeIds[0];
        }
    }

    $areaId = (int) ($furnitureRequest['area_id'] ?? ($problemDetails['area_id'] ?? 0));
    $areaName = trim((string) ($furnitureRequest['area_name'] ?? ($problemDetails['area_name'] ?? '')));
    if ($areaId > 0 && specialServiceTableExists('furniture_areas')) {
        $areaRow = db()->fetch(
            'SELECT name_ar FROM furniture_areas WHERE id = ? LIMIT 1',
            [$areaId]
        );
        if ($areaRow && trim((string) ($areaRow['name_ar'] ?? '')) !== '') {
            $areaName = trim((string) $areaRow['name_ar']);
        }
    } else {
        $areaId = 0;
    }

    $estimatedWeightKg = specialToPositiveFloat(
        $furnitureRequest['estimated_weight_kg']
        ?? $problemDetails['estimated_weight_kg']
        ?? 0
    );
    $estimatedDistanceMeters = specialToPositiveFloat(
        $furnitureRequest['estimated_distance_meters']
        ?? $problemDetails['estimated_distance_meters']
        ?? 0
    );
    $estimatedPrice = specialToPositiveFloat(
        $furnitureRequest['estimated_price']
        ?? $problemDetails['estimated_price']
        ?? 0
    );
    if ($estimatedPrice <= 0 && $orderTotalAmount > 0) {
        $estimatedPrice = $orderTotalAmount;
    }

    $finalPrice = null;
    $furnitureFinalPrice = specialToPositiveFloat($furnitureRequest['final_price'] ?? 0);
    if ($specialStatus === 'completed') {
        if ($furnitureFinalPrice > 0) {
            $finalPrice = $furnitureFinalPrice;
        } elseif ($orderTotalAmount > 0) {
            $finalPrice = $orderTotalAmount;
        }
    }

    $detailsPayload = [
        'source' => 'mobile_order',
        'source_order_id' => (int) $orderId,
        'module' => $module !== '' ? $module : 'furniture_moving',
        'problem_details' => $problemDetails,
    ];
    $detailsJson = json_encode($detailsPayload, JSON_UNESCAPED_UNICODE);

    $insertData = [
        'request_number' => specialGenerateRequestNumber('FM', 'furniture_requests'),
        'user_id' => $userId,
        'service_id' => $furnitureServiceId > 0 ? $furnitureServiceId : null,
        'area_id' => $areaId > 0 ? $areaId : null,
        'area_name' => $areaName !== '' ? $areaName : null,
        'customer_name' => $customerName,
        'phone' => $customerPhone,
        'pickup_city' => trim((string) ($furnitureRequest['pickup_city'] ?? '')),
        'pickup_address' => trim((string) ($furnitureRequest['pickup_address'] ?? $address ?? '')),
        'dropoff_city' => trim((string) ($furnitureRequest['dropoff_city'] ?? '')),
        'dropoff_address' => trim((string) ($furnitureRequest['dropoff_address'] ?? '')),
        'move_date' => specialNormalizeDateValue($furnitureRequest['move_date'] ?? ($orderRow['scheduled_date'] ?? null)),
        'preferred_time' => trim((string) ($furnitureRequest['preferred_time'] ?? ($orderRow['scheduled_time'] ?? ''))),
        'rooms_count' => max(1, (int) ($furnitureRequest['rooms_count'] ?? ($problemDetails['rooms_count'] ?? 1))),
        'floors_from' => max(0, (int) ($furnitureRequest['floors_from'] ?? ($problemDetails['floors_from'] ?? 0))),
        'floors_to' => max(0, (int) ($furnitureRequest['floors_to'] ?? ($problemDetails['floors_to'] ?? 0))),
        'elevator_from' => !empty($furnitureRequest['elevator_from']) ? 1 : 0,
        'elevator_to' => !empty($furnitureRequest['elevator_to']) ? 1 : 0,
        'needs_packing' => !empty($furnitureRequest['needs_packing']) ? 1 : 0,
        'estimated_items' => max(0, (int) ($furnitureRequest['estimated_items'] ?? ($problemDetails['estimated_items'] ?? 0))),
        'details_json' => $detailsJson,
        'notes' => trim((string) ($furnitureRequest['notes'] ?? $notes ?? '')),
        'status' => $specialStatus,
        'estimated_price' => $estimatedPrice > 0 ? $estimatedPrice : null,
        'final_price' => $finalPrice,
        'estimated_weight_kg' => $estimatedWeightKg > 0 ? $estimatedWeightKg : null,
        'estimated_distance_meters' => $estimatedDistanceMeters > 0 ? $estimatedDistanceMeters : null,
        'source_order_id' => $orderId,
    ];

    db()->insert('furniture_requests', $insertData);
}

/**
 * Fetch order-specific requested spare parts.
 */
function fetchOrderRequestedSpareParts($orderId)
{
    global $conn;

    ensureOrderSparePartsSchema();

    if (!tableExists('order_spare_parts')) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT osp.*, s.name_ar AS store_name
         FROM order_spare_parts osp
         LEFT JOIN stores s ON osp.store_id = s.id
         WHERE osp.order_id = ?
         ORDER BY osp.id ASC"
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'provider_id' => !empty($row['provider_id']) ? (int) $row['provider_id'] : null,
            'store_id' => !empty($row['store_id']) ? (int) $row['store_id'] : null,
            'store_name' => $row['store_name'] ?? null,
            'spare_part_id' => !empty($row['spare_part_id']) ? (int) $row['spare_part_id'] : null,
            'name' => $row['spare_part_name'],
            'quantity' => (int) $row['quantity'],
            'pricing_mode' => normalizeSparePricingMode($row['pricing_mode'] ?? null) ?? 'with_installation',
            'requires_installation' => (int) ($row['requires_installation'] ?? 1) === 1,
            'unit_price' => (float) $row['unit_price'],
            'total_price' => (float) $row['total_price'],
            'notes' => $row['notes'] ?? null,
            'is_committed' => (int) ($row['is_committed'] ?? 0) === 1,
            'committed_at' => $row['committed_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    return $items;
}

/**
 * Commit requested spare parts to inventory and store accounting when client approves invoice.
 */
function commitRequestedSparePartsForOrder($orderId)
{
    global $conn;

    ensureOrderSparePartsSchema();

    if (!tableExists('order_spare_parts')) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT *
         FROM order_spare_parts
         WHERE order_id = ? AND is_committed = 0
         ORDER BY id ASC
         FOR UPDATE"
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        return;
    }

    foreach ($rows as $requestedPart) {
        $sparePartId = (int) ($requestedPart['spare_part_id'] ?? 0);
        $quantity = (int) ($requestedPart['quantity'] ?? 0);
        if ($sparePartId <= 0 || $quantity <= 0) {
            continue;
        }

        $spareStmt = $conn->prepare(
            "SELECT id, store_id, name_ar, price, price_with_installation, price_without_installation, stock_quantity, is_active
             FROM spare_parts
             WHERE id = ?
             FOR UPDATE"
        );
        $spareStmt->bind_param("i", $sparePartId);
        $spareStmt->execute();
        $sparePart = $spareStmt->get_result()->fetch_assoc();

        if (!$sparePart) {
            throw new RuntimeException('قطعة غيار مطلوبة غير موجودة (#' . $sparePartId . ')');
        }

        if ((int) ($sparePart['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('قطعة الغيار "' . $sparePart['name_ar'] . '" غير مفعلة حالياً');
        }

        $availableQty = (int) ($sparePart['stock_quantity'] ?? 0);
        if ($quantity > $availableQty) {
            throw new RuntimeException('المخزون لا يكفي لقطعة "' . $sparePart['name_ar'] . '"');
        }

        $pricingMode = normalizeSparePricingMode($requestedPart['pricing_mode'] ?? null) ?? 'with_installation';
        $requiresInstallation = array_key_exists('requires_installation', $requestedPart)
            ? (((int) $requestedPart['requires_installation']) === 1 ? 1 : 0)
            : ($pricingMode !== 'without_installation' ? 1 : 0);
        $unitPrice = (float) ($requestedPart['unit_price'] ?? 0);
        if ($unitPrice <= 0) {
            $unitPrice = resolveSparePartUnitPrice($sparePart, $pricingMode);
        }
        $lineTotal = $unitPrice * $quantity;
        $storeId = !empty($requestedPart['store_id']) ? (int) $requestedPart['store_id'] : (int) ($sparePart['store_id'] ?? 0);
        $partName = trim((string) ($requestedPart['spare_part_name'] ?? ''));
        if ($partName === '') {
            $partName = (string) ($sparePart['name_ar'] ?? ('قطعة #' . $sparePartId));
        }

        $newStock = $availableQty - $quantity;
        $updateStock = $conn->prepare("UPDATE spare_parts SET stock_quantity = ? WHERE id = ?");
        $updateStock->bind_param("ii", $newStock, $sparePartId);
        if (!$updateStock->execute()) {
            throw new RuntimeException('فشل تحديث مخزون قطعة الغيار #' . $sparePartId);
        }

        $updateRequested = $conn->prepare(
            "UPDATE order_spare_parts
             SET store_id = ?, spare_part_name = ?, pricing_mode = ?, requires_installation = ?, unit_price = ?, total_price = ?
             WHERE id = ?"
        );
        $updateRequested->bind_param(
            "issiddi",
            $storeId,
            $partName,
            $pricingMode,
            $requiresInstallation,
            $unitPrice,
            $lineTotal,
            $requestedPart['id']
        );
        $updateRequested->execute();

        $movementId = null;
        if ($storeId > 0 && tableExists('store_spare_part_movements')) {
            $movementNotes = 'صرف لطلب #' . $orderId;
            $extraNotes = trim((string) ($requestedPart['notes'] ?? ''));
            if ($extraNotes !== '') {
                $movementNotes .= ' - ' . $extraNotes;
            }

            $insertMovement = $conn->prepare(
                "INSERT INTO store_spare_part_movements
                (store_id, spare_part_id, movement_type, quantity, unit_price, notes)
                VALUES (?, ?, 'withdrawal', ?, ?, ?)"
            );
            $insertMovement->bind_param(
                "iiids",
                $storeId,
                $sparePartId,
                $quantity,
                $unitPrice,
                $movementNotes
            );
            $insertMovement->execute();
            $movementId = (int) $conn->insert_id;
        }

        if ($storeId > 0 && tableExists('store_account_entries')) {
            $accountNotes = 'قيمة قطعة لطلب #' . $orderId . ' - ' . $partName . ' × ' . $quantity;
            $referenceId = $movementId;

            $insertAccount = $conn->prepare(
                "INSERT INTO store_account_entries
                (store_id, entry_type, amount, source, notes, reference_id)
                VALUES (?, 'credit', ?, 'withdrawal', ?, ?)"
            );
            $insertAccount->bind_param(
                "idsi",
                $storeId,
                $lineTotal,
                $accountNotes,
                $referenceId
            );
            $insertAccount->execute();
        }
    }

    $markCommitted = $conn->prepare(
        "UPDATE order_spare_parts
         SET is_committed = 1, committed_at = NOW()
         WHERE order_id = ? AND is_committed = 0"
    );
    $markCommitted->bind_param("i", $orderId);
    $markCommitted->execute();
}

/**
 * Infer mysqli bind type from value.
 */
function inferBindTypeByColumn($column, $value)
{
    $intColumns = ['user_id', 'category_id', 'provider_id', 'service_type_id', 'type_option_id', 'address_id'];
    $floatColumns = ['lat', 'lng', 'total_amount', 'labor_cost', 'parts_cost', 'inspection_fee', 'min_estimate', 'max_estimate'];

    if (in_array($column, $intColumns, true) || is_int($value) || is_bool($value)) {
        return 'i';
    }

    if (in_array($column, $floatColumns, true) || is_float($value)) {
        return 'd';
    }

    return 's';
}

/**
 * Apply lifecycle defaults right after order creation.
 */
function applyOrderLifecycleDefaults($orderId, $scheduledDate, $scheduledTime)
{
    global $conn;

    $updates = [];
    $types = '';
    $values = [];

    if (orderColumnExists('inspection_fee')) {
        $updates[] = 'inspection_fee = ?';
        $types .= 'd';
        $values[] = 0.0; // Free inspection by product policy
    }

    if (orderColumnExists('confirmation_status')) {
        $updates[] = "confirmation_status = 'pending'";
    }

    if (orderColumnExists('confirmation_attempts')) {
        $updates[] = 'confirmation_attempts = 0';
    }

    if (orderColumnExists('confirmation_due_at')) {
        $dueAt = calculateConfirmationDueAt($scheduledDate, $scheduledTime);
        if ($dueAt !== null) {
            $updates[] = 'confirmation_due_at = ?';
            $types .= 's';
            $values[] = $dueAt;
        }
    }

    if (empty($updates)) {
        return;
    }

    $values[] = (int) $orderId;
    $types .= 'i';
    $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
}

/**
 * Calculate default confirmation time (2 hours before appointment).
 */
function calculateConfirmationDueAt($scheduledDate, $scheduledTime)
{
    if (empty($scheduledDate) || empty($scheduledTime)) {
        return null;
    }

    $dateTime = strtotime($scheduledDate . ' ' . $scheduledTime);
    if (!$dateTime) {
        return null;
    }

    $leadHours = getConfirmationLeadHours();
    $dueTimestamp = $dateTime - ($leadHours * 60 * 60);
    return date('Y-m-d H:i:s', $dueTimestamp);
}

/**
 * Lead time in hours before appointment for confirmation calls.
 * Can be controlled from app_settings.confirmation_lead_hours.
 */
function getConfirmationLeadHours()
{
    global $conn;

    static $hours = null;
    if ($hours !== null) {
        return $hours;
    }

    $hours = 2;
    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'confirmation_lead_hours' LIMIT 1");
    if ($stmt && $stmt->execute()) {
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && is_numeric($row['setting_value'])) {
            $parsed = (int) $row['setting_value'];
            if ($parsed >= 1 && $parsed <= 48) {
                $hours = $parsed;
            }
        }
    }

    return $hours;
}

/**
 * Max tolerated no-show count before restricting new bookings.
 */
function getNoShowThreshold()
{
    global $conn;

    static $threshold = null;
    if ($threshold !== null) {
        return $threshold;
    }

    $threshold = 3;
    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'no_show_blacklist_threshold' LIMIT 1");
    if ($stmt && $stmt->execute()) {
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && is_numeric($row['setting_value'])) {
            $parsed = (int) $row['setting_value'];
            if ($parsed >= 1 && $parsed <= 20) {
                $threshold = $parsed;
            }
        }
    }

    return $threshold;
}

/**
 * Check if a table exists.
 */
function tableExists($table)
{
    global $conn;
    $escaped = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$escaped'");
    return $result && $result->num_rows > 0;
}
