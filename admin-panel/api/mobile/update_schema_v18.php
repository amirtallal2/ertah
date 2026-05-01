<?php
/**
 * Schema update v18
 * Fixes container orders:
 * - Keeps Containers as a root category.
 * - Removes wrongly persisted regular sub-service rows from container orders.
 * - Creates/syncs container store reviews and account entries.
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/special_services.php';

function v18TableExists(string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    return (bool) db()->fetch('SHOW TABLES LIKE ?', [$safe]);
}

function v18ColumnExists(string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !v18TableExists($safeTable)) {
        return false;
    }
    return (bool) db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE ?", [$safeColumn]);
}

function v18UpdateReferences(string $table, string $column, array $duplicateIds, int $canonicalId): int
{
    if (!v18ColumnExists($table, $column) || empty($duplicateIds) || $canonicalId <= 0) {
        return 0;
    }

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
    $params = array_merge([$canonicalId], $duplicateIds);

    return db()->query(
        "UPDATE `{$safeTable}` SET `{$safeColumn}` = ? WHERE `{$safeColumn}` IN ({$placeholders})",
        $params
    )->rowCount();
}

echo "Preparing container category/store accounting update...\n";

ensureSpecialServicesSchema();

$containerCategoryId = (int) (specialEnsureContainerCategoryId() ?? 0);
$furnitureCategoryId = (int) (specialEnsureFurnitureCategoryId() ?? 0);
echo "canonical_container_category_candidate={$containerCategoryId}\n";
echo "canonical_furniture_category_candidate={$furnitureCategoryId}\n";

if (v18TableExists('service_categories')) {
    $containerCategories = db()->fetchAll(
        "SELECT id, parent_id, name_ar, name_en, icon, image, is_active, sort_order
         FROM service_categories
         WHERE name_ar LIKE ?
            OR name_ar LIKE ?
            OR name_en LIKE ?
         ORDER BY id ASC",
        ['%حاويات%', '%حاوية%', '%container%']
    );

    echo 'container_categories_found=' . count($containerCategories) . "\n";

    if ($containerCategoryId <= 0 && !empty($containerCategories)) {
        $containerCategoryId = (int) ($containerCategories[0]['id'] ?? 0);
    }

    if ($containerCategoryId > 0) {
        specialNormalizeRootServiceCategory($containerCategoryId, 'الحاويات', 'Containers', '📦', 9002);

        $duplicateIds = [];
        foreach ($containerCategories as $category) {
            $id = (int) ($category['id'] ?? 0);
            if ($id > 0 && $id !== $containerCategoryId) {
                $duplicateIds[] = $id;
            }
        }

        echo 'canonical_container_category_id=' . $containerCategoryId . "\n";
        echo 'duplicate_container_category_ids=' . implode(',', $duplicateIds) . "\n";

        if (!empty($duplicateIds)) {
            $referenceUpdates = [
                ['service_categories', 'parent_id'],
                ['services', 'category_id'],
                ['orders', 'category_id'],
                ['offers', 'category_id'],
                ['provider_services', 'category_id'],
                ['problem_detail_options', 'category_id'],
                ['spare_parts', 'category_id'],
            ];

            foreach ($referenceUpdates as [$table, $column]) {
                $updated = v18UpdateReferences($table, $column, $duplicateIds, $containerCategoryId);
                echo "{$table}.{$column}_updated={$updated}\n";
            }

            $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
            try {
                $deleted = db()->query(
                    "DELETE FROM service_categories WHERE id IN ({$placeholders})",
                    $duplicateIds
                )->rowCount();
                echo "duplicate_container_categories_deleted={$deleted}\n";
            } catch (Throwable $e) {
                if (v18ColumnExists('service_categories', 'is_active')) {
                    $deactivated = db()->query(
                        "UPDATE service_categories SET is_active = 0 WHERE id IN ({$placeholders})",
                        $duplicateIds
                    )->rowCount();
                    echo "duplicate_container_categories_deactivated={$deactivated}\n";
                }
                echo 'duplicate_container_categories_delete_error=' . $e->getMessage() . "\n";
            }
        }
    }
}

$removedWrongServiceRows = 0;
if (
    v18TableExists('orders')
    && v18TableExists('order_services')
    && v18ColumnExists('orders', 'problem_details')
    && v18ColumnExists('order_services', 'order_id')
) {
    // الطلبات الخاصة لها جداول مستقلة، لذلك لا نعرض/نحتفظ بصفوف order_services القديمة التي كانت تسبب ظهور "خدمة أخرى".
    $removedWrongServiceRows = db()->query(
        "DELETE os
         FROM order_services os
         INNER JOIN orders o ON o.id = os.order_id
         WHERE (
                o.problem_details LIKE '%\"module\":\"container_rental\"%'
                OR o.problem_details LIKE '%\"type\":\"container_rental\"%'
                OR o.problem_details LIKE '%\"container_request\"%'
                OR o.problem_details LIKE '%\"module\":\"furniture_moving\"%'
                OR o.problem_details LIKE '%\"type\":\"furniture_moving\"%'
                OR o.problem_details LIKE '%\"furniture_request\"%'
           )"
    )->rowCount();
}
echo "special_order_service_rows_removed={$removedWrongServiceRows}\n";

$containerOrdersUpdated = 0;
if (
    $containerCategoryId > 0
    && v18TableExists('orders')
    && v18ColumnExists('orders', 'category_id')
    && v18ColumnExists('orders', 'problem_details')
) {
    $containerOrdersUpdated = db()->query(
        "UPDATE orders
         SET category_id = ?
         WHERE problem_details IS NOT NULL
           AND problem_details <> ''
           AND (
                problem_details LIKE '%\"module\":\"container_rental\"%'
                OR problem_details LIKE '%\"type\":\"container_rental\"%'
                OR problem_details LIKE '%\"container_request\"%'
           )",
        [$containerCategoryId]
    )->rowCount();
}
echo "container_orders_category_updated={$containerOrdersUpdated}\n";

$furnitureOrdersUpdated = 0;
if (
    $furnitureCategoryId > 0
    && v18TableExists('orders')
    && v18ColumnExists('orders', 'category_id')
    && v18ColumnExists('orders', 'problem_details')
) {
    $furnitureOrdersUpdated = db()->query(
        "UPDATE orders
         SET category_id = ?
         WHERE problem_details IS NOT NULL
           AND problem_details <> ''
           AND (
                problem_details LIKE '%\"module\":\"furniture_moving\"%'
                OR problem_details LIKE '%\"type\":\"furniture_moving\"%'
                OR problem_details LIKE '%\"furniture_request\"%'
           )",
        [$furnitureCategoryId]
    )->rowCount();
}
echo "furniture_orders_category_updated={$furnitureOrdersUpdated}\n";

$accountEntriesSynced = specialBackfillContainerStoreAccountEntries(5000);
echo "container_store_account_entries_synced={$accountEntriesSynced}\n";

$ratingsRecalculated = 0;
if (v18TableExists('container_stores')) {
    $stores = db()->fetchAll('SELECT id FROM container_stores ORDER BY id ASC');
    foreach ($stores as $store) {
        specialRecalculateContainerStoreRating((int) ($store['id'] ?? 0));
        $ratingsRecalculated++;
    }
}
echo "container_store_ratings_recalculated={$ratingsRecalculated}\n";

echo "Schema update v18 completed.\n";
