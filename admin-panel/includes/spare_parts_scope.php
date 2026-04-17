<?php
/**
 * Spare Parts Scope Helpers
 * ربط قطع الغيار بالخدمات ومناطق الخدمة
 */

if (!function_exists('sparePartScopeTableExists')) {
    function sparePartScopeTableExists(string $table): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($safeTable === '') {
            return false;
        }

        $quoted = db()->getConnection()->quote($safeTable);
        $row = db()->fetch("SHOW TABLES LIKE {$quoted}");
        return !empty($row);
    }
}

if (!function_exists('sparePartScopeColumnExists')) {
    function sparePartScopeColumnExists(string $table, string $column): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($safeTable === '' || $safeColumn === '') {
            return false;
        }

        $quoted = db()->getConnection()->quote($safeColumn);
        $row = db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quoted}");
        return !empty($row);
    }
}

if (!function_exists('sparePartScopeIndexExists')) {
    function sparePartScopeIndexExists(string $table, string $indexName): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
        if ($safeTable === '' || $safeIndex === '') {
            return false;
        }

        $quoted = db()->getConnection()->quote($safeIndex);
        $rows = db()->fetchAll("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = {$quoted}");
        return !empty($rows);
    }
}

if (!function_exists('sparePartScopeEnsureSchema')) {
    function sparePartScopeEnsureSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        if (function_exists('serviceAreaEnsureSchema')) {
            serviceAreaEnsureSchema();
        }
        if (function_exists('serviceAreaEnsureServiceLinksSchema')) {
            serviceAreaEnsureServiceLinksSchema();
        }

        db()->query("CREATE TABLE IF NOT EXISTS `spare_part_services` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `spare_part_id` INT NOT NULL,
            `service_id` INT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_spare_part_service` (`spare_part_id`, `service_id`),
            INDEX `idx_sps_spare_part` (`spare_part_id`),
            INDEX `idx_sps_service` (`service_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->query("CREATE TABLE IF NOT EXISTS `spare_part_service_areas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `spare_part_id` INT NOT NULL,
            `service_area_id` INT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_spare_part_service_area` (`spare_part_id`, `service_area_id`),
            INDEX `idx_spsa_spare_part` (`spare_part_id`),
            INDEX `idx_spsa_service_area` (`service_area_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!sparePartScopeColumnExists('spare_part_services', 'spare_part_id')) {
            db()->query("ALTER TABLE `spare_part_services` ADD COLUMN `spare_part_id` INT NOT NULL");
        }
        if (!sparePartScopeColumnExists('spare_part_services', 'service_id')) {
            db()->query("ALTER TABLE `spare_part_services` ADD COLUMN `service_id` INT NOT NULL");
        }
        if (!sparePartScopeColumnExists('spare_part_services', 'created_at')) {
            db()->query("ALTER TABLE `spare_part_services` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!sparePartScopeIndexExists('spare_part_services', 'idx_sps_spare_part')) {
            db()->query("ALTER TABLE `spare_part_services` ADD INDEX `idx_sps_spare_part` (`spare_part_id`)");
        }
        if (!sparePartScopeIndexExists('spare_part_services', 'idx_sps_service')) {
            db()->query("ALTER TABLE `spare_part_services` ADD INDEX `idx_sps_service` (`service_id`)");
        }
        if (!sparePartScopeIndexExists('spare_part_services', 'uniq_spare_part_service')) {
            db()->query("ALTER TABLE `spare_part_services` ADD UNIQUE KEY `uniq_spare_part_service` (`spare_part_id`, `service_id`)");
        }

        if (!sparePartScopeColumnExists('spare_part_service_areas', 'spare_part_id')) {
            db()->query("ALTER TABLE `spare_part_service_areas` ADD COLUMN `spare_part_id` INT NOT NULL");
        }
        if (!sparePartScopeColumnExists('spare_part_service_areas', 'service_area_id')) {
            db()->query("ALTER TABLE `spare_part_service_areas` ADD COLUMN `service_area_id` INT NOT NULL");
        }
        if (!sparePartScopeColumnExists('spare_part_service_areas', 'created_at')) {
            db()->query("ALTER TABLE `spare_part_service_areas` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!sparePartScopeIndexExists('spare_part_service_areas', 'idx_spsa_spare_part')) {
            db()->query("ALTER TABLE `spare_part_service_areas` ADD INDEX `idx_spsa_spare_part` (`spare_part_id`)");
        }
        if (!sparePartScopeIndexExists('spare_part_service_areas', 'idx_spsa_service_area')) {
            db()->query("ALTER TABLE `spare_part_service_areas` ADD INDEX `idx_spsa_service_area` (`service_area_id`)");
        }
        if (!sparePartScopeIndexExists('spare_part_service_areas', 'uniq_spare_part_service_area')) {
            db()->query("ALTER TABLE `spare_part_service_areas` ADD UNIQUE KEY `uniq_spare_part_service_area` (`spare_part_id`, `service_area_id`)");
        }
    }
}

if (!function_exists('sparePartScopeNormalizeIds')) {
    function sparePartScopeNormalizeIds($raw): array
    {
        $items = [];
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw)) {
            $items = preg_split('/[,\|;\s]+/', $raw) ?: [];
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('sparePartScopeSyncServices')) {
    function sparePartScopeSyncServices(int $sparePartId, array $serviceIds): void
    {
        if ($sparePartId <= 0) {
            return;
        }

        sparePartScopeEnsureSchema();
        $serviceIds = sparePartScopeNormalizeIds($serviceIds);
        db()->delete('spare_part_services', 'spare_part_id = ?', [$sparePartId]);
        foreach ($serviceIds as $serviceId) {
            db()->insert('spare_part_services', [
                'spare_part_id' => $sparePartId,
                'service_id' => (int) $serviceId,
            ]);
        }
    }
}

if (!function_exists('sparePartScopeSyncAreas')) {
    function sparePartScopeSyncAreas(int $sparePartId, array $areaIds): void
    {
        if ($sparePartId <= 0) {
            return;
        }

        sparePartScopeEnsureSchema();
        $areaIds = sparePartScopeNormalizeIds($areaIds);
        db()->delete('spare_part_service_areas', 'spare_part_id = ?', [$sparePartId]);
        foreach ($areaIds as $areaId) {
            db()->insert('spare_part_service_areas', [
                'spare_part_id' => $sparePartId,
                'service_area_id' => (int) $areaId,
            ]);
        }
    }
}

if (!function_exists('sparePartScopeResolveServiceLinkedAreaIds')) {
    function sparePartScopeResolveServiceLinkedAreaIds(array $serviceIds, bool $activeOnly = true): array
    {
        sparePartScopeEnsureSchema();
        $serviceIds = sparePartScopeNormalizeIds($serviceIds);
        if (empty($serviceIds) || !sparePartScopeTableExists('service_area_services')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $sql = "SELECT DISTINCT sas.service_area_id
                FROM service_area_services sas";
        if ($activeOnly && sparePartScopeTableExists('service_areas')) {
            $sql .= " JOIN service_areas sa ON sa.id = sas.service_area_id AND sa.is_active = 1";
        }
        $sql .= " WHERE sas.service_id IN ({$placeholders})";

        $rows = db()->fetchAll($sql, $serviceIds);
        $areaIds = [];
        foreach ($rows as $row) {
            $areaId = (int) ($row['service_area_id'] ?? 0);
            if ($areaId > 0) {
                $areaIds[$areaId] = $areaId;
            }
        }

        return array_values($areaIds);
    }
}

if (!function_exists('sparePartScopeExtractCoverageAreaIds')) {
    function sparePartScopeExtractCoverageAreaIds(array $coverage): array
    {
        $matchedArea = $coverage['matched_area'] ?? null;
        if (!is_array($matchedArea)) {
            return [];
        }

        $matchedAreaId = (int) ($matchedArea['id'] ?? 0);
        if ($matchedAreaId <= 0) {
            return [];
        }

        return [$matchedAreaId];
    }
}

if (!function_exists('sparePartScopeBuildVisibilityFragment')) {
    function sparePartScopeBuildVisibilityFragment(string $partAlias, array $areaIds = [], array $serviceIds = []): array
    {
        sparePartScopeEnsureSchema();

        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $partAlias);
        if ($safeAlias === '') {
            $safeAlias = 'sp';
        }

        $conditions = [];
        $types = '';
        $params = [];

        $areaIds = sparePartScopeNormalizeIds($areaIds);
        if (!empty($areaIds) && sparePartScopeTableExists('spare_part_service_areas')) {
            $areaPlaceholders = implode(',', array_fill(0, count($areaIds), '?'));
            $conditions[] = "(NOT EXISTS (
                    SELECT 1
                    FROM spare_part_service_areas spsa_any
                    WHERE spsa_any.spare_part_id = {$safeAlias}.id
                ) OR EXISTS (
                    SELECT 1
                    FROM spare_part_service_areas spsa_match
                    WHERE spsa_match.spare_part_id = {$safeAlias}.id
                      AND spsa_match.service_area_id IN ({$areaPlaceholders})
                ))";
            $types .= str_repeat('i', count($areaIds));
            $params = array_merge($params, $areaIds);
        }

        $serviceIds = sparePartScopeNormalizeIds($serviceIds);
        if (!empty($serviceIds) && sparePartScopeTableExists('spare_part_services')) {
            $servicePlaceholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $conditions[] = "(NOT EXISTS (
                    SELECT 1
                    FROM spare_part_services sps_any
                    WHERE sps_any.spare_part_id = {$safeAlias}.id
                ) OR EXISTS (
                    SELECT 1
                    FROM spare_part_services sps_match
                    WHERE sps_match.spare_part_id = {$safeAlias}.id
                      AND sps_match.service_id IN ({$servicePlaceholders})
                ))";
            $types .= str_repeat('i', count($serviceIds));
            $params = array_merge($params, $serviceIds);
        }

        if (empty($conditions)) {
            return ['sql' => '1=1', 'types' => '', 'params' => []];
        }

        return [
            'sql' => implode(' AND ', $conditions),
            'types' => $types,
            'params' => $params,
        ];
    }
}

