<?php
/**
 * Provider finance helpers.
 *
 * Uses the admin PDO connection so the same calculations can be reused by
 * admin pages, mobile APIs, and schema update scripts.
 */

if (!function_exists('db')) {
    require_once __DIR__ . '/database.php';
}

function providerFinanceSafeName(string $value): string
{
    return preg_replace('/[^a-zA-Z0-9_]/', '', $value);
}

function providerFinanceTableExists(string $table, bool $forceRefresh = false): bool
{
    static $cache = [];

    $safeTable = providerFinanceSafeName($table);
    if ($safeTable === '') {
        return false;
    }

    if (!$forceRefresh && array_key_exists($safeTable, $cache)) {
        return (bool) $cache[$safeTable];
    }

    try {
        $quoted = db()->getConnection()->quote($safeTable);
        $cache[$safeTable] = !empty(db()->fetch("SHOW TABLES LIKE {$quoted}"));
    } catch (Throwable $e) {
        $cache[$safeTable] = false;
    }

    return (bool) $cache[$safeTable];
}

function providerFinanceColumnExists(string $table, string $column, bool $forceRefresh = false): bool
{
    static $cache = [];

    $safeTable = providerFinanceSafeName($table);
    $safeColumn = providerFinanceSafeName($column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $key = $safeTable . ':' . $safeColumn;
    if (!$forceRefresh && array_key_exists($key, $cache)) {
        return (bool) $cache[$key];
    }

    if (!providerFinanceTableExists($safeTable, $forceRefresh)) {
        $cache[$key] = false;
        return false;
    }

    try {
        $quoted = db()->getConnection()->quote($safeColumn);
        $cache[$key] = !empty(db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quoted}"));
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return (bool) $cache[$key];
}

function providerFinanceIndexExists(string $table, string $indexName, bool $forceRefresh = false): bool
{
    static $cache = [];

    $safeTable = providerFinanceSafeName($table);
    $safeIndex = providerFinanceSafeName($indexName);
    if ($safeTable === '' || $safeIndex === '') {
        return false;
    }

    $key = $safeTable . ':' . $safeIndex;
    if (!$forceRefresh && array_key_exists($key, $cache)) {
        return (bool) $cache[$key];
    }

    if (!providerFinanceTableExists($safeTable, $forceRefresh)) {
        $cache[$key] = false;
        return false;
    }

    try {
        $quoted = db()->getConnection()->quote($safeIndex);
        $cache[$key] = !empty(db()->fetch("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = {$quoted}"));
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return (bool) $cache[$key];
}

function providerFinanceEnsureColumn(string $table, string $column, string $definition): void
{
    $safeTable = providerFinanceSafeName($table);
    $safeColumn = providerFinanceSafeName($column);
    if ($safeTable === '' || $safeColumn === '' || !providerFinanceTableExists($safeTable)) {
        return;
    }

    if (providerFinanceColumnExists($safeTable, $safeColumn, true)) {
        return;
    }

    try {
        db()->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
        providerFinanceColumnExists($safeTable, $safeColumn, true);
    } catch (Throwable $e) {
        error_log('Provider finance add column failed: ' . $safeTable . '.' . $safeColumn . ' ' . $e->getMessage());
    }
}

function providerFinanceEnsureIndex(string $table, string $indexName, array $columns): void
{
    $safeTable = providerFinanceSafeName($table);
    $safeIndex = providerFinanceSafeName($indexName);
    if ($safeTable === '' || $safeIndex === '' || !providerFinanceTableExists($safeTable)) {
        return;
    }

    if (providerFinanceIndexExists($safeTable, $safeIndex, true)) {
        return;
    }

    $safeColumns = [];
    foreach ($columns as $column) {
        $safeColumn = providerFinanceSafeName((string) $column);
        if ($safeColumn !== '' && providerFinanceColumnExists($safeTable, $safeColumn, true)) {
            $safeColumns[] = "`{$safeColumn}`";
        }
    }

    if (empty($safeColumns)) {
        return;
    }

    try {
        db()->query("ALTER TABLE `{$safeTable}` ADD INDEX `{$safeIndex}` (" . implode(', ', $safeColumns) . ")");
        providerFinanceIndexExists($safeTable, $safeIndex, true);
    } catch (Throwable $e) {
        error_log('Provider finance add index failed: ' . $safeIndex . ' ' . $e->getMessage());
    }
}

function providerFinanceEnsureSchema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    try {
        if (!providerFinanceTableExists('transactions', true)) {
            db()->query(
                "CREATE TABLE IF NOT EXISTS `transactions` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `user_id` INT NULL,
                    `provider_id` INT NULL,
                    `order_id` INT NULL,
                    `type` ENUM('deposit','withdrawal','payment','refund','commission','reward','referral_bonus','earning','transfer','deduction','adjustment') NOT NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `balance_after` DECIMAL(10,2) NULL,
                    `description` VARCHAR(255) NULL,
                    `reference_number` VARCHAR(80) NULL,
                    `status` ENUM('pending','completed','failed','cancelled') DEFAULT 'completed',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_transactions_user` (`user_id`),
                    KEY `idx_transactions_provider` (`provider_id`),
                    KEY `idx_transactions_order` (`order_id`),
                    KEY `idx_transactions_reference` (`reference_number`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            providerFinanceTableExists('transactions', true);
        }

        providerFinanceEnsureColumn('transactions', 'user_id', 'INT NULL');
        providerFinanceEnsureColumn('transactions', 'provider_id', 'INT NULL');
        providerFinanceEnsureColumn('transactions', 'order_id', 'INT NULL');
        providerFinanceEnsureColumn('transactions', 'type', "ENUM('deposit','withdrawal','payment','refund','commission','reward','referral_bonus','earning','transfer','deduction','adjustment') NOT NULL");
        providerFinanceEnsureColumn('transactions', 'amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        providerFinanceEnsureColumn('transactions', 'balance_after', 'DECIMAL(10,2) NULL');
        providerFinanceEnsureColumn('transactions', 'description', 'VARCHAR(255) NULL');
        providerFinanceEnsureColumn('transactions', 'reference_number', 'VARCHAR(80) NULL');
        providerFinanceEnsureColumn('transactions', 'status', "ENUM('pending','completed','failed','cancelled') DEFAULT 'completed'");
        providerFinanceEnsureColumn('transactions', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

        $typeColumn = providerFinanceColumnExists('transactions', 'type', true)
            ? db()->fetch("SHOW COLUMNS FROM `transactions` LIKE 'type'")
            : null;
        $typeDefinition = strtolower((string) ($typeColumn['Type'] ?? ''));
        if (
            $typeDefinition !== ''
            && (
                strpos($typeDefinition, 'earning') === false
                || strpos($typeDefinition, 'transfer') === false
                || strpos($typeDefinition, 'deduction') === false
                || strpos($typeDefinition, 'adjustment') === false
            )
        ) {
            db()->query(
                "ALTER TABLE `transactions`
                 MODIFY COLUMN `type` ENUM('deposit','withdrawal','payment','refund','commission','reward','referral_bonus','earning','transfer','deduction','adjustment') NOT NULL"
            );
        }

        $statusColumn = providerFinanceColumnExists('transactions', 'status', true)
            ? db()->fetch("SHOW COLUMNS FROM `transactions` LIKE 'status'")
            : null;
        $statusDefinition = strtolower((string) ($statusColumn['Type'] ?? ''));
        if (
            $statusDefinition !== ''
            && (
                strpos($statusDefinition, 'pending') === false
                || strpos($statusDefinition, 'completed') === false
                || strpos($statusDefinition, 'failed') === false
                || strpos($statusDefinition, 'cancelled') === false
            )
        ) {
            db()->query(
                "ALTER TABLE `transactions`
                 MODIFY COLUMN `status` ENUM('pending','completed','failed','cancelled') DEFAULT 'completed'"
            );
        }

        $referenceColumn = providerFinanceColumnExists('transactions', 'reference_number', true)
            ? db()->fetch("SHOW COLUMNS FROM `transactions` LIKE 'reference_number'")
            : null;
        $referenceDefinition = strtolower((string) ($referenceColumn['Type'] ?? ''));
        if ($referenceDefinition !== '' && preg_match('/varchar\((\d+)\)/', $referenceDefinition, $matches) && (int) $matches[1] < 80) {
            db()->query("ALTER TABLE `transactions` MODIFY COLUMN `reference_number` VARCHAR(80) NULL");
        }

        providerFinanceEnsureIndex('transactions', 'idx_transactions_user', ['user_id']);
        providerFinanceEnsureIndex('transactions', 'idx_transactions_provider', ['provider_id']);
        providerFinanceEnsureIndex('transactions', 'idx_transactions_order', ['order_id']);
        providerFinanceEnsureIndex('transactions', 'idx_transactions_reference', ['reference_number']);

        if (providerFinanceTableExists('providers')) {
            providerFinanceEnsureColumn('providers', 'wallet_balance', 'DECIMAL(10,2) DEFAULT 0.00');
            providerFinanceEnsureColumn('providers', 'commission_rate', 'DECIMAL(5,2) DEFAULT 15.00');
            providerFinanceEnsureColumn('providers', 'total_orders', 'INT DEFAULT 0');
            providerFinanceEnsureColumn('providers', 'completed_orders', 'INT DEFAULT 0');
        }
    } catch (Throwable $e) {
        error_log('Provider finance schema ensure failed: ' . $e->getMessage());
    }
}

function providerFinanceNormalizeStatus(string $status): string
{
    $status = strtolower(trim($status));
    return in_array($status, ['pending', 'completed', 'failed', 'cancelled'], true) ? $status : 'completed';
}

function providerFinanceCreditTypes(): array
{
    return ['deposit', 'refund', 'reward', 'referral_bonus', 'earning'];
}

function providerFinanceDebitTypes(): array
{
    return ['withdrawal', 'payment', 'commission', 'transfer', 'deduction'];
}

function providerFinanceSignedAmount(array $transaction): float
{
    $type = strtolower(trim((string) ($transaction['type'] ?? '')));
    $amount = (float) ($transaction['amount'] ?? 0);

    if ($type === 'adjustment') {
        return round($amount, 2);
    }
    if (in_array($type, providerFinanceDebitTypes(), true)) {
        return -abs($amount);
    }
    if (in_array($type, providerFinanceCreditTypes(), true)) {
        return abs($amount);
    }

    return $amount;
}

function providerFinanceGetSummary(int $providerId): array
{
    providerFinanceEnsureSchema();

    $summary = [
        'available_balance' => 0.0,
        'pending_balance' => 0.0,
        'gross_earnings' => 0.0,
        'completed_earnings' => 0.0,
        'pending_earnings' => 0.0,
        'commission_total' => 0.0,
        'pending_commission' => 0.0,
        'deductions_total' => 0.0,
        'transferred_total' => 0.0,
        'adjustments_total' => 0.0,
        'failed_total' => 0.0,
        'cancelled_total' => 0.0,
        'transaction_count' => 0,
    ];

    if ($providerId <= 0 || !providerFinanceTableExists('transactions')) {
        return $summary;
    }

    $rows = db()->fetchAll(
        "SELECT type, amount, status
         FROM transactions
         WHERE provider_id = ?",
        [$providerId]
    );

    foreach ($rows as $row) {
        $summary['transaction_count']++;

        $status = providerFinanceNormalizeStatus((string) ($row['status'] ?? 'completed'));
        $type = strtolower(trim((string) ($row['type'] ?? '')));
        $amount = abs((float) ($row['amount'] ?? 0));
        $signed = providerFinanceSignedAmount($row);

        if ($status === 'pending') {
            $summary['pending_balance'] += $signed;
        } elseif ($status === 'completed') {
            $summary['available_balance'] += $signed;
        } elseif ($status === 'failed') {
            $summary['failed_total'] += $amount;
        } elseif ($status === 'cancelled') {
            $summary['cancelled_total'] += $amount;
        }

        if ($type === 'earning') {
            $summary['gross_earnings'] += $amount;
            if ($status === 'completed') {
                $summary['completed_earnings'] += $amount;
            } elseif ($status === 'pending') {
                $summary['pending_earnings'] += $amount;
            }
        } elseif ($type === 'commission') {
            if ($status === 'pending') {
                $summary['pending_commission'] += $amount;
            } elseif ($status === 'completed') {
                $summary['commission_total'] += $amount;
            }
        } elseif ($type === 'deduction') {
            if (in_array($status, ['completed', 'pending'], true)) {
                $summary['deductions_total'] += $amount;
            }
        } elseif (in_array($type, ['withdrawal', 'transfer'], true)) {
            if ($status === 'completed') {
                $summary['transferred_total'] += $amount;
            }
        } elseif ($type === 'adjustment' && $status === 'completed') {
            $summary['adjustments_total'] += (float) ($row['amount'] ?? 0);
        }
    }

    foreach ($summary as $key => $value) {
        if (is_float($value)) {
            $summary[$key] = round($value, 2);
        }
    }

    $summary['net_pending_balance'] = round((float) $summary['pending_balance'], 2);
    $summary['net_total_balance'] = round((float) $summary['available_balance'] + (float) $summary['pending_balance'], 2);

    return $summary;
}

function providerFinanceSyncProviderWalletBalance(int $providerId): array
{
    providerFinanceEnsureSchema();

    $summary = providerFinanceGetSummary($providerId);
    if ($providerId > 0 && providerFinanceTableExists('providers') && providerFinanceColumnExists('providers', 'wallet_balance')) {
        try {
            db()->query(
                "UPDATE providers SET wallet_balance = ? WHERE id = ?",
                [(float) $summary['available_balance'], $providerId]
            );
        } catch (Throwable $e) {
            error_log('Provider wallet sync failed: ' . $e->getMessage());
        }
    }

    return $summary;
}

function providerFinanceResolveOrderGross(array $order): float
{
    $gross = (float) ($order['total_amount'] ?? 0);
    if ($gross > 0) {
        return round($gross, 2);
    }

    $subtotal = (float) ($order['subtotal_amount'] ?? 0);
    if ($subtotal > 0) {
        return round($subtotal, 2);
    }

    $gross = (float) ($order['labor_cost'] ?? 0)
        + (float) ($order['parts_cost'] ?? 0)
        + (float) ($order['service_fee'] ?? 0)
        + (float) ($order['inspection_fee'] ?? 0);

    return round(max(0, $gross), 2);
}

function providerFinanceOrderIsPaid(array $order): bool
{
    return strtolower(trim((string) ($order['payment_status'] ?? ''))) === 'paid';
}

function providerFinanceUpsertTransaction(
    int $providerId,
    ?int $orderId,
    string $type,
    float $amount,
    string $description,
    string $referenceNumber,
    string $status = 'completed'
): void {
    providerFinanceEnsureSchema();

    if ($providerId <= 0 || $referenceNumber === '' || !providerFinanceTableExists('transactions')) {
        return;
    }

    $status = providerFinanceNormalizeStatus($status);
    $type = strtolower(trim($type));
    $amount = round($amount, 2);
    $description = mb_substr(trim($description), 0, 255);

    $existing = db()->fetch(
        "SELECT id FROM transactions
         WHERE provider_id = ? AND reference_number = ?
         LIMIT 1",
        [$providerId, $referenceNumber]
    );

    if ($existing) {
        db()->query(
            "UPDATE transactions
             SET order_id = ?, type = ?, amount = ?, description = ?, status = ?
             WHERE id = ?",
            [$orderId, $type, $amount, $description, $status, (int) $existing['id']]
        );
        return;
    }

    db()->query(
        "INSERT INTO transactions
            (provider_id, order_id, type, amount, description, reference_number, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$providerId, $orderId, $type, $amount, $description, $referenceNumber, $status]
    );
}

function providerFinanceSyncOrder(int $orderId): bool
{
    providerFinanceEnsureSchema();

    if ($orderId <= 0 || !providerFinanceTableExists('orders') || !providerFinanceTableExists('providers')) {
        return false;
    }

    $order = db()->fetch(
        "SELECT o.*, p.commission_rate AS provider_commission_rate
         FROM orders o
         LEFT JOIN providers p ON p.id = o.provider_id
         WHERE o.id = ?
         LIMIT 1",
        [$orderId]
    );

    if (!$order) {
        return false;
    }

    $providerId = (int) ($order['provider_id'] ?? 0);
    if ($providerId <= 0) {
        return false;
    }

    if (strtolower(trim((string) ($order['status'] ?? ''))) !== 'completed') {
        providerFinanceSyncProviderWalletBalance($providerId);
        providerFinanceSyncProviderOrderStats($providerId);
        return false;
    }

    $grossAmount = providerFinanceResolveOrderGross($order);
    if ($grossAmount <= 0) {
        providerFinanceSyncProviderWalletBalance($providerId);
        providerFinanceSyncProviderOrderStats($providerId);
        return false;
    }

    $commissionRate = (float) ($order['provider_commission_rate'] ?? 0);
    $commissionRate = max(0, min(100, $commissionRate));
    $commissionAmount = round($grossAmount * ($commissionRate / 100), 2);
    $status = providerFinanceOrderIsPaid($order) ? 'completed' : 'pending';
    $orderNumber = trim((string) ($order['order_number'] ?? ''));
    $orderLabel = $orderNumber !== '' ? $orderNumber : (string) $orderId;

    providerFinanceUpsertTransaction(
        $providerId,
        $orderId,
        'earning',
        $grossAmount,
        'مستحق مقدم الخدمة عن الطلب #' . $orderLabel,
        'provider_earning_order_' . $orderId,
        $status
    );

    if ($commissionAmount > 0) {
        providerFinanceUpsertTransaction(
            $providerId,
            $orderId,
            'commission',
            $commissionAmount,
            'خصم عمولة المنصة (' . number_format($commissionRate, 2) . '%) للطلب #' . $orderLabel,
            'provider_commission_order_' . $orderId,
            $status
        );
    } else {
        providerFinanceUpsertTransaction(
            $providerId,
            $orderId,
            'commission',
            0,
            'لا توجد عمولة للطلب #' . $orderLabel,
            'provider_commission_order_' . $orderId,
            'cancelled'
        );
    }

    providerFinanceSyncProviderWalletBalance($providerId);
    providerFinanceSyncProviderOrderStats($providerId);
    return true;
}

function providerFinanceSyncProviderOrderStats(int $providerId): void
{
    if (
        $providerId <= 0
        || !providerFinanceTableExists('providers')
        || !providerFinanceTableExists('orders')
    ) {
        return;
    }

    $updates = [];
    $params = [];

    if (providerFinanceColumnExists('providers', 'total_orders')) {
        $total = db()->fetch(
            "SELECT COUNT(*) AS total FROM orders WHERE provider_id = ?",
            [$providerId]
        );
        $updates[] = 'total_orders = ?';
        $params[] = (int) ($total['total'] ?? 0);
    }

    if (providerFinanceColumnExists('providers', 'completed_orders')) {
        $completed = db()->fetch(
            "SELECT COUNT(*) AS total FROM orders WHERE provider_id = ? AND status = 'completed'",
            [$providerId]
        );
        $updates[] = 'completed_orders = ?';
        $params[] = (int) ($completed['total'] ?? 0);
    }

    if (empty($updates)) {
        return;
    }

    $params[] = $providerId;
    db()->query("UPDATE providers SET " . implode(', ', $updates) . " WHERE id = ?", $params);
}

function providerFinanceSyncAllFromOrders(): array
{
    providerFinanceEnsureSchema();

    $result = [
        'orders_synced' => 0,
        'providers_synced' => 0,
    ];

    if (!providerFinanceTableExists('orders')) {
        return $result;
    }

    $orders = db()->fetchAll(
        "SELECT id
         FROM orders
         WHERE provider_id IS NOT NULL
           AND provider_id > 0
           AND status = 'completed'
         ORDER BY id ASC"
    );

    foreach ($orders as $order) {
        if (providerFinanceSyncOrder((int) ($order['id'] ?? 0))) {
            $result['orders_synced']++;
        }
    }

    if (providerFinanceTableExists('providers')) {
        $providers = db()->fetchAll("SELECT id FROM providers ORDER BY id ASC");
        foreach ($providers as $provider) {
            $providerId = (int) ($provider['id'] ?? 0);
            if ($providerId <= 0) {
                continue;
            }
            providerFinanceSyncProviderWalletBalance($providerId);
            providerFinanceSyncProviderOrderStats($providerId);
            $result['providers_synced']++;
        }
    }

    return $result;
}

function providerFinanceGetProviderTransactions(int $providerId, int $limit = 20): array
{
    providerFinanceEnsureSchema();

    if ($providerId <= 0 || !providerFinanceTableExists('transactions')) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $orderJoin = providerFinanceTableExists('orders')
        ? 'LEFT JOIN orders o ON o.id = t.order_id'
        : '';
    $orderSelect = providerFinanceTableExists('orders')
        ? ', o.order_number'
        : ', NULL AS order_number';

    $rows = db()->fetchAll(
        "SELECT t.*{$orderSelect}
         FROM transactions t
         {$orderJoin}
         WHERE t.provider_id = ?
         ORDER BY t.created_at DESC, t.id DESC
         LIMIT {$limit}",
        [$providerId]
    );

    foreach ($rows as &$row) {
        $row['signed_amount'] = providerFinanceSignedAmount($row);
    }
    unset($row);

    return $rows;
}

function providerFinanceCreateManualMovement(
    int $providerId,
    string $movementType,
    float $amount,
    string $description,
    string $status = 'completed'
): void {
    providerFinanceEnsureSchema();

    $providerId = (int) $providerId;
    $amount = round(abs($amount), 2);
    if ($providerId <= 0 || $amount <= 0) {
        throw new InvalidArgumentException('قيمة الحركة المالية غير صالحة');
    }

    $status = providerFinanceNormalizeStatus($status);
    $movementType = strtolower(trim($movementType));
    $type = 'adjustment';
    $storedAmount = $amount;
    $defaultDescription = 'تسوية مالية لمقدم الخدمة';

    if ($movementType === 'transfer') {
        $type = 'transfer';
        $defaultDescription = 'تحويل أموال لمقدم الخدمة';
        $status = 'completed';
    } elseif ($movementType === 'deduction') {
        $type = 'deduction';
        $defaultDescription = 'خصم من حساب مقدم الخدمة';
        $status = 'completed';
    } elseif ($movementType === 'earning') {
        $type = 'earning';
        $defaultDescription = 'إضافة مستحق يدوي لمقدم الخدمة';
        $status = 'completed';
    } elseif ($movementType === 'earning_pending') {
        $type = 'earning';
        $defaultDescription = 'إضافة مستحق معلق لمقدم الخدمة';
        $status = 'pending';
    } elseif ($movementType === 'adjustment_out') {
        $type = 'adjustment';
        $storedAmount = -$amount;
        $defaultDescription = 'تسوية مدينة على مقدم الخدمة';
        $status = 'completed';
    } elseif ($movementType === 'adjustment_in') {
        $type = 'adjustment';
        $defaultDescription = 'تسوية دائنة لمقدم الخدمة';
        $status = 'completed';
    } else {
        throw new InvalidArgumentException('نوع الحركة المالية غير صحيح');
    }

    $description = trim($description) !== '' ? trim($description) : $defaultDescription;
    $reference = 'provider_manual_' . $movementType . '_' . $providerId . '_' . date('YmdHis') . '_' . random_int(1000, 9999);

    providerFinanceUpsertTransaction(
        $providerId,
        null,
        $type,
        $storedAmount,
        $description,
        $reference,
        $status
    );

    providerFinanceSyncProviderWalletBalance($providerId);
}

function providerFinanceTransactionTypeLabel(string $type): string
{
    $labels = [
        'deposit' => 'إيداع',
        'withdrawal' => 'سحب',
        'payment' => 'دفع',
        'refund' => 'استرجاع',
        'commission' => 'عمولة / خصم',
        'reward' => 'مكافأة',
        'referral_bonus' => 'مكافأة إحالة',
        'earning' => 'مستحق مقدم خدمة',
        'transfer' => 'تحويل أموال',
        'deduction' => 'خصم',
        'adjustment' => 'تسوية',
    ];

    return $labels[$type] ?? $type;
}

function providerFinanceTransactionStatusLabel(string $status): string
{
    $labels = [
        'pending' => 'معلق',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
        'cancelled' => 'ملغي',
    ];

    return $labels[$status] ?? $status;
}
