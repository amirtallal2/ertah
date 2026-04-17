<?php
/**
 * Add Dummy Spare Parts
 * إضافة بيانات تجريبية لقطع الغيار
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Adding Dummy Spare Parts ===\n\n";

// 1. Drop table to ensure new schema (Experimental data mode)
$conn->query("DROP TABLE IF EXISTS spare_parts");

// 2. Create table with correct schema
$conn->query("CREATE TABLE spare_parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    description_ar TEXT,
    description_en TEXT,
    image VARCHAR(255),
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    old_price DECIMAL(10,2),
    price_with_installation DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_without_installation DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    old_price_with_installation DECIMAL(10,2) NULL,
    old_price_without_installation DECIMAL(10,2) NULL,
    stock_quantity INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

echo "✅ Re-created 'spare_parts' table with correct schema.\n";

// 3. Insert Data
$parts = [
    [
        'name_ar' => 'قطع غيار مكيفات',
        'name_en' => 'AC Parts',
        'description_ar' => 'شامل التركيب والصيانة',
        'image' => 'https://images.unsplash.com/photo-1631545804989-16b0d0d5dead?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxhaXIlMjBjb25kaXRpb25lciUyMHBhcnRzfGVufDF8fHx8MTc2NTIyMjc0Nnww&ixlib=rb-4.1.0&q=80&w=1080',
        'price' => 150.00,
        'old_price' => 200.00,
        'stock_quantity' => 12,
        'sort_order' => 1
    ],
    [
        'name_ar' => 'قطع غيار سباكة',
        'name_en' => 'Plumbing Parts',
        'description_ar' => 'خلاطات وأدوات متنوعة',
        'image' => 'https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxwbHVtYmluZyUyMHBhcnRzfGVufDF8fHx8MTc2NTIyMjc0Nnww&ixlib=rb-4.1.0&q=80&w=1080',
        'price' => 80.00,
        'old_price' => 120.00,
        'stock_quantity' => 20,
        'sort_order' => 2
    ],
    [
        'name_ar' => 'قطع غيار كهربائية',
        'name_en' => 'Electrical Parts',
        'description_ar' => 'مفاتيح وأفياش حديثة',
        'image' => 'https://images.unsplash.com/photo-1621905252472-b5b13467b2f4?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlbGVjdHJpY2FsJTIwcGFydHN8ZW58MXx8fHwxNzY1MjIyNzQ2fDA&ixlib=rb-4.1.0&q=80&w=1080',
        'price' => 50.00,
        'old_price' => 80.00,
        'stock_quantity' => 25,
        'sort_order' => 3
    ],
    [
        'name_ar' => 'قطع غيار أجهزة منزلية',
        'name_en' => 'Home Appliance Parts',
        'description_ar' => 'غسالات وثلاجات',
        'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxob21lJTIwYXBwbGlhbmNlJTIwcGFydHN8ZW58MXx8fHwxNzY1MjIyNzQ3fDA&ixlib=rb-4.1.0&q=80&w=1080',
        'price' => 200.00,
        'old_price' => 280.00,
        'stock_quantity' => 8,
        'sort_order' => 4
    ]
];

$stmt = $conn->prepare("INSERT INTO spare_parts (name_ar, name_en, description_ar, image, price, old_price, price_with_installation, price_without_installation, old_price_with_installation, old_price_without_installation, stock_quantity, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($parts as $part) {
    $stmt->bind_param(
        "ssssddddddii",
        $part['name_ar'],
        $part['name_en'],
        $part['description_ar'],
        $part['image'],
        $part['price'],
        $part['old_price'],
        $part['price'],
        $part['price'],
        $part['old_price'],
        $part['old_price'],
        $part['stock_quantity'],
        $part['sort_order']
    );

    if ($stmt->execute()) {
        echo "✅ Added: " . $part['name_ar'] . "\n";
    } else {
        echo "❌ Failed to add " . $part['name_ar'] . ": " . $stmt->error . "\n";
    }
}

// 4. Also Add a Dummy Banner for 'home_mid' position if needed?
// User said "Add experimental data for spare parts ONLY".
// I will stick to spare parts strictly. But 'home_mid' banner is needed for the design.
// I'll add one if none exists?
// "Banner before Spare Parts" logic depends on it.
// I'll add one JUST IN CASE.
$checkBanner = $conn->query("SELECT id FROM banners WHERE position = 'home_mid'");
if ($checkBanner->num_rows == 0) {
    echo "\nℹ️ Adding 'home_mid' banner for completeness...\n";
    $conn->query("INSERT INTO banners (image, link, position, is_active) VALUES ('https://iili.io/fxX6tGp.jpg', '', 'home_mid', 1)");
    echo "✅ Added 'home_mid' banner.\n";
}

echo "\nDone!\n";
