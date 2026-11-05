
<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    "success" => true,
    "message" => "Telegram Bot API is running"
], JSON_UNESCAPED_UNICODE);
?>
