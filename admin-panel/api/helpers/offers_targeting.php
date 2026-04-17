<?php
/**
 * Offers targeting helpers
 * - Determine user segment (new/existing)
 * - Apply audience filter for offers
 */

require_once __DIR__ . '/jwt.php';

/**
 * Ensure offers.target_audience exists.
 */
function offersTargetAudienceColumnExists($conn)
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $tableRes = $conn->query("SHOW TABLES LIKE 'offers'");
        if (!$tableRes || $tableRes->num_rows === 0) {
            $exists = false;
            return $exists;
        }

        $columnRes = $conn->query("SHOW COLUMNS FROM `offers` LIKE 'target_audience'");
        if ($columnRes && $columnRes->num_rows > 0) {
            $exists = true;
            return $exists;
        }

        try {
            $conn->query(
                "ALTER TABLE `offers`
                 ADD COLUMN `target_audience` ENUM('all','new','existing')
                 NOT NULL DEFAULT 'all'
                 AFTER `category_id`"
            );
        } catch (Throwable $e) {
            // Ignore race condition when two concurrent requests try to add same column.
            $isDuplicateColumn = strpos((string) $e->getMessage(), 'Duplicate column name') !== false
                || (int) $e->getCode() === 1060;
            if (!$isDuplicateColumn) {
                $exists = false;
                return $exists;
            }
        }

        $columnResAfter = $conn->query("SHOW COLUMNS FROM `offers` LIKE 'target_audience'");
        $exists = $columnResAfter && $columnResAfter->num_rows > 0;
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Keep backward compatibility for existing callers.
 */
function ensureOffersTargetAudienceColumn($conn)
{
    offersTargetAudienceColumnExists($conn);
}

/**
 * Resolve current user offer segment:
 * - new: user has no orders
 * - existing: user has at least one order
 * - null: guest/unknown
 */
function getCurrentUserOfferSegment($conn)
{
    $role = getAuthRole();
    if ($role !== 'user') {
        return null;
    }

    $userId = (int) (getAuthUserId() ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $userStmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    if (!$userStmt) {
        return null;
    }
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    if (!$user) {
        return null;
    }

    $ordersTableRes = $conn->query("SHOW TABLES LIKE 'orders'");
    if (!$ordersTableRes || $ordersTableRes->num_rows === 0) {
        return 'new';
    }

    $ordersStmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? LIMIT 1");
    if (!$ordersStmt) {
        return null;
    }
    $ordersStmt->bind_param("i", $userId);
    $ordersStmt->execute();
    $ordersRow = $ordersStmt->get_result()->fetch_assoc();

    return $ordersRow ? 'existing' : 'new';
}

/**
 * Build SQL condition for offers.target_audience.
 * - Guests/unknown users see only public offers (all).
 */
function getOffersAudienceFilterSql($columnExpression, $userSegment, $hasTargetAudienceColumn = true)
{
    if (!$hasTargetAudienceColumn) {
        return '';
    }

    if ($userSegment === 'new') {
        return " AND COALESCE($columnExpression, 'all') IN ('all', 'new') ";
    }

    if ($userSegment === 'existing') {
        return " AND COALESCE($columnExpression, 'all') IN ('all', 'existing') ";
    }

    return " AND COALESCE($columnExpression, 'all') = 'all' ";
}
