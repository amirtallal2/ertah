<?php
/**
 * Schema update v14
 * Adds admin-controlled inspection pricing for service categories and services.
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/inspection_pricing.php';

echo "Preparing inspection pricing schema...\n";
inspectionPricingEnsureSchema();

$checks = [
    'service_categories.inspection_pricing_mode' => (bool) db()->fetch("SHOW COLUMNS FROM `service_categories` LIKE 'inspection_pricing_mode'"),
    'service_categories.inspection_fee' => (bool) db()->fetch("SHOW COLUMNS FROM `service_categories` LIKE 'inspection_fee'"),
    'service_categories.inspection_details_ar' => (bool) db()->fetch("SHOW COLUMNS FROM `service_categories` LIKE 'inspection_details_ar'"),
    'services.inspection_pricing_mode' => (bool) db()->fetch("SHOW COLUMNS FROM `services` LIKE 'inspection_pricing_mode'"),
    'services.inspection_fee' => (bool) db()->fetch("SHOW COLUMNS FROM `services` LIKE 'inspection_fee'"),
    'services.inspection_details_ar' => (bool) db()->fetch("SHOW COLUMNS FROM `services` LIKE 'inspection_details_ar'"),
];

foreach ($checks as $name => $ok) {
    echo $name . ': ' . ($ok ? 'ready' : 'missing') . "\n";
}

echo "Schema update v14 completed.\n";
