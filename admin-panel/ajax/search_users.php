<?php
/**
 * Ajax search for users (notifications).
 */
require_once '../init.php';

requireLogin();

$admin = getCurrentAdmin();
if (!hasPermission('notifications') && ($admin['role'] ?? '') !== 'super_admin') {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
}

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
    jsonResponse(['success' => true, 'items' => []]);
}

try {
    $columns = db()->fetchAll("SHOW COLUMNS FROM users");
    $columnNames = [];
    foreach ($columns as $col) {
        if (!empty($col['Field'])) {
            $columnNames[] = $col['Field'];
        }
    }
    $hasFullName = in_array('full_name', $columnNames, true);
    $hasName = in_array('name', $columnNames, true);
    $hasUsername = in_array('username', $columnNames, true);
    $hasEmail = in_array('email', $columnNames, true);
    $hasPhone = in_array('phone', $columnNames, true);
    $hasMobile = in_array('mobile', $columnNames, true);
    $hasIsActive = in_array('is_active', $columnNames, true);

    $nameExpr = $hasFullName
        ? 'full_name'
        : ($hasName
            ? 'name'
            : ($hasUsername
                ? 'username'
                : ($hasEmail ? 'email' : "CONCAT('مستخدم #', id)")));
    $phoneExpr = $hasPhone
        ? 'phone'
        : ($hasMobile ? 'mobile' : ($hasEmail ? 'email' : "''"));

    $whereParts = [];
    $params = [];
    $like = '%' . $query . '%';
    if ($hasFullName) {
        $whereParts[] = 'full_name LIKE ?';
        $params[] = $like;
    }
    if ($hasName) {
        $whereParts[] = 'name LIKE ?';
        $params[] = $like;
    }
    if ($hasUsername) {
        $whereParts[] = 'username LIKE ?';
        $params[] = $like;
    }
    if ($hasEmail) {
        $whereParts[] = 'email LIKE ?';
        $params[] = $like;
    }
    if ($hasPhone) {
        $whereParts[] = 'phone LIKE ?';
        $params[] = $like;
    }
    if ($hasMobile) {
        $whereParts[] = 'mobile LIKE ?';
        $params[] = $like;
    }
    if (ctype_digit($query)) {
        $whereParts[] = 'id = ?';
        $params[] = (int) $query;
    }

    if (empty($whereParts)) {
        jsonResponse(['success' => true, 'items' => []]);
    }

    $selectFields = "id, {$nameExpr} AS full_name, {$phoneExpr} AS phone";
    if ($hasIsActive) {
        $selectFields .= ', is_active';
    }

    $rows = db()->fetchAll(
        "SELECT {$selectFields}
         FROM users
         WHERE " . implode(' OR ', $whereParts) . "
         ORDER BY id DESC
         LIMIT 25",
        $params
    );

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => trim((string) ($row['full_name'] ?? '')) ?: ('مستخدم #' . (int) ($row['id'] ?? 0)),
            'phone' => trim((string) ($row['phone'] ?? '')),
            'is_active' => isset($row['is_active']) ? (int) $row['is_active'] : null,
        ];
    }

    jsonResponse(['success' => true, 'items' => $items]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Search failed'], 500);
}
