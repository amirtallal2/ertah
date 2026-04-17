<?php
/**
 * Ajax Endpoint for Notifications
 */
require_once '../init.php';

// Basic Auth Check
if (function_exists('isLoggedIn') && !isLoggedIn()) {
    if (function_exists('jsonResponse')) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    } else {
        http_response_code(401);
        exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }
}

try {
    // Count pending orders
    $count = db()->count('orders', "status = 'pending'");

    // Get latest pending orders
    $orders = db()->fetchAll("
        SELECT o.id, o.order_number, o.created_at, o.total_amount,
               u.full_name as user_name,
               c.name_ar as service_name, c.icon as service_icon
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN service_categories c ON o.category_id = c.id
        WHERE o.status = 'pending'
        ORDER BY o.created_at DESC
        LIMIT 10
    ");

    $items = [];
    foreach ($orders as $order) {
        $items[] = [
            'id' => $order['id'],
            'title' => 'طلب جديد #' . $order['order_number'],
            'user' => $order['user_name'] ?? 'عميل',
            'service' => ($order['service_icon'] ?? '🔧') . ' ' . ($order['service_name'] ?? 'خدمة'),
            'amount' => function_exists('formatMoney') ? formatMoney($order['total_amount']) : number_format($order['total_amount'], 2),
            'time' => function_exists('timeAgo') ? timeAgo($order['created_at']) : $order['created_at']
        ];
    }

    if (function_exists('jsonResponse')) {
        jsonResponse(['success' => true, 'count' => $count, 'items' => $items]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'count' => $count, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    if (function_exists('jsonResponse')) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
