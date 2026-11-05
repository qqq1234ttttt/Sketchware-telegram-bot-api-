<?php
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_OFF);

function sendResponse($success, $message, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "success" => $success,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Database credentials ကို Render Environment Variables မှယူခြင်း
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    sendResponse(false, "Database configuration missing", 500);
}

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    sendResponse(false, "Database connection failed", 500);
}

$conn->set_charset("utf8mb4");

// POST data
$user_id = trim($_POST['user_id'] ?? '');
$token = trim($_POST['token'] ?? '');
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$img = trim($_POST['img'] ?? '');

if ($user_id === '' || $token === '' ||
    $name === '' || $username === '') {
    sendResponse(false, "Missing fields", 400);
}

if (strlen($token) > 255) {
    sendResponse(false, "Invalid bot token", 400);
}

// Telegram Bot Token စစ်ဆေးခြင်း
$check = curl_init(
    "https://api.telegram.org/bot" . $token . "/getMe"
);

curl_setopt_array($check, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$checkResponse = curl_exec($check);
$checkError = curl_errno($check);
$checkHttpCode = (int) curl_getinfo($check, CURLINFO_HTTP_CODE);
curl_close($check);

$checkData = json_decode($checkResponse ?: '', true);

if ($checkResponse === false || $checkError !== 0) {
    sendResponse(false, "Unable to connect to Telegram", 502);
}

if (
    $checkHttpCode !== 200 ||
    !is_array($checkData) ||
    ($checkData['ok'] ?? false) !== true
) {
    sendResponse(false, "Invalid bot token", 400);
}

// Telegram က အတည်ပြုပေးတဲ့ Bot အချက်အလက်ကို အသုံးပြုခြင်း
$verifiedName = $checkData['result']['first_name'] ?? $name;
$verifiedUsername = $checkData['result']['username'] ?? $username;

if ($verifiedUsername === '') {
    sendResponse(false, "Bot username unavailable", 400);
}

// Database ထဲသိမ်းခြင်း
$stmt = $conn->prepare(
    "INSERT INTO bots (user_id, bot_token, name, username, img)
     VALUES (?, ?, ?, ?, ?)"
);

if (!$stmt) {
    sendResponse(false, "Database query failed", 500);
}

$stmt->bind_param(
    "sssss",
    $user_id,
    $token,
    $verifiedName,
    $verifiedUsername,
    $img
);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    sendResponse(false, "Bot already added or database error", 400);
}

$botId = $stmt->insert_id;
$stmt->close();

// Render မှာ Deploy ပြီးရလာမယ့် Base URL ကို
// Render Environment Variables ထဲမှာ API_BASE_URL အဖြစ်ထည့်ပါ။
$baseUrl = rtrim(getenv('API_BASE_URL') ?: '', '/');

if ($baseUrl === '') {
    $delete = $conn->prepare("DELETE FROM bots WHERE id = ?");
    if ($delete) {
        $delete->bind_param("i", $botId);
        $delete->execute();
        $delete->close();
    }
    $conn->close();
    sendResponse(false, "API_BASE_URL is not configured", 500);
}

$webhookUrl = $baseUrl . "/webhook.php?token=" . urlencode($token);

// Telegram Webhook ချိတ်ခြင်း
$ch = curl_init(
    "https://api.telegram.org/bot" . $token . "/setWebhook"
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "url" => $webhookUrl
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$webhookResponse = curl_exec($ch);
$webhookError = curl_errno($ch);
$webhookHttpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$webhookData = json_decode($webhookResponse ?: '', true);

if (
    $webhookResponse === false ||
    $webhookError !== 0 ||
    $webhookHttpCode !== 200 ||
    !is_array($webhookData) ||
    ($webhookData['ok'] ?? false) !== true
) {
    $delete = $conn->prepare("DELETE FROM bots WHERE id = ?");
    if ($delete) {
        $delete->bind_param("i", $botId);
        $delete->execute();
        $delete->close();
    }

    $conn->close();
    sendResponse(false, "Webhook setup failed", 502);
}

$conn->close();

sendResponse(true, "Bot added and webhook connected");
?>
