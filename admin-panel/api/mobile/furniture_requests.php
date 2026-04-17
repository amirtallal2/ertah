<?php
/**
 * Mobile API - Furniture Requests
 * طلبات نقل العفش (مخصصة)
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
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/special_services.php';
require_once __DIR__ . '/../../includes/notification_service.php';

ensureSpecialServicesSchema();

$action = $_GET['action'] ?? 'config';
$method = $_SERVER['REQUEST_METHOD'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

switch ($action) {
    case 'config':
        getFurnitureRequestConfig();
        break;
    case 'create':
        if ($method !== 'POST') {
            sendError('Method not allowed', 405);
        }
        createFurnitureRequest($input);
        break;
    case 'list':
        getUserFurnitureRequests();
        break;
    case 'detail':
        getUserFurnitureRequestDetail();
        break;
    default:
        sendError('Invalid action', 400);
}

function getFurnitureRequestConfig(): void
{
    $services = [];
    if (specialServiceTableExists('furniture_services')) {
        $rows = db()->fetchAll(
            "SELECT id, name_ar, name_en, name_ur, description_ar, description_en, description_ur,
                    base_price, price_per_kg, price_per_meter, minimum_charge,
                    price_note, estimated_duration_hours, image
             FROM furniture_services
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );

        foreach ($rows as $row) {
            $nameAr = $row['name_ar'] ?? '';
            $nameEn = $row['name_en'] ?? '';
            if ($nameEn === '') {
                $nameEn = $nameAr;
            }
            $nameUr = $row['name_ur'] ?? '';
            if ($nameUr === '') {
                $nameUr = $nameEn !== '' ? $nameEn : $nameAr;
            }
            $descriptionAr = $row['description_ar'] ?? '';
            $descriptionEn = $row['description_en'] ?? '';
            if ($descriptionEn === '') {
                $descriptionEn = $descriptionAr;
            }
            $descriptionUr = $row['description_ur'] ?? '';
            if ($descriptionUr === '') {
                $descriptionUr = $descriptionEn !== '' ? $descriptionEn : $descriptionAr;
            }

            $services[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'name_ur' => $nameUr,
                'description_ar' => $descriptionAr,
                'description_en' => $descriptionEn,
                'description_ur' => $descriptionUr,
                'base_price' => (float) ($row['base_price'] ?? 0),
                'price_per_kg' => (float) ($row['price_per_kg'] ?? 0),
                'price_per_meter' => (float) ($row['price_per_meter'] ?? 0),
                'minimum_charge' => (float) ($row['minimum_charge'] ?? 0),
                'price_note' => $row['price_note'] ?? null,
                'estimated_duration_hours' => (float) ($row['estimated_duration_hours'] ?? 0),
                'image' => !empty($row['image']) ? imageUrl($row['image']) : null,
            ];
        }
    }

    $areas = [];
    if (specialServiceTableExists('furniture_areas')) {
        $rows = db()->fetchAll(
            "SELECT id, name_ar, name_en, name_ur
             FROM furniture_areas
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );

        foreach ($rows as $row) {
            $nameAr = $row['name_ar'] ?? '';
            $nameEn = $row['name_en'] ?? '';
            if ($nameEn === '') {
                $nameEn = $nameAr;
            }
            $nameUr = $row['name_ur'] ?? '';
            if ($nameUr === '') {
                $nameUr = $nameEn !== '' ? $nameEn : $nameAr;
            }
            $areas[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'name_ur' => $nameUr,
            ];
        }
    }

    $fields = getActiveFurnitureRequestFields();

    sendSuccess([
        'special_module' => 'furniture_moving',
        'services' => $services,
        'areas' => $areas,
        'fields' => $fields,
    ]);
}

function getActiveFurnitureRequestFields(): array
{
    if (!specialServiceTableExists('furniture_request_fields')) {
        return [];
    }

    $rows = db()->fetchAll(
        "SELECT id, field_key, label_ar, label_en, label_ur, field_type, placeholder_ar, placeholder_en, placeholder_ur, options_json, is_required
         FROM furniture_request_fields
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC"
    );

    $fields = [];
    foreach ($rows as $row) {
        $options = [];
        if (!empty($row['options_json'])) {
            $decoded = json_decode((string) $row['options_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $value = trim((string) ($item['value'] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $options[] = [
                        'value' => $value,
                        'label_ar' => trim((string) ($item['label_ar'] ?? $value)),
                        'label_en' => trim((string) ($item['label_en'] ?? ($item['label_ar'] ?? $value))),
                        'label_ur' => trim((string) ($item['label_ur'] ?? ($item['label_en'] ?? ($item['label_ar'] ?? $value)))),
                    ];
                }
            }
        }

        $labelAr = $row['label_ar'] ?? '';
        $labelEn = $row['label_en'] ?? '';
        if ($labelEn === '') {
            $labelEn = $labelAr;
        }
        $labelUr = $row['label_ur'] ?? '';
        if ($labelUr === '') {
            $labelUr = $labelEn !== '' ? $labelEn : $labelAr;
        }
        $placeholderAr = $row['placeholder_ar'] ?? '';
        $placeholderEn = $row['placeholder_en'] ?? '';
        if ($placeholderEn === '') {
            $placeholderEn = $placeholderAr;
        }
        $placeholderUr = $row['placeholder_ur'] ?? '';
        if ($placeholderUr === '') {
            $placeholderUr = $placeholderEn !== '' ? $placeholderEn : $placeholderAr;
        }

        $fields[] = [
            'id' => (int) ($row['id'] ?? 0),
            'field_key' => $row['field_key'] ?? '',
            'label_ar' => $labelAr,
            'label_en' => $labelEn,
            'label_ur' => $labelUr,
            'field_type' => $row['field_type'] ?? 'text',
            'placeholder_ar' => $placeholderAr,
            'placeholder_en' => $placeholderEn,
            'placeholder_ur' => $placeholderUr,
            'is_required' => !empty($row['is_required']),
            'options' => $options,
        ];
    }

    return $fields;
}

function furnitureResolveOrderCategoryId(): ?int
{
    static $resolved = false;
    static $categoryId = null;

    if ($resolved) {
        return $categoryId;
    }
    $resolved = true;

    if (!specialServiceTableExists('service_categories')) {
        return null;
    }

    $preferredId = specialEnsureFurnitureCategoryId();
    if (($preferredId ?? 0) > 0) {
        $categoryId = (int) $preferredId;
        return $categoryId;
    }

    $fallback = db()->fetch(
        "SELECT id
         FROM service_categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC
         LIMIT 1"
    );
    if (!empty($fallback['id'])) {
        $categoryId = (int) $fallback['id'];
    }

    return $categoryId;
}

function furnitureMapRequestStatusToOrderStatus(string $status): string
{
    return match (strtolower(trim($status))) {
        'in_progress' => 'in_progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => 'pending',
    };
}

function ensureOrderMirrorForFurnitureRequest(array $requestRow, array $details = []): ?int
{
    if (!specialServiceTableExists('orders')) {
        return null;
    }

    $requestId = (int) ($requestRow['id'] ?? 0);
    $userId = (int) ($requestRow['user_id'] ?? 0);
    if ($requestId <= 0 || $userId <= 0) {
        return null;
    }

    $sourceOrderId = (int) ($requestRow['source_order_id'] ?? 0);
    if ($sourceOrderId > 0) {
        $orderExists = db()->fetch('SELECT id FROM orders WHERE id = ? LIMIT 1', [$sourceOrderId]);
        if (!empty($orderExists['id'])) {
            return $sourceOrderId;
        }
    }

    $categoryId = furnitureResolveOrderCategoryId();
    if (($categoryId ?? 0) <= 0) {
        return null;
    }

    $serviceName = trim((string) ($requestRow['service_name'] ?? ''));
    if ($serviceName === '') {
        $serviceName = 'نقل العفش';
    }

    $problemDetails = [
        'type' => 'furniture_moving',
        'module' => 'furniture_moving',
        'is_custom_service' => true,
        'custom_service' => [
            'title' => $serviceName,
            'description' => trim((string) ($requestRow['notes'] ?? '')),
        ],
        'furniture_request' => [
            'request_id' => $requestId,
            'request_number' => $requestRow['request_number'] ?? '',
            'service_id' => !empty($requestRow['service_id']) ? (int) $requestRow['service_id'] : null,
            'service_name' => $serviceName,
            'area_id' => !empty($requestRow['area_id']) ? (int) $requestRow['area_id'] : null,
            'area_name' => $requestRow['area_name'] ?? null,
            'pickup_city' => $requestRow['pickup_city'] ?? null,
            'pickup_address' => $requestRow['pickup_address'] ?? '',
            'dropoff_city' => $requestRow['dropoff_city'] ?? null,
            'dropoff_address' => $requestRow['dropoff_address'] ?? '',
            'move_date' => $requestRow['move_date'] ?? null,
            'preferred_time' => $requestRow['preferred_time'] ?? null,
            'rooms_count' => isset($requestRow['rooms_count']) ? (int) $requestRow['rooms_count'] : null,
            'floors_from' => isset($requestRow['floors_from']) ? (int) $requestRow['floors_from'] : null,
            'floors_to' => isset($requestRow['floors_to']) ? (int) $requestRow['floors_to'] : null,
            'elevator_from' => !empty($requestRow['elevator_from']),
            'elevator_to' => !empty($requestRow['elevator_to']),
            'needs_packing' => !empty($requestRow['needs_packing']),
            'estimated_items' => isset($requestRow['estimated_items']) ? (int) $requestRow['estimated_items'] : null,
            'estimated_weight_kg' => isset($requestRow['estimated_weight_kg']) ? (float) $requestRow['estimated_weight_kg'] : null,
            'estimated_distance_meters' => isset($requestRow['estimated_distance_meters']) ? (float) $requestRow['estimated_distance_meters'] : null,
            'estimated_price' => isset($requestRow['estimated_price']) ? (float) $requestRow['estimated_price'] : null,
            'final_price' => isset($requestRow['final_price']) ? (float) $requestRow['final_price'] : null,
            'notes' => trim((string) ($requestRow['notes'] ?? '')),
            'details' => $details,
        ],
    ];

    $insertData = [
        'order_number' => generateOrderNumber(),
        'user_id' => $userId,
        'category_id' => $categoryId,
        'status' => furnitureMapRequestStatusToOrderStatus((string) ($requestRow['status'] ?? 'new')),
        'total_amount' => isset($requestRow['final_price']) && (float) $requestRow['final_price'] > 0
            ? (float) $requestRow['final_price']
            : (float) ($requestRow['estimated_price'] ?? 0),
    ];

    if (specialServiceColumnExists('orders', 'address')) {
        $insertData['address'] = trim((string) ($requestRow['pickup_address'] ?? ''));
    }
    if (specialServiceColumnExists('orders', 'notes')) {
        $insertData['notes'] = trim((string) ($requestRow['notes'] ?? ''));
    }
    if (specialServiceColumnExists('orders', 'scheduled_date')) {
        $insertData['scheduled_date'] = !empty($requestRow['move_date']) ? $requestRow['move_date'] : null;
    }
    if (specialServiceColumnExists('orders', 'scheduled_time')) {
        $insertData['scheduled_time'] = !empty($requestRow['preferred_time']) ? $requestRow['preferred_time'] : null;
    }
    if (specialServiceColumnExists('orders', 'problem_description')) {
        $insertData['problem_description'] = trim((string) ($requestRow['notes'] ?? ''));
    }
    if (specialServiceColumnExists('orders', 'problem_details')) {
        $insertData['problem_details'] = json_encode($problemDetails, JSON_UNESCAPED_UNICODE);
    }

    $orderId = (int) db()->insert('orders', $insertData);

    if (specialServiceColumnExists('furniture_requests', 'source_order_id')) {
        db()->update('furniture_requests', ['source_order_id' => $orderId], 'id = ?', [$requestId]);
    }

    return $orderId > 0 ? $orderId : null;
}

function createFurnitureRequest(array $input): void
{
    $userId = requireAuth();
    $role = getAuthRole();
    if ($role !== 'user') {
        sendError('غير مسموح', 403);
    }

    $user = db()->fetch('SELECT id, full_name, phone FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$user) {
        sendError('المستخدم غير موجود', 404);
    }

    $serviceId = (int) ($input['service_id'] ?? 0);
    $selectedServiceIds = normalizeIntegerArray($input['service_ids'] ?? []);
    if ($serviceId > 0 && !in_array($serviceId, $selectedServiceIds, true)) {
        $selectedServiceIds[] = $serviceId;
    }

    $service = null;
    if ($serviceId > 0) {
        $service = db()->fetch(
            'SELECT id, name_ar, base_price, price_per_kg, price_per_meter, minimum_charge
             FROM furniture_services
             WHERE id = ? AND is_active = 1
             LIMIT 1',
            [$serviceId]
        );
        if (!$service) {
            sendError('الخدمة المختارة غير متاحة', 422);
        }
    }

    $areaId = (int) ($input['area_id'] ?? 0);
    if ($areaId <= 0) {
        sendError('يرجى اختيار المنطقة', 422);
    }

    $area = db()->fetch('SELECT id, name_ar FROM furniture_areas WHERE id = ? AND is_active = 1 LIMIT 1', [$areaId]);
    if (!$area) {
        sendError('المنطقة المختارة غير متاحة', 422);
    }

    $customerName = trim((string) ($input['customer_name'] ?? ''));
    if ($customerName === '') {
        $customerName = trim((string) ($user['full_name'] ?? ''));
    }
    if ($customerName === '') {
        $customerName = 'عميل';
    }

    $phone = trim((string) ($input['phone'] ?? ''));
    if ($phone === '') {
        $phone = trim((string) ($user['phone'] ?? ''));
    }
    if ($phone === '') {
        sendError('رقم الجوال مطلوب', 422);
    }

    $pickupAddress = trim((string) ($input['pickup_address'] ?? ''));
    $dropoffAddress = trim((string) ($input['dropoff_address'] ?? ''));

    if ($pickupAddress === '' || $dropoffAddress === '') {
        sendError('يرجى إدخال عنوان التحميل وعنوان التنزيل', 422);
    }

    $moveDate = trim((string) ($input['move_date'] ?? ''));
    if ($moveDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $moveDate)) {
        sendError('صيغة تاريخ النقل غير صحيحة', 422);
    }

    $detailsInput = $input['details'] ?? [];
    if (is_string($detailsInput)) {
        $decoded = json_decode($detailsInput, true);
        $detailsInput = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($detailsInput)) {
        $detailsInput = [];
    }

    $fieldDefinitions = getActiveFurnitureRequestFields();
    $normalizedDetails = [];

    foreach ($fieldDefinitions as $field) {
        $fieldKey = trim((string) ($field['field_key'] ?? ''));
        if ($fieldKey === '') {
            continue;
        }

        $fieldType = trim((string) ($field['field_type'] ?? 'text'));
        $isRequired = !empty($field['is_required']);
        $labelAr = trim((string) ($field['label_ar'] ?? $fieldKey));
        $value = $detailsInput[$fieldKey] ?? null;

        if ($fieldType === 'checkbox') {
            $boolValue = in_array($value, [1, '1', true, 'true', 'yes', 'on'], true);
            if ($isRequired && !$boolValue) {
                sendError('الحقل مطلوب: ' . $labelAr, 422);
            }
            $normalizedDetails[$fieldKey] = $boolValue;
            continue;
        }

        $textValue = trim((string) ($value ?? ''));
        if ($isRequired && $textValue === '') {
            sendError('الحقل مطلوب: ' . $labelAr, 422);
        }

        if ($fieldType === 'number' && $textValue !== '' && !is_numeric($textValue)) {
            sendError('قيمة غير صحيحة للحقل: ' . $labelAr, 422);
        }

        if ($fieldType === 'select' && $textValue !== '') {
            $allowed = [];
            foreach (($field['options'] ?? []) as $option) {
                $allowed[] = (string) ($option['value'] ?? '');
            }
            if (!empty($allowed) && !in_array($textValue, $allowed, true)) {
                sendError('خيار غير صالح للحقل: ' . $labelAr, 422);
            }
        }

        $normalizedDetails[$fieldKey] = $textValue;
    }

    $roomsCount = max(1, (int) ($input['rooms_count'] ?? ($normalizedDetails['rooms_count'] ?? 1)));
    $floorsFrom = max(0, (int) ($input['floors_from'] ?? ($normalizedDetails['floors_from'] ?? 0)));
    $floorsTo = max(0, (int) ($input['floors_to'] ?? ($normalizedDetails['floors_to'] ?? 0)));
    $estimatedItems = max(0, (int) ($input['estimated_items'] ?? ($normalizedDetails['estimated_items'] ?? 0)));

    $elevatorFrom = !empty($input['elevator_from']) || (!empty($normalizedDetails['elevator_from']));
    $elevatorTo = !empty($input['elevator_to']) || (!empty($normalizedDetails['elevator_to']));
    $needsPacking = !empty($input['needs_packing']) || (!empty($normalizedDetails['needs_packing']));

    $preferredTime = trim((string) ($input['preferred_time'] ?? ($normalizedDetails['preferred_time'] ?? '')));

    $estimatedWeightKg = specialToPositiveFloat($input['estimated_weight_kg'] ?? ($normalizedDetails['estimated_weight_kg'] ?? 0));
    $estimatedDistanceMeters = specialToPositiveFloat($input['estimated_distance_meters'] ?? ($normalizedDetails['estimated_distance_meters'] ?? 0));
    if ($estimatedWeightKg > 0) {
        $normalizedDetails['estimated_weight_kg'] = $estimatedWeightKg;
    }
    if ($estimatedDistanceMeters > 0) {
        $normalizedDetails['estimated_distance_meters'] = $estimatedDistanceMeters;
    }

    $payloadMeta = [
        'selected_service_ids' => $selectedServiceIds,
        'selected_services' => is_array($input['selected_services'] ?? null) ? $input['selected_services'] : [],
    ];

    $estimatedPrice = null;
    if (!empty($selectedServiceIds)) {
        $estimatedPrice = calculateFurnitureEstimatedPrice(
            $selectedServiceIds,
            $estimatedWeightKg,
            $estimatedDistanceMeters
        );
    } elseif (is_array($service) && !empty($service)) {
        $estimatedPrice = specialCalculateFlexiblePrice(
            (float) ($service['base_price'] ?? 0),
            (float) ($service['price_per_kg'] ?? 0),
            (float) ($service['price_per_meter'] ?? 0),
            (float) ($service['minimum_charge'] ?? 0),
            $estimatedWeightKg,
            $estimatedDistanceMeters
        );
    }

    if ($estimatedPrice !== null) {
        $payloadMeta['auto_estimated_price'] = $estimatedPrice;
    }

    $detailsJson = json_encode([
        'fields' => $normalizedDetails,
        'meta' => $payloadMeta,
    ], JSON_UNESCAPED_UNICODE);

    $requestNumber = specialGenerateRequestNumber('FM', 'furniture_requests');

    $requestId = db()->insert('furniture_requests', [
        'request_number' => $requestNumber,
        'user_id' => $userId,
        'service_id' => $serviceId > 0 ? $serviceId : null,
        'area_id' => (int) $area['id'],
        'area_name' => $area['name_ar'] ?? null,
        'customer_name' => $customerName,
        'phone' => $phone,
        'pickup_city' => trim((string) ($input['pickup_city'] ?? ($area['name_ar'] ?? ''))),
        'pickup_address' => $pickupAddress,
        'dropoff_city' => trim((string) ($input['dropoff_city'] ?? '')),
        'dropoff_address' => $dropoffAddress,
        'move_date' => $moveDate !== '' ? $moveDate : null,
        'preferred_time' => $preferredTime !== '' ? $preferredTime : null,
        'rooms_count' => $roomsCount,
        'floors_from' => $floorsFrom,
        'floors_to' => $floorsTo,
        'elevator_from' => $elevatorFrom ? 1 : 0,
        'elevator_to' => $elevatorTo ? 1 : 0,
        'needs_packing' => $needsPacking ? 1 : 0,
        'estimated_items' => $estimatedItems,
        'estimated_weight_kg' => $estimatedWeightKg > 0 ? $estimatedWeightKg : null,
        'estimated_distance_meters' => $estimatedDistanceMeters > 0 ? $estimatedDistanceMeters : null,
        'details_json' => $detailsJson,
        'notes' => trim((string) ($input['notes'] ?? '')),
        'status' => 'new',
        'estimated_price' => $estimatedPrice,
    ]);

    $createdRequest = db()->fetch(
        "SELECT r.*, s.name_ar AS service_name
         FROM furniture_requests r
         LEFT JOIN furniture_services s ON s.id = r.service_id
         WHERE r.id = ?
         LIMIT 1",
        [$requestId]
    );
    $linkedOrderId = null;
    if ($createdRequest) {
        $linkedOrderId = ensureOrderMirrorForFurnitureRequest($createdRequest, [
            'fields' => $normalizedDetails,
            'meta' => $payloadMeta,
        ]);
    }

    // Notify admins about new furniture request
    try {
        ensureNotificationSchema();
        notifyAdminNewFurnitureRequest((int)$requestId, [
            'request_number'  => $requestNumber,
            'customer_name'   => $customerName,
            'phone'           => $phone,
            'pickup_address'  => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'move_date'       => $moveDate ?: '-',
            'estimated_price' => $estimatedPrice,
        ]);
    } catch (Throwable $notifErr) {
        error_log('Furniture request notification error: ' . $notifErr->getMessage());
    }

    sendSuccess([
        'id' => (int) $requestId,
        'request_number' => $requestNumber,
        'source_order_id' => $linkedOrderId,
        'status' => 'new',
        'status_label' => specialRequestStatusLabel('new'),
        'area_name' => $area['name_ar'] ?? '',
        'estimated_price' => $estimatedPrice,
    ], 'تم إرسال طلب نقل العفش بنجاح');
}

function getUserFurnitureRequests(): void
{
    $userId = requireAuth();

    $rows = db()->fetchAll(
        "SELECT r.*, s.name_ar AS service_name
         FROM furniture_requests r
         LEFT JOIN furniture_services s ON s.id = r.service_id
         WHERE r.user_id = ?
         ORDER BY r.id DESC
         LIMIT 100",
        [$userId]
    );

    $items = [];
    foreach ($rows as $row) {
        $details = [];
        if (!empty($row['details_json'])) {
            $decoded = json_decode((string) $row['details_json'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        $sourceOrderId = ensureOrderMirrorForFurnitureRequest($row, $details);

        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'request_number' => $row['request_number'] ?? '',
            'source_order_id' => $sourceOrderId,
            'service_id' => !empty($row['service_id']) ? (int) $row['service_id'] : null,
            'service_name' => $row['service_name'] ?? null,
            'area_id' => !empty($row['area_id']) ? (int) $row['area_id'] : null,
            'area_name' => $row['area_name'] ?? null,
            'pickup_address' => $row['pickup_address'] ?? '',
            'dropoff_address' => $row['dropoff_address'] ?? '',
            'move_date' => $row['move_date'] ?? null,
            'status' => $row['status'] ?? 'new',
            'status_label' => specialRequestStatusLabel((string) ($row['status'] ?? 'new')),
            'estimated_price' => isset($row['estimated_price']) ? (float) $row['estimated_price'] : null,
            'estimated_weight_kg' => isset($row['estimated_weight_kg']) ? (float) $row['estimated_weight_kg'] : null,
            'estimated_distance_meters' => isset($row['estimated_distance_meters']) ? (float) $row['estimated_distance_meters'] : null,
            'final_price' => isset($row['final_price']) ? (float) $row['final_price'] : null,
            'notes' => $row['notes'] ?? '',
            'details' => $details,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    sendSuccess($items);
}

function getUserFurnitureRequestDetail(): void
{
    $userId = requireAuth();
    $requestId = (int) ($_GET['id'] ?? 0);
    if ($requestId <= 0) {
        sendError('id is required', 422);
    }

    $row = db()->fetch(
        "SELECT r.*, s.name_ar AS service_name
         FROM furniture_requests r
         LEFT JOIN furniture_services s ON s.id = r.service_id
         WHERE r.id = ? AND r.user_id = ?
         LIMIT 1",
        [$requestId, $userId]
    );

    if (!$row) {
        sendError('الطلب غير موجود', 404);
    }

    $details = [];
    if (!empty($row['details_json'])) {
        $decoded = json_decode((string) $row['details_json'], true);
        if (is_array($decoded)) {
            $details = $decoded;
        }
    }

    $sourceOrderId = ensureOrderMirrorForFurnitureRequest($row, $details);

    sendSuccess([
        'id' => (int) ($row['id'] ?? 0),
        'request_number' => $row['request_number'] ?? '',
        'source_order_id' => $sourceOrderId,
        'service_id' => !empty($row['service_id']) ? (int) $row['service_id'] : null,
        'service_name' => $row['service_name'] ?? null,
        'area_id' => !empty($row['area_id']) ? (int) $row['area_id'] : null,
        'area_name' => $row['area_name'] ?? null,
        'customer_name' => $row['customer_name'] ?? '',
        'phone' => $row['phone'] ?? '',
        'pickup_city' => $row['pickup_city'] ?? '',
        'pickup_address' => $row['pickup_address'] ?? '',
        'dropoff_city' => $row['dropoff_city'] ?? '',
        'dropoff_address' => $row['dropoff_address'] ?? '',
        'move_date' => $row['move_date'] ?? null,
        'preferred_time' => $row['preferred_time'] ?? null,
        'rooms_count' => (int) ($row['rooms_count'] ?? 1),
        'floors_from' => (int) ($row['floors_from'] ?? 0),
        'floors_to' => (int) ($row['floors_to'] ?? 0),
        'elevator_from' => !empty($row['elevator_from']),
        'elevator_to' => !empty($row['elevator_to']),
        'needs_packing' => !empty($row['needs_packing']),
        'estimated_items' => (int) ($row['estimated_items'] ?? 0),
        'estimated_weight_kg' => isset($row['estimated_weight_kg']) ? (float) $row['estimated_weight_kg'] : null,
        'estimated_distance_meters' => isset($row['estimated_distance_meters']) ? (float) $row['estimated_distance_meters'] : null,
        'status' => $row['status'] ?? 'new',
        'status_label' => specialRequestStatusLabel((string) ($row['status'] ?? 'new')),
        'estimated_price' => isset($row['estimated_price']) ? (float) $row['estimated_price'] : null,
        'final_price' => isset($row['final_price']) ? (float) $row['final_price'] : null,
        'admin_notes' => $row['admin_notes'] ?? null,
        'notes' => $row['notes'] ?? '',
        'details' => $details,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ]);
}

function normalizeIntegerArray($value): array
{
    $items = [];

    if (is_array($value)) {
        foreach ($value as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $items[$id] = $id;
            }
        }
        return array_values($items);
    }

    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $id = (int) $item;
                if ($id > 0) {
                    $items[$id] = $id;
                }
            }
            return array_values($items);
        }

        $parts = explode(',', $value);
        foreach ($parts as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $items[$id] = $id;
            }
        }
    }

    return array_values($items);
}

function calculateFurnitureEstimatedPrice(array $serviceIds, float $estimatedWeightKg, float $estimatedDistanceMeters): ?float
{
    if (empty($serviceIds) || !specialServiceTableExists('furniture_services')) {
        return null;
    }

    $serviceIds = array_values(array_filter(array_map('intval', $serviceIds), static fn($id) => $id > 0));
    if (empty($serviceIds)) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $rows = db()->fetchAll(
        "SELECT id, base_price, price_per_kg, price_per_meter, minimum_charge
         FROM furniture_services
         WHERE is_active = 1 AND id IN ($placeholders)",
        $serviceIds
    );
    if (empty($rows)) {
        return null;
    }

    $total = 0.0;
    foreach ($rows as $row) {
        $total += specialCalculateFlexiblePrice(
            (float) ($row['base_price'] ?? 0),
            (float) ($row['price_per_kg'] ?? 0),
            (float) ($row['price_per_meter'] ?? 0),
            (float) ($row['minimum_charge'] ?? 0),
            $estimatedWeightKg,
            $estimatedDistanceMeters
        );
    }

    return round($total, 2);
}
