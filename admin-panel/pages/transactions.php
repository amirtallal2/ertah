<?php
/**
 * صفحة المعاملات المالية والتقارير الموحدة
 * Unified Financial Transactions & Reports Page
 */

require_once '../init.php';
requireLogin();
require_once '../includes/store_accounting.php';
require_once '../includes/special_services.php';
require_once '../includes/provider_finance.php';

ensureStoreSparePartsAccountingSchema();
ensureSpecialServicesSchema();
providerFinanceEnsureSchema();

$pageTitle = 'التقارير المالية';
$pageSubtitle = 'سجل موحد وتقارير مالية مع فلاتر وطباعة';

$page = max(1, (int) get('page', 1));
$entityFilter = trim((string) get('entity', 'all'));
$typeFilter = trim((string) get('type'));
$dateFromRaw = trim((string) get('from'));
$dateToRaw = trim((string) get('to'));

$allowedEntities = ['all', 'users', 'providers', 'stores'];
if (!in_array($entityFilter, $allowedEntities, true)) {
    $entityFilter = 'all';
}

$transactionTypes = ['deposit', 'withdrawal', 'payment', 'commission', 'refund', 'reward', 'referral_bonus', 'earning', 'transfer', 'deduction', 'adjustment'];
$storeEntryTypes = ['credit', 'debit'];
$allowedTypes = array_merge($transactionTypes, $storeEntryTypes);
if (!in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = '';
}

function normalizeReportDate($date, $end = false)
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    return $date . ($end ? ' 23:59:59' : ' 00:00:00');
}

$dateFrom = normalizeReportDate($dateFromRaw, false);
$dateTo = normalizeReportDate($dateToRaw, true);

$includeTransactions = $entityFilter !== 'stores';
$includeStores = $entityFilter === 'stores' || $entityFilter === 'all';

$txWhere = '1=1';
$txParams = [];
if ($entityFilter === 'users') {
    $txWhere .= ' AND t.user_id IS NOT NULL';
}
if ($entityFilter === 'providers') {
    $txWhere .= ' AND t.provider_id IS NOT NULL';
}
if ($typeFilter !== '' && in_array($typeFilter, $transactionTypes, true)) {
    $txWhere .= ' AND t.type = ?';
    $txParams[] = $typeFilter;
}
if ($dateFrom) {
    $txWhere .= ' AND t.created_at >= ?';
    $txParams[] = $dateFrom;
}
if ($dateTo) {
    $txWhere .= ' AND t.created_at <= ?';
    $txParams[] = $dateTo;
}

$storeWhere = '1=1';
$storeParams = [];
if ($typeFilter !== '' && in_array($typeFilter, $storeEntryTypes, true)) {
    $storeWhere .= ' AND e.entry_type = ?';
    $storeParams[] = $typeFilter;
}
if ($dateFrom) {
    $storeWhere .= ' AND e.created_at >= ?';
    $storeParams[] = $dateFrom;
}
if ($dateTo) {
    $storeWhere .= ' AND e.created_at <= ?';
    $storeParams[] = $dateTo;
}

$queries = [];
$params = [];

if ($includeTransactions) {
    $queries[] = "
        SELECT
            'transaction' AS source_table,
            t.id,
            t.created_at,
            t.amount,
            t.type AS action_type,
            t.status,
            t.description,
            t.reference_number,
            t.order_id,
            t.user_id,
            t.provider_id,
            NULL AS store_id,
            NULL AS store_type,
            u.full_name AS user_name,
            p.full_name AS provider_name,
            NULL AS store_name,
            NULL AS entry_type,
            NULL AS entry_source
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN providers p ON t.provider_id = p.id
        WHERE {$txWhere}
    ";
    $params = array_merge($params, $txParams);
}

if ($includeStores) {
    $queries[] = "
        SELECT
            'store_account' AS source_table,
            e.id,
            e.created_at,
            e.amount,
            e.entry_type AS action_type,
            NULL AS status,
            e.notes AS description,
            NULL AS reference_number,
            NULL AS order_id,
            NULL AS user_id,
            NULL AS provider_id,
            e.store_id,
            'spare_parts' AS store_type,
            NULL AS user_name,
            NULL AS provider_name,
            s.name_ar AS store_name,
            e.entry_type AS entry_type,
            e.source AS entry_source
        FROM store_account_entries e
        LEFT JOIN stores s ON e.store_id = s.id
        WHERE {$storeWhere}
    ";
    $params = array_merge($params, $storeParams);

    $queries[] = "
        SELECT
            'container_store_account' AS source_table,
            e.id,
            e.created_at,
            e.amount,
            e.entry_type AS action_type,
            NULL AS status,
            e.notes AS description,
            NULL AS reference_number,
            NULL AS order_id,
            NULL AS user_id,
            NULL AS provider_id,
            e.store_id,
            'containers' AS store_type,
            NULL AS user_name,
            NULL AS provider_name,
            cs.name_ar AS store_name,
            e.entry_type AS entry_type,
            e.source AS entry_source
        FROM container_store_account_entries e
        LEFT JOIN container_stores cs ON e.store_id = cs.id
        WHERE {$storeWhere}
    ";
    $params = array_merge($params, $storeParams);
}

$reportRows = [];
$total = 0;
$pagination = paginate(0, $page);

if (!empty($queries)) {
    $unionSql = implode(' UNION ALL ', $queries);
    $countRow = db()->fetch("SELECT COUNT(*) AS total FROM ({$unionSql}) AS report_rows", $params);
    $total = (int) ($countRow['total'] ?? 0);
    $pagination = paginate($total, $page);

    $reportRows = db()->fetchAll(
        "SELECT * FROM ({$unionSql}) AS report_rows\n         ORDER BY created_at DESC\n         LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
        $params
    );
}

$txSummaryRows = [];
$txTotals = ['total_count' => 0, 'total_amount' => 0];
$txEntityRows = [];
if ($includeTransactions) {
    $txTotals = db()->fetch(
        "SELECT COUNT(*) AS total_count, COALESCE(SUM(t.amount), 0) AS total_amount\n         FROM transactions t\n         WHERE {$txWhere}",
        $txParams
    ) ?: $txTotals;

    $txSummaryRows = db()->fetchAll(
        "SELECT t.type, COUNT(*) AS total_count, COALESCE(SUM(t.amount), 0) AS total_amount\n         FROM transactions t\n         WHERE {$txWhere}\n         GROUP BY t.type",
        $txParams
    );

    $txEntityRows = db()->fetchAll(
        "SELECT\n            CASE\n                WHEN t.user_id IS NOT NULL THEN 'users'\n                WHEN t.provider_id IS NOT NULL THEN 'providers'\n                ELSE 'other'\n            END AS entity_type,\n            COUNT(*) AS total_count,\n            COALESCE(SUM(t.amount), 0) AS total_amount\n         FROM transactions t\n         WHERE {$txWhere}\n         GROUP BY entity_type",
        $txParams
    );
}

$storeSummary = ['total_count' => 0, 'credit_total' => 0, 'debit_total' => 0];
$containerSummary = ['total_count' => 0, 'credit_total' => 0, 'debit_total' => 0];
if ($includeStores) {
    $storeSummary = db()->fetch(
        "SELECT\n            COUNT(*) AS total_count,\n            COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) AS credit_total,\n            COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) AS debit_total\n         FROM store_account_entries e\n         WHERE {$storeWhere}",
        $storeParams
    ) ?: $storeSummary;

    $containerSummary = db()->fetch(
        "SELECT\n            COUNT(*) AS total_count,\n            COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) AS credit_total,\n            COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) AS debit_total\n         FROM container_store_account_entries e\n         WHERE {$storeWhere}",
        $storeParams
    ) ?: $containerSummary;
}

$entityLabels = [
    'all' => 'الكل',
    'users' => 'العملاء',
    'providers' => 'مقدمي الخدمة',
    'stores' => 'المتاجر'
];

$typeLabels = [
    '' => 'الكل',
    'deposit' => 'إيداع',
    'withdrawal' => 'سحب',
    'payment' => 'مدفوعات',
    'commission' => 'عمولات',
    'refund' => 'استرجاع',
    'reward' => 'مكافأة',
    'referral_bonus' => 'مكافأة إحالة',
    'earning' => 'مستحق مقدم خدمة',
    'transfer' => 'تحويل أموال',
    'deduction' => 'خصم',
    'adjustment' => 'تسوية',
    'credit' => 'له',
    'debit' => 'عليه'
];

$storeTypeLabels = [
    'spare_parts' => 'متجر قطع غيار',
    'containers' => 'متجر حاويات'
];

$transactionTypeBadges = [
    'deposit' => ['text' => 'إيداع', 'class' => 'success'],
    'withdrawal' => ['text' => 'سحب', 'class' => 'danger'],
    'payment' => ['text' => 'دفع', 'class' => 'warning'],
    'commission' => ['text' => 'عمولة', 'class' => 'info'],
    'refund' => ['text' => 'استرجاع', 'class' => 'primary'],
    'reward' => ['text' => 'مكافأة', 'class' => 'secondary'],
    'referral_bonus' => ['text' => 'مكافأة إحالة', 'class' => 'secondary'],
    'earning' => ['text' => 'مستحق', 'class' => 'success'],
    'transfer' => ['text' => 'تحويل', 'class' => 'primary'],
    'deduction' => ['text' => 'خصم', 'class' => 'danger'],
    'adjustment' => ['text' => 'تسوية', 'class' => 'secondary'],
    'credit' => ['text' => 'له', 'class' => 'success'],
    'debit' => ['text' => 'عليه', 'class' => 'danger']
];

include '../includes/header.php';
?>

<style>
    @media print {
        .sidebar,
        .topbar,
        .no-print,
        .pagination {
            display: none !important;
        }

        .admin-layout {
            display: block !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #e5e7eb !important;
        }
    }
</style>

<div class="card animate-slideUp">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
        <h3 class="card-title"><i class="fas fa-file-invoice-dollar" style="color: var(--primary-color);"></i> التقرير المالي الموحد</h3>
        <div class="no-print" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-outline" onclick="window.print()">
                <i class="fas fa-print"></i>
                طباعة التقرير
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="no-print" style="margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; align-items: end;">
            <div class="form-group">
                <label class="form-label">الفئة</label>
                <select name="entity" class="form-control">
                    <?php foreach ($entityLabels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $entityFilter === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">نوع الحركة</label>
                <select name="type" class="form-control">
                    <?php foreach ($typeLabels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $typeFilter === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($dateFromRaw, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($dateToRaw, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group" style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">تطبيق الفلاتر</button>
                <a href="transactions.php" class="btn btn-outline" style="flex: 1;">مسح</a>
            </div>
        </form>

        <div style="margin-bottom: 20px; padding: 12px; border: 1px dashed #e5e7eb; border-radius: 10px; background: #fafafa;">
            <div style="font-size: 13px; color: #6b7280;">ملخص الفلاتر</div>
            <div style="font-weight: 600;">
                الفئة: <?php echo $entityLabels[$entityFilter] ?? 'الكل'; ?> |
                النوع: <?php echo $typeLabels[$typeFilter] ?? 'الكل'; ?> |
                من: <?php echo $dateFromRaw ?: '-'; ?> |
                إلى: <?php echo $dateToRaw ?: '-'; ?>
            </div>
        </div>

        <?php if ($includeTransactions): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px;">ملخص معاملات العملاء ومقدمي الخدمة</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">إجمالي العمليات</div>
                        <div style="font-size: 20px; font-weight: 700;"><?php echo (int) ($txTotals['total_count'] ?? 0); ?></div>
                    </div>
                    <div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">إجمالي المبالغ</div>
                        <div style="font-size: 20px; font-weight: 700;"><?php echo number_format((float) ($txTotals['total_amount'] ?? 0), 2); ?> ⃁</div>
                    </div>
                    <?php foreach ($txEntityRows as $row): ?>
                        <div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px;">
                            <div style="font-size: 12px; color: #6b7280;">
                                <?php
                                    $entityLabel = $row['entity_type'] === 'users' ? 'العملاء' : ($row['entity_type'] === 'providers' ? 'مقدمي الخدمة' : 'أخرى');
                                    echo $entityLabel;
                                ?>
                            </div>
                            <div style="font-size: 16px; font-weight: 700;"><?php echo number_format((float) $row['total_amount'], 2); ?> ⃁</div>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo (int) $row['total_count']; ?> عملية</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($txSummaryRows)): ?>
                    <div style="margin-top: 14px; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;">
                        <?php foreach ($txSummaryRows as $row): ?>
                            <?php
                                $label = $typeLabels[$row['type']] ?? $row['type'];
                            ?>
                            <div style="padding: 10px; border: 1px solid #f3f4f6; border-radius: 8px; background: #fff;">
                                <div style="font-size: 12px; color: #6b7280;"><?php echo $label; ?></div>
                                <div style="font-size: 15px; font-weight: 700;"><?php echo number_format((float) $row['total_amount'], 2); ?> ⃁</div>
                                <div style="font-size: 12px; color: #6b7280;"><?php echo (int) $row['total_count']; ?> عملية</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($includeStores): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px;">ملخص المتاجر</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">متاجر قطع الغيار</div>
                        <div style="font-size: 16px; font-weight: 700;">له: <?php echo number_format((float) $storeSummary['credit_total'], 2); ?> ⃁</div>
                        <div style="font-size: 16px; font-weight: 700;">عليه: <?php echo number_format((float) $storeSummary['debit_total'], 2); ?> ⃁</div>
                        <div style="font-size: 12px; color: #6b7280;"><?php echo (int) $storeSummary['total_count']; ?> حركة</div>
                    </div>
                    <div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <div style="font-size: 12px; color: #6b7280;">متاجر الحاويات</div>
                        <div style="font-size: 16px; font-weight: 700;">له: <?php echo number_format((float) $containerSummary['credit_total'], 2); ?> ⃁</div>
                        <div style="font-size: 16px; font-weight: 700;">عليه: <?php echo number_format((float) $containerSummary['debit_total'], 2); ?> ⃁</div>
                        <div style="font-size: 12px; color: #6b7280;"><?php echo (int) $containerSummary['total_count']; ?> حركة</div>
                    </div>
                    <div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px;">
                        <?php
                            $allCredit = (float) $storeSummary['credit_total'] + (float) $containerSummary['credit_total'];
                            $allDebit = (float) $storeSummary['debit_total'] + (float) $containerSummary['debit_total'];
                            $net = $allCredit - $allDebit;
                        ?>
                        <div style="font-size: 12px; color: #6b7280;">الإجمالي للمتاجر</div>
                        <div style="font-size: 16px; font-weight: 700;">له: <?php echo number_format($allCredit, 2); ?> ⃁</div>
                        <div style="font-size: 16px; font-weight: 700;">عليه: <?php echo number_format($allDebit, 2); ?> ⃁</div>
                        <div style="font-size: 12px; color: #6b7280;">الصافي: <?php echo number_format($net, 2); ?> ⃁</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($reportRows)): ?>
            <div class="empty-state">
                <h3>لا توجد معاملات</h3>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم العملية</th>
                            <th>الطرف المعني</th>
                            <th>النوع</th>
                            <th>المبلغ</th>
                            <th>الرصيد بعد</th>
                            <th>الطلب</th>
                            <th>المرجع</th>
                            <th>الوصف</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportRows as $row): ?>
                            <tr>
                                <td>#<?php echo (int) $row['id']; ?></td>
                                <td>
                                    <?php if (!empty($row['user_id'])): ?>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars((string) $row['user_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php elseif (!empty($row['provider_id'])): ?>
                                        <i class="fas fa-hard-hat"></i>
                                        <?php echo htmlspecialchars((string) $row['provider_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php elseif (!empty($row['store_id'])): ?>
                                        <i class="fas fa-store"></i>
                                        <?php echo htmlspecialchars((string) ($row['store_name'] ?: ('#' . $row['store_id'])), ENT_QUOTES, 'UTF-8'); ?>
                                        <div style="font-size: 11px; color: #6b7280;">
                                            <?php echo $storeTypeLabels[$row['store_type']] ?? 'متجر'; ?>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $typeKey = $row['action_type'];
                                        $typeInfo = $transactionTypeBadges[$typeKey] ?? ['text' => $typeKey, 'class' => 'secondary'];
                                    ?>
                                    <span class="badge badge-<?php echo $typeInfo['class']; ?>">
                                        <?php echo $typeInfo['text']; ?>
                                    </span>
                                </td>
                                <td style="font-weight: bold; direction: ltr;">
                                    <?php
                                        $displayAmount = (float) $row['amount'];
                                        if (($row['source_table'] ?? '') === 'transaction' && !empty($row['provider_id'])) {
                                            $displayAmount = providerFinanceSignedAmount([
                                                'type' => $row['action_type'] ?? '',
                                                'amount' => $row['amount'] ?? 0,
                                            ]);
                                        }
                                    ?>
                                    <?php echo ($displayAmount > 0 ? '+' : '') . number_format($displayAmount, 2); ?> ⃁
                                </td>
                                <td>
                                    <?php echo $row['balance_after'] ? number_format((float) $row['balance_after'], 2) : '-'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['order_id'])): ?>
                                        <a href="orders.php?action=view&id=<?php echo (int) $row['order_id']; ?>" class="btn btn-outline btn-sm">
                                            #<?php echo htmlspecialchars((string) ($row['order_id']), ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td dir="ltr" style="font-size: 12px;">
                                    <?php if (!empty($row['reference_number'])): ?>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars((string) $row['reference_number'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php elseif (!empty($row['entry_source'])): ?>
                                        <span class="badge badge-secondary">
                                            <?php echo htmlspecialchars((string) $row['entry_source'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 13px; color: #666;">
                                    <?php echo htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['status'])): ?>
                                        <span class="badge <?php echo $row['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo providerFinanceTransactionStatusLabel((string) $row['status']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; white-space: nowrap;">
                                    <?php echo htmlspecialchars((string) $row['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination" style="margin-top: 20px;">
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($typeFilter); ?>&entity=<?php echo urlencode($entityFilter); ?>&from=<?php echo urlencode($dateFromRaw); ?>&to=<?php echo urlencode($dateToRaw); ?>"
                            class="page-link <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
