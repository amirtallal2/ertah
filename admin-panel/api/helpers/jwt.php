<?php
/**
 * JWT Helper
 * مساعد JWT للتوكنات
 */

define('JWT_SECRET', getenv('JWT_SECRET') ?: 'ertah_secret_key_change_in_production_2024');
define('JWT_EXPIRY', (int) (getenv('JWT_EXPIRY_SECONDS') ?: 86400 * 30)); // 30 days by default

/**
 * Generate JWT token
 */
function generateJWT($userId, $type = 'user')
{
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'type' => $type,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRY
    ]));

    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    return "$header.$payload.$signature";
}

/**
 * Verify JWT token
 */
function verifyJWT($token)
{
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return false;
    }

    list($header, $payload, $signature) = $parts;

    // Verify signature
    $expectedSignature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }

    // Decode payload
    $payloadData = json_decode(base64_decode($payload), true);

    // Check expiry
    if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return false;
    }

    return $payloadData;
}

/**
 * Get user ID from Authorization header
 */
function getAuthUserId()
{
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    $authHeader = '';
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (empty($authHeader) || substr($authHeader, 0, 7) !== 'Bearer ') {
        return null;
    }

    $token = substr($authHeader, 7);
    $payload = verifyJWT($token);

    return $payload['user_id'] ?? null;
}

/**
 * Require authentication
 */
function requireAuth()
{
    $userId = getAuthUserId();

    if (!$userId) {
        sendError('Unauthorized', 401);
    }

    return $userId;
}

/**
 * Get authenitcated user role/type
 */
function getAuthRole()
{
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    $authHeader = '';
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (empty($authHeader) || substr($authHeader, 0, 7) !== 'Bearer ') {
        return null; // Guest
    }

    $token = substr($authHeader, 7);
    $payload = verifyJWT($token);

    if (!$payload)
        return null;

    return $payload['type'] ?? 'user';
}
