<?php
/**
 * Apply database schema updates for admin/mobile modules.
 *
 * Usage:
 *   /Applications/XAMPP/xamppfiles/bin/php /path/to/admin-panel/apply_db_updates.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI only\n";
    exit(1);
}

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/special_services.php';
require_once __DIR__ . '/includes/service_areas.php';
require_once __DIR__ . '/includes/spare_parts_scope.php';
require_once __DIR__ . '/includes/store_accounting.php';
require_once __DIR__ . '/includes/complaint_support.php';
require_once __DIR__ . '/includes/provider_finance.php';
require_once __DIR__ . '/includes/inspection_pricing.php';

$executed = [];
$errors = [];
$synced = ['container' => 0, 'furniture' => 0];

$runStep = function (string $name, callable $callback) use (&$executed, &$errors): void {
    try {
        $callback();
        $executed[$name] = 'ok';
    } catch (Throwable $e) {
        $executed[$name] = 'error';
        $errors[$name] = $e->getMessage();
    }
};

$runStep('special_services_schema', function (): void {
    ensureSpecialServicesSchema();
});

$runStep('service_areas_schema', function (): void {
    if (function_exists('serviceAreaEnsureSchema')) {
        serviceAreaEnsureSchema();
    }
    if (function_exists('serviceAreaEnsureServiceLinksSchema')) {
        serviceAreaEnsureServiceLinksSchema();
    }
});

$runStep('spare_parts_scope_schema', function (): void {
    if (function_exists('sparePartScopeEnsureSchema')) {
        sparePartScopeEnsureSchema();
    }
});

$runStep('store_accounting_schema', function (): void {
    if (function_exists('ensureStoreSparePartsAccountingSchema')) {
        ensureStoreSparePartsAccountingSchema();
    }
});

$runStep('complaint_replies_schema', function (): void {
    if (function_exists('complaintSupportEnsureRepliesSchema')) {
        complaintSupportEnsureRepliesSchema();
    }
});

$runStep('provider_finance_schema', function (): void {
    if (function_exists('providerFinanceEnsureSchema')) {
        providerFinanceEnsureSchema();
    }
    if (function_exists('providerFinanceSyncAllFromOrders')) {
        providerFinanceSyncAllFromOrders();
    }
});

$runStep('inspection_pricing_schema', function (): void {
    if (function_exists('inspectionPricingEnsureSchema')) {
        inspectionPricingEnsureSchema();
    }
});

$runStep('special_requests_backfill', function () use (&$synced): void {
    if (function_exists('specialBackfillSpecialRequestsFromOrders')) {
        $synced = specialBackfillSpecialRequestsFromOrders(2000);
    }
});

$checks = [
    'table:container_stores' => (bool) db()->fetch("SHOW TABLES LIKE 'container_stores'"),
    'table:container_store_account_entries' => (bool) db()->fetch("SHOW TABLES LIKE 'container_store_account_entries'"),
    'table:complaint_replies' => (bool) db()->fetch("SHOW TABLES LIKE 'complaint_replies'"),
    'table:transactions' => (bool) db()->fetch("SHOW TABLES LIKE 'transactions'"),
    'column:complaint_replies.message' => (bool) db()->fetch("SHOW COLUMNS FROM `complaint_replies` LIKE 'message'"),
    'column:transactions.provider_id' => (bool) db()->fetch("SHOW COLUMNS FROM `transactions` LIKE 'provider_id'"),
    'column:container_services.store_id' => (bool) db()->fetch("SHOW COLUMNS FROM `container_services` LIKE 'store_id'"),
    'column:container_requests.container_store_id' => (bool) db()->fetch("SHOW COLUMNS FROM `container_requests` LIKE 'container_store_id'"),
    'column:container_requests.source_order_id' => (bool) db()->fetch("SHOW COLUMNS FROM `container_requests` LIKE 'source_order_id'"),
    'column:furniture_requests.source_order_id' => (bool) db()->fetch("SHOW COLUMNS FROM `furniture_requests` LIKE 'source_order_id'"),
    'column:service_categories.inspection_pricing_mode' => (bool) db()->fetch("SHOW COLUMNS FROM `service_categories` LIKE 'inspection_pricing_mode'"),
    'column:services.inspection_pricing_mode' => (bool) db()->fetch("SHOW COLUMNS FROM `services` LIKE 'inspection_pricing_mode'"),
];

$response = [
    'ok' => empty($errors),
    'executed' => $executed,
    'synced' => $synced,
    'checks' => $checks,
    'errors' => $errors,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

exit(empty($errors) ? 0 : 2);
