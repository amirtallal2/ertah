<?php
/**
 * API Response Helper
 * مساعد الردود للـ API
 */

/**
 * Send success response
 */
function sendSuccess($data = null, $message = 'Success')
{
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $code = 400, $errors = null)
{
    http_response_code($code);
    $response = [
        'success' => false,
        'message' => $message
    ];
    if ($errors) {
        $response['errors'] = $errors;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send paginated response
 */
function sendPaginated($data, $page, $perPage, $total)
{
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'current_page' => (int) $page,
            'per_page' => (int) $perPage,
            'total' => (int) $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
