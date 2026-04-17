<?php
/**
 * Store accounting and spare-parts schema helpers.
 */

if (!function_exists('storeSanitizeIdentifier')) {
    function storeSanitizeIdentifier($name)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', (string) $name);
    }
}

if (!function_exists('storeTableExists')) {
    function storeTableExists($table)
    {
        $safeTable = storeSanitizeIdentifier($table);
        if ($safeTable === '') {
            return false;
        }

        $row = db()->fetch("SHOW TABLES LIKE '{$safeTable}'");
        return !empty($row);
    }
}

if (!function_exists('storeColumnExists')) {
    function storeColumnExists($table, $column)
    {
        $safeTable = storeSanitizeIdentifier($table);
        $safeColumn = storeSanitizeIdentifier($column);
        if ($safeTable === '' || $safeColumn === '') {
            return false;
        }

        $row = db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return !empty($row);
    }
}

if (!function_exists('storeIndexExists')) {
    function storeIndexExists($table, $index)
    {
        $safeTable = storeSanitizeIdentifier($table);
        $safeIndex = storeSanitizeIdentifier($index);
        if ($safeTable === '' || $safeIndex === '') {
            return false;
        }

        $row = db()->fetch("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
        return !empty($row);
    }
}

if (!function_exists('storeAddColumnIfMissing')) {
    function storeAddColumnIfMissing($table, $column, $definition)
    {
        $safeTable = storeSanitizeIdentifier($table);
        $safeColumn = storeSanitizeIdentifier($column);
        if ($safeTable === '' || $safeColumn === '') {
            return;
        }

        if (!storeTableExists($safeTable)) {
            return;
        }

        if (!storeColumnExists($safeTable, $safeColumn)) {
            db()->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
        }
    }
}

if (!function_exists('storeAddIndexIfMissing')) {
    function storeAddIndexIfMissing($table, $index, array $columns)
    {
        $safeTable = storeSanitizeIdentifier($table);
        $safeIndex = storeSanitizeIdentifier($index);
        if ($safeTable === '' || $safeIndex === '' || empty($columns)) {
            return;
        }

        if (!storeTableExists($safeTable) || storeIndexExists($safeTable, $safeIndex)) {
            return;
        }

        $safeColumns = [];
        foreach ($columns as $column) {
            $safeColumn = storeSanitizeIdentifier($column);
            if ($safeColumn !== '') {
                $safeColumns[] = "`{$safeColumn}`";
            }
        }

        if (empty($safeColumns)) {
            return;
        }

        db()->query("ALTER TABLE `{$safeTable}` ADD INDEX `{$safeIndex}` (" . implode(', ', $safeColumns) . ")");
    }
}

if (!function_exists('ensureStoreSparePartsAccountingSchema')) {
    function ensureStoreSparePartsAccountingSchema()
    {
        if (storeTableExists('spare_parts')) {
            storeAddColumnIfMissing('spare_parts', 'old_price', 'DECIMAL(10,2) NULL');
            storeAddColumnIfMissing('spare_parts', 'sort_order', 'INT DEFAULT 0');
            storeAddColumnIfMissing('spare_parts', 'stock_quantity', 'INT DEFAULT 0');
            storeAddColumnIfMissing('spare_parts', 'store_id', 'INT NULL');
            storeAddColumnIfMissing('spare_parts', 'category_id', 'INT NULL');
            storeAddColumnIfMissing('spare_parts', 'price_with_installation', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            storeAddColumnIfMissing('spare_parts', 'price_without_installation', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            storeAddColumnIfMissing('spare_parts', 'old_price_with_installation', 'DECIMAL(10,2) NULL');
            storeAddColumnIfMissing('spare_parts', 'old_price_without_installation', 'DECIMAL(10,2) NULL');
            storeAddColumnIfMissing('spare_parts', 'warranty_duration', 'VARCHAR(150) NULL');
            storeAddColumnIfMissing('spare_parts', 'warranty_terms', 'TEXT NULL');
            storeAddIndexIfMissing('spare_parts', 'idx_spare_parts_store_id', ['store_id']);
            storeAddIndexIfMissing('spare_parts', 'idx_spare_parts_category_id', ['category_id']);

            if (storeColumnExists('spare_parts', 'price')) {
                db()->query(
                    "UPDATE spare_parts
                     SET price_with_installation = price
                     WHERE price_with_installation IS NULL OR price_with_installation <= 0"
                );
                db()->query(
                    "UPDATE spare_parts
                     SET price_without_installation = price
                     WHERE price_without_installation IS NULL OR price_without_installation <= 0"
                );
            }

            if (storeColumnExists('spare_parts', 'old_price')) {
                db()->query(
                    "UPDATE spare_parts
                     SET old_price_with_installation = old_price
                     WHERE old_price_with_installation IS NULL AND old_price IS NOT NULL"
                );
                db()->query(
                    "UPDATE spare_parts
                     SET old_price_without_installation = old_price
                     WHERE old_price_without_installation IS NULL AND old_price IS NOT NULL"
                );
            }
        }

        db()->query("CREATE TABLE IF NOT EXISTS `store_account_entries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `store_id` INT NOT NULL,
            `entry_type` ENUM('credit','debit') NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `source` ENUM('manual','withdrawal','return','adjustment') NOT NULL DEFAULT 'manual',
            `notes` VARCHAR(255) NULL,
            `reference_id` INT NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_store_account_store` (`store_id`),
            INDEX `idx_store_account_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->query("CREATE TABLE IF NOT EXISTS `store_spare_part_movements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `store_id` INT NOT NULL,
            `spare_part_id` INT NOT NULL,
            `movement_type` ENUM('withdrawal','return','adjustment_in','adjustment_out') NOT NULL DEFAULT 'withdrawal',
            `quantity` INT NOT NULL,
            `unit_price` DECIMAL(10,2) NULL,
            `notes` VARCHAR(255) NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_store_movements_store` (`store_id`),
            INDEX `idx_store_movements_part` (`spare_part_id`),
            INDEX `idx_store_movements_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
