<?php
// Probe an URL server-side and return its HTTP status, bypassing browser CORS.
// Used by slides.js to detect 404/5xx on cross-origin iframes after they load.

header('Content-Type: application/json');
header('Cache-Control: no-store');

$url = $_GET['url'] ?? '';

if (!$url || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid url']);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_NOBODY         => true,        // HEAD
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'visionner-probe/1.0',
]);

curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

// Some servers reject HEAD with 405/501 — fall back to a tiny GET.
if ($status === 405 || $status === 501) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_RANGE          => '0-0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'visionner-probe/1.0',
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);
}

echo json_encode([
    'status' => $status,
    'ok'     => $status >= 200 && $status < 400,
    'error'  => $err,
]);
