<?php
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$name = getenv('DB_NAME');

if (!$host || !$user || !$pass || !$name) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Missing database environment variables'
    ]);
    exit;
}

$conn = @new mysqli($host, $user, $pass, $name);

if ($conn->connect_error) {
    http_response_code(500);
    error_log('Database connection failed: ' . $conn->connect_error);

    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Check Render logs.'
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

$tables = [];
$result = $conn->query("SHOW TABLES");

if ($result) {
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $tables[] = $row[0];
    }
    $result->free();
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Database connection successful',
    'tables' => $tables
], JSON_UNESCAPED_UNICODE);
?>
