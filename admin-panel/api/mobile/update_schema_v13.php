<?php
/**
 * Schema update v13
 * Provider finance ledger:
 * - Extends transactions with provider earning/transfer/deduction/adjustment types.
 * - Backfills provider earnings and commission deductions from completed orders.
 * - Recalculates provider wallet balances and order counters.
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/provider_finance.php';

echo "Preparing provider finance schema...\n";
providerFinanceEnsureSchema();

echo "Syncing provider finance from completed orders...\n";
$result = providerFinanceSyncAllFromOrders();

echo "orders_synced={$result['orders_synced']}\n";
echo "providers_synced={$result['providers_synced']}\n";
echo "Schema update v13 completed.\n";
