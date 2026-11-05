<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Render Environment Variables
$servername = getenv('DB_HOST');
$username   = getenv('DB_USER');
$password   = getenv('DB_PASSWORD');
$dbname     = getenv('DB_NAME');

if (!$servername || !$username || !$password || !$dbname) {
    sendResponse([], 500);
}

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);

if ($conn->connect_error) {
    sendResponse([], 500);
}

$conn->set_charset("utf8mb4");

$token = trim($_POST['token'] ?? $_GET['token'] ?? '');

if ($token === '' || strlen($token) > 255) {
    $conn->close();
    sendResponse([], 400);
}

$stmt = $conn->prepare(
    "SELECT id, q, a FROM ml WHERE token = ? ORDER BY id DESC"
);

if (!$stmt) {
    $conn->close();
    sendResponse([], 500);
}

$stmt->bind_param("s", $token);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    sendResponse([], 500);
}

$result = $stmt->get_result();
$items = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

$stmt->close();
$conn->close();

sendResponse($items);
?>
