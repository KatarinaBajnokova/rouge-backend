<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/send.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

// POST /api/checkout
if ($method === 'POST' && $path === 'checkout') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    $stmt = $db->prepare('
        INSERT INTO orders (
          full_name, email, phone, address_1, address_2,
          company_name, vat_number, payment_method, is_gift,
          friend_name, friend_email
        )
        VALUES (
          :full_name, :email, :phone, :address_1, :address_2,
          :company_name, :vat_number, :payment_method, :is_gift,
          :friend_name, :friend_email
        )
    ');

    $stmt->execute([
        ':full_name'       => $data['full_name'] ?? null,
        ':email'           => $data['email'] ?? null,
        ':phone'           => $data['phone'] ?? null,
        ':address_1'       => $data['address_1'] ?? null,
        ':address_2'       => $data['address_2'] ?? null,
        ':company_name'    => $data['company_name'] ?? null,
        ':vat_number'      => $data['vat_number'] ?? null,
        ':payment_method'  => $data['payment_method'] ?? null,
        ':is_gift'         => !empty($data['is_gift']) ? 1 : 0,
        ':friend_name'     => $data['friend_name'] ?? null,
        ':friend_email'    => $data['friend_email'] ?? null,
    ]);

    send(['success' => true, 'order_id' => (int)$db->lastInsertId()], 201);
}
