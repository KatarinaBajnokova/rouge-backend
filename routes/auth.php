<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api/', '', $requestUri), '/');

function loginUser(PDO $db) {
    session_start(); 

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = $data['email'] ?? '';

    if (!$email) {
        send(['error' => 'Email is required'], 400);
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send(['error' => 'User not found in backend'], 401);
    }

    $_SESSION['backendUserId'] = $user['id'];

    send([
        'message' => 'Backend login successful',
        'user_id' => (int)$user['id'],
    ], 200);
}

if ($method === 'POST' && $path === 'auth/login') {
    loginUser($db);
} else {
    send(['error' => 'Invalid route or method'], 405);
}
