<?php
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_OFF);

function sendResponse($success, $message, $statusCode = 200) {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Database credentials from Render Environment Variables
$servername = getenv('DB_HOST');
$username   = getenv('DB_USER');
$password   = getenv('DB_PASSWORD');
$dbname     = getenv('DB_NAME');

if (!$servername || !$username || !$password || !$dbname) {
    sendResponse(false, "Database configuration missing", 500);
}

// Database connection
$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);

if ($conn->connect_error) {
    sendResponse(false, "Database connection failed", 500);
}

$conn->set_charset("utf8mb4");

// Receive POST data
$q     = trim($_POST['q'] ?? '');
$a     = trim($_POST['a'] ?? '');
$token = trim($_POST['token'] ?? '');

// Validate required fields
if ($q === '' || $a === '' || $token === '') {
    $conn->close();

    sendResponse(
        false,
        "Question, answer and token are required",
        400
    );
}

if (strlen($token) > 255) {
    $conn->close();

    sendResponse(false, "Invalid token", 400);
}

// Check whether the bot exists
$check = $conn->prepare(
    "SELECT id FROM bots WHERE bot_token = ? LIMIT 1"
);

if (!$check) {
    $conn->close();

    sendResponse(false, "Unable to check bot", 500);
}

$check->bind_param("s", $token);

if (!$check->execute()) {
    $check->close();
    $conn->close();

    sendResponse(false, "Bot validation failed", 500);
}

$result = $check->get_result();
$botExists = ($result && $result->num_rows > 0);

$check->close();

if (!$botExists) {
    $conn->close();

    sendResponse(false, "Bot not found", 404);
}

// Insert ML question and answer
$stmt = $conn->prepare(
    "INSERT INTO ml (token, q, a) VALUES (?, ?, ?)"
);

if (!$stmt) {
    $conn->close();

    sendResponse(false, "Unable to prepare insert query", 500);
}

$stmt->bind_param("sss", $token, $q, $a);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();

    sendResponse(false, "Failed to save ML data", 500);
}

$stmt->close();
$conn->close();

sendResponse(true, "Added successfully");
?>
