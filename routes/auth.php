<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/send.php';

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path       = trim(str_replace('/api/', '', $requestUri), '/');

function loginUser(PDO $db) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['email']) || empty($data['password'])) {
        send(['error' => 'email and password are required'], 400);
    }

    $stmt = $db->prepare('SELECT id, password FROM users WHERE email = :email');
    $stmt->execute([':email' => $data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data['password'], $user['password'])) {
        send(['error' => 'Invalid credentials'], 401);
    }

    send([
      'message' => 'Login successful',
      'user_id' => (int)$user['id'],
    ], 200);
}


if ($method === 'POST' && $path === 'auth/login') {
    loginUser($db);
} else {
    
    send(['error' => 'Invalid route or method'], 405);
}
