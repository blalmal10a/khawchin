<?php
// Set headers to allow CORS and return content appropriately
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$targetUrl = '';

// 1. Try standard $_GET first
if (!empty($_GET['url'])) {
    $targetUrl = $_GET['url'];
}

// 2. Fallback: Parse raw QUERY_STRING directly (bypasses server/rewrite quirks)
if (empty($targetUrl) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $queryParams);
    if (!empty($queryParams['url'])) {
        $targetUrl = $queryParams['url'];
    }
}

// 3. Fallback: Regex check on REQUEST_URI
if (empty($targetUrl) && !empty($_SERVER['REQUEST_URI'])) {
    if (preg_match('/[?&]url=([^&]+)/i', $_SERVER['REQUEST_URI'], $matches)) {
        $targetUrl = urldecode($matches[1]);
    }
}

// If still empty, throw the error
if (empty($targetUrl)) {
    http_response_code(400);
    echo "Error: Missing 'url' query parameter. Usage: ?url=https://example.com";
    exit();
}

// Default to https:// if scheme is missing
if (!preg_match("~^(?:f|ht)tps?://~i", $targetUrl)) {
    $targetUrl = "https://" . $targetUrl;
}

// Initialize cURL session
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

// Realistic Browser User-Agent and Headers to avoid bot blocks
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Accept-Encoding: gzip, deflate, br',
    'Cache-Control: no-cache',
    'Pragma: no-cache'
]);
curl_setopt($ch, CURLOPT_ENCODING, '');

// Execute cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    http_response_code(500);
    echo "cURL Error: " . htmlspecialchars($error);
    exit();
}

http_response_code($httpCode);
echo $response;
