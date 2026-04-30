<?php
/**
 * Inspection pricing helpers.
 */

function inspectionPricingTableExists(string $table): bool
{
    static $cache = [];
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return false;
    }
    if (array_key_exists($safeTable, $cache)) {
        return $cache[$safeTable];
    }
    $quoted = db()->getConnection()->quote($safeTable);
    $cache[$safeTable] = (bool) db()->fetch("SHOW TABLES LIKE {$quoted}");
    return $cache[$safeTable];
}

function inspectionPricingColumnExists(string $table, string $column): bool
{
    static $cache = [];
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !inspectionPricingTableExists($safeTable)) {
        return false;
    }
    $key = $safeTable . '.' . $safeColumn;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $quoted = db()->getConnection()->quote($safeColumn);
    $exists = (bool) db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quoted}");
    if ($exists) {
        $cache[$key] = true;
    }
    return $exists;
}

function inspectionPricingEnsureColumn(string $table, string $column, string $definition): void
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !inspectionPricingTableExists($safeTable)) {
        return;
    }
    if (!inspectionPricingColumnExists($safeTable, $safeColumn)) {
        db()->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    }
}

function inspectionPricingEnsureSchema(): void
{
    if (inspectionPricingTableExists('service_categories')) {
        inspectionPricingEnsureColumn('service_categories', 'inspection_pricing_mode', "VARCHAR(20) NOT NULL DEFAULT 'free'");
        inspectionPricingEnsureColumn('service_categories', 'inspection_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        inspectionPricingEnsureColumn('service_categories', 'inspection_details_ar', 'TEXT NULL');
        inspectionPricingEnsureColumn('service_categories', 'inspection_details_en', 'TEXT NULL');
        inspectionPricingEnsureColumn('service_categories', 'inspection_details_ur', 'TEXT NULL');
    }

    if (inspectionPricingTableExists('services')) {
        inspectionPricingEnsureColumn('services', 'inspection_pricing_mode', "VARCHAR(20) NOT NULL DEFAULT 'inherit'");
        inspectionPricingEnsureColumn('services', 'inspection_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        inspectionPricingEnsureColumn('services', 'inspection_details_ar', 'TEXT NULL');
        inspectionPricingEnsureColumn('services', 'inspection_details_en', 'TEXT NULL');
        inspectionPricingEnsureColumn('services', 'inspection_details_ur', 'TEXT NULL');
    }
}

function inspectionPricingNormalizeMode($value, bool $allowInherit = false): string
{
    $mode = strtolower(trim((string) $value));
    $valid = $allowInherit ? ['inherit', 'free', 'paid'] : ['free', 'paid'];
    if (!in_array($mode, $valid, true)) {
        return $allowInherit ? 'inherit' : 'free';
    }
    return $mode;
}

function inspectionPricingNormalizeFee($value): float
{
    $fee = (float) $value;
    if ($fee < 0) {
        return 0.0;
    }
    return round($fee, 2);
}

function inspectionPricingPolicyFromRow(array $row, string $sourceType, int $sourceId, string $sourceName, string $defaultMode = 'free'): array
{
    $allowInherit = $defaultMode === 'inherit';
    $mode = inspectionPricingNormalizeMode($row['inspection_pricing_mode'] ?? $defaultMode, $allowInherit);
    $fee = inspectionPricingNormalizeFee($row['inspection_fee'] ?? 0);
    if ($mode !== 'paid' || $fee <= 0) {
        $mode = $mode === 'inherit' ? 'inherit' : 'free';
        $fee = 0.0;
    }

    return [
        'mode' => $mode,
        'is_free' => $mode !== 'paid',
        'fee' => $fee,
        'details_ar' => trim((string) ($row['inspection_details_ar'] ?? '')),
        'details_en' => trim((string) ($row['inspection_details_en'] ?? '')),
        'details_ur' => trim((string) ($row['inspection_details_ur'] ?? '')),
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'source_name' => $sourceName,
    ];
}

function inspectionPricingDefaultPolicy(): array
{
    return [
        'mode' => 'free',
        'is_free' => true,
        'fee' => 0.0,
        'details_ar' => '',
        'details_en' => '',
        'details_ur' => '',
        'source_type' => 'default',
        'source_id' => 0,
        'source_name' => '',
    ];
}

function inspectionPricingResolveForOrder(int $categoryId, array $serviceIds = []): array
{
    $categoryPolicy = inspectionPricingDefaultPolicy();

    if (
        $categoryId > 0
        && inspectionPricingTableExists('service_categories')
        && inspectionPricingColumnExists('service_categories', 'inspection_pricing_mode')
    ) {
        $category = db()->fetch(
            "SELECT id, name_ar, inspection_pricing_mode, inspection_fee, inspection_details_ar, inspection_details_en, inspection_details_ur
             FROM service_categories
             WHERE id = ?
             LIMIT 1",
            [$categoryId]
        );
        if ($category) {
            $categoryPolicy = inspectionPricingPolicyFromRow(
                $category,
                'category',
                (int) ($category['id'] ?? $categoryId),
                (string) ($category['name_ar'] ?? ''),
                'free'
            );
        }
    }

    $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds), static fn($id) => $id > 0)));
    if (
        empty($serviceIds)
        || !inspectionPricingTableExists('services')
        || !inspectionPricingColumnExists('services', 'inspection_pricing_mode')
    ) {
        return $categoryPolicy;
    }

    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $services = db()->fetchAll(
        "SELECT id, name_ar, inspection_pricing_mode, inspection_fee, inspection_details_ar, inspection_details_en, inspection_details_ur
         FROM services
         WHERE id IN ({$placeholders})",
        $serviceIds
    );

    $paidPolicies = [];
    $freeOverride = null;
    foreach ($services as $service) {
        $policy = inspectionPricingPolicyFromRow(
            $service,
            'service',
            (int) ($service['id'] ?? 0),
            (string) ($service['name_ar'] ?? ''),
            'inherit'
        );

        if ($policy['mode'] === 'inherit') {
            continue;
        }
        if ($policy['mode'] === 'paid' && $policy['fee'] > 0) {
            $paidPolicies[] = $policy;
            continue;
        }
        if ($freeOverride === null) {
            $freeOverride = $policy;
        }
    }

    if (!empty($paidPolicies)) {
        usort($paidPolicies, static fn($a, $b) => ((float) $b['fee']) <=> ((float) $a['fee']));
        return $paidPolicies[0];
    }

    if ($freeOverride !== null) {
        return $freeOverride;
    }

    return $categoryPolicy;
}
