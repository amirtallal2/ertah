<?php
/**
 * Database Connection
 * الاتصال بقاعدة البيانات
 */

require_once __DIR__ . '/../../config/config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Set charset
    $conn->set_charset(DB_CHARSET);

    // Keep MySQL session timezone aligned with PHP timezone for consistent timestamps.
    $timezoneOffset = date('P'); // e.g. +03:00
    $safeOffset = $conn->real_escape_string($timezoneOffset);
    $conn->query("SET time_zone = '{$safeOffset}'");

} catch (Exception $e) {
    // For API, return JSON error
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'خطأ في الاتصال بقاعدة البيانات'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    die("Database connection failed");
}
