<?php
/**
 * خدمات خاصة: نقل العفش والحاويات
 * Special Services Helpers
 */

/**
 * التحقق من وجود جدول.
 */
function specialServiceTableExists(string $tableName): bool
{
    static $cache = [];

    $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    if ($safeName === '') {
        return false;
    }

    if (array_key_exists($safeName, $cache)) {
        return $cache[$safeName];
    }

    $quoted = db()->getConnection()->quote($safeName);
    $exists = (bool) db()->fetch("SHOW TABLES LIKE {$quoted}");
    $cache[$safeName] = $exists;

    return $exists;
}

/**
 * التحقق من وجود عمود.
 */
function specialServiceColumnExists(string $tableName, string $columnName): bool
{
    static $cache = [];

    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    if ($table === '' || $column === '') {
        return false;
    }

    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!specialServiceTableExists($table)) {
        $cache[$cacheKey] = false;
        return false;
    }

    $quotedColumn = db()->getConnection()->quote($column);
    $row = db()->fetch("SHOW COLUMNS FROM `{$table}` LIKE {$quotedColumn}");
    $exists = !empty($row);
    $cache[$cacheKey] = $exists;

    return $exists;
}

/**
 * إضافة عمود عند الحاجة.
 */
function specialEnsureColumn(string $tableName, string $columnName, string $definition): void
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    if ($table === '' || $column === '') {
        return;
    }

    if (!specialServiceTableExists($table)) {
        return;
    }

    if (!specialServiceColumnExists($table, $column)) {
        db()->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function specialEnsureServiceCategory(
    string $nameAr,
    string $nameEn,
    string $icon,
    int $sortOrder,
    array $nameArLike = [],
    array $nameEnLike = []
): ?int {
    if (!specialServiceTableExists('service_categories')) {
        return null;
    }

    $nameAr = trim($nameAr);
    $nameEn = trim($nameEn);

    if ($nameAr !== '') {
        $row = db()->fetch('SELECT id FROM service_categories WHERE name_ar = ? LIMIT 1', [$nameAr]);
        if (!empty($row['id'])) {
            return (int) $row['id'];
        }
    }

    if ($nameEn !== '' && specialServiceColumnExists('service_categories', 'name_en')) {
        $row = db()->fetch('SELECT id FROM service_categories WHERE name_en = ? LIMIT 1', [$nameEn]);
        if (!empty($row['id'])) {
            return (int) $row['id'];
        }
    }

    $clauses = [];
    $params = [];
    foreach ($nameArLike as $pattern) {
        $clauses[] = 'name_ar LIKE ?';
        $params[] = $pattern;
    }
    if (specialServiceColumnExists('service_categories', 'name_en')) {
        foreach ($nameEnLike as $pattern) {
            $clauses[] = 'name_en LIKE ?';
            $params[] = $pattern;
        }
    }

    if (!empty($clauses)) {
        $row = db()->fetch(
            "SELECT id FROM service_categories WHERE (" . implode(' OR ', $clauses) . ") ORDER BY sort_order ASC, id ASC LIMIT 1",
            $params
        );
        if (!empty($row['id'])) {
            return (int) $row['id'];
        }
    }

    if ($nameAr === '') {
        return null;
    }

    $insertData = [
        'name_ar' => $nameAr,
    ];
    if ($nameEn !== '' && specialServiceColumnExists('service_categories', 'name_en')) {
        $insertData['name_en'] = $nameEn;
    }
    if ($icon !== '' && specialServiceColumnExists('service_categories', 'icon')) {
        $insertData['icon'] = $icon;
    }
    if (specialServiceColumnExists('service_categories', 'is_active')) {
        $insertData['is_active'] = 1;
    }
    if (specialServiceColumnExists('service_categories', 'sort_order')) {
        $insertData['sort_order'] = $sortOrder;
    }
    if (specialServiceColumnExists('service_categories', 'warranty_days')) {
        $insertData['warranty_days'] = 0;
    }

    $newId = (int) db()->insert('service_categories', $insertData);
    if ($newId > 0) {
        return $newId;
    }

    $row = db()->fetch('SELECT id FROM service_categories WHERE name_ar = ? LIMIT 1', [$nameAr]);
    if (!empty($row['id'])) {
        return (int) $row['id'];
    }

    return null;
}

function specialNormalizeRootServiceCategory(int $categoryId, string $nameAr, string $nameEn, string $icon, int $sortOrder): void
{
    if ($categoryId <= 0 || !specialServiceTableExists('service_categories')) {
        return;
    }

    $updates = [];
    $params = [];

    if (specialServiceColumnExists('service_categories', 'parent_id')) {
        $updates[] = 'parent_id = NULL';
    }
    if ($nameAr !== '') {
        $updates[] = 'name_ar = ?';
        $params[] = $nameAr;
    }
    if ($nameEn !== '' && specialServiceColumnExists('service_categories', 'name_en')) {
        $updates[] = 'name_en = ?';
        $params[] = $nameEn;
    }
    if ($icon !== '' && specialServiceColumnExists('service_categories', 'icon')) {
        $updates[] = 'icon = ?';
        $params[] = $icon;
    }
    if (specialServiceColumnExists('service_categories', 'is_active')) {
        $updates[] = 'is_active = 1';
    }
    if (specialServiceColumnExists('service_categories', 'sort_order')) {
        $updates[] = 'sort_order = ?';
        $params[] = $sortOrder;
    }
    if (specialServiceColumnExists('service_categories', 'warranty_days')) {
        $updates[] = 'warranty_days = 0';
    }

    if (empty($updates)) {
        return;
    }

    $params[] = $categoryId;
    db()->query(
        'UPDATE service_categories SET ' . implode(', ', $updates) . ' WHERE id = ?',
        $params
    );
}

function specialEnsureFurnitureCategoryId(): ?int
{
    $categoryId = specialEnsureServiceCategory(
        'نقل العفش',
        'Furniture Moving',
        '🚚',
        9001,
        ['%عفش%', '%نقل العفش%'],
        ['%furniture%', '%moving%']
    );

    if (($categoryId ?? 0) > 0) {
        specialNormalizeRootServiceCategory((int) $categoryId, 'نقل العفش', 'Furniture Moving', '🚚', 9001);
    }

    return $categoryId;
}

function specialEnsureContainerCategoryId(): ?int
{
    $categoryId = specialEnsureServiceCategory(
        'الحاويات',
        'Containers',
        '📦',
        9002,
        ['%حاويات%', '%حاوية%'],
        ['%container%']
    );

    if (($categoryId ?? 0) > 0) {
        specialNormalizeRootServiceCategory((int) $categoryId, 'الحاويات', 'Containers', '📦', 9002);
    }

    return $categoryId;
}

/**
 * إنشاء/تحديث جداول الخدمات الخاصة.
 */
function ensureSpecialServicesSchema(): void
{
    static $schemaReady = false;

    if ($schemaReady) {
        return;
    }
    $schemaReady = true;

    $hadFurnitureAreasTable = specialServiceTableExists('furniture_areas');

    db()->query("CREATE TABLE IF NOT EXISTS `furniture_services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar` VARCHAR(150) NOT NULL,
        `name_en` VARCHAR(150) DEFAULT NULL,
        `name_ur` VARCHAR(150) DEFAULT NULL,
        `description_ar` TEXT DEFAULT NULL,
        `description_en` TEXT DEFAULT NULL,
        `description_ur` TEXT DEFAULT NULL,
        `base_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `price_note` VARCHAR(255) DEFAULT NULL,
        `estimated_duration_hours` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `image` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_furniture_services_active_sort` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `furniture_areas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar` VARCHAR(120) NOT NULL,
        `name_en` VARCHAR(120) DEFAULT NULL,
        `name_ur` VARCHAR(120) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_furniture_areas_active_sort` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `furniture_request_fields` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `field_key` VARCHAR(80) NOT NULL,
        `label_ar` VARCHAR(150) NOT NULL,
        `label_en` VARCHAR(150) DEFAULT NULL,
        `label_ur` VARCHAR(150) DEFAULT NULL,
        `field_type` VARCHAR(30) NOT NULL DEFAULT 'text',
        `placeholder_ar` VARCHAR(255) DEFAULT NULL,
        `placeholder_en` VARCHAR(255) DEFAULT NULL,
        `placeholder_ur` VARCHAR(255) DEFAULT NULL,
        `options_json` LONGTEXT DEFAULT NULL,
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_furniture_request_field_key` (`field_key`),
        INDEX `idx_furniture_request_fields_active_sort` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `furniture_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `request_number` VARCHAR(30) NOT NULL UNIQUE,
        `user_id` INT DEFAULT NULL,
        `service_id` INT DEFAULT NULL,
        `area_id` INT DEFAULT NULL,
        `area_name` VARCHAR(150) DEFAULT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `pickup_city` VARCHAR(100) DEFAULT NULL,
        `pickup_address` TEXT DEFAULT NULL,
        `dropoff_city` VARCHAR(100) DEFAULT NULL,
        `dropoff_address` TEXT DEFAULT NULL,
        `move_date` DATE DEFAULT NULL,
        `preferred_time` VARCHAR(50) DEFAULT NULL,
        `rooms_count` INT NOT NULL DEFAULT 1,
        `floors_from` INT NOT NULL DEFAULT 0,
        `floors_to` INT NOT NULL DEFAULT 0,
        `elevator_from` TINYINT(1) NOT NULL DEFAULT 0,
        `elevator_to` TINYINT(1) NOT NULL DEFAULT 0,
        `needs_packing` TINYINT(1) NOT NULL DEFAULT 0,
        `estimated_items` INT NOT NULL DEFAULT 0,
        `details_json` LONGTEXT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'new',
        `estimated_price` DECIMAL(10,2) DEFAULT NULL,
        `final_price` DECIMAL(10,2) DEFAULT NULL,
        `admin_notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_furniture_requests_status` (`status`),
        INDEX `idx_furniture_requests_service` (`service_id`),
        INDEX `idx_furniture_requests_area` (`area_id`),
        INDEX `idx_furniture_requests_user` (`user_id`),
        INDEX `idx_furniture_requests_move_date` (`move_date`),
        INDEX `idx_furniture_requests_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `container_services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar` VARCHAR(150) NOT NULL,
        `name_en` VARCHAR(150) DEFAULT NULL,
        `name_ur` VARCHAR(150) DEFAULT NULL,
        `description_ar` TEXT DEFAULT NULL,
        `description_en` TEXT DEFAULT NULL,
        `description_ur` TEXT DEFAULT NULL,
        `container_size` VARCHAR(100) NOT NULL,
        `capacity_ton` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `daily_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `weekly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `price_note` VARCHAR(255) DEFAULT NULL,
        `image` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_container_services_active_sort` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `container_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `request_number` VARCHAR(30) NOT NULL UNIQUE,
        `user_id` INT DEFAULT NULL,
        `container_service_id` INT DEFAULT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `site_city` VARCHAR(100) DEFAULT NULL,
        `site_address` TEXT DEFAULT NULL,
        `start_date` DATE DEFAULT NULL,
        `end_date` DATE DEFAULT NULL,
        `duration_days` INT NOT NULL DEFAULT 1,
        `quantity` INT NOT NULL DEFAULT 1,
        `needs_loading_help` TINYINT(1) NOT NULL DEFAULT 0,
        `needs_operator` TINYINT(1) NOT NULL DEFAULT 0,
        `purpose` VARCHAR(255) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'new',
        `estimated_price` DECIMAL(10,2) DEFAULT NULL,
        `final_price` DECIMAL(10,2) DEFAULT NULL,
        `admin_notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_container_requests_status` (`status`),
        INDEX `idx_container_requests_service` (`container_service_id`),
        INDEX `idx_container_requests_user` (`user_id`),
        INDEX `idx_container_requests_start_date` (`start_date`),
        INDEX `idx_container_requests_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `container_stores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar` VARCHAR(150) NOT NULL,
        `name_en` VARCHAR(150) DEFAULT NULL,
        `name_ur` VARCHAR(150) DEFAULT NULL,
        `contact_person` VARCHAR(150) DEFAULT NULL,
        `phone` VARCHAR(40) DEFAULT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `logo` VARCHAR(255) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_container_stores_active_sort` (`is_active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `container_store_account_entries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `store_id` INT NOT NULL,
        `entry_type` ENUM('credit','debit') NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `source` ENUM('manual','request','payment','settlement','adjustment') NOT NULL DEFAULT 'manual',
        `reference_type` VARCHAR(60) DEFAULT NULL,
        `reference_id` INT DEFAULT NULL,
        `notes` VARCHAR(255) DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_container_store_account_store` (`store_id`),
        INDEX `idx_container_store_account_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query("CREATE TABLE IF NOT EXISTS `container_store_reviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `store_id` INT NOT NULL,
        `user_id` INT DEFAULT NULL,
        `order_id` INT DEFAULT NULL,
        `rating` TINYINT NOT NULL,
        `comment` TEXT DEFAULT NULL,
        `quality_rating` TINYINT DEFAULT NULL,
        `speed_rating` TINYINT DEFAULT NULL,
        `price_rating` TINYINT DEFAULT NULL,
        `behavior_rating` TINYINT DEFAULT NULL,
        `tags` LONGTEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_container_store_review_order` (`order_id`),
        INDEX `idx_container_store_reviews_store` (`store_id`),
        INDEX `idx_container_store_reviews_user` (`user_id`),
        INDEX `idx_container_store_reviews_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // توافق رجعي مع الإصدارات السابقة
    specialEnsureColumn('furniture_requests', 'area_id', 'INT DEFAULT NULL');
    specialEnsureColumn('furniture_requests', 'area_name', 'VARCHAR(150) DEFAULT NULL');
    specialEnsureColumn('furniture_requests', 'details_json', 'LONGTEXT DEFAULT NULL');
    specialEnsureColumn('furniture_areas', 'name_ur', "VARCHAR(120) DEFAULT NULL");
    specialEnsureColumn('furniture_request_fields', 'label_ur', "VARCHAR(150) DEFAULT NULL");
    specialEnsureColumn('furniture_request_fields', 'placeholder_ur', "VARCHAR(255) DEFAULT NULL");

    // تسعير مرن لخدمات نقل العفش (بالكيلو/بالمتر)
    specialEnsureColumn('furniture_services', 'name_ur', "VARCHAR(150) DEFAULT NULL");
    specialEnsureColumn('furniture_services', 'description_ur', "TEXT DEFAULT NULL");
    specialEnsureColumn('furniture_services', 'price_per_kg', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    specialEnsureColumn('furniture_services', 'price_per_meter', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    specialEnsureColumn('furniture_services', 'minimum_charge', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");

    // تسعير مرن لخدمات الحاويات (بالكيلو/بالمتر)
    specialEnsureColumn('container_services', 'name_ur', "VARCHAR(150) DEFAULT NULL");
    specialEnsureColumn('container_services', 'description_ur', "TEXT DEFAULT NULL");
    specialEnsureColumn('container_services', 'price_per_kg', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    specialEnsureColumn('container_services', 'price_per_meter', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    specialEnsureColumn('container_services', 'minimum_charge', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    specialEnsureColumn('container_services', 'store_id', "INT DEFAULT NULL");
    specialEnsureColumn('container_stores', 'rating', "DECIMAL(3,2) NOT NULL DEFAULT 0.00");
    specialEnsureColumn('container_stores', 'reviews_count', "INT NOT NULL DEFAULT 0");
    specialEnsureColumn('container_store_reviews', 'quality_rating', "TINYINT DEFAULT NULL");
    specialEnsureColumn('container_store_reviews', 'speed_rating', "TINYINT DEFAULT NULL");
    specialEnsureColumn('container_store_reviews', 'price_rating', "TINYINT DEFAULT NULL");
    specialEnsureColumn('container_store_reviews', 'behavior_rating', "TINYINT DEFAULT NULL");
    specialEnsureColumn('container_store_reviews', 'tags', "LONGTEXT DEFAULT NULL");

    // تفاصيل تسعير الطلبات الخاصة
    specialEnsureColumn('furniture_requests', 'estimated_weight_kg', "DECIMAL(10,2) DEFAULT NULL");
    specialEnsureColumn('furniture_requests', 'estimated_distance_meters', "DECIMAL(10,2) DEFAULT NULL");
    specialEnsureColumn('furniture_requests', 'source_order_id', "INT DEFAULT NULL");
    specialEnsureColumn('container_requests', 'estimated_weight_kg', "DECIMAL(10,2) DEFAULT NULL");
    specialEnsureColumn('container_requests', 'estimated_distance_meters', "DECIMAL(10,2) DEFAULT NULL");
    specialEnsureColumn('container_requests', 'details_json', "LONGTEXT DEFAULT NULL");
    specialEnsureColumn('container_requests', 'media_json', "LONGTEXT DEFAULT NULL");
    specialEnsureColumn('container_requests', 'source_order_id', "INT DEFAULT NULL");
    specialEnsureColumn('container_requests', 'container_store_id', "INT DEFAULT NULL");

    specialSeedFurnitureDefaults(!$hadFurnitureAreasTable);
}

/**
 * تعبئة بيانات افتراضية لنظام نقل العفش.
 */
function specialSeedFurnitureDefaults(bool $seedDefaultAreas = true): void
{
    if (!specialServiceTableExists('furniture_areas')) {
        return;
    }

    specialDeduplicateFurnitureAreas();

    $defaultAreas = [
        ['name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'sort_order' => 1],
        ['name_ar' => 'جدة', 'name_en' => 'Jeddah', 'sort_order' => 2],
        ['name_ar' => 'الدمام', 'name_en' => 'Dammam', 'sort_order' => 3],
    ];

    if ($seedDefaultAreas) {
        foreach ($defaultAreas as $area) {
            $exists = db()->fetch(
                'SELECT id FROM furniture_areas WHERE name_ar = ? LIMIT 1',
                [$area['name_ar']]
            );
            if ($exists) {
                continue;
            }

            db()->insert('furniture_areas', [
                'name_ar' => $area['name_ar'],
                'name_en' => $area['name_en'],
                'sort_order' => $area['sort_order'],
                'is_active' => 1,
            ]);
        }
    }

    if (!specialServiceTableExists('furniture_request_fields')) {
        return;
    }

    $fieldsCount = (int) db()->count('furniture_request_fields');
    if ($fieldsCount > 0) {
        return;
    }

    $seedFields = [
        [
            'field_key' => 'rooms_count',
            'label_ar' => 'عدد الغرف',
            'label_en' => 'Rooms Count',
            'field_type' => 'number',
            'placeholder_ar' => 'مثال: 3',
            'placeholder_en' => 'Example: 3',
            'options_json' => null,
            'is_required' => 1,
            'is_active' => 1,
            'sort_order' => 1,
        ],
        [
            'field_key' => 'floors_from',
            'label_ar' => 'الدور في موقع التحميل',
            'label_en' => 'Pickup Floor',
            'field_type' => 'number',
            'placeholder_ar' => 'مثال: 2',
            'placeholder_en' => 'Example: 2',
            'options_json' => null,
            'is_required' => 1,
            'is_active' => 1,
            'sort_order' => 2,
        ],
        [
            'field_key' => 'floors_to',
            'label_ar' => 'الدور في موقع التنزيل',
            'label_en' => 'Dropoff Floor',
            'field_type' => 'number',
            'placeholder_ar' => 'مثال: 4',
            'placeholder_en' => 'Example: 4',
            'options_json' => null,
            'is_required' => 1,
            'is_active' => 1,
            'sort_order' => 3,
        ],
        [
            'field_key' => 'elevator_from',
            'label_ar' => 'هل يوجد مصعد في موقع التحميل؟',
            'label_en' => 'Elevator at Pickup?',
            'field_type' => 'checkbox',
            'placeholder_ar' => null,
            'placeholder_en' => null,
            'options_json' => null,
            'is_required' => 0,
            'is_active' => 1,
            'sort_order' => 4,
        ],
        [
            'field_key' => 'elevator_to',
            'label_ar' => 'هل يوجد مصعد في موقع التنزيل؟',
            'label_en' => 'Elevator at Dropoff?',
            'field_type' => 'checkbox',
            'placeholder_ar' => null,
            'placeholder_en' => null,
            'options_json' => null,
            'is_required' => 0,
            'is_active' => 1,
            'sort_order' => 5,
        ],
        [
            'field_key' => 'needs_packing',
            'label_ar' => 'هل تحتاج خدمة تغليف؟',
            'label_en' => 'Needs Packing?',
            'field_type' => 'checkbox',
            'placeholder_ar' => null,
            'placeholder_en' => null,
            'options_json' => null,
            'is_required' => 0,
            'is_active' => 1,
            'sort_order' => 6,
        ],
        [
            'field_key' => 'estimated_items',
            'label_ar' => 'عدد القطع التقريبي',
            'label_en' => 'Estimated Items',
            'field_type' => 'number',
            'placeholder_ar' => 'مثال: 25',
            'placeholder_en' => 'Example: 25',
            'options_json' => null,
            'is_required' => 0,
            'is_active' => 1,
            'sort_order' => 7,
        ],
    ];

    foreach ($seedFields as $field) {
        db()->insert('furniture_request_fields', $field);
    }
}

/**
 * إزالة تكرارات المناطق بناءً على الاسم العربي.
 */
function specialDeduplicateFurnitureAreas(): void
{
    if (!specialServiceTableExists('furniture_areas')) {
        return;
    }

    $duplicates = db()->fetchAll(
        "SELECT name_ar, COUNT(*) AS rows_count
         FROM furniture_areas
         WHERE name_ar IS NOT NULL AND name_ar != ''
         GROUP BY name_ar
         HAVING COUNT(*) > 1"
    );

    foreach ($duplicates as $duplicate) {
        $nameAr = $duplicate['name_ar'] ?? '';
        if ($nameAr === '') {
            continue;
        }

        $rows = db()->fetchAll(
            'SELECT id FROM furniture_areas WHERE name_ar = ? ORDER BY id ASC',
            [$nameAr]
        );
        if (count($rows) <= 1) {
            continue;
        }

        $keepId = (int) ($rows[0]['id'] ?? 0);
        if ($keepId <= 0) {
            continue;
        }

        for ($i = 1; $i < count($rows); $i++) {
            $duplicateId = (int) ($rows[$i]['id'] ?? 0);
            if ($duplicateId <= 0) {
                continue;
            }

            if (specialServiceTableExists('furniture_requests')) {
                db()->query(
                    'UPDATE furniture_requests SET area_id = ?, area_name = ? WHERE area_id = ?',
                    [$keepId, $nameAr, $duplicateId]
                );
            }

            db()->delete('furniture_areas', 'id = ?', [$duplicateId]);
        }
    }
}

/**
 * خيارات حالات الطلبات الخاصة.
 */
function specialRequestStatusOptions(): array
{
    return [
        'new' => 'جديد',
        'reviewed' => 'تمت المراجعة',
        'quoted' => 'تم التسعير',
        'confirmed' => 'مؤكد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ];
}

/**
 * اسم حالة الطلب بالعربي.
 */
function specialRequestStatusLabel(string $status): string
{
    $options = specialRequestStatusOptions();
    return $options[$status] ?? $status;
}

/**
 * لون البادج الخاص بحالة الطلب.
 */
function specialRequestStatusBadgeClass(string $status): string
{
    $map = [
        'new' => 'badge-warning',
        'reviewed' => 'badge-info',
        'quoted' => 'badge-primary',
        'confirmed' => 'badge-success',
        'in_progress' => 'badge-secondary',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
    ];

    return $map[$status] ?? 'badge-secondary';
}

/**
 * تنظيف قيمة الحالة.
 */
function normalizeSpecialRequestStatus(string $status): string
{
    $status = trim($status);
    $options = specialRequestStatusOptions();

    if (!isset($options[$status])) {
        return 'new';
    }

    return $status;
}

/**
 * إنشاء رقم طلب فريد.
 */
function specialGenerateRequestNumber(string $prefix = 'REQ', string $tableName = ''): string
{
    $safePrefix = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $prefix));
    if ($safePrefix === '') {
        $safePrefix = 'REQ';
    }

    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

    for ($i = 0; $i < 6; $i++) {
        $number = $safePrefix . date('ymd') . generateCode(5, 'numeric');

        if ($table === '' || !specialServiceTableExists($table)) {
            return $number;
        }

        $exists = (int) db()->count($table, 'request_number = ?', [$number]);
        if ($exists === 0) {
            return $number;
        }
    }

    return $safePrefix . date('ymdHis') . generateCode(3, 'numeric');
}

/**
 * تحويل القيمة إلى رقم عشري موجب.
 */
function specialToPositiveFloat($value): float
{
    $parsed = (float) $value;
    if (!is_finite($parsed) || $parsed < 0) {
        return 0.0;
    }
    return $parsed;
}

/**
 * حساب السعر التقديري لخدمة مرنة تعتمد على:
 * - سعر أساسي
 * - سعر لكل كيلو
 * - سعر لكل متر
 * - حد أدنى
 */
function specialCalculateFlexiblePrice(
    float $basePrice,
    float $pricePerKg,
    float $pricePerMeter,
    float $minimumCharge,
    float $estimatedWeightKg,
    float $estimatedDistanceMeters
): float {
    $base = specialToPositiveFloat($basePrice);
    $perKg = specialToPositiveFloat($pricePerKg);
    $perMeter = specialToPositiveFloat($pricePerMeter);
    $minimum = specialToPositiveFloat($minimumCharge);
    $weight = specialToPositiveFloat($estimatedWeightKg);
    $distance = specialToPositiveFloat($estimatedDistanceMeters);

    $total = $base + ($perKg * $weight) + ($perMeter * $distance);
    if ($minimum > 0) {
        $total = max($total, $minimum);
    }

    return round($total, 2);
}

/**
 * حساب السعر الأساسي للحاويات حسب مدة الإيجار.
 */
function specialCalculateContainerRentalBase(array $serviceRow, int $durationDays, int $quantity): float
{
    $days = max(1, $durationDays);
    $qty = max(1, $quantity);

    $daily = specialToPositiveFloat($serviceRow['daily_price'] ?? 0);
    $weekly = specialToPositiveFloat($serviceRow['weekly_price'] ?? 0);
    $monthly = specialToPositiveFloat($serviceRow['monthly_price'] ?? 0);
    $deliveryFee = specialToPositiveFloat($serviceRow['delivery_fee'] ?? 0);

    if ($monthly > 0 && $days >= 30) {
        $base = ($monthly / 30) * $days;
    } elseif ($weekly > 0 && $days >= 7) {
        $base = ($weekly / 7) * $days;
    } else {
        $base = $daily * $days;
    }

    $baseTotal = ($base * $qty) + $deliveryFee;
    return round($baseTotal, 2);
}

/**
 * رصيد حساب متجر الحاويات = (إجمالي دائن - إجمالي مدين).
 */
function specialContainerStoreBalance(int $storeId): float
{
    $storeId = (int) $storeId;
    if ($storeId <= 0 || !specialServiceTableExists('container_store_account_entries')) {
        return 0.0;
    }

    $row = db()->fetch(
        "SELECT
            COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0) AS total_credit,
            COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0) AS total_debit
         FROM container_store_account_entries
         WHERE store_id = ?",
        [$storeId]
    );

    $credit = (float) ($row['total_credit'] ?? 0);
    $debit = (float) ($row['total_debit'] ?? 0);
    return round($credit - $debit, 2);
}

function specialResolveContainerRequestStoreId(array $requestRow): int
{
    $storeId = (int) ($requestRow['container_store_id'] ?? 0);
    if ($storeId > 0) {
        return $storeId;
    }

    $serviceId = (int) ($requestRow['container_service_id'] ?? 0);
    if ($serviceId <= 0 || !specialServiceTableExists('container_services') || !specialServiceColumnExists('container_services', 'store_id')) {
        return 0;
    }

    $serviceRow = db()->fetch('SELECT store_id FROM container_services WHERE id = ? LIMIT 1', [$serviceId]);
    return (int) ($serviceRow['store_id'] ?? 0);
}

function specialContainerRequestFinancialAmount(array $requestRow): float
{
    $finalPrice = specialToPositiveFloat($requestRow['final_price'] ?? 0);
    if ($finalPrice > 0) {
        return $finalPrice;
    }

    $estimatedPrice = specialToPositiveFloat($requestRow['estimated_price'] ?? 0);
    if ($estimatedPrice > 0) {
        return $estimatedPrice;
    }

    $orderTotal = specialToPositiveFloat($requestRow['order_total_amount'] ?? 0);
    if ($orderTotal > 0) {
        return $orderTotal;
    }

    return 0.0;
}

function specialSyncContainerStoreAccountEntryForRequest(int $requestId, ?int $adminId = null): bool
{
    $requestId = (int) $requestId;
    if ($requestId <= 0) {
        return false;
    }

    ensureSpecialServicesSchema();
    if (!specialServiceTableExists('container_requests') || !specialServiceTableExists('container_store_account_entries')) {
        return false;
    }

    $ordersJoin = '';
    $orderSelect = 'NULL AS order_total_amount, NULL AS order_number';
    if (specialServiceTableExists('orders') && specialServiceColumnExists('container_requests', 'source_order_id')) {
        $ordersJoin = 'LEFT JOIN orders o ON o.id = cr.source_order_id';
        $orderSelect = 'o.total_amount AS order_total_amount, o.order_number AS order_number';
    }

    $requestRow = db()->fetch(
        "SELECT cr.*, {$orderSelect}
         FROM container_requests cr
         {$ordersJoin}
         WHERE cr.id = ?
         LIMIT 1",
        [$requestId]
    );

    if (!$requestRow) {
        db()->delete(
            'container_store_account_entries',
            'source = ? AND reference_type = ? AND reference_id = ?',
            ['request', 'container_request', $requestId]
        );
        return false;
    }

    $storeId = specialResolveContainerRequestStoreId($requestRow);
    $amount = specialContainerRequestFinancialAmount($requestRow);

    if ($storeId <= 0 || $amount <= 0) {
        db()->delete(
            'container_store_account_entries',
            'source = ? AND reference_type = ? AND reference_id = ?',
            ['request', 'container_request', $requestId]
        );
        return false;
    }

    $requestNumber = trim((string) ($requestRow['request_number'] ?? ''));
    $orderNumber = trim((string) ($requestRow['order_number'] ?? ''));
    $status = specialRequestStatusLabel((string) ($requestRow['status'] ?? 'new'));
    $notes = 'مستحقات طلب حاوية';
    if ($requestNumber !== '') {
        $notes .= ' #' . $requestNumber;
    }
    if ($orderNumber !== '') {
        $notes .= ' / طلب التطبيق #' . $orderNumber;
    }
    $notes .= ' - الحالة: ' . $status;

    $existing = db()->fetch(
        "SELECT id
         FROM container_store_account_entries
         WHERE source = ?
           AND reference_type = ?
           AND reference_id = ?
         LIMIT 1",
        ['request', 'container_request', $requestId]
    );

    if (!empty($existing['id'])) {
        db()->update(
            'container_store_account_entries',
            [
                'store_id' => $storeId,
                'entry_type' => 'credit',
                'amount' => $amount,
                'notes' => $notes,
            ],
            'id = ?',
            [(int) $existing['id']]
        );
        return true;
    }

    db()->insert('container_store_account_entries', [
        'store_id' => $storeId,
        'entry_type' => 'credit',
        'amount' => $amount,
        'source' => 'request',
        'reference_type' => 'container_request',
        'reference_id' => $requestId,
        'notes' => $notes,
        'created_by' => ($adminId ?? 0) > 0 ? (int) $adminId : null,
    ]);

    return true;
}

function specialBackfillContainerStoreAccountEntries(int $limit = 1000): int
{
    ensureSpecialServicesSchema();
    if (!specialServiceTableExists('container_requests') || !specialServiceTableExists('container_store_account_entries')) {
        return 0;
    }

    $safeLimit = max(1, min(5000, (int) $limit));
    $rows = db()->fetchAll(
        "SELECT cr.id
         FROM container_requests cr
         LEFT JOIN container_services cs ON cs.id = cr.container_service_id
         WHERE COALESCE(cr.container_store_id, cs.store_id) IS NOT NULL
         ORDER BY cr.id DESC
         LIMIT {$safeLimit}"
    );

    $synced = 0;
    foreach ($rows as $row) {
        if (specialSyncContainerStoreAccountEntryForRequest((int) ($row['id'] ?? 0))) {
            $synced++;
        }
    }

    return $synced;
}

function specialRecalculateContainerStoreRating(int $storeId): void
{
    $storeId = (int) $storeId;
    if ($storeId <= 0 || !specialServiceTableExists('container_store_reviews') || !specialServiceTableExists('container_stores')) {
        return;
    }

    $row = db()->fetch(
        'SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS reviews_count
         FROM container_store_reviews
         WHERE store_id = ?',
        [$storeId]
    );

    $rating = round((float) ($row['avg_rating'] ?? 0), 2);
    $reviewsCount = (int) ($row['reviews_count'] ?? 0);

    $updates = [];
    $params = [];
    if (specialServiceColumnExists('container_stores', 'rating')) {
        $updates[] = 'rating = ?';
        $params[] = $rating;
    }
    if (specialServiceColumnExists('container_stores', 'reviews_count')) {
        $updates[] = 'reviews_count = ?';
        $params[] = $reviewsCount;
    }
    if (empty($updates)) {
        return;
    }

    $params[] = $storeId;
    db()->query('UPDATE container_stores SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);
}

/**
 * فك ترميز JSON لتفاصيل المشكلة.
 */
function specialDecodeOrderProblemDetailsPayload($problemDetailsRaw): array
{
    if (is_array($problemDetailsRaw)) {
        return $problemDetailsRaw;
    }

    if (!is_string($problemDetailsRaw)) {
        return [];
    }

    $trimmed = trim($problemDetailsRaw);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * تحديد نوع الطلب الخاص (حاويات/عفش) من تفاصيل المشكلة.
 */
function specialDetectOrderModuleFromProblemDetails($problemDetailsRaw): string
{
    $details = specialDecodeOrderProblemDetailsPayload($problemDetailsRaw);
    if (empty($details)) {
        return '';
    }

    $module = strtolower(trim((string) ($details['module'] ?? '')));
    $type = strtolower(trim((string) ($details['type'] ?? '')));

    if (
        isset($details['container_request'])
        || strpos($module, 'container') !== false
        || strpos($type, 'container') !== false
    ) {
        return 'container';
    }

    if (
        isset($details['furniture_request'])
        || strpos($module, 'furniture') !== false
        || strpos($type, 'furniture') !== false
    ) {
        return 'furniture';
    }

    return '';
}

/**
 * مواءمة حالة orders إلى حالات الطلبات الخاصة.
 */
function specialMapOrderStatusToRequestStatus($orderStatus): string
{
    $status = strtolower(trim((string) $orderStatus));
    switch ($status) {
        case 'completed':
            return 'completed';
        case 'cancelled':
            return 'cancelled';
        case 'in_progress':
        case 'arrived':
        case 'on_the_way':
            return 'in_progress';
        case 'assigned':
        case 'accepted':
            return 'confirmed';
        default:
            return 'new';
    }
}

/**
 * تحويل أي تاريخ إلى صيغة YYYY-MM-DD عند الإمكان.
 */
function specialNormalizeDateValue($value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

/**
 * توحيد تواريخ إيجار الحاويات: تاريخ النهاية يجب أن يكون بعد البداية.
 */
function specialNormalizeContainerRentalDates($startDate, $endDate, $durationDays = 1): array
{
    $durationDays = max(1, (int) $durationDays);
    $normalizedStart = specialNormalizeDateValue($startDate);
    $normalizedEnd = specialNormalizeDateValue($endDate);

    if ($normalizedStart === null) {
        return [
            'start_date' => null,
            'end_date' => $normalizedEnd,
            'duration_days' => $durationDays,
        ];
    }

    $startTimestamp = strtotime($normalizedStart);
    $endTimestamp = $normalizedEnd !== null ? strtotime($normalizedEnd) : false;

    if ($startTimestamp === false) {
        return [
            'start_date' => null,
            'end_date' => $normalizedEnd,
            'duration_days' => $durationDays,
        ];
    }

    if ($endTimestamp === false || $endTimestamp <= $startTimestamp) {
        $normalizedEnd = date('Y-m-d', strtotime('+' . $durationDays . ' days', $startTimestamp));
    } else {
        $durationDays = max(1, (int) floor(($endTimestamp - $startTimestamp) / 86400));
    }

    return [
        'start_date' => $normalizedStart,
        'end_date' => $normalizedEnd,
        'duration_days' => $durationDays,
    ];
}

/**
 * ترحيل تلقائي لطلبات الحاويات/العفش القديمة من orders إلى الجداول المخصصة.
 * يفيد عند وجود بيانات تاريخية قبل الفصل بين الصفحات.
 */
function specialBackfillSpecialRequestsFromOrders(int $limit = 250): array
{
    ensureSpecialServicesSchema();

    $result = ['container' => 0, 'furniture' => 0];
    if (!specialServiceTableExists('orders') || !specialServiceColumnExists('orders', 'problem_details')) {
        return $result;
    }
    if (!specialServiceTableExists('users')) {
        return $result;
    }

    $syncClauses = [];

    if (specialServiceTableExists('container_requests')) {
        $containerClause = "(
            o.problem_details LIKE '%\"module\":\"container_rental\"%'
            OR o.problem_details LIKE '%\"type\":\"container_rental\"%'
            OR o.problem_details LIKE '%\"container_request\"%'
        )";
        if (specialServiceColumnExists('container_requests', 'source_order_id')) {
            $containerClause .= " AND NOT EXISTS (
                SELECT 1
                FROM container_requests cr
                WHERE cr.source_order_id = o.id
            )";
        }
        $syncClauses[] = '(' . $containerClause . ')';
    }

    if (specialServiceTableExists('furniture_requests')) {
        $furnitureClause = "(
            o.problem_details LIKE '%\"module\":\"furniture_moving\"%'
            OR o.problem_details LIKE '%\"type\":\"furniture_moving\"%'
            OR o.problem_details LIKE '%\"furniture_request\"%'
        )";
        if (specialServiceColumnExists('furniture_requests', 'source_order_id')) {
            $furnitureClause .= " AND NOT EXISTS (
                SELECT 1
                FROM furniture_requests fr
                WHERE fr.source_order_id = o.id
            )";
        }
        $syncClauses[] = '(' . $furnitureClause . ')';
    }

    if (empty($syncClauses)) {
        return $result;
    }

    $safeLimit = max(1, min(1000, (int) $limit));
    $orders = db()->fetchAll(
        "SELECT o.*, u.full_name AS user_name, u.phone AS user_phone
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.problem_details IS NOT NULL
           AND o.problem_details <> ''
           AND (" . implode(' OR ', $syncClauses) . ")
         ORDER BY o.id ASC
         LIMIT {$safeLimit}"
    );

    foreach ($orders as $order) {
        $orderId = (int) ($order['id'] ?? 0);
        $userId = (int) ($order['user_id'] ?? 0);
        if ($orderId <= 0 || $userId <= 0) {
            continue;
        }

        $problemDetails = specialDecodeOrderProblemDetailsPayload($order['problem_details'] ?? null);
        if (empty($problemDetails)) {
            continue;
        }

        $module = specialDetectOrderModuleFromProblemDetails($problemDetails);
        if ($module === '') {
            continue;
        }

        $requestStatus = specialMapOrderStatusToRequestStatus($order['status'] ?? '');
        $requestStatus = normalizeSpecialRequestStatus($requestStatus);
        $orderTotalAmount = specialToPositiveFloat($order['total_amount'] ?? 0);
        $orderNotes = trim((string) ($order['notes'] ?? ''));
        $orderAddress = trim((string) ($order['address'] ?? ''));
        $userName = trim((string) ($order['user_name'] ?? ''));
        $userPhone = trim((string) ($order['user_phone'] ?? ''));
        if ($userName === '') {
            $userName = 'عميل #' . $userId;
        }

        if ($module === 'container' && specialServiceTableExists('container_requests')) {
            if (specialServiceColumnExists('container_requests', 'source_order_id')) {
                $exists = (int) db()->count('container_requests', 'source_order_id = ?', [$orderId]);
                if ($exists > 0) {
                    continue;
                }
            }

            $containerRequest = $problemDetails['container_request'] ?? [];
            if ($containerRequest instanceof stdClass) {
                $containerRequest = (array) $containerRequest;
            }
            if (!is_array($containerRequest)) {
                $containerRequest = [];
            }

            $serviceId = (int) ($containerRequest['container_service_id'] ?? 0);
            if ($serviceId <= 0) {
                $subServices = $problemDetails['service_type_ids'] ?? ($problemDetails['sub_services'] ?? []);
                if ($subServices instanceof stdClass) {
                    $subServices = (array) $subServices;
                }
                if (is_array($subServices)) {
                    foreach ($subServices as $candidateId) {
                        $candidate = (int) $candidateId;
                        if ($candidate > 0) {
                            $serviceId = $candidate;
                            break;
                        }
                    }
                }
            }

            $serviceRow = null;
            if ($serviceId > 0 && specialServiceTableExists('container_services')) {
                $serviceRow = db()->fetch(
                    'SELECT id, store_id, daily_price, weekly_price, monthly_price, delivery_fee, price_per_kg, price_per_meter, minimum_charge
                     FROM container_services
                     WHERE id = ? LIMIT 1',
                    [$serviceId]
                );
            }

            $storeId = (int) ($containerRequest['container_store_id'] ?? 0);
            if ($storeId <= 0 && is_array($serviceRow)) {
                $storeId = (int) ($serviceRow['store_id'] ?? 0);
            }
            if ($storeId > 0 && specialServiceTableExists('container_stores')) {
                $storeExists = db()->fetch('SELECT id FROM container_stores WHERE id = ? LIMIT 1', [$storeId]);
                if (!$storeExists) {
                    $storeId = 0;
                }
            } else {
                $storeId = 0;
            }

            $durationDays = max(1, (int) ($containerRequest['duration_days'] ?? 1));
            $containerDates = specialNormalizeContainerRentalDates(
                $containerRequest['start_date'] ?? ($order['scheduled_date'] ?? null),
                $containerRequest['end_date'] ?? null,
                $durationDays
            );
            $durationDays = (int) $containerDates['duration_days'];
            $quantity = max(1, (int) ($containerRequest['quantity'] ?? 1));
            $weightKg = specialToPositiveFloat($containerRequest['estimated_weight_kg'] ?? 0);
            $distanceMeters = specialToPositiveFloat($containerRequest['estimated_distance_meters'] ?? 0);

            $estimatedPrice = specialToPositiveFloat($containerRequest['estimated_price'] ?? 0);
            if ($estimatedPrice <= 0 && $orderTotalAmount > 0) {
                $estimatedPrice = $orderTotalAmount;
            }
            if ($estimatedPrice <= 0 && is_array($serviceRow)) {
                $basePrice = specialCalculateContainerRentalBase($serviceRow, $durationDays, $quantity);
                $estimatedPrice = specialCalculateFlexiblePrice(
                    $basePrice,
                    specialToPositiveFloat($serviceRow['price_per_kg'] ?? 0),
                    specialToPositiveFloat($serviceRow['price_per_meter'] ?? 0),
                    specialToPositiveFloat($serviceRow['minimum_charge'] ?? 0),
                    $weightKg,
                    $distanceMeters
                );
            }

            $finalPrice = null;
            $containerFinal = specialToPositiveFloat($containerRequest['final_price'] ?? 0);
            if ($requestStatus === 'completed') {
                if ($containerFinal > 0) {
                    $finalPrice = $containerFinal;
                } elseif ($orderTotalAmount > 0) {
                    $finalPrice = $orderTotalAmount;
                }
            }

            $detailsPayload = [
                'source' => 'orders_backfill',
                'source_order_id' => $orderId,
                'source_order_number' => $order['order_number'] ?? null,
                'problem_details' => $problemDetails,
            ];

            $insertData = [
                'request_number' => specialGenerateRequestNumber('CT', 'container_requests'),
                'user_id' => $userId,
                'container_service_id' => $serviceId > 0 ? $serviceId : null,
                'container_store_id' => $storeId > 0 ? $storeId : null,
                'customer_name' => $userName,
                'phone' => $userPhone,
                'site_city' => trim((string) ($containerRequest['site_city'] ?? '')),
                'site_address' => trim((string) ($containerRequest['site_address'] ?? $orderAddress)),
                'start_date' => $containerDates['start_date'],
                'end_date' => $containerDates['end_date'],
                'duration_days' => $durationDays,
                'quantity' => $quantity,
                'needs_loading_help' => !empty($containerRequest['needs_loading_help']) ? 1 : 0,
                'needs_operator' => !empty($containerRequest['needs_operator']) ? 1 : 0,
                'purpose' => trim((string) ($containerRequest['purpose'] ?? '')),
                'notes' => trim((string) ($containerRequest['notes'] ?? $orderNotes)),
                'status' => $requestStatus,
                'estimated_price' => $estimatedPrice > 0 ? $estimatedPrice : null,
                'final_price' => $finalPrice,
                'estimated_weight_kg' => $weightKg > 0 ? $weightKg : null,
                'estimated_distance_meters' => $distanceMeters > 0 ? $distanceMeters : null,
                'details_json' => json_encode($detailsPayload, JSON_UNESCAPED_UNICODE),
                'source_order_id' => $orderId,
            ];

            $newRequestId = (int) db()->insert('container_requests', $insertData);
            specialSyncContainerStoreAccountEntryForRequest($newRequestId);

            // Notify admins about new container request
            try {
                require_once __DIR__ . '/notification_service.php';
                ensureNotificationSchema();
                notifyAdminNewContainerRequest($newRequestId, [
                    'request_number'  => $insertData['request_number'],
                    'customer_name'   => $userName,
                    'phone'           => $userPhone,
                    'site_address'    => $insertData['site_address'] ?? $orderAddress,
                ]);
            } catch (Throwable $notifErr) {
                error_log('Container request notification error: ' . $notifErr->getMessage());
            }

            $result['container']++;
            continue;
        }

        if ($module === 'furniture' && specialServiceTableExists('furniture_requests')) {
            if (specialServiceColumnExists('furniture_requests', 'source_order_id')) {
                $exists = (int) db()->count('furniture_requests', 'source_order_id = ?', [$orderId]);
                if ($exists > 0) {
                    continue;
                }
            }

            $furnitureRequest = $problemDetails['furniture_request'] ?? [];
            if ($furnitureRequest instanceof stdClass) {
                $furnitureRequest = (array) $furnitureRequest;
            }
            if (!is_array($furnitureRequest)) {
                $furnitureRequest = [];
            }

            $serviceId = (int) ($furnitureRequest['service_id'] ?? 0);
            if ($serviceId <= 0) {
                $subServices = $problemDetails['service_type_ids'] ?? ($problemDetails['sub_services'] ?? []);
                if ($subServices instanceof stdClass) {
                    $subServices = (array) $subServices;
                }
                if (is_array($subServices)) {
                    foreach ($subServices as $candidateId) {
                        $candidate = (int) $candidateId;
                        if ($candidate > 0) {
                            $serviceId = $candidate;
                            break;
                        }
                    }
                }
            }

            $areaId = (int) ($furnitureRequest['area_id'] ?? ($problemDetails['area_id'] ?? 0));
            $areaName = trim((string) ($furnitureRequest['area_name'] ?? ($problemDetails['area_name'] ?? '')));
            if ($areaId > 0 && specialServiceTableExists('furniture_areas')) {
                $areaRow = db()->fetch('SELECT name_ar FROM furniture_areas WHERE id = ? LIMIT 1', [$areaId]);
                if ($areaRow && trim((string) ($areaRow['name_ar'] ?? '')) !== '') {
                    $areaName = trim((string) $areaRow['name_ar']);
                }
            } else {
                $areaId = 0;
            }

            $estimatedWeightKg = specialToPositiveFloat(
                $furnitureRequest['estimated_weight_kg']
                ?? $problemDetails['estimated_weight_kg']
                ?? 0
            );
            $estimatedDistanceMeters = specialToPositiveFloat(
                $furnitureRequest['estimated_distance_meters']
                ?? $problemDetails['estimated_distance_meters']
                ?? 0
            );

            $estimatedPrice = specialToPositiveFloat($furnitureRequest['estimated_price'] ?? 0);
            if ($estimatedPrice <= 0 && $orderTotalAmount > 0) {
                $estimatedPrice = $orderTotalAmount;
            }

            $finalPrice = null;
            $furnitureFinal = specialToPositiveFloat($furnitureRequest['final_price'] ?? 0);
            if ($requestStatus === 'completed') {
                if ($furnitureFinal > 0) {
                    $finalPrice = $furnitureFinal;
                } elseif ($orderTotalAmount > 0) {
                    $finalPrice = $orderTotalAmount;
                }
            }

            $detailsPayload = [
                'source' => 'orders_backfill',
                'source_order_id' => $orderId,
                'source_order_number' => $order['order_number'] ?? null,
                'problem_details' => $problemDetails,
            ];

            $insertData = [
                'request_number' => specialGenerateRequestNumber('FM', 'furniture_requests'),
                'user_id' => $userId,
                'service_id' => $serviceId > 0 ? $serviceId : null,
                'area_id' => $areaId > 0 ? $areaId : null,
                'area_name' => $areaName !== '' ? $areaName : null,
                'customer_name' => $userName,
                'phone' => $userPhone,
                'pickup_city' => trim((string) ($furnitureRequest['pickup_city'] ?? '')),
                'pickup_address' => trim((string) ($furnitureRequest['pickup_address'] ?? $orderAddress)),
                'dropoff_city' => trim((string) ($furnitureRequest['dropoff_city'] ?? '')),
                'dropoff_address' => trim((string) ($furnitureRequest['dropoff_address'] ?? '')),
                'move_date' => specialNormalizeDateValue($furnitureRequest['move_date'] ?? ($order['scheduled_date'] ?? null)),
                'preferred_time' => trim((string) ($furnitureRequest['preferred_time'] ?? ($order['scheduled_time'] ?? ''))),
                'rooms_count' => max(1, (int) ($furnitureRequest['rooms_count'] ?? ($problemDetails['rooms_count'] ?? 1))),
                'floors_from' => max(0, (int) ($furnitureRequest['floors_from'] ?? ($problemDetails['floors_from'] ?? 0))),
                'floors_to' => max(0, (int) ($furnitureRequest['floors_to'] ?? ($problemDetails['floors_to'] ?? 0))),
                'elevator_from' => !empty($furnitureRequest['elevator_from']) ? 1 : 0,
                'elevator_to' => !empty($furnitureRequest['elevator_to']) ? 1 : 0,
                'needs_packing' => !empty($furnitureRequest['needs_packing']) ? 1 : 0,
                'estimated_items' => max(0, (int) ($furnitureRequest['estimated_items'] ?? ($problemDetails['estimated_items'] ?? 0))),
                'details_json' => json_encode($detailsPayload, JSON_UNESCAPED_UNICODE),
                'notes' => trim((string) ($furnitureRequest['notes'] ?? $orderNotes)),
                'status' => $requestStatus,
                'estimated_price' => $estimatedPrice > 0 ? $estimatedPrice : null,
                'final_price' => $finalPrice,
                'estimated_weight_kg' => $estimatedWeightKg > 0 ? $estimatedWeightKg : null,
                'estimated_distance_meters' => $estimatedDistanceMeters > 0 ? $estimatedDistanceMeters : null,
                'source_order_id' => $orderId,
            ];

            db()->insert('furniture_requests', $insertData);
            $result['furniture']++;
        }
    }

    return $result;
}
