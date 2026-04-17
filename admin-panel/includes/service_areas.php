<?php
/**
 * Service Areas Helpers
 * إدارة نطاقات تقديم الخدمة حسب GPS
 */

if (!function_exists('serviceAreaNormalizeCountryCode')) {
    function serviceAreaNormalizeCountryCode($value): string
    {
        $code = strtoupper(trim((string) $value));
        if ($code === '' || $code === 'NULL' || $code === '-') {
            return '';
        }

        return preg_replace('/[^A-Z]/', '', $code);
    }
}

if (!function_exists('serviceAreaTableExists')) {
    function serviceAreaTableExists(string $table): bool
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

if (!function_exists('serviceAreaColumnExists')) {
    function serviceAreaColumnExists(string $table, string $column): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($safeTable === '' || $safeColumn === '') {
            return false;
        }

        $quotedColumn = db()->getConnection()->quote($safeColumn);
        $row = db()->fetch("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
        return !empty($row);
    }
}

if (!function_exists('serviceAreaIndexExists')) {
    function serviceAreaIndexExists(string $table, string $indexName): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
        if ($safeTable === '' || $safeIndex === '') {
            return false;
        }

        $quotedIndex = db()->getConnection()->quote($safeIndex);
        $rows = db()->fetchAll("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = {$quotedIndex}");
        return !empty($rows);
    }
}

if (!function_exists('serviceAreaEnsureSchema')) {
    function serviceAreaEnsureSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        db()->query("CREATE TABLE IF NOT EXISTS `service_areas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `name_en` VARCHAR(150) NULL,
            `name_ur` VARCHAR(150) NULL,
            `country_code` VARCHAR(8) NOT NULL DEFAULT 'SA',
            `city_name` VARCHAR(120) NULL,
            `city_name_en` VARCHAR(120) NULL,
            `city_name_ur` VARCHAR(120) NULL,
            `village_name` VARCHAR(120) NULL,
            `village_name_en` VARCHAR(120) NULL,
            `village_name_ur` VARCHAR(120) NULL,
            `geometry_type` ENUM('circle','polygon') NOT NULL DEFAULT 'circle',
            `center_lat` DECIMAL(10,8) NULL,
            `center_lng` DECIMAL(11,8) NULL,
            `radius_km` DECIMAL(8,3) NULL,
            `polygon_json` LONGTEXT NULL,
            `notes` VARCHAR(255) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `priority` INT NOT NULL DEFAULT 0,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_service_areas_country` (`country_code`),
            INDEX `idx_service_areas_active` (`is_active`),
            INDEX `idx_service_areas_priority` (`priority`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = [
            'name_en' => 'VARCHAR(150) NULL',
            'name_ur' => 'VARCHAR(150) NULL',
            'country_code' => "VARCHAR(8) NOT NULL DEFAULT 'SA'",
            'city_name' => 'VARCHAR(120) NULL',
            'city_name_en' => 'VARCHAR(120) NULL',
            'city_name_ur' => 'VARCHAR(120) NULL',
            'village_name' => 'VARCHAR(120) NULL',
            'village_name_en' => 'VARCHAR(120) NULL',
            'village_name_ur' => 'VARCHAR(120) NULL',
            'geometry_type' => "ENUM('circle','polygon') NOT NULL DEFAULT 'circle'",
            'center_lat' => 'DECIMAL(10,8) NULL',
            'center_lng' => 'DECIMAL(11,8) NULL',
            'radius_km' => 'DECIMAL(8,3) NULL',
            'polygon_json' => 'LONGTEXT NULL',
            'notes' => 'VARCHAR(255) NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'priority' => 'INT NOT NULL DEFAULT 0',
            'created_by' => 'INT NULL',
            'updated_by' => 'INT NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($columns as $column => $definition) {
            if (!serviceAreaColumnExists('service_areas', $column)) {
                db()->query("ALTER TABLE `service_areas` ADD COLUMN `{$column}` {$definition}");
            }
        }

        if (!serviceAreaIndexExists('service_areas', 'idx_service_areas_country')) {
            db()->query("ALTER TABLE `service_areas` ADD INDEX `idx_service_areas_country` (`country_code`)");
        }
        if (!serviceAreaIndexExists('service_areas', 'idx_service_areas_active')) {
            db()->query("ALTER TABLE `service_areas` ADD INDEX `idx_service_areas_active` (`is_active`)");
        }
        if (!serviceAreaIndexExists('service_areas', 'idx_service_areas_priority')) {
            db()->query("ALTER TABLE `service_areas` ADD INDEX `idx_service_areas_priority` (`priority`)");
        }
    }
}

if (!function_exists('serviceAreaEnsureServiceLinksSchema')) {
    function serviceAreaEnsureServiceLinksSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        serviceAreaEnsureSchema();

        db()->query("CREATE TABLE IF NOT EXISTS `service_area_services` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `service_area_id` INT NOT NULL,
            `service_id` INT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_service_area_service` (`service_area_id`, `service_id`),
            INDEX `idx_sas_service_area` (`service_area_id`),
            INDEX `idx_sas_service` (`service_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!serviceAreaColumnExists('service_area_services', 'service_area_id')) {
            db()->query("ALTER TABLE `service_area_services` ADD COLUMN `service_area_id` INT NOT NULL");
        }
        if (!serviceAreaColumnExists('service_area_services', 'service_id')) {
            db()->query("ALTER TABLE `service_area_services` ADD COLUMN `service_id` INT NOT NULL");
        }
        if (!serviceAreaColumnExists('service_area_services', 'created_at')) {
            db()->query("ALTER TABLE `service_area_services` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!serviceAreaIndexExists('service_area_services', 'idx_sas_service_area')) {
            db()->query("ALTER TABLE `service_area_services` ADD INDEX `idx_sas_service_area` (`service_area_id`)");
        }
        if (!serviceAreaIndexExists('service_area_services', 'idx_sas_service')) {
            db()->query("ALTER TABLE `service_area_services` ADD INDEX `idx_sas_service` (`service_id`)");
        }
        if (!serviceAreaIndexExists('service_area_services', 'uniq_service_area_service')) {
            db()->query("ALTER TABLE `service_area_services` ADD UNIQUE KEY `uniq_service_area_service` (`service_area_id`, `service_id`)");
        }
    }
}

if (!function_exists('serviceAreaParseCountryList')) {
    function serviceAreaParseCountryList($rawValue): array
    {
        $raw = trim((string) $rawValue);
        if ($raw === '') {
            return [];
        }

        $items = [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/[,\|;\n\r،]+/u', $raw) ?: [];
        }

        $countries = [];
        foreach ($items as $item) {
            $code = serviceAreaNormalizeCountryCode($item);
            if ($code === '') {
                continue;
            }
            $countries[$code] = $code;
        }

        return array_values($countries);
    }
}

if (!function_exists('serviceAreaSupportedCountries')) {
    function serviceAreaSupportedCountries(): array
    {
        $default = ['SA'];
        if (!serviceAreaTableExists('app_settings')) {
            return $default;
        }

        $row = db()->fetch("SELECT setting_value FROM app_settings WHERE setting_key = 'supported_countries' LIMIT 1");
        $raw = trim((string) ($row['setting_value'] ?? ''));
        if ($raw === '') {
            return $default;
        }

        $countries = serviceAreaParseCountryList($raw);

        return !empty($countries) ? array_values(array_unique($countries)) : $default;
    }
}

if (!function_exists('serviceAreaDecodePolygon')) {
    function serviceAreaDecodePolygon($polygonRaw): array
    {
        if (is_array($polygonRaw)) {
            $decoded = $polygonRaw;
        } else {
            $decoded = json_decode((string) $polygonRaw, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        $points = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $lat = isset($item['lat']) ? (float) $item['lat'] : null;
            $lng = isset($item['lng']) ? (float) $item['lng'] : null;
            if ($lat === null || $lng === null) {
                continue;
            }
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }
            $points[] = ['lat' => $lat, 'lng' => $lng];
        }

        return $points;
    }
}

if (!function_exists('serviceAreaDistanceKm')) {
    function serviceAreaDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }
}

if (!function_exists('serviceAreaPointInPolygon')) {
    function serviceAreaPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $count = count($polygon);
        if ($count < 3) {
            return false;
        }

        $inside = false;
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $latI = (float) $polygon[$i]['lat'];
            $lngI = (float) $polygon[$i]['lng'];
            $latJ = (float) $polygon[$j]['lat'];
            $lngJ = (float) $polygon[$j]['lng'];

            $intersects = (($latI > $lat) !== ($latJ > $lat))
                && ($lng < (($lngJ - $lngI) * ($lat - $latI) / (($latJ - $latI) ?: 0.00000001) + $lngI));
            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}

if (!function_exists('serviceAreaComputeCenter')) {
    function serviceAreaComputeCenter(array $area): ?array
    {
        $centerLat = isset($area['center_lat']) && $area['center_lat'] !== null
            ? (float) $area['center_lat']
            : null;
        $centerLng = isset($area['center_lng']) && $area['center_lng'] !== null
            ? (float) $area['center_lng']
            : null;

        if ($centerLat !== null && $centerLng !== null) {
            return ['lat' => $centerLat, 'lng' => $centerLng];
        }

        $polygon = serviceAreaDecodePolygon($area['polygon_json'] ?? null);
        if (empty($polygon)) {
            return null;
        }

        $sumLat = 0.0;
        $sumLng = 0.0;
        foreach ($polygon as $point) {
            $sumLat += (float) $point['lat'];
            $sumLng += (float) $point['lng'];
        }

        $count = count($polygon);
        return [
            'lat' => $sumLat / $count,
            'lng' => $sumLng / $count,
        ];
    }
}

if (!function_exists('serviceAreaPointInArea')) {
    function serviceAreaPointInArea(float $lat, float $lng, array $area): bool
    {
        $geometryType = strtolower(trim((string) ($area['geometry_type'] ?? 'circle')));

        if ($geometryType === 'polygon') {
            $polygon = serviceAreaDecodePolygon($area['polygon_json'] ?? null);
            if (!empty($polygon) && serviceAreaPointInPolygon($lat, $lng, $polygon)) {
                return true;
            }
        }

        $center = serviceAreaComputeCenter($area);
        $radius = isset($area['radius_km']) ? (float) $area['radius_km'] : 0.0;
        if ($center === null || $radius <= 0) {
            return false;
        }

        return serviceAreaDistanceKm($lat, $lng, (float) $center['lat'], (float) $center['lng']) <= $radius;
    }
}

if (!function_exists('serviceAreaFetchActiveAreas')) {
    function serviceAreaFetchActiveAreas(?string $countryCode = null): array
    {
        serviceAreaEnsureSchema();

        $country = serviceAreaNormalizeCountryCode($countryCode ?? '');
        if ($country !== '') {
            return db()->fetchAll(
                "SELECT * FROM service_areas WHERE is_active = 1 AND country_code = ? ORDER BY priority ASC, id ASC",
                [$country]
            );
        }

        return db()->fetchAll(
            "SELECT * FROM service_areas WHERE is_active = 1 ORDER BY priority ASC, id ASC"
        );
    }
}

if (!function_exists('serviceAreaSummarizeArea')) {
    function serviceAreaSummarizeArea(array $area): array
    {
        $center = serviceAreaComputeCenter($area);
        $name = (string) ($area['name'] ?? '');
        $nameEn = trim((string) ($area['name_en'] ?? ''));
        if ($nameEn === '') {
            $nameEn = $name;
        }
        $nameUr = trim((string) ($area['name_ur'] ?? ''));
        if ($nameUr === '') {
            $nameUr = $nameEn !== '' ? $nameEn : $name;
        }
        $cityName = trim((string) ($area['city_name'] ?? ''));
        $cityNameEn = trim((string) ($area['city_name_en'] ?? ''));
        if ($cityNameEn === '') {
            $cityNameEn = $cityName;
        }
        $cityNameUr = trim((string) ($area['city_name_ur'] ?? ''));
        if ($cityNameUr === '') {
            $cityNameUr = $cityNameEn !== '' ? $cityNameEn : $cityName;
        }
        $villageName = trim((string) ($area['village_name'] ?? ''));
        $villageNameEn = trim((string) ($area['village_name_en'] ?? ''));
        if ($villageNameEn === '') {
            $villageNameEn = $villageName;
        }
        $villageNameUr = trim((string) ($area['village_name_ur'] ?? ''));
        if ($villageNameUr === '') {
            $villageNameUr = $villageNameEn !== '' ? $villageNameEn : $villageName;
        }

        return [
            'id' => (int) ($area['id'] ?? 0),
            'name' => $name,
            'name_en' => $nameEn,
            'name_ur' => $nameUr,
            'country_code' => serviceAreaNormalizeCountryCode($area['country_code'] ?? ''),
            'city_name' => $cityName,
            'city_name_en' => $cityNameEn,
            'city_name_ur' => $cityNameUr,
            'village_name' => $villageName,
            'village_name_en' => $villageNameEn,
            'village_name_ur' => $villageNameUr,
            'geometry_type' => (string) ($area['geometry_type'] ?? 'circle'),
            'radius_km' => isset($area['radius_km']) ? (float) $area['radius_km'] : null,
            'center_lat' => $center['lat'] ?? null,
            'center_lng' => $center['lng'] ?? null,
        ];
    }
}

if (!function_exists('serviceAreaEvaluateCoverage')) {
    function serviceAreaEvaluateCoverage($countryCode = '', $lat = null, $lng = null): array
    {
        serviceAreaEnsureSchema();

        $country = serviceAreaNormalizeCountryCode($countryCode);
        $supportedCountries = serviceAreaSupportedCountries();
        $countrySupported = $country === '' || empty($supportedCountries) || in_array($country, $supportedCountries, true);

        if (!$countrySupported) {
            return [
                'is_supported' => false,
                'reason' => 'country_not_supported',
                'requested_country' => $country,
                'supported_countries' => $supportedCountries,
                'message_ar' => 'أنت خارج نطاق تقديم الخدمة',
                'message_en' => 'You are outside the service coverage area.',
                'matched_area' => null,
                'has_active_service_areas' => false,
                'active_service_areas_count' => 0,
            ];
        }

        $latValue = is_numeric($lat) ? (float) $lat : null;
        $lngValue = is_numeric($lng) ? (float) $lng : null;
        $hasPoint = $latValue !== null && $lngValue !== null
            && $latValue >= -90 && $latValue <= 90
            && $lngValue >= -180 && $lngValue <= 180;

        $areas = serviceAreaFetchActiveAreas($country !== '' ? $country : null);
        $hasAreas = !empty($areas);

        if (!$hasAreas) {
            return [
                'is_supported' => true,
                'reason' => 'no_service_areas_defined',
                'requested_country' => $country,
                'supported_countries' => $supportedCountries,
                'message_ar' => '',
                'message_en' => '',
                'matched_area' => null,
                'has_active_service_areas' => false,
                'active_service_areas_count' => 0,
            ];
        }

        if (!$hasPoint) {
            return [
                'is_supported' => false,
                'reason' => 'missing_coordinates',
                'requested_country' => $country,
                'supported_countries' => $supportedCountries,
                'message_ar' => 'يرجى تفعيل الموقع لتحديد نطاق الخدمة',
                'message_en' => 'Please enable location to validate service coverage.',
                'matched_area' => null,
                'has_active_service_areas' => true,
                'active_service_areas_count' => count($areas),
            ];
        }

        foreach ($areas as $area) {
            if (serviceAreaPointInArea($latValue, $lngValue, $area)) {
                return [
                    'is_supported' => true,
                    'reason' => 'inside_service_area',
                    'requested_country' => $country,
                    'supported_countries' => $supportedCountries,
                    'message_ar' => '',
                    'message_en' => '',
                    'matched_area' => serviceAreaSummarizeArea($area),
                    'has_active_service_areas' => true,
                    'active_service_areas_count' => count($areas),
                ];
            }
        }

        return [
            'is_supported' => false,
            'reason' => 'outside_service_area',
            'requested_country' => $country,
            'supported_countries' => $supportedCountries,
            'message_ar' => 'أنت خارج نطاق تقديم الخدمة',
            'message_en' => 'You are outside the service coverage area.',
            'matched_area' => null,
            'has_active_service_areas' => true,
            'active_service_areas_count' => count($areas),
        ];
    }
}

if (!function_exists('serviceAreaProviderCoordinateColumns')) {
    function serviceAreaProviderCoordinateColumns(): array
    {
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        $columns = [
            'current_lat' => serviceAreaColumnExists('providers', 'current_lat'),
            'current_lng' => serviceAreaColumnExists('providers', 'current_lng'),
            'lat' => serviceAreaColumnExists('providers', 'lat'),
            'lng' => serviceAreaColumnExists('providers', 'lng'),
            'is_available' => serviceAreaColumnExists('providers', 'is_available'),
        ];
        return $columns;
    }
}

if (!function_exists('serviceAreaFetchProviders')) {
    function serviceAreaFetchProviders(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        if (!serviceAreaTableExists('providers')) {
            $cached = [];
            return $cached;
        }

        $columns = serviceAreaProviderCoordinateColumns();
        $select = [
            'id',
            'full_name',
            'phone',
            'city',
            'status',
            'rating',
        ];

        if ($columns['is_available']) {
            $select[] = 'is_available';
        }
        if ($columns['current_lat']) {
            $select[] = 'current_lat';
        }
        if ($columns['current_lng']) {
            $select[] = 'current_lng';
        }
        if ($columns['lat']) {
            $select[] = 'lat';
        }
        if ($columns['lng']) {
            $select[] = 'lng';
        }

        $rows = db()->fetchAll(
            "SELECT " . implode(', ', $select) . " FROM providers WHERE status = 'approved' ORDER BY rating DESC, id ASC"
        );

        $providers = [];
        foreach ($rows as $row) {
            $lat = null;
            $lng = null;

            if (array_key_exists('current_lat', $row) && array_key_exists('current_lng', $row)
                && $row['current_lat'] !== null && $row['current_lng'] !== null
            ) {
                $lat = (float) $row['current_lat'];
                $lng = (float) $row['current_lng'];
            } elseif (array_key_exists('lat', $row) && array_key_exists('lng', $row)
                && $row['lat'] !== null && $row['lng'] !== null
            ) {
                $lat = (float) $row['lat'];
                $lng = (float) $row['lng'];
            }

            if ($lat === null || $lng === null) {
                continue;
            }

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            $providers[] = [
                'id' => (int) ($row['id'] ?? 0),
                'full_name' => (string) ($row['full_name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'rating' => isset($row['rating']) ? (float) $row['rating'] : null,
                'is_available' => array_key_exists('is_available', $row) ? (int) $row['is_available'] === 1 : null,
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        $cached = $providers;
        return $cached;
    }
}

if (!function_exists('serviceAreaNearestProvidersForArea')) {
    function serviceAreaNearestProvidersForArea(array $area, int $limit = 5): array
    {
        $center = serviceAreaComputeCenter($area);
        if ($center === null) {
            return [];
        }

        $providers = serviceAreaFetchProviders();
        $withDistances = [];
        foreach ($providers as $provider) {
            $distance = serviceAreaDistanceKm(
                (float) $center['lat'],
                (float) $center['lng'],
                (float) $provider['lat'],
                (float) $provider['lng']
            );

            $provider['distance_km'] = round($distance, 2);
            $provider['inside_area'] = serviceAreaPointInArea(
                (float) $provider['lat'],
                (float) $provider['lng'],
                $area
            );
            $withDistances[] = $provider;
        }

        usort($withDistances, function ($a, $b) {
            if ($a['inside_area'] !== $b['inside_area']) {
                return $a['inside_area'] ? -1 : 1;
            }
            if ($a['distance_km'] === $b['distance_km']) {
                return ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0);
            }
            return $a['distance_km'] <=> $b['distance_km'];
        });

        return array_slice($withDistances, 0, max(1, $limit));
    }
}
