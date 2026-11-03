<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$targetUrl = $_GET['url'] ?? '';
if (empty($targetUrl) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $targetUrl = $params['url'] ?? '';
}

if (empty($targetUrl)) {
    http_response_code(400);
    echo "Error: Missing 'url' query parameter.";
    exit();
}

if (!preg_match("~^(?:f|ht)tps?://~i", $targetUrl)) {
    $targetUrl = "https://" . $targetUrl;
}

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

// Force HTTP 1.1 / HTTP 2 protocol handling
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// Spoof Desktop Windows Chrome 122 User-Agent
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');

// Complete Desktop Request Headers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Cache-Control: max-age=0',
    'Sec-Ch-Ua: "Chromium";v="122", "Not(A:Brand";v="8", "Google Chrome";v="122"',
    'Sec-Ch-Ua-Mobile: ?0',
    'Sec-Ch-Ua-Platform: "Windows"',
    'Sec-Fetch-Dest: document',
    'Sec-Fetch-Mode: navigate',
    'Sec-Fetch-Site: none',
    'Sec-Fetch-User: ?1',
    'Upgrade-Insecure-Requests: 1'
]);

// Enable automatic decompression for Accept-Encoding headers
curl_setopt($ch, CURLOPT_ENCODING, '');

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
