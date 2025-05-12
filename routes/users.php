<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/send.php';

function generateSalt($length = 32) {
    return bin2hex(random_bytes($length));
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');
$subpath = trim(str_replace('users/', '', $path), '/');

error_log("🔀 Routing: method=$method path=$path subpath=$subpath");

// ✅ Handle Firebase UID fetch directly first
if ($method === 'GET' && $path === 'users/by-firebase-uid') {
    fetchUserByFirebaseUid($db);
    exit;
}

if ($method === 'POST' && $path === 'users/by-firebase-uid') {
    updateUserByFirebaseUid($db);
    exit;
}


// HANDLERS

function createUser($db) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    foreach (['first_name', 'last_name', 'email', 'password'] as $field) {
        if (empty($data[$field])) {
            send(['error' => "$field is required"], 400);
        }
    }

    $salt = generateSalt();
    $passwordHash = hash('sha256', $salt . $data['password']);

    error_log("Creating user with UID: " . ($data['firebase_uid'] ?? 'null'));

    $stmt = $db->prepare('
        INSERT INTO users (first_name, last_name, email, password, salt, firebase_uid)
        VALUES (:first_name, :last_name, :email, :password, :salt, :firebase_uid)
    ');

    $stmt->execute([
        ':first_name' => $data['first_name'],
        ':last_name'  => $data['last_name'],
        ':email'      => $data['email'],
        ':password'   => $passwordHash,
        ':salt'       => $salt,
        ':firebase_uid' => $data['firebase_uid'] ?? null,
    ]);

    send([
        'message' => 'User created successfully',
        'user_id' => (int)$db->lastInsertId(),
    ], 201);
}

function updateUser($db) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['user_id'])) send(['error' => 'user_id is required'], 400);

    $allowedFields = ['address_1', 'phone', 'look_id', 'profile_image', 'birthdate', 'country', 'secondary_email'];
    $updates = [];
    $params = [':user_id' => $data['user_id']];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = :$field";
            $params[":$field"] = $data[$field];
        }
    }

    if (empty($updates)) send(['error' => 'No valid fields to update'], 400);

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :user_id'; // ✅ ID not firebase_uid
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    send(['message' => 'User profile updated']);
}

function fetchUserByEmail($db) {
    if (empty($_GET['email'])) send(['error' => 'Email is required'], 400);

    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $_GET['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) send(['error' => 'User not found'], 404);
    send(['user_id' => $user['id']]);
}

function fetchUserById($db, $userId) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) send(['error' => 'User not found'], 404);
    unset($user['password']);
    send($user);
}

function fetchUserByFirebaseUid($db) {
    $uid = $_GET['uid'] ?? '';
    error_log("🔍 Looking for Firebase UID: $uid");

    if (!$uid) {
        error_log("⛔ No UID provided");
        send(['error' => 'Missing Firebase UID'], 400);
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE firebase_uid = :uid');
    $stmt->execute([':uid' => $uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("❌ No user found for UID: $uid");
        send(['error' => 'User not found'], 404);
    }

    error_log("✅ User found for UID: $uid");

    unset($user['password'], $user['salt']);
    send($user);   // ✅ send full clean user object
}



function updateUserByFirebaseUid($db) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($data['firebase_uid'])) {
        send(['error' => 'firebase_uid is required'], 400);
    }

    $allowedFields = ['address_1', 'phone', 'look_id', 'profile_image', 'birthdate', 'country', 'secondary_email'];
    $updates = [];
    $params = [':firebase_uid' => $data['firebase_uid']];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = :$field";
            $params[":$field"] = $data[$field];
        }
    }

    if (empty($updates)) {
        send(['error' => 'No valid fields to update'], 400);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE firebase_uid = :firebase_uid';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    send(['message' => 'User profile updated via firebase_uid']);
}


// ROUTING (fallback)

if ($method === 'POST' && $path === 'users') {
    createUser($db);
} elseif ($method === 'POST' && $subpath === 'update') {
    updateUser($db);
} elseif ($method === 'GET' && $subpath === 'by-email') {
    fetchUserByEmail($db);
} elseif ($method === 'GET' && preg_match('#^(\d+)$#', $subpath, $matches)) {
    fetchUserById($db, (int)$matches[1]);
} else {
    send(['error' => 'Invalid API route or method.'], 405);
}
