<?php
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_OFF);

function sendResponse($success, $message, $status = 200, $step = '') {
    http_response_code($status);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "step" => $step
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/* 1. Check request method */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "POST required", 405, "request");
}

/* 2. Read database configuration */
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    sendResponse(false, "Database configuration missing", 500, "config");
}

/* 3. Connect database */
mysqli_report(MYSQLI_REPORT_OFF);
ini_set('mysqli.connect_timeout', '5');

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    error_log("add_bot.php: Database connection failed");
    sendResponse(false, "Database connection failed", 500, "database");
}

$conn->set_charset("utf8mb4");

/* 4. Read POST data */
$user_id = trim($_POST['user_id'] ?? '');
$token = trim($_POST['token'] ?? '');
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$img = trim($_POST['img'] ?? '');

if (
    $user_id === '' ||
    $token === '' ||
    $name === '' ||
    $username === ''
) {
    $conn->close();
    sendResponse(false, "Missing required fields", 400, "input");
}

if (
    strlen($user_id) > 100 ||
    strlen($token) > 255 ||
    strlen($name) > 255 ||
    strlen($username) > 255 ||
    strlen($img) > 2048
) {
    $conn->close();
    sendResponse(false, "Input too long", 400, "input");
}

/* 5. Verify Telegram bot token */
$check = curl_init(
    "https://api.telegram.org/bot" . $token . "/getMe"
);

curl_setopt_array($check, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$checkResponse = curl_exec($check);
$checkError = curl_errno($check);
$checkHttpCode = (int) curl_getinfo($check, CURLINFO_HTTP_CODE);

curl_close($check);

if ($checkResponse === false || $checkError !== 0) {
    $conn->close();
    sendResponse(false, "Telegram connection failed", 502, "telegram_getme");
}

$checkData = json_decode($checkResponse, true);

if (
    $checkHttpCode !== 200 ||
    !is_array($checkData) ||
    ($checkData['ok'] ?? false) !== true
) {
    $conn->close();
    sendResponse(false, "Invalid bot token", 400, "telegram_getme");
}

$bot = $checkData['result'] ?? [];
$verifiedName = $bot['first_name'] ?? $name;
$verifiedUsername = $bot['username'] ?? $username;

if (empty($bot['id']) || $verifiedUsername === '') {
    $conn->close();
    sendResponse(false, "Invalid Telegram bot information", 502, "telegram_getme");
}

/* 6. Check webhook base URL */
$baseUrl = rtrim(getenv('API_BASE_URL') ?: '', '/');

if ($baseUrl === '') {
    $conn->close();
    sendResponse(false, "API_BASE_URL is not configured", 500, "config");
}

/* 7. Save bot to database */
$stmt = $conn->prepare(
    "INSERT INTO bots (user_id, bot_token, name, username, img)
     VALUES (?, ?, ?, ?, ?)"
);

if (!$stmt) {
    $conn->close();
    sendResponse(false, "Database query preparation failed", 500, "database_insert");
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
    error_log("add_bot.php: Insert failed: " . $stmt->errno);

    $stmt->close();
    $conn->close();

    sendResponse(false, "Bot already added or database error", 400, "database_insert");
}

$botId = $stmt->insert_id;
$stmt->close();

/* 8. Register webhook */
$webhookUrl = $baseUrl . "/webhook.php?token=" . urlencode($token);

$ch = curl_init(
    "https://api.telegram.org/bot" . $token . "/setWebhook"
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "url" => $webhookUrl
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$webhookResponse = curl_exec($ch);
$webhookError = curl_errno($ch);
$webhookHttpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($webhookResponse === false || $webhookError !== 0) {
    error_log("add_bot.php: Webhook request failed, cURL error " . $webhookError);

    $conn->close();

    sendResponse(
        false,
        "Webhook connection failed. Bot record was saved; check webhook setup.",
        502,
        "telegram_webhook"
    );
}

$webhookData = json_decode($webhookResponse, true);

if (
    $webhookHttpCode !== 200 ||
    !is_array($webhookData) ||
    ($webhookData['ok'] ?? false) !== true
) {
    error_log("add_bot.php: Telegram webhook rejected the request");

    $conn->close();

    sendResponse(
        false,
        "Telegram rejected webhook setup. Bot record was saved.",
        502,
        "telegram_webhook"
    );
}

/* 9. Success */
$conn->close();

sendResponse(
    true,
    "Bot added and webhook connected",
    200,
    "complete"
);
?>
