<?php
/**
 * Mobile API - Rewards & Points
 * المكافآت والنقاط
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

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getRewards();
        break;
    case 'info':
        getRewardInfo();
        break;
    case 'redeem':
        redeemReward();
        break;
    case 'history':
        getRewardHistory();
        break;
    default:
        sendError('Invalid action', 400);
}

function resolvePointsPerCurrencyUnit(): float
{
    global $conn;

    $defaultRate = 10.0;
    if (!$conn) {
        return $defaultRate;
    }

    $keys = ['points_per_currency_unit', 'points_per_sar', 'points_conversion_rate'];

    if ($conn->query("SHOW TABLES LIKE 'app_settings'")->num_rows > 0) {
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$keys);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($keys as $key) {
                foreach ($rows as $row) {
                    if (($row['setting_key'] ?? '') !== $key) {
                        continue;
                    }
                    $value = $row['setting_value'] ?? null;
                    $rate = is_numeric($value) ? (float) $value : 0.0;
                    if ($rate > 0) {
                        return $rate;
                    }
                }
            }
        }
    }

    if ($conn->query("SHOW TABLES LIKE 'settings'")->num_rows > 0) {
        foreach ($keys as $key) {
            $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $value = $row['value'] ?? null;
            $rate = is_numeric($value) ? (float) $value : 0.0;
            if ($rate > 0) {
                return $rate;
            }
        }
    }

    return $defaultRate;
}

function resolveMinRedeemPoints(): int
{
    global $conn;

    $defaultMin = 100;
    if (!$conn) {
        return $defaultMin;
    }

    $key = 'min_redeem_points';
    if ($conn->query("SHOW TABLES LIKE 'app_settings'")->num_rows > 0) {
        $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $value = $row['setting_value'] ?? null;
            $min = is_numeric($value) ? (int) $value : 0;
            if ($min > 0) {
                return $min;
            }
        }
    }

    if ($conn->query("SHOW TABLES LIKE 'settings'")->num_rows > 0) {
        $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $value = $row['value'] ?? null;
            $min = is_numeric($value) ? (int) $value : 0;
            if ($min > 0) {
                return $min;
            }
        }
    }

    return $defaultMin;
}

/**
 * Get available rewards
 */
function getRewards()
{
    global $conn;

    $userId = getAuthUserId();
    $userPoints = 0;
    $pointsPerCurrencyUnit = resolvePointsPerCurrencyUnit();
    $minRedeemPoints = resolveMinRedeemPoints();

    if ($userId) {
        $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $userPoints = $user['points'] ?? 0;
    }

    $stmt = $conn->prepare("SELECT * FROM rewards WHERE is_active = 1 ORDER BY points_required ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $rewards = [];
    while ($row = $result->fetch_assoc()) {
        $titleAr = $row['title'] ?? '';
        $titleEn = $row['title_en'] ?? '';
        if ($titleEn === '') {
            $titleEn = $titleAr;
        }
        $titleUr = $row['title_ur'] ?? '';
        if ($titleUr === '') {
            $titleUr = $titleEn !== '' ? $titleEn : $titleAr;
        }
        $descriptionAr = $row['description'] ?? '';
        $descriptionEn = $row['description_en'] ?? '';
        if ($descriptionEn === '') {
            $descriptionEn = $descriptionAr;
        }
        $descriptionUr = $row['description_ur'] ?? '';
        if ($descriptionUr === '') {
            $descriptionUr = $descriptionEn !== '' ? $descriptionEn : $descriptionAr;
        }

        $rewards[] = [
            'id' => (int) $row['id'],
            'title' => $titleAr,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'title_ur' => $titleUr,
            'description' => $descriptionAr,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'description_ur' => $descriptionUr,
            'points_required' => (int) $row['points_required'],
            'discount_value' => (float) $row['discount_value'],
            'discount_type' => $row['discount_type'],
            'icon' => $row['icon'],
            'color_class' => $row['color_class'],
            'can_redeem' => $userPoints >= $row['points_required']
        ];
    }

    sendSuccess([
        'user_points' => $userPoints,
        'rewards' => $rewards,
        'points_per_currency_unit' => $pointsPerCurrencyUnit,
        'min_redeem_points' => $minRedeemPoints
    ]);
}

/**
 * Redeem a reward
 */
function redeemReward()
{
    global $conn;

    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $rewardId = (int) ($input['reward_id'] ?? 0);
    $requestedPoints = (int) ($input['points'] ?? 0);

    if ($rewardId <= 0 && $requestedPoints <= 0) {
        sendError('reward_id أو points مطلوب', 422);
    }

    $pointsToRedeem = 0;
    $creditAmount = 0.0;
    $discountType = 'fixed';
    $description = '';
    $isPointsToWallet = $rewardId <= 0;
    $pointsPerCurrencyUnit = resolvePointsPerCurrencyUnit();
    $minRedeemPoints = resolveMinRedeemPoints();

    if ($rewardId > 0) {
        $stmt = $conn->prepare("SELECT * FROM rewards WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $rewardId);
        $stmt->execute();
        $reward = $stmt->get_result()->fetch_assoc();

        if (!$reward) {
            sendError('المكافأة غير متوفرة', 404);
        }

        $pointsToRedeem = (int) $reward['points_required'];
        $discountType = strtolower(trim((string) ($reward['discount_type'] ?? 'fixed')));
        $discountValue = (float) ($reward['discount_value'] ?? 0);

        if ($discountType === 'percentage') {
            $baseCredit = $pointsPerCurrencyUnit > 0
                ? ($pointsToRedeem / $pointsPerCurrencyUnit)
                : ($pointsToRedeem / 10);
            $creditAmount = round(($baseCredit * $discountValue) / 100, 2);
        } else {
            if ($discountValue > 0) {
                $creditAmount = $discountValue;
            } else {
                $creditAmount = round(
                    $pointsPerCurrencyUnit > 0
                        ? ($pointsToRedeem / $pointsPerCurrencyUnit)
                        : ($pointsToRedeem / 10),
                    2
                );
            }
        }

        $description = "استبدال مكافأة: " . $reward['title'];
    } else {
        // Backward compatibility for apps that send points directly.
        if ($requestedPoints < $minRedeemPoints) {
            sendError('الحد الأدنى للاستبدال هو ' . $minRedeemPoints . ' نقطة', 422);
        }

        $pointsToRedeem = $requestedPoints;
        // Keep the same conversion used by the mobile UI: 10 points = 1 SAR.
        $creditAmount = round(
            $pointsPerCurrencyUnit > 0 ? ($pointsToRedeem / $pointsPerCurrencyUnit) : ($pointsToRedeem / 10),
            2
        );
        $discountType = 'fixed';
        $description = "استبدال نقاط (" . $pointsToRedeem . " نقطة)";
    }

    if ($pointsToRedeem <= 0) {
        sendError('قيمة الاستبدال غير صالحة', 422);
    }
    $couponCode = 'RWD' . $userId . time();

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT points, wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            throw new Exception('User not found');
        }

        $userPoints = (int) ($user['points'] ?? 0);
        if ($userPoints < $pointsToRedeem) {
            throw new RuntimeException('INSUFFICIENT_POINTS');
        }

        $currentWalletBalance = (float) ($user['wallet_balance'] ?? 0);
        $newPoints = $userPoints - $pointsToRedeem;
        $pointsChange = -1 * $pointsToRedeem;
        $newWalletBalance = $currentWalletBalance;

        $shouldCreditWallet = $creditAmount > 0 && ($isPointsToWallet || $rewardId > 0);

        // Convert redeemed points (direct or reward) into wallet money when possible.
        if ($shouldCreditWallet) {
            $newWalletBalance = $currentWalletBalance + $creditAmount;
            $stmt = $conn->prepare("UPDATE users SET points = ?, wallet_balance = ? WHERE id = ?");
            $stmt->bind_param("idi", $newPoints, $newWalletBalance, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
            $stmt->bind_param("ii", $newPoints, $userId);
        }
        if (!$stmt->execute()) {
            throw new Exception('Failed to update points');
        }

        // Log points movement for rewards history.
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, balance_after, description, status) VALUES (?, 'reward', ?, ?, ?, 'completed')");
        $stmt->bind_param("idds", $userId, $pointsChange, $newPoints, $description);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create transaction');
        }

        // Also log wallet credit as a real money movement.
        if ($shouldCreditWallet) {
            $walletDescription = $rewardId > 0
                ? "تحويل مكافأة إلى رصيد محفظة (" . $pointsToRedeem . " نقطة)"
                : "تحويل نقاط إلى رصيد محفظة (" . $pointsToRedeem . " نقطة)";
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, balance_after, description, status) VALUES (?, 'deposit', ?, ?, ?, 'completed')");
            $stmt->bind_param("idds", $userId, $creditAmount, $newWalletBalance, $walletDescription);
            if (!$stmt->execute()) {
                throw new Exception('Failed to create wallet transaction');
            }
        }

        $conn->commit();

        sendSuccess([
            'coupon_code' => $couponCode,
            'redeemed_points' => $pointsToRedeem,
            'credit_amount' => $creditAmount,
            'discount_value' => $creditAmount,
            'discount_type' => $discountType,
            'remaining_points' => $newPoints,
            'wallet_balance' => $newWalletBalance,
            'converted_to_wallet' => $shouldCreditWallet
        ], 'تم استبدال المكافأة بنجاح');
    } catch (Throwable $e) {
        $conn->rollback();
        if ($e instanceof RuntimeException && $e->getMessage() === 'INSUFFICIENT_POINTS') {
            sendError('نقاطك غير كافية', 422);
        }
        sendError('حدث خطأ أثناء استبدال النقاط', 500);
    }
}

/**
 * Get reward history
 */
function getRewardHistory()
{
    global $conn;

    $userId = requireAuth();

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? AND type = 'reward' ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'id' => (int) $row['id'],
            'amount' => (float) $row['amount'],
            'description' => $row['description'],
            'created_at' => $row['created_at']
        ];
    }

    sendSuccess($history);
}

/**
 * Get comprehensive reward info
 */
function getRewardInfo()
{
    global $conn;

    $userId = requireAuth();
    $userPoints = 0;
    $pointsPerCurrencyUnit = resolvePointsPerCurrencyUnit();
    $minRedeemPoints = resolveMinRedeemPoints();

    // 1. Get Points
    $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $userPoints = $user['points'] ?? 0;

    // 2. Get Rewards List
    $stmt = $conn->prepare("SELECT * FROM rewards WHERE is_active = 1 ORDER BY points_required ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $rewards = [];
    while ($row = $result->fetch_assoc()) {
        $titleAr = $row['title'] ?? '';
        $titleEn = $row['title_en'] ?? '';
        if ($titleEn === '') {
            $titleEn = $titleAr;
        }
        $titleUr = $row['title_ur'] ?? '';
        if ($titleUr === '') {
            $titleUr = $titleEn !== '' ? $titleEn : $titleAr;
        }
        $descriptionAr = $row['description'] ?? '';
        $descriptionEn = $row['description_en'] ?? '';
        if ($descriptionEn === '') {
            $descriptionEn = $descriptionAr;
        }
        $descriptionUr = $row['description_ur'] ?? '';
        if ($descriptionUr === '') {
            $descriptionUr = $descriptionEn !== '' ? $descriptionEn : $descriptionAr;
        }

        $rewards[] = [
            'id' => (int) $row['id'],
            'title' => $titleAr,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'title_ur' => $titleUr,
            'description' => $descriptionAr,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'description_ur' => $descriptionUr,
            'points_required' => (int) $row['points_required'],
            'discount_value' => (float) $row['discount_value'],
            'discount_type' => $row['discount_type'],
            'icon' => $row['icon'],
            'color_class' => $row['color_class'],
            'can_redeem' => $userPoints >= $row['points_required']
        ];
    }

    // 3. Get History
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? AND (type = 'reward' OR description LIKE '%نقاط%') ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $historyResult = $stmt->get_result();
    $history = [];
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = [
            'id' => (int) $row['id'],
            'amount' => (float) $row['amount'],
            'description' => $row['description'],
            'date' => timeAgo($row['created_at'])
        ];
    }

    sendSuccess([
        'user_points' => $userPoints,
        'rewards' => $rewards,
        'history' => $history,
        'points_per_currency_unit' => $pointsPerCurrencyUnit,
        'min_redeem_points' => $minRedeemPoints
    ]);
}

// Helper
function timeAgo($timestamp)
{
    if (!ctype_digit($timestamp))
        $timestamp = strtotime($timestamp);
    $diff = time() - $timestamp;
    if ($diff < 60)
        return 'الآن';
    if ($diff < 3600)
        return floor($diff / 60) . ' دقيقة';
    if ($diff < 86400)
        return floor($diff / 3600) . ' ساعة';
    return date('Y-m-d', $timestamp);
}
