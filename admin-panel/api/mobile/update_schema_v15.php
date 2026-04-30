<?php
/**
 * Schema update v15
 * Normalizes "Other Service" categories:
 * - Removes static emoji/tool icons from Other Service.
 * - Merges duplicate active Other Service categories into one canonical row.
 * - Keeps admin-uploaded image/icon media paths intact.
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

function v15TableExists(string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }

    return (bool) db()->fetch('SHOW TABLES LIKE ?', [$safe]);
}

function v15ColumnExists(string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '' || !v15TableExists($safeTable)) {
        return false;
    }

    return (bool) db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE ?", [$safeColumn]);
}

function v15UpdateCategoryReferences(string $table, string $column, array $duplicateIds, int $canonicalId): int
{
    if (!v15ColumnExists($table, $column) || empty($duplicateIds) || $canonicalId <= 0) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
    $params = array_merge([$canonicalId], $duplicateIds);
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

    return db()->query(
        "UPDATE `{$safeTable}` SET `{$safeColumn}` = ? WHERE `{$safeColumn}` IN ({$placeholders})",
        $params
    )->rowCount();
}

function v15OtherCategoryRank(array $category): array
{
    $image = trim((string) ($category['image'] ?? ''));
    $icon = trim((string) ($category['icon'] ?? ''));

    $rank = 0;
    if ($image !== '' && mediaValueLooksLikeFile($image)) {
        $rank += 100;
    }
    if ($icon !== '' && mediaValueLooksLikeFile($icon)) {
        $rank += 25;
    }
    if ((int) ($category['is_active'] ?? 0) === 1) {
        $rank += 10;
    }

    return [
        $rank,
        -((int) ($category['sort_order'] ?? 0)),
        -((int) ($category['id'] ?? 0)),
    ];
}

echo "Preparing Other Service category cleanup...\n";

if (!v15TableExists('service_categories')) {
    echo "service_categories table missing; nothing to update.\n";
    echo "Schema update v15 completed.\n";
    exit;
}

$categories = db()->fetchAll(
    "SELECT id, name_ar, name_en, icon, image, is_active, sort_order
     FROM service_categories
     ORDER BY id ASC"
);

$otherCategories = [];
foreach ($categories as $category) {
    if (isOtherServiceCategoryLabel($category['name_ar'] ?? '', $category['name_en'] ?? '')) {
        $otherCategories[] = $category;
    }
}

echo 'other_service_categories_found=' . count($otherCategories) . "\n";

$staticIconsCleared = 0;
foreach ($otherCategories as $category) {
    $icon = trim((string) ($category['icon'] ?? ''));
    if ($icon === '' || mediaValueLooksLikeFile($icon)) {
        continue;
    }

    db()->query('UPDATE `service_categories` SET `icon` = NULL WHERE `id` = ?', [(int) $category['id']]);
    $staticIconsCleared++;
}
echo "static_icons_cleared={$staticIconsCleared}\n";

if (count($otherCategories) > 1) {
    usort($otherCategories, function (array $a, array $b): int {
        $rankA = v15OtherCategoryRank($a);
        $rankB = v15OtherCategoryRank($b);
        for ($i = 0; $i < count($rankA); $i++) {
            if ($rankA[$i] === $rankB[$i]) {
                continue;
            }
            return $rankB[$i] <=> $rankA[$i];
        }
        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    });

    $canonicalId = (int) ($otherCategories[0]['id'] ?? 0);
    $duplicateIds = [];
    for ($i = 1; $i < count($otherCategories); $i++) {
        $duplicateId = (int) ($otherCategories[$i]['id'] ?? 0);
        if ($duplicateId > 0 && $duplicateId !== $canonicalId) {
            $duplicateIds[] = $duplicateId;
        }
    }

    echo "canonical_other_service_category_id={$canonicalId}\n";
    echo 'duplicate_other_service_category_ids=' . implode(',', $duplicateIds) . "\n";

    if ($canonicalId > 0 && !empty($duplicateIds)) {
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
            $updated = v15UpdateCategoryReferences($table, $column, $duplicateIds, $canonicalId);
            echo "{$table}.{$column}_updated={$updated}\n";
        }

        $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
        try {
            $deleted = db()->query(
                "DELETE FROM `service_categories` WHERE `id` IN ({$placeholders})",
                $duplicateIds
            )->rowCount();
            echo "duplicates_deleted={$deleted}\n";
        } catch (Throwable $e) {
            if (v15ColumnExists('service_categories', 'is_active')) {
                $deactivated = db()->query(
                    "UPDATE `service_categories` SET `is_active` = 0 WHERE `id` IN ({$placeholders})",
                    $duplicateIds
                )->rowCount();
                echo "duplicates_deactivated={$deactivated}\n";
            }
            echo 'duplicates_delete_error=' . $e->getMessage() . "\n";
        }
    }
} elseif (count($otherCategories) === 1) {
    echo 'canonical_other_service_category_id=' . (int) ($otherCategories[0]['id'] ?? 0) . "\n";
}

echo "Schema update v15 completed.\n";
