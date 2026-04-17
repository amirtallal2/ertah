<?php
/**
 * Mobile API - Complaints & Support
 * نظام الشكاوى والدعم الفني
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
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notification_service.php';

require_once __DIR__ . '/../helpers/jwt.php';

// Authentication Check
try {
    $userId = requireAuth();
} catch (Exception $e) {
    sendError('Unauthorized', 401);
}

// Verify User exists
$stmt = $conn->prepare("SELECT id, full_name, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    sendError('User not found', 401);
}

$user = $userResult->fetch_assoc();
// $userId is already set

$action = $_GET['action'] ?? 'list';

ensureComplaintRepliesSchema();

switch ($action) {
    case 'list':
        listComplaints($userId);
        break;
    case 'create':
        createComplaint($userId);
        break;
    case 'detail':
    case 'details':
        getComplaintDetails($userId);
        break;
    case 'reply':
        replyComplaint($userId);
        break;
    default:
        sendError('Invalid action');
}

function getTableColumnMeta($tableName)
{
    global $conn;

    $meta = [];
    $table = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if (!$result) {
        return $meta;
    }

    while ($row = $result->fetch_assoc()) {
        $meta[$row['Field']] = [
            'nullable' => ($row['Null'] ?? 'YES') === 'YES',
            'default' => $row['Default'] ?? null,
        ];
    }

    return $meta;
}

function ensureComplaintRepliesSchema()
{
    global $conn;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS `complaint_replies` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `complaint_id` INT NOT NULL,
            `user_id` INT NULL,
            `admin_id` INT NULL,
            `message` TEXT NOT NULL,
            `attachments` LONGTEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_complaint_replies_complaint` (`complaint_id`),
            INDEX `idx_complaint_replies_user` (`user_id`),
            INDEX `idx_complaint_replies_admin` (`admin_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function normalizeUploadedFiles($fieldName)
{
    if (!isset($_FILES[$fieldName])) {
        return [];
    }

    $raw = $_FILES[$fieldName];
    if (!is_array($raw) || !isset($raw['name'])) {
        return [];
    }

    if (!is_array($raw['name'])) {
        return [$raw];
    }

    $files = [];
    $count = count($raw['name']);
    for ($index = 0; $index < $count; $index++) {
        $files[] = [
            'name' => $raw['name'][$index] ?? '',
            'type' => $raw['type'][$index] ?? '',
            'tmp_name' => $raw['tmp_name'][$index] ?? '',
            'error' => $raw['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $raw['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function collectUploadedImagePaths($fieldName = 'attachments', $folder = 'complaints')
{
    $paths = [];
    $files = normalizeUploadedFiles($fieldName);

    foreach ($files as $file) {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            sendError('File upload failed', 422);
        }

        $upload = uploadFile($file, $folder);
        if (!($upload['success'] ?? false)) {
            sendError('Failed to upload attachment: ' . ($upload['message'] ?? 'Unknown error'), 422);
        }

        if (!empty($upload['path'])) {
            $paths[] = $upload['path'];
        }
    }

    return $paths;
}

function decodeAttachmentPaths($rawValue)
{
    if (is_array($rawValue)) {
        $values = $rawValue;
    } elseif (is_string($rawValue)) {
        $trimmed = trim($rawValue);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $values = $decoded;
        } else {
            $values = array_map('trim', explode(',', $trimmed));
        }
    } else {
        return [];
    }

    $paths = [];
    foreach ($values as $item) {
        $value = '';
        if (is_array($item)) {
            $value = trim((string) ($item['path'] ?? $item['url'] ?? $item['image'] ?? ''));
        } else {
            $value = trim((string) $item);
        }

        if ($value === '' || strtolower($value) === 'null') {
            continue;
        }

        $paths[] = $value;
    }

    return array_values(array_unique($paths));
}

function mapAttachmentUrls($rawValue)
{
    $paths = decodeAttachmentPaths($rawValue);
    if (empty($paths)) {
        return [];
    }

    return array_values(array_filter(array_map(function ($path) {
        return imageUrl($path);
    }, $paths)));
}

function listComplaints($userId)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id, ticket_number, subject, status, created_at, updated_at 
        FROM complaints 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $complaints = [];
    while ($row = $result->fetch_assoc()) {
        $complaints[] = [
            'id' => (int) $row['id'],
            'ticket_number' => $row['ticket_number'],
            'subject' => $row['subject'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    sendSuccess($complaints);
}

function createComplaint($userId)
{
    global $conn, $user;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }

    // Support JSON body
    $input = $_POST;
    if (empty($input)) {
        $json = file_get_contents('php://input');
        $dt = json_decode($json, true);
        if (is_array($dt)) {
            $input = $dt;
        }
    }

    $subject = trim((string) ($input['subject'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $orderId = isset($input['order_id']) ? (int) $input['order_id'] : null;
    $providerId = isset($input['provider_id']) ? (int) $input['provider_id'] : null;
    $priority = trim((string) ($input['priority'] ?? 'medium'));
    $attachmentPaths = collectUploadedImagePaths('attachments');

    if (empty($subject) || empty($description)) {
        // Log detailed error for debugging
        $debugInfo = json_encode(['input' => $input, 'post' => $_POST, 'json_input' => file_get_contents('php://input')], JSON_UNESCAPED_UNICODE);
        // file_put_contents(__DIR__ . '/complaints_validation_error.log', $debugInfo . "\n", FILE_APPEND); // Start debugging if needed
        sendError('Subject and description are required. Sent: ' . (empty($subject) ? 'No Subject' : 'Subject OK') . ', ' . (empty($description) ? 'No Description' : 'Description OK'));
    }

    // Generate Ticket Number
    $ticketNumber = 'TKT-' . date('Ymd') . '-' . rand(1000, 9999);

    $columnsMeta = getTableColumnMeta('complaints');
    if (empty($columnsMeta)) {
        sendError('Complaints table is missing or inaccessible', 500);
    }

    $fields = ['user_id', 'ticket_number', 'subject', 'description'];
    $placeholders = ['?', '?', '?', '?'];
    $types = 'isss';
    $values = [$userId, $ticketNumber, $subject, $description];

    if (isset($columnsMeta['order_id'])) {
        $fields[] = 'order_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $orderId;
    }

    if (isset($columnsMeta['provider_id'])) {
        $fields[] = 'provider_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $providerId;
    }

    if (isset($columnsMeta['priority'])) {
        $fields[] = 'priority';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $priority !== '' ? $priority : 'medium';
    }

    if (isset($columnsMeta['status'])) {
        $fields[] = 'status';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = 'open';
    }

    if (isset($columnsMeta['attachments']) && !empty($attachmentPaths)) {
        $fields[] = 'attachments';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = json_encode(array_values($attachmentPaths), JSON_UNESCAPED_UNICODE);
    }

    $insertSql = "INSERT INTO complaints (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        sendError('Failed to prepare complaint insert: ' . $conn->error, 500);
    }

    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        $complaintId = $conn->insert_id;

        // Notify admins about new complaint
        try {
            ensureNotificationSchema();
            notifyAdminNewComplaint($complaintId, [
                'ticket_number' => $ticketNumber,
                'user_name'     => $user['full_name'] ?? 'غير معروف',
                'phone'         => $user['phone'] ?? '',
                'subject'       => $subject,
                'description'   => $description,
                'priority'      => $priority,
            ]);
        } catch (Throwable $notifErr) {
            error_log('Complaint notification error: ' . $notifErr->getMessage());
        }

        sendSuccess([
            'id' => $complaintId,
            'ticket_number' => $ticketNumber,
            'attachments' => mapAttachmentUrls($attachmentPaths),
            'message' => 'Complaint created successfully'
        ]);
    } else {
        sendError('Failed to create complaint: ' . $conn->error);
    }
}

function getComplaintDetails($userId)
{
    global $conn;

    $complaintId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (!$complaintId) {
        sendError('Complaint ID required');
    }

    // Get Complaint Info
    $stmt = $conn->prepare("
        SELECT c.*
        FROM complaints c
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        sendError('Failed to load complaint details', 500);
    }

    $stmt->bind_param("ii", $complaintId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendError('Complaint not found or access denied', 404);
    }

    $complaint = $result->fetch_assoc();

    $replies = [];
    $replyColumnsMeta = getTableColumnMeta('complaint_replies');
    if (!empty($replyColumnsMeta)) {
        $selectColumns = [
            'r.id',
            'r.message',
            'r.created_at',
            isset($replyColumnsMeta['user_id']) ? 'r.user_id' : 'NULL AS user_id',
            isset($replyColumnsMeta['admin_id']) ? 'r.admin_id' : 'NULL AS admin_id',
            isset($replyColumnsMeta['attachments']) ? 'r.attachments' : 'NULL AS attachments',
            isset($replyColumnsMeta['sender_type']) ? 'r.sender_type' : 'NULL AS sender_type',
            'u.full_name AS user_name',
            'a.username AS admin_name',
        ];

        $joinUserSql = isset($replyColumnsMeta['user_id'])
            ? 'LEFT JOIN users u ON u.id = r.user_id'
            : 'LEFT JOIN users u ON 1 = 0';
        $joinAdminSql = isset($replyColumnsMeta['admin_id'])
            ? 'LEFT JOIN admins a ON a.id = r.admin_id'
            : 'LEFT JOIN admins a ON 1 = 0';

        $replySql = "SELECT " . implode(', ', $selectColumns) . "
                     FROM complaint_replies r
                     {$joinUserSql}
                     {$joinAdminSql}
                     WHERE r.complaint_id = ?
                     ORDER BY r.created_at ASC, r.id ASC";

        $replyStmt = $conn->prepare($replySql);
        if ($replyStmt) {
            $replyStmt->bind_param("i", $complaintId);
            $replyStmt->execute();
            $repliesResult = $replyStmt->get_result();

            while ($row = $repliesResult->fetch_assoc()) {
                $senderType = strtolower(trim((string) ($row['sender_type'] ?? '')));
                if ($senderType !== 'user' && $senderType !== 'admin') {
                    $senderType = !empty($row['admin_id']) ? 'admin' : 'user';
                }

                if ($senderType === 'admin') {
                    $senderName = trim((string) ($row['admin_name'] ?? ''));
                    if ($senderName === '') {
                        $senderName = 'Support Team';
                    }
                } else {
                    $senderName = trim((string) ($row['user_name'] ?? ''));
                    if ($senderName === '') {
                        $senderName = ((int) ($row['user_id'] ?? 0) === (int) $userId)
                            ? 'You'
                            : (string) ($GLOBALS['user']['full_name'] ?? 'User');
                    }
                }

                $replies[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'message' => trim((string) ($row['message'] ?? '')),
                    'sender_type' => $senderType,
                    'sender_name' => $senderName,
                    'attachments' => mapAttachmentUrls($row['attachments'] ?? null),
                    'created_at' => $row['created_at'] ?? ($complaint['created_at'] ?? date('Y-m-d H:i:s'))
                ];
            }
        }
    }

    $data = [
        'id' => (int) ($complaint['id'] ?? 0),
        'ticket_number' => $complaint['ticket_number'] ?? ('C-' . $complaintId),
        'subject' => $complaint['subject'] ?? ('Complaint #' . $complaintId),
        'description' => $complaint['description'] ?? '',
        'attachments' => mapAttachmentUrls($complaint['attachments'] ?? null),
        'status' => $complaint['status'] ?? 'open',
        'priority' => $complaint['priority'] ?? 'medium',
        'created_at' => $complaint['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $complaint['updated_at'] ?? ($complaint['created_at'] ?? date('Y-m-d H:i:s')),
        'replies' => $replies
    ];

    sendSuccess($data);
}

function replyComplaint($userId)
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }

    // Support JSON body
    $input = $_POST;
    if (empty($input)) {
        $json = file_get_contents('php://input');
        $dt = json_decode($json, true);
        if (is_array($dt)) {
            $input = $dt;
        }
    }

    $complaintId = isset($input['complaint_id']) ? (int) $input['complaint_id'] : 0;
    $message = trim((string) ($input['message'] ?? ''));
    $attachmentPaths = collectUploadedImagePaths('attachments');

    if (!$complaintId || ($message === '' && empty($attachmentPaths))) {
        sendError('Complaint ID and message or image are required');
    }

    // Check ownership
    $stmt = $conn->prepare("SELECT id FROM complaints WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        sendError('Failed to validate complaint ownership', 500);
    }

    $stmt->bind_param("ii", $complaintId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        sendError('Complaint not found or access denied', 404);
    }

    // Insert Reply (schema-tolerant to avoid failures across old DB variants)
    $columnsMeta = getTableColumnMeta('complaint_replies');
    if (empty($columnsMeta)) {
        sendError('Replies table is missing or inaccessible', 500);
    }

    $fields = ['complaint_id'];
    $placeholders = ['?'];
    $types = 'i';
    $values = [$complaintId];

    if (isset($columnsMeta['user_id'])) {
        $fields[] = 'user_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $userId;
    }

    if (isset($columnsMeta['sender_type'])) {
        $fields[] = 'sender_type';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = 'user';
    }

    $fields[] = 'message';
    $placeholders[] = '?';
    $types .= 's';
    $values[] = $message;

    if (isset($columnsMeta['attachments']) && !empty($attachmentPaths)) {
        $fields[] = 'attachments';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = json_encode(array_values($attachmentPaths), JSON_UNESCAPED_UNICODE);
    }

    if (isset($columnsMeta['created_at']) && $columnsMeta['created_at']['default'] === null) {
        $fields[] = 'created_at';
        $placeholders[] = 'NOW()';
    }

    $insertSql = "INSERT INTO complaint_replies (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        sendError('Failed to prepare reply insert: ' . $conn->error, 500);
    }

    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        $complaintColumnsMeta = getTableColumnMeta('complaints');
        if (!empty($complaintColumnsMeta)) {
            $updates = [];
            if (isset($complaintColumnsMeta['updated_at'])) {
                $updates[] = 'updated_at = NOW()';
            }
            if (isset($complaintColumnsMeta['status'])) {
                $updates[] = "status = CASE WHEN status IN ('resolved', 'closed') THEN 'open' ELSE status END";
            }

            if (!empty($updates)) {
                $safeComplaintId = (int) $complaintId;
                $conn->query("UPDATE complaints SET " . implode(', ', $updates) . " WHERE id = {$safeComplaintId}");
            }
        }

        sendSuccess([
            'message' => 'Reply added successfully',
            'attachments' => mapAttachmentUrls($attachmentPaths),
        ]);
    } else {
        sendError('Failed to add reply: ' . $stmt->error, 500);
    }
}
