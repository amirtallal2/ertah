<?php
/**
 * نظام الإعلامات المتقدم
 * Advanced Notification Service
 * 
 * Supports: Email (SMTP) + WhatsApp (4Jawaly API)
 * 
 * Usage:
 *   notifyNewOrder($orderId, $orderData);
 *   notifyNewComplaint($complaintId, $complaintData);
 *   notifyNewFurnitureRequest($requestId, $requestData);
 *   notifyNewContainerRequest($requestId, $requestData);
 *   notifyIncompleteOrders(); // cron
 */

require_once __DIR__ . '/database.php';

// =====================================================
// Schema Initialization
// =====================================================

function ensureNotificationSchema(): void
{
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    $pdo = db()->getConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notification_logs` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `channel` ENUM('email','whatsapp','both') NOT NULL DEFAULT 'email',
            `event_type` VARCHAR(80) NOT NULL,
            `recipient_email` VARCHAR(255) DEFAULT NULL,
            `recipient_phone` VARCHAR(30) DEFAULT NULL,
            `recipient_name` VARCHAR(150) DEFAULT NULL,
            `subject` VARCHAR(255) DEFAULT NULL,
            `body` TEXT DEFAULT NULL,
            `reference_type` VARCHAR(50) DEFAULT NULL,
            `reference_id` INT DEFAULT NULL,
            `status` ENUM('sent','failed','queued') NOT NULL DEFAULT 'queued',
            `error_message` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_notif_logs_event` (`event_type`),
            INDEX `idx_notif_logs_status` (`status`),
            INDEX `idx_notif_logs_reference` (`reference_type`,`reference_id`),
            INDEX `idx_notif_logs_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notification_recipients` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(30) DEFAULT NULL,
            `receive_new_orders` TINYINT(1) NOT NULL DEFAULT 1,
            `receive_complaints` TINYINT(1) NOT NULL DEFAULT 1,
            `receive_furniture` TINYINT(1) NOT NULL DEFAULT 1,
            `receive_containers` TINYINT(1) NOT NULL DEFAULT 1,
            `receive_incomplete` TINYINT(1) NOT NULL DEFAULT 0,
            `channels` VARCHAR(50) NOT NULL DEFAULT 'email',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_notif_recipients_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Ensure app_settings rows exist for SMTP and WhatsApp
    $requiredSettings = [
        'smtp_enabled'    => '0',
        'smtp_host'       => '',
        'smtp_port'       => '587',
        'smtp_username'   => '',
        'smtp_password'   => '',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => '',
        'smtp_from_name'  => 'Darfix',
        'whatsapp_enabled'    => '0',
        'whatsapp_api_key'    => '',
        'whatsapp_api_secret' => '',
        'whatsapp_sender'     => '',
        'whatsapp_gateway'    => '4jawaly',
        'notification_enabled' => '1',
        'notify_new_orders'    => '1',
        'notify_complaints'    => '1',
        'notify_furniture'     => '1',
        'notify_containers'    => '1',
        'notify_incomplete'    => '1',
        'incomplete_order_hours' => '24',
    ];

    // Check if app_settings table exists
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'app_settings'")->fetch();
        if (!$check) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `app_settings` (
                    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                    `setting_value` TEXT DEFAULT NULL,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $e) {
        // Table might exist with different schema
    }

    // Detect column names: setting_key/setting_value or key_name/value
    $cols = notifDetectSettingsColumns();

    foreach ($requiredSettings as $key => $defaultValue) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO app_settings ({$cols['key']}, {$cols['val']}) VALUES (?, ?)");
            $stmt->execute([$key, $defaultValue]);
        } catch (Throwable $e) {
            // Silently continue
        }
    }
}

// =====================================================
// Settings Helpers
// =====================================================

/**
 * Auto-detect app_settings column names (supports both old & new schemas)
 */
function notifDetectSettingsColumns(): array
{
    static $cols = null;
    if ($cols !== null) return $cols;

    try {
        $pdo = db()->getConnection();
        $result = $pdo->query("SHOW COLUMNS FROM app_settings")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('setting_key', $result)) {
            $cols = ['key' => 'setting_key', 'val' => 'setting_value'];
        } else {
            $cols = ['key' => 'key_name', 'val' => 'value'];
        }
    } catch (Throwable $e) {
        $cols = ['key' => 'setting_key', 'val' => 'setting_value'];
    }
    return $cols;
}

function getNotifSetting(string $key, string $default = ''): string
{
    try {
        $cols = notifDetectSettingsColumns();
        $row = db()->fetch("SELECT {$cols['val']} AS val FROM app_settings WHERE {$cols['key']} = ? LIMIT 1", [$key]);
        return trim((string)($row['val'] ?? $default));
    } catch (Throwable $e) {
        return $default;
    }
}

function setNotifSetting(string $key, string $value): void
{
    try {
        $cols = notifDetectSettingsColumns();
        $pdo = db()->getConnection();
        // Update first (covers legacy schemas without unique key constraints)
        $stmt = $pdo->prepare("UPDATE app_settings SET {$cols['val']} = ? WHERE {$cols['key']} = ?");
        $stmt->execute([$value, $key]);

        if ($stmt->rowCount() === 0) {
            $insert = $pdo->prepare("INSERT INTO app_settings ({$cols['key']}, {$cols['val']}) VALUES (?, ?)");
            $insert->execute([$key, $value]);
        }
    } catch (Throwable $e) {
        error_log("Failed to set notification setting [{$key}]: " . $e->getMessage());
    }
}

function isNotificationEnabled(): bool
{
    return getNotifSetting('notification_enabled', '1') === '1';
}

function isWhatsAppEnabled(): bool
{
    return getNotifSetting('whatsapp_enabled', '0') === '1';
}

function notifEnvValue(string $key): string
{
    $value = getenv($key);
    if ($value === false) {
        return '';
    }
    return trim((string) $value);
}

function notifEnvTruthy(string $value): bool
{
    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function isGmailSmtpHost(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return false;
    }
    return strpos($host, 'gmail.com') !== false || strpos($host, 'googlemail.com') !== false;
}

function normalizeSmtpPassword(string $password, string $host = ''): string
{
    $normalized = trim($password);
    if ($normalized === '') {
        return '';
    }
    if (isGmailSmtpHost($host)) {
        // Gmail app passwords are often shown with spaces; remove them.
        $normalized = preg_replace('/\s+/', '', $normalized);
    }
    return $normalized;
}

function getSmtpConfig(bool $includeSecrets = false): array
{
    $env = [
        'enabled'    => notifEnvValue('SMTP_ENABLED'),
        'host'       => notifEnvValue('SMTP_HOST'),
        'port'       => notifEnvValue('SMTP_PORT'),
        'username'   => notifEnvValue('SMTP_USERNAME'),
        'password'   => notifEnvValue('SMTP_PASSWORD'),
        'pass_alt'   => notifEnvValue('SMTP_PASS'),
        'encryption' => notifEnvValue('SMTP_ENCRYPTION'),
        'from_email' => notifEnvValue('SMTP_FROM_EMAIL'),
        'from_name'  => notifEnvValue('SMTP_FROM_NAME'),
    ];

    $hasEnv = false;
    foreach (['enabled', 'host', 'port', 'username', 'password', 'pass_alt', 'encryption', 'from_email', 'from_name'] as $key) {
        if ($env[$key] !== '') {
            $hasEnv = true;
            break;
        }
    }

    $enabledSetting = getNotifSetting('smtp_enabled', '0');
    $enabled = $env['enabled'] !== '' ? notifEnvTruthy($env['enabled']) : ($enabledSetting === '1');

    $host = $env['host'] !== '' ? $env['host'] : getNotifSetting('smtp_host');
    $portRaw = $env['port'] !== '' ? $env['port'] : getNotifSetting('smtp_port', '587');
    $username = $env['username'] !== '' ? $env['username'] : getNotifSetting('smtp_username');

    $password = '';
    $passwordSet = false;
    $envPassword = $env['password'] !== '' ? $env['password'] : $env['pass_alt'];
    if ($envPassword !== '') {
        $passwordSet = true;
        if ($includeSecrets) {
            $password = $envPassword;
        }
    } else {
        $storedPassword = getNotifSetting('smtp_password');
        if ($storedPassword !== '') {
            $passwordSet = true;
            if ($includeSecrets) {
                $password = $storedPassword;
            }
        }
    }

    if ($includeSecrets && $password !== '') {
        $password = normalizeSmtpPassword($password, $host);
    }

    $encryption = $env['encryption'] !== '' ? $env['encryption'] : getNotifSetting('smtp_encryption', 'tls');
    $fromEmail = $env['from_email'] !== '' ? $env['from_email'] : getNotifSetting('smtp_from_email');
    $fromName = $env['from_name'] !== '' ? $env['from_name'] : getNotifSetting('smtp_from_name', 'Darfix');

    return [
        'enabled' => $enabled ? '1' : '0',
        'host' => $host,
        'port' => $portRaw,
        'username' => $username,
        'password' => $password,
        'password_set' => $passwordSet,
        'encryption' => $encryption,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'uses_env' => $hasEnv,
    ];
}

function isSmtpEnabled(): bool
{
    $config = getSmtpConfig(true);
    return $config['enabled'] === '1';
}

// =====================================================
// SMTP Email Sending
// =====================================================

function sendSmtpEmail(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    if (!isSmtpEnabled()) {
        return ['success' => false, 'error' => 'SMTP is disabled'];
    }

    $config     = getSmtpConfig(true);
    $host       = $config['host'];
    $port       = (int) $config['port'];
    $username   = $config['username'];
    $password   = $config['password'];
    $encryption = $config['encryption'];
    $fromEmail  = $config['from_email'];
    $fromName   = $config['from_name'];

    if (empty($host) || empty($username) || empty($password) || empty($fromEmail)) {
        return ['success' => false, 'error' => 'SMTP settings incomplete'];
    }

    if (empty($toEmail)) {
        return ['success' => false, 'error' => 'Recipient email is empty'];
    }

    try {
        // Use fsockopen-based SMTP implementation (no external library needed)
        $result = smtpRawSend($host, $port, $encryption, $username, $password, $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody);
        return $result;
    } catch (Throwable $e) {
        error_log("SMTP send error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Raw SMTP sending via fsockopen (TLS/SSL supported, no external deps)
 */
function smtpRawSend(string $host, int $port, string $encryption, string $user, string $pass,
                     string $fromEmail, string $fromName, string $toEmail, string $toName,
                     string $subject, string $htmlBody): array
{
    $timeout = 15;
    $errno = 0;
    $errstr = '';

    // Determine connection prefix
    $prefix = '';
    if ($encryption === 'ssl') {
        $prefix = 'ssl://';
    }

    $sock = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
    if (!$sock) {
        return ['success' => false, 'error' => "Connection failed: {$errstr} (#{$errno})"];
    }

    stream_set_timeout($sock, $timeout);

    $response = smtpReadResponse($sock);
    if (substr($response, 0, 3) !== '220') {
        fclose($sock);
        return ['success' => false, 'error' => "Server greeting failed: {$response}"];
    }

    // EHLO
    smtpSendCommand($sock, "EHLO " . gethostname());
    $ehloResponse = smtpReadResponse($sock);

    // STARTTLS if needed
    if ($encryption === 'tls') {
        smtpSendCommand($sock, "STARTTLS");
        $tlsResponse = smtpReadResponse($sock);
        if (substr($tlsResponse, 0, 3) !== '220') {
            fclose($sock);
            return ['success' => false, 'error' => "STARTTLS failed: {$tlsResponse}"];
        }
        // Enable TLS on the connection
        $cryptoResult = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            | STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$cryptoResult) {
            fclose($sock);
            return ['success' => false, 'error' => 'TLS encryption handshake failed'];
        }
        // Re-EHLO after TLS
        smtpSendCommand($sock, "EHLO " . gethostname());
        smtpReadResponse($sock);
    }

    // AUTH LOGIN
    smtpSendCommand($sock, "AUTH LOGIN");
    $authResponse = smtpReadResponse($sock);
    if (substr($authResponse, 0, 3) !== '334') {
        fclose($sock);
        return ['success' => false, 'error' => "AUTH LOGIN failed: {$authResponse}"];
    }

    smtpSendCommand($sock, base64_encode($user));
    $userResponse = smtpReadResponse($sock);
    if (substr($userResponse, 0, 3) !== '334') {
        fclose($sock);
        return ['success' => false, 'error' => "Auth username failed: {$userResponse}"];
    }

    smtpSendCommand($sock, base64_encode($pass));
    $passResponse = smtpReadResponse($sock);
    if (substr($passResponse, 0, 3) !== '235') {
        fclose($sock);
        return ['success' => false, 'error' => "Auth password failed: {$passResponse}"];
    }

    // MAIL FROM
    smtpSendCommand($sock, "MAIL FROM:<{$fromEmail}>");
    $mailFromResponse = smtpReadResponse($sock);
    if (substr($mailFromResponse, 0, 3) !== '250') {
        fclose($sock);
        return ['success' => false, 'error' => "MAIL FROM rejected: {$mailFromResponse}"];
    }

    // RCPT TO
    smtpSendCommand($sock, "RCPT TO:<{$toEmail}>");
    $rcptResponse = smtpReadResponse($sock);
    if (substr($rcptResponse, 0, 3) !== '250' && substr($rcptResponse, 0, 3) !== '251') {
        fclose($sock);
        return ['success' => false, 'error' => "RCPT TO rejected: {$rcptResponse}"];
    }

    // DATA
    smtpSendCommand($sock, "DATA");
    $dataResponse = smtpReadResponse($sock);
    if (substr($dataResponse, 0, 3) !== '354') {
        fclose($sock);
        return ['success' => false, 'error' => "DATA command failed: {$dataResponse}"];
    }

    // Compose message
    $boundary = md5(uniqid(time()));
    $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedTo = '=?UTF-8?B?' . base64_encode($toName) . '?=';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers  = "From: {$encodedFrom} <{$fromEmail}>\r\n";
    $headers .= "To: {$encodedTo} <{$toEmail}>\r\n";
    $headers .= "Subject: {$encodedSubject}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "\r\n";
    $headers .= chunk_split(base64_encode($htmlBody));
    $headers .= "\r\n.\r\n";

    fwrite($sock, $headers);
    $sendResponse = smtpReadResponse($sock);

    // QUIT
    smtpSendCommand($sock, "QUIT");
    fclose($sock);

    if (substr($sendResponse, 0, 3) === '250') {
        return ['success' => true, 'error' => null];
    }

    return ['success' => false, 'error' => "Message rejected: {$sendResponse}"];
}

function smtpSendCommand($sock, string $command): void
{
    fwrite($sock, $command . "\r\n");
}

function smtpReadResponse($sock): string
{
    $response = '';
    while ($line = @fgets($sock, 512)) {
        $response .= $line;
        // Check if this is the last line (4th char is space)
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
        // Also break on timeout or small responses
        if (strlen($line) < 4) {
            break;
        }
    }
    return trim($response);
}

// =====================================================
// WhatsApp via 4Jawaly API
// =====================================================

function sendWhatsAppMessage(string $phone, string $message): array
{
    if (!isWhatsAppEnabled()) {
        return ['success' => false, 'error' => 'WhatsApp is disabled'];
    }

    $apiKey  = getNotifSetting('whatsapp_api_key');
    $apiSecret = getNotifSetting('whatsapp_api_secret');
    $sender  = getNotifSetting('whatsapp_sender');
    $gateway = getNotifSetting('whatsapp_gateway', '4jawaly');

    if ($gateway === '4jawaly') {
        if (empty($apiKey) || empty($apiSecret) || empty($sender)) {
            return ['success' => false, 'error' => 'WhatsApp settings incomplete'];
        }
    } elseif (empty($apiKey) || empty($sender)) {
        return ['success' => false, 'error' => 'WhatsApp settings incomplete'];
    }

    if (empty($phone)) {
        return ['success' => false, 'error' => 'Recipient phone is empty'];
    }

    // Normalize phone: remove spaces, ensure begins with +
    $phone = preg_replace('/\s+/', '', $phone);
    if (strpos($phone, '+') !== 0 && strpos($phone, '00') !== 0) {
        $phone = '+966' . ltrim($phone, '0');
    }

    try {
        if ($gateway === '4jawaly') {
            return send4JawalyWhatsApp($apiKey, $apiSecret, $sender, $phone, $message);
        }

        // Generic WhatsApp API fallback
        return sendGenericWhatsApp($apiKey, $sender, $phone, $message);
    } catch (Throwable $e) {
        error_log("WhatsApp send error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function send4JawalyWhatsApp(string $apiKey, string $apiSecret, string $sender, string $phone, string $message): array
{
    // 4Jawaly SMS/WhatsApp API
    $url = 'https://api-sms.4jawaly.com/api/v1/account/area/sms/send';

    $data = [
        'messages' => [
            [
                'text' => $message,
                'numbers' => [$phone],
                'sender' => $sender,
            ]
        ]
    ];

    $ch = curl_init();
    $authToken = base64_encode($apiKey . ':' . $apiSecret);
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . $authToken,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => "cURL error: {$curlError}"];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'error' => null, 'response' => $decoded];
    }

    $apiError = $decoded['message'] ?? ($decoded['error'] ?? "HTTP {$httpCode}");
    return ['success' => false, 'error' => "4Jawaly API: {$apiError}"];
}

function sendGenericWhatsApp(string $apiKey, string $sender, string $phone, string $message): array
{
    // Generic implementation placeholder
    return ['success' => false, 'error' => 'Selected WhatsApp gateway not implemented'];
}

// =====================================================
// Logging
// =====================================================

function logNotification(string $channel, string $eventType, ?string $email, ?string $phone,
                         ?string $name, string $subject, string $body,
                         ?string $refType, ?int $refId, string $status, ?string $error): void
{
    try {
        ensureNotificationSchema();
        db()->insert('notification_logs', [
            'channel'         => $channel,
            'event_type'      => $eventType,
            'recipient_email' => $email,
            'recipient_phone' => $phone,
            'recipient_name'  => $name,
            'subject'         => $subject,
            'body'            => mb_substr($body, 0, 5000),
            'reference_type'  => $refType,
            'reference_id'    => $refId,
            'status'          => $status,
            'error_message'   => $error,
        ]);
    } catch (Throwable $e) {
        error_log("Failed to log notification: " . $e->getMessage());
    }
}

// =====================================================
// Get Recipients by Event Type
// =====================================================

function getRecipientsForEvent(string $eventType): array
{
    ensureNotificationSchema();

    $columnMap = [
        'new_order'           => 'receive_new_orders',
        'new_complaint'       => 'receive_complaints',
        'new_furniture_request'  => 'receive_furniture',
        'new_container_request'  => 'receive_containers',
        'incomplete_order'    => 'receive_incomplete',
    ];

    $column = $columnMap[$eventType] ?? 'receive_new_orders';

    try {
        return db()->fetchAll(
            "SELECT * FROM notification_recipients WHERE is_active = 1 AND {$column} = 1 ORDER BY id ASC"
        );
    } catch (Throwable $e) {
        error_log("Failed to fetch notification recipients: " . $e->getMessage());
        return [];
    }
}

// =====================================================
// Dispatch Notification to All Recipients
// =====================================================

function dispatchNotification(string $eventType, string $subject, string $htmlBody,
                              string $whatsappMessage, ?string $refType = null, ?int $refId = null): void
{
    if (!isNotificationEnabled()) {
        return;
    }

    ensureNotificationSchema();
    $recipients = getRecipientsForEvent($eventType);

    foreach ($recipients as $recipient) {
        $channels = strtolower(trim((string)($recipient['channels'] ?? 'email')));
        $name     = trim((string)($recipient['name'] ?? ''));
        $email    = trim((string)($recipient['email'] ?? ''));
        $phone    = trim((string)($recipient['phone'] ?? ''));

        $shouldEmail    = ($channels === 'email' || $channels === 'both') && $email !== '';
        $shouldWhatsApp = ($channels === 'whatsapp' || $channels === 'both') && $phone !== '';

        if ($shouldEmail) {
            $result = sendSmtpEmail($email, $name, $subject, $htmlBody);
            logNotification(
                'email', $eventType, $email, null, $name, $subject, $htmlBody,
                $refType, $refId,
                $result['success'] ? 'sent' : 'failed',
                $result['error'] ?? null
            );
        }

        if ($shouldWhatsApp) {
            $result = sendWhatsAppMessage($phone, $whatsappMessage);
            logNotification(
                'whatsapp', $eventType, null, $phone, $name, $subject, $whatsappMessage,
                $refType, $refId,
                $result['success'] ? 'sent' : 'failed',
                $result['error'] ?? null
            );
        }
    }
}

// =====================================================
// Email Templates
// =====================================================

function buildEmailTemplate(string $title, string $content, string $color = '#0ea5e9'): string
{
    $appName = 'ارتاح';
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:30px 0;">
        <tr><td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.07);">
                <tr>
                    <td style="background:linear-gradient(135deg, {$color}, #2563eb); padding:30px 40px; text-align:center;">
                        <h1 style="margin:0; color:#ffffff; font-size:22px;">🏠 {$appName}</h1>
                        <p style="margin:8px 0 0; color:rgba(255,255,255,0.9); font-size:14px;">{$title}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px 40px; direction:rtl; text-align:right; line-height:1.8; color:#374151; font-size:14px;">
                        {$content}
                    </td>
                </tr>
                <tr>
                    <td style="background:#f9fafb; padding:20px 40px; text-align:center; border-top:1px solid #e5e7eb;">
                        <p style="margin:0; color:#9ca3af; font-size:12px;">© {$year} {$appName} - جميع الحقوق محفوظة</p>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}

// =====================================================
// Event-Specific Notification Functions
// =====================================================

/**
 * إشعار طلب جديد
 */
function notifyAdminNewOrder(int $orderId, array $orderData = []): void
{
    if (getNotifSetting('notify_new_orders', '1') !== '1') return;

    $orderNumber = $orderData['order_number'] ?? "#{$orderId}";
    $userName    = $orderData['user_name'] ?? ($orderData['full_name'] ?? 'غير معروف');
    $category    = $orderData['category_name'] ?? ($orderData['service'] ?? '-');
    $address     = $orderData['address'] ?? '-';
    $amount      = isset($orderData['total_amount']) ? number_format((float)$orderData['total_amount'], 2) : '0.00';
    $notes       = trim((string)($orderData['notes'] ?? ''));
    $phone       = $orderData['phone'] ?? ($orderData['user_phone'] ?? '');

    $subject = "🔔 طلب خدمة جديد #{$orderNumber}";

    $content = "
        <h2 style='color:#0ea5e9; margin:0 0 15px; font-size:18px;'>📋 طلب خدمة جديد</h2>
        <table style='width:100%; border-collapse:collapse;'>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>رقم الطلب:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6; font-weight:bold;'>{$orderNumber}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>العميل:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$userName}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>الهاتف:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;' dir='ltr'>{$phone}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>الخدمة:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$category}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>العنوان:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$address}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>المبلغ:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$amount} ⃁</td></tr>
        </table>
    ";

    if ($notes !== '') {
        $content .= "<p style='margin:15px 0 0; padding:12px; background:#f0f9ff; border-radius:8px; color:#374151;'><strong>ملاحظات:</strong> {$notes}</p>";
    }

    $content .= "<p style='margin:20px 0 5px; text-align:center;'>
        <a href='" . (defined('APP_URL') ? APP_URL : '') . "/pages/orders.php?action=view&id={$orderId}' 
           style='display:inline-block; padding:10px 30px; background:#0ea5e9; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>
            عرض الطلب في لوحة التحكم
        </a>
    </p>";

    $htmlBody = buildEmailTemplate($subject, $content);

    $whatsappMsg = "🔔 *طلب جديد #{$orderNumber}*\n"
        . "👤 العميل: {$userName}\n"
        . "📞 الهاتف: {$phone}\n"
        . "🔧 الخدمة: {$category}\n"
        . "📍 العنوان: {$address}\n"
        . "💰 المبلغ: {$amount} ⃁\n";
    if ($notes !== '') {
        $whatsappMsg .= "📝 ملاحظات: {$notes}\n";
    }

    dispatchNotification('new_order', $subject, $htmlBody, $whatsappMsg, 'order', $orderId);
}

/**
 * إشعار شكوى/تذكرة جديدة
 */
function notifyAdminNewComplaint(int $complaintId, array $data = []): void
{
    if (getNotifSetting('notify_complaints', '1') !== '1') return;

    $ticketNumber = $data['ticket_number'] ?? "#{$complaintId}";
    $userName     = $data['user_name'] ?? ($data['full_name'] ?? 'غير معروف');
    $subjectText  = $data['subject'] ?? 'بدون عنوان';
    $description  = truncate($data['description'] ?? '', 200);
    $priority     = $data['priority'] ?? 'medium';
    $phone        = $data['phone'] ?? '';

    $priorityLabels = [
        'low' => '🟢 منخفضة', 'medium' => '🟡 متوسطة',
        'high' => '🟠 عالية', 'urgent' => '🔴 عاجلة'
    ];
    $priorityLabel = $priorityLabels[$priority] ?? '🟡 متوسطة';

    $subject = "🎫 تذكرة دعم جديدة {$ticketNumber}";

    $content = "
        <h2 style='color:#dc2626; margin:0 0 15px; font-size:18px;'>🎫 شكوى / تذكرة دعم جديدة</h2>
        <table style='width:100%; border-collapse:collapse;'>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>رقم التذكرة:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6; font-weight:bold;'>{$ticketNumber}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>العميل:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$userName}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>الموضوع:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$subjectText}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>الأولوية:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$priorityLabel}</td></tr>
        </table>
        <p style='margin:15px 0 0; padding:12px; background:#fef2f2; border-radius:8px; border-right:4px solid #dc2626;'>{$description}</p>
        <p style='margin:20px 0 5px; text-align:center;'>
            <a href='" . (defined('APP_URL') ? APP_URL : '') . "/pages/complaints.php?action=view&id={$complaintId}' 
               style='display:inline-block; padding:10px 30px; background:#dc2626; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>
                عرض التذكرة
            </a>
        </p>";

    $htmlBody = buildEmailTemplate($subject, $content, '#dc2626');

    $whatsappMsg = "🎫 *تذكرة دعم جديدة {$ticketNumber}*\n"
        . "👤 العميل: {$userName}\n"
        . "📋 الموضوع: {$subjectText}\n"
        . "⚠️ الأولوية: {$priorityLabel}\n"
        . "📝 {$description}\n";

    dispatchNotification('new_complaint', $subject, $htmlBody, $whatsappMsg, 'complaint', $complaintId);
}

/**
 * إشعار طلب نقل عفش جديد
 */
function notifyAdminNewFurnitureRequest(int $requestId, array $data = []): void
{
    if (getNotifSetting('notify_furniture', '1') !== '1') return;

    $requestNumber = $data['request_number'] ?? "#{$requestId}";
    $customerName  = $data['customer_name'] ?? 'غير معروف';
    $phone         = $data['phone'] ?? '';
    $pickupAddress = $data['pickup_address'] ?? '-';
    $dropoffAddress = $data['dropoff_address'] ?? '-';
    $moveDate      = $data['move_date'] ?? '-';
    $price         = isset($data['estimated_price']) ? number_format((float)$data['estimated_price'], 2) : '-';

    $subject = "🚚 طلب نقل عفش جديد {$requestNumber}";

    $content = "
        <h2 style='color:#f59e0b; margin:0 0 15px; font-size:18px;'>🚚 طلب نقل عفش جديد</h2>
        <table style='width:100%; border-collapse:collapse;'>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>رقم الطلب:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6; font-weight:bold;'>{$requestNumber}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>العميل:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$customerName}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>الهاتف:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;' dir='ltr'>{$phone}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>من:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$pickupAddress}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>إلى:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$dropoffAddress}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>تاريخ النقل:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$moveDate}</td></tr>
            <tr><td style='padding:8px 0; color:#6b7280;'>السعر التقديري:</td>
                <td style='padding:8px 0; font-weight:bold; color:#059669;'>{$price} ⃁</td></tr>
        </table>
        <p style='margin:20px 0 5px; text-align:center;'>
            <a href='" . (defined('APP_URL') ? APP_URL : '') . "/pages/furniture-requests.php?action=view&id={$requestId}' 
               style='display:inline-block; padding:10px 30px; background:#f59e0b; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>
                عرض الطلب
            </a>
        </p>";

    $htmlBody = buildEmailTemplate($subject, $content, '#f59e0b');

    $whatsappMsg = "🚚 *طلب نقل عفش جديد {$requestNumber}*\n"
        . "👤 العميل: {$customerName}\n"
        . "📞 الهاتف: {$phone}\n"
        . "📍 من: {$pickupAddress}\n"
        . "📍 إلى: {$dropoffAddress}\n"
        . "📅 تاريخ النقل: {$moveDate}\n"
        . "💰 السعر التقديري: {$price} ⃁\n";

    dispatchNotification('new_furniture_request', $subject, $htmlBody, $whatsappMsg, 'furniture_request', $requestId);
}

/**
 * إشعار طلب حاوية جديد
 */
function notifyAdminNewContainerRequest(int $requestId, array $data = []): void
{
    if (getNotifSetting('notify_containers', '1') !== '1') return;

    $requestNumber = $data['request_number'] ?? "#{$requestId}";
    $customerName  = $data['customer_name'] ?? 'غير معروف';
    $phone         = $data['phone'] ?? '';
    $siteAddress   = $data['site_address'] ?? '-';

    $subject = "📦 طلب حاوية جديد {$requestNumber}";

    $content = "
        <h2 style='color:#7c3aed; margin:0 0 15px; font-size:18px;'>📦 طلب حاوية جديد</h2>
        <table style='width:100%; border-collapse:collapse;'>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>رقم الطلب:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6; font-weight:bold;'>{$requestNumber}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>العميل:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;'>{$customerName}</td></tr>
            <tr><td style='padding:8px 0; border-bottom:1px solid #f3f4f6; color:#6b7280;'>الهاتف:</td>
                <td style='padding:8px 0; border-bottom:1px solid #f3f4f6;' dir='ltr'>{$phone}</td></tr>
            <tr><td style='padding:8px 0; color:#6b7280;'>عنوان الموقع:</td>
                <td style='padding:8px 0;'>{$siteAddress}</td></tr>
        </table>
        <p style='margin:20px 0 5px; text-align:center;'>
            <a href='" . (defined('APP_URL') ? APP_URL : '') . "/pages/container-requests.php?action=view&id={$requestId}' 
               style='display:inline-block; padding:10px 30px; background:#7c3aed; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>
                عرض الطلب
            </a>
        </p>";

    $htmlBody = buildEmailTemplate($subject, $content, '#7c3aed');

    $whatsappMsg = "📦 *طلب حاوية جديد {$requestNumber}*\n"
        . "👤 العميل: {$customerName}\n"
        . "📞 الهاتف: {$phone}\n"
        . "📍 الموقع: {$siteAddress}\n";

    dispatchNotification('new_container_request', $subject, $htmlBody, $whatsappMsg, 'container_request', $requestId);
}

/**
 * إشعار الطلبات غير المكتملة (يعمل عبر Cron)
 */
function notifyIncompleteOrders(): array
{
    if (getNotifSetting('notify_incomplete', '1') !== '1') {
        return ['sent' => 0, 'message' => 'Incomplete order notifications disabled'];
    }

    ensureNotificationSchema();
    $hours = max(1, (int)getNotifSetting('incomplete_order_hours', '24'));
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

    $pendingOrders = db()->fetchAll("
        SELECT o.id, o.order_number, o.address, o.total_amount, o.created_at,
               u.full_name AS user_name, u.phone AS user_phone,
               c.name_ar AS category_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN service_categories c ON o.category_id = c.id
        WHERE o.status = 'pending'
          AND o.created_at <= ?
          AND o.id NOT IN (
              SELECT DISTINCT reference_id FROM notification_logs
              WHERE event_type = 'incomplete_order'
                AND reference_type = 'order'
                AND status = 'sent'
                AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
          )
        ORDER BY o.created_at ASC
        LIMIT 50
    ", [$cutoff]);

    if (empty($pendingOrders)) {
        return ['sent' => 0, 'message' => 'No incomplete orders to notify about'];
    }

    $subject = "⚠️ طلبات معلقة تحتاج متابعة (" . count($pendingOrders) . " طلب)";

    $tableRows = '';
    $whatsappLines = "⚠️ *طلبات معلقة تحتاج متابعة*\n\n";

    foreach ($pendingOrders as $index => $order) {
        $num = $index + 1;
        $orderNum = $order['order_number'] ?? "#{$order['id']}";
        $user = $order['user_name'] ?? 'غير معروف';
        $cat = $order['category_name'] ?? '-';
        $createdAt = $order['created_at'] ?? '-';

        $tableRows .= "
            <tr>
                <td style='padding:8px 12px; border-bottom:1px solid #f3f4f6;'>{$num}</td>
                <td style='padding:8px 12px; border-bottom:1px solid #f3f4f6; font-weight:bold;'>{$orderNum}</td>
                <td style='padding:8px 12px; border-bottom:1px solid #f3f4f6;'>{$user}</td>
                <td style='padding:8px 12px; border-bottom:1px solid #f3f4f6;'>{$cat}</td>
                <td style='padding:8px 12px; border-bottom:1px solid #f3f4f6; font-size:12px;'>{$createdAt}</td>
            </tr>";

        $whatsappLines .= "{$num}. طلب {$orderNum} - {$user} ({$cat}) - منذ {$createdAt}\n";
    }

    $content = "
        <h2 style='color:#f59e0b; margin:0 0 15px; font-size:18px;'>⚠️ طلبات معلقة منذ أكثر من {$hours} ساعة</h2>
        <p style='color:#6b7280;'>الطلبات التالية لم يتم الرد عليها وتحتاج متابعة:</p>
        <table style='width:100%; border-collapse:collapse; margin:15px 0;'>
            <thead>
                <tr style='background:#f9fafb;'>
                    <th style='padding:8px 12px; text-align:right; color:#6b7280; font-size:12px;'>#</th>
                    <th style='padding:8px 12px; text-align:right; color:#6b7280; font-size:12px;'>رقم الطلب</th>
                    <th style='padding:8px 12px; text-align:right; color:#6b7280; font-size:12px;'>العميل</th>
                    <th style='padding:8px 12px; text-align:right; color:#6b7280; font-size:12px;'>الخدمة</th>
                    <th style='padding:8px 12px; text-align:right; color:#6b7280; font-size:12px;'>التاريخ</th>
                </tr>
            </thead>
            <tbody>{$tableRows}</tbody>
        </table>
        <p style='margin:20px 0 5px; text-align:center;'>
            <a href='" . (defined('APP_URL') ? APP_URL : '') . "/pages/orders.php?status=pending' 
               style='display:inline-block; padding:10px 30px; background:#f59e0b; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>
                عرض الطلبات المعلقة
            </a>
        </p>";

    $htmlBody = buildEmailTemplate($subject, $content, '#f59e0b');

    dispatchNotification('incomplete_order', $subject, $htmlBody, $whatsappLines, 'order', $pendingOrders[0]['id'] ?? null);

    return ['sent' => count($pendingOrders), 'message' => 'Notifications dispatched for ' . count($pendingOrders) . ' orders'];
}

/**
 * إرسال إشعار تجريبي
 */
function sendTestNotification(string $channel, string $email = '', string $phone = ''): array
{
    ensureNotificationSchema();

    $subject = '✅ رسالة تجريبية من ارتاح';
    $content = "
        <h2 style='color:#059669; margin:0 0 15px; font-size:18px;'>✅ الإعدادات تعمل بنجاح!</h2>
        <p>هذه رسالة تجريبية للتأكد من صحة إعدادات الإشعارات.</p>
        <p style='color:#6b7280; font-size:12px;'>تم الإرسال في: " . date('Y-m-d H:i:s') . "</p>";

    $htmlBody = buildEmailTemplate($subject, $content, '#059669');
    $whatsappMsg = "✅ *رسالة تجريبية من ارتاح*\nالإعدادات تعمل بنجاح!\n⏰ " . date('Y-m-d H:i:s');

    $results = [];

    if ($channel === 'email' || $channel === 'both') {
        if ($email !== '') {
            $result = sendSmtpEmail($email, 'Test', $subject, $htmlBody);
            $results['email'] = $result;
            logNotification(
                'email', 'test', $email, null, 'Test', $subject, '',
                null, null,
                $result['success'] ? 'sent' : 'failed',
                $result['error'] ?? null
            );
        } else {
            $results['email'] = ['success' => false, 'error' => 'No email provided'];
        }
    }

    if ($channel === 'whatsapp' || $channel === 'both') {
        if ($phone !== '') {
            $result = sendWhatsAppMessage($phone, $whatsappMsg);
            $results['whatsapp'] = $result;
            logNotification(
                'whatsapp', 'test', null, $phone, 'Test', $subject, $whatsappMsg,
                null, null,
                $result['success'] ? 'sent' : 'failed',
                $result['error'] ?? null
            );
        } else {
            $results['whatsapp'] = ['success' => false, 'error' => 'No phone provided'];
        }
    }

    return $results;
}
