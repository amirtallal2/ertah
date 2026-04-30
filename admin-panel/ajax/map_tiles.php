<?php
/**
 * Same-origin map tile proxy for admin Leaflet maps.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(401);
    exit('Unauthorized');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$providerKey = strtolower(trim((string) ($_GET['provider'] ?? 'auto')));
$z = filter_input(INPUT_GET, 'z', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 22],
]);
$x = filter_input(INPUT_GET, 'x', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0],
]);
$y = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0],
]);

if ($z === false || $x === false || $y === false || $z === null || $x === null || $y === null) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit('Invalid tile coordinates');
}

$tileBound = 1 << $z;
if ($x >= $tileBound || $y >= $tileBound) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit('Tile coordinates out of range');
}

$providers = [
    'osm' => [
        'label' => 'OpenStreetMap',
        'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'subdomains' => ['a', 'b', 'c'],
        'content_type' => 'image/png',
    ],
    'carto_light' => [
        'label' => 'Carto Light',
        'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
        'subdomains' => ['a', 'b', 'c', 'd'],
        'content_type' => 'image/png',
    ],
    'esri_street' => [
        'label' => 'Esri Streets',
        'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
        'subdomains' => [],
        'content_type' => 'image/jpeg',
    ],
];

if ($providerKey === 'auto') {
    $providerOrder = ['osm', 'carto_light', 'esri_street'];
} elseif (isset($providers[$providerKey])) {
    $providerOrder = [$providerKey];
} else {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit('Unsupported tile provider');
}

function mapTileResolveUrl(array $provider, int $z, int $x, int $y): string
{
    $subdomain = '';
    $subdomains = $provider['subdomains'] ?? [];
    if (!empty($subdomains)) {
        $subdomain = $subdomains[abs(($x + $y) % count($subdomains))];
    }

    return strtr($provider['url'], [
        '{s}' => $subdomain,
        '{z}' => (string) $z,
        '{x}' => (string) $x,
        '{y}' => (string) $y,
        '{r}' => '',
    ]);
}

function mapTileCacheFile(string $providerKey, int $z, int $x, int $y): string
{
    $safeProvider = preg_replace('/[^a-z0-9_]/', '', strtolower($providerKey)) ?: 'osm';
    return __DIR__ . '/../uploads/cache/map-tiles/' . $safeProvider . '/' . $z . '/' . $x . '/' . $y . '.tile';
}

function mapTileSendResponse(string $body, string $contentType, string $providerKey, int $cacheSeconds): void
{
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, max-age=' . $cacheSeconds . ', stale-while-revalidate=86400');
    header('X-Darfix-Map-Provider: ' . $providerKey);
    echo $body;
    exit;
}

function mapTileReadCache(string $cacheFile)
{
    if (!is_file($cacheFile)) {
        return false;
    }

    $maxAge = 60 * 60 * 24 * 14;
    if ((time() - (int) @filemtime($cacheFile)) > $maxAge) {
        return false;
    }

    return @file_get_contents($cacheFile);
}

function mapTileWriteCache(string $cacheFile, string $body): void
{
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
        return;
    }

    @file_put_contents($cacheFile, $body, LOCK_EX);
}

function mapTileFetchUpstream(string $url): array
{
    $headers = [
        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        'User-Agent: DarfixAdminMapProxy/1.0 (+https://ertah.org)',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            return [
                'success' => false,
                'status' => $status > 0 ? $status : 502,
                'content_type' => '',
                'body' => '',
                'error' => $error !== '' ? $error : ('HTTP ' . ($status > 0 ? $status : 502)),
            ];
        }

        return [
            'success' => true,
            'status' => $status,
            'content_type' => $contentType,
            'body' => (string) $body,
            'error' => '',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    $status = 0;
    $contentType = '';
    foreach ($responseHeaders as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches)) {
            $status = (int) $matches[1];
            continue;
        }
        if (stripos($headerLine, 'Content-Type:') === 0) {
            $contentType = trim(substr($headerLine, strlen('Content-Type:')));
        }
    }

    if ($body === false || $status < 200 || $status >= 300) {
        return [
            'success' => false,
            'status' => $status > 0 ? $status : 502,
            'content_type' => '',
            'body' => '',
            'error' => $body === false ? 'file_get_contents failed' : ('HTTP ' . ($status > 0 ? $status : 502)),
        ];
    }

    return [
        'success' => true,
        'status' => $status,
        'content_type' => $contentType,
        'body' => (string) $body,
        'error' => '',
    ];
}

$upstreamErrors = [];

foreach ($providerOrder as $currentProviderKey) {
    $provider = $providers[$currentProviderKey];
    $cacheFile = mapTileCacheFile($currentProviderKey, $z, $x, $y);
    $cachedBody = mapTileReadCache($cacheFile);
    if ($cachedBody !== false && $cachedBody !== '') {
        mapTileSendResponse($cachedBody, $provider['content_type'], $currentProviderKey, 86400);
    }

    $url = mapTileResolveUrl($provider, $z, $x, $y);
    $response = mapTileFetchUpstream($url);
    if (!$response['success']) {
        $upstreamErrors[] = $currentProviderKey . ': ' . $response['error'];
        continue;
    }

    $body = (string) ($response['body'] ?? '');
    if ($body === '') {
        $upstreamErrors[] = $currentProviderKey . ': empty response body';
        continue;
    }

    $contentType = trim((string) ($response['content_type'] ?? ''));
    if ($contentType === '') {
        $contentType = $provider['content_type'];
    } else {
        $contentType = strtolower(explode(';', $contentType)[0]);
    }

    if (strpos($contentType, 'image/') !== 0) {
        $upstreamErrors[] = $currentProviderKey . ': invalid content type ' . $contentType;
        continue;
    }

    mapTileWriteCache($cacheFile, $body);
    mapTileSendResponse($body, $contentType, $currentProviderKey, 86400);
}

header('Content-Type: text/plain; charset=utf-8');
http_response_code(502);
echo 'Failed to load map tile';
if (!empty($upstreamErrors)) {
    echo "\n" . implode("\n", $upstreamErrors);
}
