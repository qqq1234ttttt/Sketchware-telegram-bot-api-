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

// Receive POST parameters
$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

$user_id = trim($_POST['user_id'] ?? '');

if ($id === false || $id === null || $id <= 0 || $user_id === '') {
    $conn->close();

    sendResponse(false, "Missing or invalid id or user_id", 400);
}

// Delete only the bot owned by this user
$stmt = $conn->prepare(
    "DELETE FROM bots WHERE id = ? AND user_id = ?"
);

if (!$stmt) {
    $conn->close();

    sendResponse(false, "Unable to prepare delete query", 500);
}

$stmt->bind_param("is", $id, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();

    sendResponse(false, "Delete failed", 500);
}

$deleted = ($stmt->affected_rows > 0);

$stmt->close();
$conn->close();

if ($deleted) {
    sendResponse(true, "Bot deleted successfully");
}

sendResponse(false, "Bot not found", 404);
?>
