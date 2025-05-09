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

// HANDLERS

function createUser($db) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    foreach (['first_name', 'last_name', 'email', 'password'] as $field) {
        if (empty($data[$field])) {
            send(['error' => "$field is required"], 400);
        }
    }

    $salt = generateSalt(); // STEP 1: Generate salt
    $passwordHash = hash('sha256', $salt . $data['password']); // STEP 2: Salted hash

    // STEP 3: Insert into database with salt
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

    if (empty($data['user_id'])) {
        send(['error' => 'user_id is required'], 400);
    }

    $allowedFields = [
        'address_1', 'phone', 'look_id',
        'profile_image', 'birthdate', 'country', 'secondary_email'
    ];

    $updates = [];
    $params = [':user_id' => $data['user_id']];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = :$field";
            $params[":$field"] = $data[$field];
        }
    }

    if (empty($updates)) {
        send(['error' => 'No valid fields to update'], 400);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :user_id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    send(['message' => 'User profile updated']);
}

function fetchUserByEmail($db) {
    if (empty($_GET['email'])) {
        send(['error' => 'Email is required'], 400);
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $_GET['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send(['error' => 'User not found'], 404);
    }

    send(['user_id' => $user['id']]);
}

function fetchUserById($db, $userId) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send(['error' => 'User not found'], 404);
    }

    unset($user['password']); //never send password to frontend
    send($user);
}

function fetchUserByFirebaseUid($db) {
    $uid = $_GET['uid'] ?? '';
    if (!$uid) {
        send(['error' => 'Missing Firebase UID'], 400);
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE firebase_uid = :uid');
    $stmt->execute([':uid' => $uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send(['error' => 'User not found'], 404);
    }

    unset($user['password']);
    unset($user['salt']);
    send($user);
}

// ROUTING

if ($method === 'POST' && $path === 'users') {
    createUser($db);
} elseif ($method === 'POST' && $subpath === 'update') {
    updateUser($db);
} elseif ($method === 'GET' && $subpath === 'by-email') {
    fetchUserByEmail($db);
} elseif ($method === 'GET' && preg_match('#^(\d+)$#', $subpath, $matches)) {
    fetchUserById($db, (int)$matches[1]);
} elseif ($method === 'GET' && $subpath === 'by-firebase-uid') {
    fetchUserByFirebaseUid($db);
}  else {
    send(['error' => 'Invalid API route or method.'], 405);
}
?>
