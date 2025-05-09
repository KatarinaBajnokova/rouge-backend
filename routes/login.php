<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/send.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (!$email || !$password) {
    send(['error' => 'Email and password are required'], 400);
}

$db = getDatabaseConnection();
$stmt = $db->prepare('SELECT id, password, salt FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    send(['error' => 'Invalid email or password'], 401);
}

$hashedInput = hash('sha256', $user['salt'] . $password);
if ($hashedInput !== $user['password']) {
    send(['error' => 'Invalid email or password'], 401);
}

// Auth success — set session and cookie
$_SESSION['user_id'] = $user['id'];
$_SESSION['session_token'] = bin2hex(random_bytes(32));

setcookie('session_token', $_SESSION['session_token'], [
    'expires' => time() + 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

send([
    'message' => 'Login successful',
    'user_id' => $user['id'],
    'session_token' => $_SESSION['session_token']
]);
