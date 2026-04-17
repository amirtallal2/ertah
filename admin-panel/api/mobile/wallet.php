<?php
/**
 * Wallet API Endpoint
 * نقطة الوصول للمحفظة
 */

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/jwt.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Check Auth
try {
    $userId = requireAuth();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 1. Get Wallet Details & Transactions
if ($action === 'details') {
    $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $transactions = [];
        // Fetch transactions (limit 20)
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = [
                    'id' => (int) $row['id'],
                    'type' => $row['type'],
                    'amount' => (float) $row['amount'],
                    'balance_after' => (float) $row['balance_after'],
                    'description' => $row['description'],
                    'date' => isset($row['created_at']) ? timeAgo($row['created_at']) : '',
                    'status' => $row['status']
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'balance' => (float) $user['wallet_balance'],
                'transactions' => $transactions
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
}

// 2. Add Funds (Mock or Simple)
elseif ($action === 'add_funds') {
    $data = json_decode(file_get_contents('php://input'), true);
    $amount = isset($data['amount']) ? (float) $data['amount'] : 0;

    if ($amount > 0) {
        $conn->begin_transaction();
        try {
            // Update Balance
            $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $userId);
            $stmt->execute();

            $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $newBalance = $stmt->get_result()->fetch_assoc()['wallet_balance'];

            // Record Transaction
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, balance_after, description, status) VALUES (?, 'deposit', ?, ?, 'شحن رصيد عبر التطبيق', 'completed')");
            $stmt->bind_param("idd", $userId, $amount, $newBalance);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Funds added successfully', 'new_balance' => $newBalance]);
        } catch (Throwable $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to add funds']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// Helper function for time ago (if not included)
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
