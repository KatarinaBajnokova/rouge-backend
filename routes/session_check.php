<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

echo json_encode([
    "user_id" => $_SESSION['user_id']
]);
?>