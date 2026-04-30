<?php
/**
 * Mobile API - Darfix AI Proxy
 * بروكسي Darfix AI لتطبيق العميل
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

const DARFIX_AI_DEFAULT_ENDPOINT = 'https://api.us-west-2.modal.direct/v1/chat/completions';
const DARFIX_AI_DEFAULT_MODEL = 'zai-org/GLM-5-FP8';
const DARFIX_AI_DEFAULT_API_KEY = 'modalresearch_yjGu-_89u70CljD8gI2xuUP7gDQIa-Y63uojEtC9Tso';
const DARFIX_AI_DEFAULT_MAX_TOKENS = 500;

$action = $_GET['action'] ?? 'chat';

switch ($action) {
    case 'chat':
        handleChat();
        break;
    default:
        sendError('Invalid action', 400);
}

function tableExists($tableName)
{
    global $conn;

    $safeTableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTableName}'");
    return $result && $result->num_rows > 0;
}

function toBoolAiSetting($value, $default = false)
{
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function normalizeAiLanguageCode($value)
{
    $language = strtolower(trim((string) $value));
    return in_array($language, ['ar', 'en', 'ur'], true) ? $language : 'ar';
}

function getDarfixAiSettings()
{
    global $conn;

    $settings = [];
    if (tableExists('app_settings')) {
        $keys = [
            'darfix_ai_enabled',
            'darfix_ai_endpoint',
            'darfix_ai_model',
            'darfix_ai_api_key',
            'darfix_ai_max_tokens',
            'darfix_ai_system_prompt',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = $conn->prepare(
            "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)"
        );

        if ($stmt) {
            $stmt->bind_param($types, ...$keys);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $key = (string) ($row['setting_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $settings[$key] = $row['setting_value'] ?? '';
            }
            $stmt->close();
        }
    }

    $endpoint = trim((string) (
        $settings['darfix_ai_endpoint']
        ?? getenv('DARFIX_AI_ENDPOINT')
        ?? DARFIX_AI_DEFAULT_ENDPOINT
    ));
    if ($endpoint === '') {
        $endpoint = DARFIX_AI_DEFAULT_ENDPOINT;
    }

    $model = trim((string) (
        $settings['darfix_ai_model']
        ?? getenv('DARFIX_AI_MODEL')
        ?? DARFIX_AI_DEFAULT_MODEL
    ));
    if ($model === '') {
        $model = DARFIX_AI_DEFAULT_MODEL;
    }

    $apiKey = trim((string) (
        $settings['darfix_ai_api_key']
        ?? getenv('DARFIX_AI_API_KEY')
        ?? DARFIX_AI_DEFAULT_API_KEY
    ));

    $maxTokensRaw = (string) (
        $settings['darfix_ai_max_tokens']
        ?? getenv('DARFIX_AI_MAX_TOKENS')
        ?? DARFIX_AI_DEFAULT_MAX_TOKENS
    );
    $maxTokens = (int) preg_replace('/[^\d]/', '', $maxTokensRaw);
    if ($maxTokens < 64) {
        $maxTokens = DARFIX_AI_DEFAULT_MAX_TOKENS;
    }
    if ($maxTokens > 4000) {
        $maxTokens = 4000;
    }

    return [
        'enabled' => toBoolAiSetting($settings['darfix_ai_enabled'] ?? null, true),
        'endpoint' => $endpoint,
        'model' => $model,
        'api_key' => $apiKey,
        'max_tokens' => $maxTokens,
        'system_prompt' => trim((string) ($settings['darfix_ai_system_prompt'] ?? '')),
    ];
}

function normalizeAiHistory($history)
{
    if (!is_array($history)) {
        return [];
    }

    $normalized = [];
    foreach ($history as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $role = strtolower(trim((string) ($entry['role'] ?? '')));
        $content = trim((string) ($entry['content'] ?? ''));
        if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
            continue;
        }

        $normalized[] = [
            'role' => $role,
            'content' => $content,
        ];
    }

    if (count($normalized) > 12) {
        $normalized = array_slice($normalized, -12);
    }

    return $normalized;
}

function buildDarfixAiSystemPrompt($localeCode, array $liveSnapshot, $extraPrompt = '')
{
    $snapshotJson = json_encode($liveSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($snapshotJson) || trim($snapshotJson) === '') {
        $snapshotJson = '{}';
    }

    $basePrompt = <<<PROMPT
You are Darfix AI inside the Darfix customer app.
Use the live application snapshot below as your primary source of truth.
Do not invent prices, offers, stores, services, orders, balances, or support details.
If a requested fact is not present in the live snapshot, say clearly that the current live data available to you does not include it.
Prefer concise, practical answers.
Reply in the same language as the latest user message unless they explicitly ask for another language.
If the user asks about their account, recent orders, wallet, or profile, use the authenticated user data when available.
Current app locale: {$localeCode}

Live app snapshot:
{$snapshotJson}
PROMPT;

    $extraPrompt = trim((string) $extraPrompt);
    if ($extraPrompt === '') {
        return $basePrompt;
    }

    return $basePrompt . "\n\nAdditional admin instructions:\n" . $extraPrompt;
}

function sendDarfixAiRequest($endpoint, $apiKey, array $payload)
{
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        return [
            'success' => false,
            'code' => 500,
            'message' => 'Failed to encode AI request payload',
            'body' => '',
        ];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'success' => false,
                'code' => 502,
                'message' => $error !== '' ? $error : 'Darfix AI upstream request failed',
                'body' => '',
            ];
        }

        return [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'code' => $statusCode > 0 ? $statusCode : 502,
            'message' => '',
            'body' => (string) $responseBody,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($endpoint, false, $context);
    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $headerLine, $matches)) {
                $statusCode = (int) ($matches[1] ?? 0);
                break;
            }
        }
    }

    if ($responseBody === false) {
        return [
            'success' => false,
            'code' => 502,
            'message' => 'Darfix AI upstream request failed',
            'body' => '',
        ];
    }

    return [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'code' => $statusCode > 0 ? $statusCode : 502,
        'message' => '',
        'body' => (string) $responseBody,
    ];
}

function extractDarfixAiContent($payload)
{
    if (!is_array($payload)) {
        return '';
    }

    $choices = $payload['choices'] ?? null;
    if (!is_array($choices) || empty($choices)) {
        return '';
    }

    $first = $choices[0] ?? null;
    if (!is_array($first)) {
        return '';
    }

    $message = $first['message'] ?? null;
    if (!is_array($message)) {
        return '';
    }

    $content = $message['content'] ?? null;
    if (is_string($content)) {
        return trim($content);
    }

    if (!is_array($content)) {
        return '';
    }

    $parts = [];
    foreach ($content as $item) {
        if (!is_array($item)) {
            continue;
        }

        $text = trim((string) ($item['text'] ?? ''));
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(implode("\n", $parts));
}

function handleChat()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '{}', true);
    if (!is_array($payload)) {
        sendError('Invalid JSON payload', 400);
    }

    $userMessage = trim((string) ($payload['message'] ?? ''));
    if ($userMessage === '') {
        sendError('Message is required', 422);
    }

    $settings = getDarfixAiSettings();
    if (!$settings['enabled']) {
        sendError('Darfix AI is disabled from admin panel', 403);
    }

    if (trim((string) $settings['api_key']) === '') {
        sendError('Darfix AI API key is not configured', 503);
    }

    $localeCode = normalizeAiLanguageCode($payload['locale'] ?? 'ar');
    $history = normalizeAiHistory($payload['history'] ?? []);
    $liveSnapshot = is_array($payload['live_snapshot'] ?? null)
        ? $payload['live_snapshot']
        : [];

    $messages = [[
        'role' => 'system',
        'content' => buildDarfixAiSystemPrompt(
            $localeCode,
            $liveSnapshot,
            $settings['system_prompt']
        ),
    ]];

    foreach ($history as $message) {
        $messages[] = $message;
    }

    $messages[] = [
        'role' => 'user',
        'content' => $userMessage,
    ];

    $upstream = sendDarfixAiRequest(
        $settings['endpoint'],
        $settings['api_key'],
        [
            'model' => $settings['model'],
            'messages' => $messages,
            'max_tokens' => $settings['max_tokens'],
        ]
    );

    if (!$upstream['success']) {
        $upstreamMessage = trim((string) ($upstream['message'] ?? ''));
        if ($upstreamMessage === '') {
            $upstreamMessage = trim((string) ($upstream['body'] ?? ''));
        }
        if ($upstreamMessage === '') {
            $upstreamMessage = 'Darfix AI upstream request failed';
        }

        sendError($upstreamMessage, (int) ($upstream['code'] ?? 502));
    }

    $decoded = json_decode((string) ($upstream['body'] ?? '{}'), true);
    $content = extractDarfixAiContent(is_array($decoded) ? $decoded : []);
    if ($content === '') {
        sendError('Empty AI response', 502);
    }

    sendSuccess([
        'content' => $content,
        'model' => $settings['model'],
    ], 'AI reply generated');
}
