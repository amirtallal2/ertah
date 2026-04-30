<?php
/**
 * Shared helpers for complaints/support schema and replies.
 */

require_once __DIR__ . '/database.php';

if (!function_exists('complaintSupportTableExists')) {
    function complaintSupportResetSchemaCache(): void
    {
        $GLOBALS['complaint_support_table_cache'] = [];
        $GLOBALS['complaint_support_columns_cache'] = [];
        $GLOBALS['complaint_support_index_cache'] = [];
        $GLOBALS['complaint_support_primary_cache'] = [];
    }

    function complaintSupportSafeName(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value);
    }

    function complaintSupportTableExists(string $table): bool
    {
        $safeTable = complaintSupportSafeName($table);
        if ($safeTable === '') {
            return false;
        }

        $cache = &$GLOBALS['complaint_support_table_cache'];
        if (!is_array($cache)) {
            $cache = [];
        }

        if (array_key_exists($safeTable, $cache)) {
            return (bool) $cache[$safeTable];
        }

        try {
            $cache[$safeTable] = (bool) db()->fetch(
                "SHOW TABLES LIKE " . db()->getConnection()->quote($safeTable)
            );
        } catch (Throwable $e) {
            $cache[$safeTable] = false;
        }

        return (bool) $cache[$safeTable];
    }

    function complaintSupportColumnsMeta(string $table): array
    {
        $safeTable = complaintSupportSafeName($table);
        if ($safeTable === '' || !complaintSupportTableExists($safeTable)) {
            return [];
        }

        $cache = &$GLOBALS['complaint_support_columns_cache'];
        if (!is_array($cache)) {
            $cache = [];
        }

        if (isset($cache[$safeTable]) && is_array($cache[$safeTable])) {
            return $cache[$safeTable];
        }

        $meta = [];
        try {
            $rows = db()->fetchAll("SHOW COLUMNS FROM `{$safeTable}`");
            foreach ($rows as $row) {
                $field = (string) ($row['Field'] ?? '');
                if ($field === '') {
                    continue;
                }
                $meta[$field] = [
                    'field' => $field,
                    'type' => strtolower(trim((string) ($row['Type'] ?? ''))),
                    'nullable' => (($row['Null'] ?? 'YES') === 'YES'),
                    'default' => $row['Default'] ?? null,
                    'extra' => strtolower(trim((string) ($row['Extra'] ?? ''))),
                    'key' => strtoupper(trim((string) ($row['Key'] ?? ''))),
                ];
            }
        } catch (Throwable $e) {
            $meta = [];
        }

        $cache[$safeTable] = $meta;
        return $meta;
    }

    function complaintSupportTableHasColumn(string $table, string $column): bool
    {
        $meta = complaintSupportColumnsMeta($table);
        return isset($meta[complaintSupportSafeName($column)]);
    }

    function complaintSupportEnsureColumn(string $table, string $column, string $definition): void
    {
        $safeTable = complaintSupportSafeName($table);
        $safeColumn = complaintSupportSafeName($column);
        if ($safeTable === '' || $safeColumn === '' || !complaintSupportTableExists($safeTable)) {
            return;
        }

        if (complaintSupportTableHasColumn($safeTable, $safeColumn)) {
            return;
        }

        try {
            db()->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
            complaintSupportResetSchemaCache();
        } catch (Throwable $e) {
            // Ignore schema self-heal failures so screens keep working.
        }
    }

    function complaintSupportIndexExists(string $table, string $index): bool
    {
        $safeTable = complaintSupportSafeName($table);
        $safeIndex = complaintSupportSafeName($index);
        if ($safeTable === '' || $safeIndex === '' || !complaintSupportTableExists($safeTable)) {
            return false;
        }

        $cache = &$GLOBALS['complaint_support_index_cache'];
        if (!is_array($cache)) {
            $cache = [];
        }

        $cacheKey = $safeTable . '.' . $safeIndex;
        if (array_key_exists($cacheKey, $cache)) {
            return (bool) $cache[$cacheKey];
        }

        try {
            $cache[$cacheKey] = (bool) db()->fetch(
                "SHOW INDEX FROM `{$safeTable}` WHERE Key_name = "
                . db()->getConnection()->quote($safeIndex)
            );
        } catch (Throwable $e) {
            $cache[$cacheKey] = false;
        }

        return (bool) $cache[$cacheKey];
    }

    function complaintSupportEnsureIndex(string $table, string $index, array $columns): void
    {
        $safeTable = complaintSupportSafeName($table);
        $safeIndex = complaintSupportSafeName($index);
        if ($safeTable === '' || $safeIndex === '' || empty($columns) || !complaintSupportTableExists($safeTable)) {
            return;
        }

        if (complaintSupportIndexExists($safeTable, $safeIndex)) {
            return;
        }

        $safeColumns = [];
        foreach ($columns as $column) {
            $safeColumn = complaintSupportSafeName((string) $column);
            if ($safeColumn !== '' && complaintSupportTableHasColumn($safeTable, $safeColumn)) {
                $safeColumns[] = "`{$safeColumn}`";
            }
        }

        if (empty($safeColumns)) {
            return;
        }

        try {
            db()->query(
                "ALTER TABLE `{$safeTable}` ADD INDEX `{$safeIndex}` (" . implode(', ', $safeColumns) . ")"
            );
            complaintSupportResetSchemaCache();
        } catch (Throwable $e) {
            // Ignore schema self-heal failures so screens keep working.
        }
    }

    function complaintSupportPrimaryKeyColumns(string $table): array
    {
        $safeTable = complaintSupportSafeName($table);
        if ($safeTable === '' || !complaintSupportTableExists($safeTable)) {
            return [];
        }

        $cache = &$GLOBALS['complaint_support_primary_cache'];
        if (!is_array($cache)) {
            $cache = [];
        }

        if (isset($cache[$safeTable]) && is_array($cache[$safeTable])) {
            return $cache[$safeTable];
        }

        $columns = [];
        try {
            $rows = db()->fetchAll("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = 'PRIMARY'");
            foreach ($rows as $row) {
                $seq = (int) ($row['Seq_in_index'] ?? 0);
                $name = (string) ($row['Column_name'] ?? '');
                if ($seq > 0 && $name !== '') {
                    $columns[$seq] = $name;
                }
            }
            ksort($columns);
        } catch (Throwable $e) {
            $columns = [];
        }

        $cache[$safeTable] = array_values($columns);
        return $cache[$safeTable];
    }

    function complaintSupportEnsurePrimaryKey(string $table, string $column = 'id'): void
    {
        $safeTable = complaintSupportSafeName($table);
        $safeColumn = complaintSupportSafeName($column);
        if (
            $safeTable === ''
            || $safeColumn === ''
            || !complaintSupportTableExists($safeTable)
            || !complaintSupportTableHasColumn($safeTable, $safeColumn)
        ) {
            return;
        }

        $primaryColumns = complaintSupportPrimaryKeyColumns($safeTable);
        if ($primaryColumns === [$safeColumn]) {
            return;
        }
        if (!empty($primaryColumns)) {
            return;
        }

        try {
            db()->query("ALTER TABLE `{$safeTable}` ADD PRIMARY KEY (`{$safeColumn}`)");
            complaintSupportResetSchemaCache();
        } catch (Throwable $e) {
            // Ignore schema self-heal failures so screens keep working.
        }
    }

    function complaintSupportEnsureAutoIncrementId(string $table, string $column = 'id'): void
    {
        $safeTable = complaintSupportSafeName($table);
        $safeColumn = complaintSupportSafeName($column);
        if (
            $safeTable === ''
            || $safeColumn === ''
            || !complaintSupportTableExists($safeTable)
            || !complaintSupportTableHasColumn($safeTable, $safeColumn)
        ) {
            return;
        }

        complaintSupportEnsurePrimaryKey($safeTable, $safeColumn);

        $meta = complaintSupportColumnsMeta($safeTable);
        $columnMeta = $meta[$safeColumn] ?? null;
        if (!is_array($columnMeta)) {
            return;
        }

        if (strpos((string) ($columnMeta['extra'] ?? ''), 'auto_increment') !== false) {
            return;
        }

        $primaryColumns = complaintSupportPrimaryKeyColumns($safeTable);
        if ($primaryColumns !== [$safeColumn]) {
            return;
        }

        try {
            db()->query("ALTER TABLE `{$safeTable}` MODIFY COLUMN `{$safeColumn}` INT NOT NULL AUTO_INCREMENT");
            complaintSupportResetSchemaCache();
        } catch (Throwable $e) {
            // Ignore schema self-heal failures so screens keep working.
        }
    }

    function complaintSupportReplyMessageColumns(array $columnsMeta): array
    {
        $available = [];
        foreach (['message', 'reply', 'content', 'body', 'text'] as $candidate) {
            if (isset($columnsMeta[$candidate])) {
                $available[] = $candidate;
            }
        }
        return $available;
    }

    function complaintSupportReplyMessageSelectSql(array $columnsMeta, string $alias = 'r'): string
    {
        $safeAlias = complaintSupportSafeName($alias);
        if ($safeAlias === '') {
            $safeAlias = 'r';
        }

        $columns = complaintSupportReplyMessageColumns($columnsMeta);
        if (empty($columns)) {
            return "'' AS reply_message";
        }

        $parts = [];
        foreach ($columns as $column) {
            $safeColumn = complaintSupportSafeName($column);
            if ($safeColumn === '') {
                continue;
            }
            $parts[] = "NULLIF(TRIM({$safeAlias}.`{$safeColumn}`), '')";
        }

        if (empty($parts)) {
            return "'' AS reply_message";
        }

        return 'COALESCE(' . implode(', ', $parts) . ') AS reply_message';
    }

    function complaintSupportCurrentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    function complaintSupportNextNumericId(string $table, string $column = 'id'): int
    {
        $safeTable = complaintSupportSafeName($table);
        $safeColumn = complaintSupportSafeName($column);
        if (
            $safeTable === ''
            || $safeColumn === ''
            || !complaintSupportTableExists($safeTable)
            || !complaintSupportTableHasColumn($safeTable, $safeColumn)
        ) {
            return 1;
        }

        try {
            $row = db()->fetch(
                "SELECT COALESCE(MAX(`{$safeColumn}`), 0) + 1 AS next_id FROM `{$safeTable}`"
            );
            return max(1, (int) ($row['next_id'] ?? 1));
        } catch (Throwable $e) {
            return 1;
        }
    }

    function complaintSupportEnumFirstValue(string $type): ?string
    {
        $type = strtolower(trim($type));
        if (!preg_match('/^enum\((.+)\)$/', $type, $matches)) {
            return null;
        }

        if (!preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $matches[1], $valueMatches)) {
            return null;
        }

        $first = $valueMatches[1][0] ?? null;
        return $first !== null ? stripcslashes($first) : null;
    }

    function complaintSupportFallbackValueForReplyColumn(string $column, array $meta, array $context)
    {
        $normalizedColumn = strtolower(trim($column));
        $type = strtolower(trim((string) ($meta['type'] ?? '')));
        $attachmentsJson = !empty($context['attachments_json']) ? (string) $context['attachments_json'] : null;
        $message = (string) ($context['message'] ?? '');
        $complaintId = (int) ($context['complaint_id'] ?? 0);
        $userId = isset($context['user_id']) ? (int) $context['user_id'] : null;
        $adminId = isset($context['admin_id']) ? (int) $context['admin_id'] : null;
        $senderType = (string) ($context['sender_type'] ?? 'user');
        $now = complaintSupportCurrentDateTime();

        if ($normalizedColumn === 'complaint_id') {
            return $complaintId;
        }
        if ($normalizedColumn === 'user_id') {
            return $userId;
        }
        if ($normalizedColumn === 'admin_id') {
            return $adminId;
        }
        if (in_array($normalizedColumn, ['message', 'reply', 'content', 'body', 'text'], true)) {
            return $message;
        }
        if ($normalizedColumn === 'sender_type') {
            return $senderType;
        }
        if ($normalizedColumn === 'attachments') {
            return $attachmentsJson !== null ? $attachmentsJson : '[]';
        }
        if (in_array($normalizedColumn, ['created_at', 'updated_at'], true)) {
            return $now;
        }

        $enumValue = complaintSupportEnumFirstValue($type);
        if ($enumValue !== null) {
            return $enumValue;
        }

        if (
            strpos($type, 'timestamp') !== false
            || strpos($type, 'datetime') !== false
            || $type === 'date'
            || $type === 'time'
        ) {
            return $type === 'date' ? date('Y-m-d') : ($type === 'time' ? date('H:i:s') : $now);
        }

        if (strpos($type, 'json') !== false) {
            return $attachmentsJson !== null ? $attachmentsJson : '[]';
        }

        if (
            strpos($type, 'int') !== false
            || strpos($type, 'decimal') !== false
            || strpos($type, 'float') !== false
            || strpos($type, 'double') !== false
        ) {
            return 0;
        }

        return '';
    }

    function complaintSupportBuildReplyInsertData(array $columnsMeta, array $context): array
    {
        $data = [];
        $message = trim((string) ($context['message'] ?? ''));
        $attachmentPaths = $context['attachment_paths'] ?? [];
        if (!is_array($attachmentPaths)) {
            $attachmentPaths = [];
        }
        $attachmentsJson = !empty($attachmentPaths)
            ? json_encode(array_values($attachmentPaths), JSON_UNESCAPED_UNICODE)
            : null;

        $normalizedContext = $context;
        $normalizedContext['message'] = $message;
        $normalizedContext['attachment_paths'] = $attachmentPaths;
        $normalizedContext['attachments_json'] = $attachmentsJson;

        if (isset($columnsMeta['complaint_id'])) {
            $data['complaint_id'] = (int) ($context['complaint_id'] ?? 0);
        }
        if (($context['user_id'] ?? null) !== null && isset($columnsMeta['user_id'])) {
            $data['user_id'] = (int) $context['user_id'];
        }
        if (($context['admin_id'] ?? null) !== null && isset($columnsMeta['admin_id'])) {
            $data['admin_id'] = (int) $context['admin_id'];
        }
        if (isset($columnsMeta['sender_type'])) {
            $data['sender_type'] = (string) ($context['sender_type'] ?? 'user');
        }

        $messageColumns = complaintSupportReplyMessageColumns($columnsMeta);
        if (!empty($messageColumns)) {
            $data[$messageColumns[0]] = $message;
        }

        if (isset($columnsMeta['attachments']) && $attachmentsJson !== null) {
            $data['attachments'] = $attachmentsJson;
        }
        if (isset($columnsMeta['created_at'])) {
            $data['created_at'] = complaintSupportCurrentDateTime();
        }
        if (isset($columnsMeta['updated_at'])) {
            $data['updated_at'] = complaintSupportCurrentDateTime();
        }
        if (isset($columnsMeta['id'])) {
            $idMeta = $columnsMeta['id'];
            $idExtra = strtolower(trim((string) ($idMeta['extra'] ?? '')));
            $idNullable = (bool) ($idMeta['nullable'] ?? true);
            $idDefault = $idMeta['default'] ?? null;

            if (
                strpos($idExtra, 'auto_increment') === false
                && !$idNullable
                && $idDefault === null
            ) {
                $data['id'] = complaintSupportNextNumericId('complaint_replies', 'id');
            }
        }

        foreach ($columnsMeta as $column => $meta) {
            if (array_key_exists($column, $data)) {
                continue;
            }

            $extra = strtolower(trim((string) ($meta['extra'] ?? '')));
            if (strpos($extra, 'auto_increment') !== false) {
                continue;
            }

            $nullable = (bool) ($meta['nullable'] ?? true);
            $default = $meta['default'] ?? null;
            if ($nullable || $default !== null) {
                continue;
            }

            $data[$column] = complaintSupportFallbackValueForReplyColumn(
                $column,
                $meta,
                $normalizedContext
            );
        }

        return $data;
    }

    function complaintSupportEnsureRepliesSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        complaintSupportResetSchemaCache();

        try {
            db()->query(
                "CREATE TABLE IF NOT EXISTS `complaint_replies` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `complaint_id` INT NOT NULL,
                    `user_id` INT NULL,
                    `admin_id` INT NULL,
                    `message` TEXT NULL,
                    `attachments` LONGTEXT NULL,
                    `sender_type` VARCHAR(20) NULL,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_complaint_replies_complaint` (`complaint_id`),
                    INDEX `idx_complaint_replies_user` (`user_id`),
                    INDEX `idx_complaint_replies_admin` (`admin_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            complaintSupportResetSchemaCache();
        } catch (Throwable $e) {
            // Ignore schema self-heal failures so screens keep working.
        }

        if (!complaintSupportTableExists('complaint_replies')) {
            return;
        }

        complaintSupportEnsureColumn('complaint_replies', 'id', 'INT NOT NULL');
        complaintSupportEnsureColumn('complaint_replies', 'complaint_id', 'INT NOT NULL');
        complaintSupportEnsureColumn('complaint_replies', 'user_id', 'INT NULL');
        complaintSupportEnsureColumn('complaint_replies', 'admin_id', 'INT NULL');
        complaintSupportEnsureColumn('complaint_replies', 'attachments', 'LONGTEXT NULL');
        complaintSupportEnsureColumn('complaint_replies', 'sender_type', 'VARCHAR(20) NULL');
        complaintSupportEnsureColumn(
            'complaint_replies',
            'created_at',
            'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'
        );

        $columnsMeta = complaintSupportColumnsMeta('complaint_replies');
        if (!isset($columnsMeta['message'])) {
            complaintSupportEnsureColumn('complaint_replies', 'message', 'TEXT NULL');
            $columnsMeta = complaintSupportColumnsMeta('complaint_replies');
            $legacyMessageColumns = array_values(
                array_filter(
                    complaintSupportReplyMessageColumns($columnsMeta),
                    static fn($column) => $column !== 'message'
                )
            );

            if (!empty($legacyMessageColumns)) {
                $parts = [];
                foreach ($legacyMessageColumns as $column) {
                    $safeColumn = complaintSupportSafeName($column);
                    if ($safeColumn !== '') {
                        $parts[] = "NULLIF(TRIM(`{$safeColumn}`), '')";
                    }
                }

                if (!empty($parts)) {
                    try {
                        db()->query(
                            "UPDATE `complaint_replies`
                             SET `message` = COALESCE(" . implode(', ', $parts) . ")
                             WHERE `message` IS NULL OR TRIM(`message`) = ''"
                        );
                    } catch (Throwable $e) {
                        // Ignore schema self-heal failures so screens keep working.
                    }
                }
            }
        }

        complaintSupportEnsurePrimaryKey('complaint_replies', 'id');
        complaintSupportEnsureAutoIncrementId('complaint_replies', 'id');
        complaintSupportEnsureIndex('complaint_replies', 'idx_complaint_replies_complaint', ['complaint_id']);
        complaintSupportEnsureIndex('complaint_replies', 'idx_complaint_replies_user', ['user_id']);
        complaintSupportEnsureIndex('complaint_replies', 'idx_complaint_replies_admin', ['admin_id']);

        if (complaintSupportTableHasColumn('complaint_replies', 'updated_at')) {
            try {
                db()->query(
                    "ALTER TABLE `complaint_replies`
                     MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
                );
                complaintSupportResetSchemaCache();
            } catch (Throwable $e) {
                // Ignore schema self-heal failures so screens keep working.
            }
        }
    }
}
