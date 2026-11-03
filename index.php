<?php
// Set headers to allow CORS and return HTML/JSON appropriately
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. Try standard $_GET parameter first
$targetUrl = $_GET['url'] ?? '';

// 2. Fallback: Parse raw query string manually if $_GET['url'] is missing
if (empty($targetUrl) && !empty($_SERVER['QUERY_STRING'])) {
    // If the query string starts with 'url=', strip it off
    if (strpos($_SERVER['QUERY_STRING'], 'url=') === 0) {
        $targetUrl = substr($_SERVER['QUERY_STRING'], 4);
        $targetUrl = urldecode($targetUrl);
    }
}

// 3. Fallback: Parse from REQUEST_URI if it was passed via path-like structure
if (empty($targetUrl)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('/[?&]url=([^&]+)/i', $requestUri, $matches)) {
        $targetUrl = urldecode($matches[1]);
    }
}

if (empty($targetUrl)) {
    http_response_code(400);
    echo "Error: Missing 'url' query parameter. Usage: ?url=https://example.com";
    exit();
}

// If the user passed a URL without a scheme, default to https://
if (!preg_match("~^(?:f|ht)tps?://~i", $targetUrl)) {
    $targetUrl = "https://" . $targetUrl;
}

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);        // Timeout after 15 seconds
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// Execute the cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

// Handle cURL errors
if ($error) {
    http_response_code(500);
    echo "cURL Error: " . htmlspecialchars($error);
    exit();
}

// Forward the HTTP status code from the remote server
http_response_code($httpCode);

// Output the retrieved HTML/content
echo $response;
