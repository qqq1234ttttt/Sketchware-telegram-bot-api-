<?php
header('Content-Type: application/json; charset=utf-8');

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Receive POST token
$token = trim($_POST['token'] ?? '');

if ($token === '' || strlen($token) > 255) {
    sendResponse([
        "success" => false,
        "message" => "Token is missing or invalid"
    ], 400);
}

// Check required cURL extension
if (!function_exists('curl_init')) {
    sendResponse([
        "success" => false,
        "message" => "Server cURL extension is unavailable"
    ], 500);
}

// Request Telegram getMe
$url = "https://api.telegram.org/bot" . $token . "/getMe";

$ch = curl_init($url);

if ($ch === false) {
    sendResponse([
        "success" => false,
        "message" => "Unable to initialize request"
    ], 500);
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$curlError = curl_errno($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false || $curlError !== 0) {
    sendResponse([
        "success" => false,
        "message" => "Unable to connect to Telegram"
    ], 502);
}

$data = json_decode($response, true);

if (
    $httpCode !== 200 ||
    !is_array($data) ||
    ($data['ok'] ?? false) !== true ||
    !isset($data['result']['username'])
) {
    sendResponse([
        "success" => false,
        "message" => "Telegram rejected the bot token"
    ], 400);
}

sendResponse([
    "name" => $data['result']['first_name'] ?? '',
    "username" => $data['result']['username'],
    "img" => "https://cdn-icons-png.flaticon.com/512/2111/2111646.png"
]);
?>
