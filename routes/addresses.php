<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

$db = getDatabaseConnection();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

error_log("🔀 Routing: method=$method path=$path");

if ($method === 'POST' && $path === 'addresses/add') {
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

    send([
  'message' => 'Address added successfully',
  'address_id' => $db->lastInsertId()
], 201);
    exit;
}


if ($method === 'GET' && $path === 'addresses/list') {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
        send(['error' => 'user_id is required'], 400);
    }

    $stmt = $db->prepare('SELECT * FROM addresses WHERE user_id = ?');
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send($addresses);
    exit;
}

send(['error' => 'Invalid route or method for addresses API.'], 405);
