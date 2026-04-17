<?php
/**
 * Cron: إرسال إشعارات الطلبات المعلقة
 * Run: curl https://yourdomain.com/admin-panel/api/mobile/cron_incomplete_orders.php?key=YOUR_SECRET
 * Or via cron: php /path/to/cron_incomplete_orders.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notification_service.php';

// Simple security check - allow CLI or valid cron key
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    $cronKey = $_GET['key'] ?? '';
    $expectedKey = getNotifSetting('cron_secret_key', 'darfix_cron_2026');
    if ($cronKey !== $expectedKey) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

try {
    $result = notifyIncompleteOrders();
    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Cron incomplete orders error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
