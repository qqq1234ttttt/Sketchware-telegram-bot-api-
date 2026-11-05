<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

function finishWebhook($conn = null) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }

    http_response_code(200);
    echo json_encode(["ok" => true]);
    exit;
}

// Database configuration from Render Environment Variables
$servername = getenv('DB_HOST');
$username   = getenv('DB_USER');
$password   = getenv('DB_PASSWORD');
$dbname     = getenv('DB_NAME');

if (!$servername || !$username || !$password || !$dbname) {
    finishWebhook();
}

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);

if ($conn->connect_error) {
    finishWebhook();
}

$conn->set_charset("utf8mb4");

// Check required cURL extension
if (!function_exists('curl_init')) {
    finishWebhook($conn);
}

// Read Telegram update
$rawUpdate = file_get_contents("php://input");
$update = json_decode($rawUpdate, true);

if (!is_array($update)) {
    finishWebhook($conn);
}

// Handle normal text messages
$message = $update['message'] ?? null;

if (
    !is_array($message) ||
    !isset($message['chat']['id']) ||
    !isset($message['text'])
) {
    finishWebhook($conn);
}

$chatId = (string) $message['chat']['id'];
$text = trim((string) $message['text']);

// Get bot token from webhook URL
$token = trim($_GET['token'] ?? '');

if ($token === '' || strlen($token) > 255 || $text === '') {
    finishWebhook($conn);
}

// Verify bot exists
$stmt = $conn->prepare(
    "SELECT id FROM bots WHERE bot_token = ? LIMIT 1"
);

if (!$stmt) {
    finishWebhook($conn);
}

$stmt->bind_param("s", $token);

if (!$stmt->execute()) {
    $stmt->close();
    finishWebhook($conn);
}

$botResult = $stmt->get_result();
$botExists = ($botResult && $botResult->num_rows > 0);
$stmt->close();

if (!$botExists) {
    finishWebhook($conn);
}

// Find matching answer for this bot
$stmt = $conn->prepare(
    "SELECT a
     FROM ml
     WHERE token = ? AND q = ?
     ORDER BY id DESC
     LIMIT 1"
);

if (!$stmt) {
    finishWebhook($conn);
}

$stmt->bind_param("ss", $token, $text);

if (!$stmt->execute()) {
    $stmt->close();
    finishWebhook($conn);
}

$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row || (string) $row['a'] === '') {
    finishWebhook($conn);
}

$replyText = (string) $row['a'];

// Send answer through Telegram Bot API
$url = "https://api.telegram.org/bot" .
       $token . "/sendMessage";

$ch = curl_init($url);

if ($ch === false) {
    finishWebhook($conn);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'chat_id' => $chatId,
        'text' => $replyText
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$apiResponse = curl_exec($ch);
$curlError = curl_errno($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);
$conn->close();

// Always acknowledge the webhook to Telegram
// Avoid repeated delivery of the same update.
if ($apiResponse === false || $curlError !== 0 || $httpCode !== 200) {
    finishWebhook();
}

$apiData = json_decode($apiResponse, true);

if (!is_array($apiData) || ($apiData['ok'] ?? false) !== true) {
    finishWebhook();
}

finishWebhook();
?>
