
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

$token = trim($_POST['token'] ?? '');

// Validate input
if ($id === false || $id === null || $id <= 0 || $token === '') {
    $conn->close();

    sendResponse(false, "Invalid or missing id or token", 400);
}

if (strlen($token) > 255) {
    $conn->close();

    sendResponse(false, "Invalid token", 400);
}

// Prepare delete query
$stmt = $conn->prepare(
    "DELETE FROM ml WHERE id = ? AND token = ?"
);

if (!$stmt) {
    $conn->close();

    sendResponse(false, "Unable to prepare delete query", 500);
}

// Bind parameters and execute
$stmt->bind_param("is", $id, $token);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();

    sendResponse(false, "Delete failed", 500);
}

// Check whether a row was deleted
$deleted = ($stmt->affected_rows > 0);

$stmt->close();
$conn->close();

if ($deleted) {
    sendResponse(true, "Deleted successfully");
}

sendResponse(false, "ML data not found", 404);
?>
