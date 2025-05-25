<?php
session_start();
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

$db = getDatabaseConnection();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');
$subpath = trim(str_replace('users/', '', $path), '/');

error_log("🔀 Routing: method=$method path=$path subpath=$subpath");

// ========== USERS HANDLERS ==========

function createUser($db) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    foreach (['first_name', 'last_name', 'email', 'password'] as $field) {
        if (empty($data[$field])) {
            send(['error' => "$field is required"], 400);
        }
    }

    $passwordHash = hash('sha256', $data['password']);

    error_log("Creating user with UID: " . ($data['firebase_uid'] ?? 'null'));

    $stmt = $db->prepare('
        INSERT INTO users (first_name, last_name, email, password, firebase_uid)
        VALUES (:first_name, :last_name, :email, :password, :firebase_uid)
    ');

    $stmt->execute([
        ':first_name' => $data['first_name'],
        ':last_name'  => $data['last_name'],
        ':email'      => $data['email'],
        ':password'   => $passwordHash,
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

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :user_id';
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
        send(['error' => 'Missing Firebase UID'], 400);
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE firebase_uid = :uid');
    $stmt->execute([':uid' => $uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send(['error' => 'User not found'], 404);
    }

    // ✅ Set the session user ID here
    $_SESSION['backendUserId'] = $user['id'];
    error_log("✅ Session set: backendUserId=" . $_SESSION['backendUserId']);

    unset($user['password']);
    send($user);
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

function logoutUser() {
    session_start();
    session_unset();
    session_destroy();

    // Explicitly remove session cookie from browser
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    require_once __DIR__ . '/../utils/cors.php';
    require_once __DIR__ . '/../utils/send.php';

    send(['message' => 'Logged out']);
}


// ========== ADDRESSES HANDLERS ==========

function addAddress($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    $userId = $input['user_id'] ?? null;
    $address1 = $input['address_1'] ?? null;
    $address2 = $input['address_2'] ?? '';
    $postalCode = $input['postal_code'] ?? null;
    $country = $input['country'] ?? null;
    $phone = $input['phone'] ?? null;

    if (!$userId || !$address1 || !$postalCode || !$country || !$phone) {
        send(['error' => 'Missing required fields'], 400);
    }

    $stmt = $db->prepare("INSERT INTO addresses (user_id, address_1, address_2, postal_code, country, phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $address1, $address2, $postalCode, $country, $phone]);

    send(['message' => 'Address added successfully'], 201);
}

function listAddresses($db) {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
        send(['error' => 'user_id is required'], 400);
    }

    $stmt = $db->prepare('SELECT * FROM addresses WHERE user_id = ?');
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send($addresses);
}

// ========== ROUTING ==========

if ($method === 'GET' && $path === 'users/by-firebase-uid') {
    fetchUserByFirebaseUid($db);
    exit;
}

if ($method === 'POST' && $path === 'users/by-firebase-uid') {
    updateUserByFirebaseUid($db);
    exit;
}

if ($method === 'POST' && $path === 'users') {
    createUser($db);
    exit;
}

if ($method === 'POST' && $subpath === 'update') {
    updateUser($db);
    exit;
}

if ($method === 'POST' && $path === 'users/logout') {
    logoutUser();
    exit;
}

if ($method === 'GET' && $subpath === 'by-email') {
    fetchUserByEmail($db);
    exit;
}

if ($method === 'GET' && preg_match('#^(\d+)$#', $subpath, $matches)) {
    fetchUserById($db, (int)$matches[1]);
    exit;
}

if ($method === 'POST' && ($path === 'addresses/add' || $path === 'users/addresses/add')) {
    addAddress($db);
    exit;
}

if ($method === 'GET' && $path === 'addresses/list') {
    listAddresses($db);
    exit;
}

if ($method === 'GET' && preg_match('#^users/(\d+)/addresses$#', $path, $matches)) {
    $userId = (int)$matches[1];

    $stmt = $db->prepare('SELECT * FROM addresses WHERE user_id = ?');
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send($addresses);
    exit;
}

if ($method === 'GET' && $path === 'session/debug') {
    send([
        'session_id' => session_id(),
        'backendUserId' => $_SESSION['backendUserId'] ?? null,
        'session_status' => session_status()
    ]);
}


// 🚨 If none matched
send(['error' => 'Invalid API route or method.'], 405);
