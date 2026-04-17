<?php
/**
 * Mobile API - Stores & Products
 * المتاجر والمنتجات
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/service_areas.php';

serviceAreaEnsureSchema();

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getStores();
        break;
    case 'products':
        getProducts();
        break;
    case 'product-categories':
    case 'product_categories':
        getProductCategories();
        break;
    default:
        sendError('Invalid action', 400);
}

/**
 * Get all active stores
 */
function getStores()
{
    global $conn;
    guardStoresCoverageList();

    $stmt = $conn->prepare("SELECT * FROM stores WHERE is_active = 1 ORDER BY name_ar ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $stores = [];
    while ($row = $result->fetch_assoc()) {
        $logo = $row['logo'] ?? ($row['image'] ?? null);
        $description = $row['description_ar'] ?? ($row['description'] ?? null);

        $stores[] = [
            'id' => (int) $row['id'],
            'name' => $row['name_ar'],
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'] ?? null,
            'logo' => $logo,
            'image' => $logo,
            'banner' => $row['banner'] ?? null,
            'description_ar' => $row['description_ar'] ?? null,
            'description_en' => $row['description_en'] ?? null,
            'description' => $description
        ];
    }

    sendSuccess($stores);
}

/**
 * Get products (optionally by store or category)
 */
function getProducts()
{
    global $conn;
    guardStoresCoverageList();

    $storeId = $_GET['store_id'] ?? null;
    $categoryId = $_GET['category_id'] ?? null;
    $search = $_GET['search'] ?? null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, (int) ($_GET['per_page'] ?? 20));
    $offset = ($page - 1) * $perPage;

    $where = "WHERE p.is_active = 1";
    $params = [];
    $types = "";

    if ($storeId) {
        $where .= " AND p.store_id = ?";
        $params[] = $storeId;
        $types .= "i";
    }

    if ($categoryId) {
        $where .= " AND p.category_id = ?";
        $params[] = $categoryId;
        $types .= "i";
    }

    if ($search) {
        $searchTerm = "%$search%";
        $where .= " AND p.name_ar LIKE ?";
        $params[] = $searchTerm;
        $types .= "s";
    }

    // Get total
    $countSql = "SELECT COUNT(*) as total FROM products p $where";
    if (!empty($params)) {
        $stmt = $conn->prepare($countSql);
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $conn->prepare($countSql);
    }
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Get products
    $sql = "SELECT p.*, s.name_ar as store_name, c.name_ar as category_name 
            FROM products p 
            LEFT JOIN stores s ON p.store_id = s.id 
            LEFT JOIN product_categories c ON p.category_id = c.id 
            $where 
            ORDER BY p.created_at DESC 
            LIMIT ? OFFSET ?";

    $params[] = $perPage;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $price = (float) $row['price'];
        $originalPrice = isset($row['original_price']) && $row['original_price'] !== null
            ? (float) $row['original_price']
            : null;
        $discount = isset($row['discount_percent']) ? (int) $row['discount_percent'] : 0;

        if ($discount <= 0 && $originalPrice !== null && $originalPrice > $price) {
            $discount = (int) round((($originalPrice - $price) / $originalPrice) * 100);
        }

        $stockQuantity = isset($row['stock_quantity']) ? (int) $row['stock_quantity'] : 0;

        $products[] = [
            'id' => (int) $row['id'],
            'name' => $row['name_ar'],
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'] ?? null,
            'price' => $price,
            'original_price' => $originalPrice,
            'old_price' => $originalPrice,
            'discount_percent' => $discount,
            'discount_percentage' => $discount,
            'image' => $row['image'] ?? null,
            'description_ar' => $row['description_ar'] ?? null,
            'description' => $row['description_ar'] ?? ($row['description'] ?? null),
            'store_id' => $row['store_id'] ? (int) $row['store_id'] : null,
            'store_name' => $row['store_name'] ?? null,
            'category_id' => $row['category_id'] ? (int) $row['category_id'] : null,
            'category_name' => $row['category_name'] ?? null,
            'stock_quantity' => $stockQuantity,
            'rating' => (float) ($row['rating'] ?? 0),
            'reviews_count' => (int) ($row['reviews_count'] ?? 0),
            'in_stock' => $stockQuantity > 0
        ];
    }

    sendPaginated($products, $page, $perPage, $total);
}

/**
 * Get product categories
 */
function getProductCategories()
{
    global $conn;
    guardStoresCoverageList();

    $stmt = $conn->prepare("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY name_ar ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'id' => (int) $row['id'],
            'name' => $row['name_ar'],
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'] ?? null,
            'image' => $row['image'] ?? null
        ];
    }

    sendSuccess($categories);
}

function storesCoverage(): array
{
    static $coverage = null;
    if ($coverage !== null) {
        return $coverage;
    }

    $country = serviceAreaNormalizeCountryCode($_GET['country_code'] ?? '');
    $lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : null;
    $lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : null;
    $hasCoordinates = $lat !== null && $lng !== null;

    if ($hasCoordinates && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
        sendError('إحداثيات الموقع غير صحيحة', 422);
    }

    $coverage = serviceAreaEvaluateCoverage($country, $lat, $lng);
    return $coverage;
}

function storesAllowOutsideBrowsing(): bool
{
    return in_array(
        strtolower(trim((string) ($_GET['allow_outside'] ?? $_GET['guest'] ?? ''))),
        ['1', 'true', 'yes', 'on'],
        true
    );
}

function guardStoresCoverageList(): void
{
    if (storesAllowOutsideBrowsing()) {
        return;
    }

    $coverage = storesCoverage();
    if (!($coverage['is_supported'] ?? true)) {
        $message = trim((string) ($coverage['message_ar'] ?? ''));
        if ($message === '') {
            $message = 'أنت خارج نطاق تقديم الخدمة';
        }
        sendSuccess([], $message);
    }
}
