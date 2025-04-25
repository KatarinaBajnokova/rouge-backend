<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/send.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

// ─── GET /api/users ───
// Get all users (if needed)
if ($method === 'GET' && $path === 'users') {
    $stmt = $db->query('SELECT * FROM users');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send($rows);
}

// ─── POST /api/users/register ───
// Register a new user
if ($method === 'POST' && $path === 'users/register') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    // Validate input
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        send(['error' => 'Name, email, and password are required'], 400);
    }

    // Hash the password
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

    // Insert user into database
    $stmt = $db->prepare('
        INSERT INTO users (name, email, password)
        VALUES (:name, :email, :password)
    ');

    $stmt->execute([
        ':name' => $data['name'],
        ':email' => $data['email'],
        ':password' => $passwordHash
    ]);

    send(['success' => true]);
}

// ─── POST /api/users/login ───
// User login
if ($method === 'POST' && $path === 'users/login') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($data['email']) || empty($data['password'])) {
        send(['error' => 'Email and password are required'], 400);
    }

    // Find user by email
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data['password'], $user['password'])) {
        send(['error' => 'Invalid credentials'], 401);
    }

    // Return user data (e.g., token or user info)
    send(['success' => true, 'user' => $user]);
}

// ─── PUT /api/users/:id ───
// Update user details
if ($method === 'PUT' && preg_match('#^users/(\d+)$#', $path, $m)) {
    $userId = (int)$m[1];
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($data['name']) && empty($data['email'])) {
        send(['error' => 'No valid fields to update'], 400);
    }

    $updates = [];
    $params = [':id' => $userId];

    if (!empty($data['name'])) {
        $updates[] = "name = :name";
        $params[':name'] = $data['name'];
    }
    if (!empty($data['email'])) {
        $updates[] = "email = :email";
        $params[':email'] = $data['email'];
    }

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    send(['updated' => (bool)$stmt->rowCount()]);
}

// ─── DELETE /api/users/:id ───
// Delete a user
if ($method === 'DELETE' && preg_match('#^users/(\d+)$#', $path, $m)) {
    $userId = (int)$m[1];

    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);

    send(['success' => (bool)$stmt->rowCount()]);
}
