<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';
session_start();


$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api/', '', $requestUri), '/');

// POST /api/checkout
if ($method === 'POST' && $path === 'checkout') {
    // 1) ensure user is logged in
    $userId = $_SESSION['backendUserId'] ?? null;
    if (!$userId) {
        http_response_code(401);
        send(['error'=>'Not authenticated']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    // 2) insert into orders (with user_id)
    $stmt = $db->prepare('
      INSERT INTO orders (
        user_id,
        full_name, email, phone,
        address_1, address_2,
        company_name, vat_number,
        payment_method, is_gift,
        friend_name, friend_email
      ) VALUES (
        :user_id,
        :full_name, :email, :phone,
        :address_1, :address_2,
        :company_name, :vat_number,
        :payment_method, :is_gift,
        :friend_name, :friend_email
      )
    ');
    $stmt->execute([
      ':user_id'        => $userId,
      ':full_name'      => $data['full_name']      ?? null,
      ':email'          => $data['email']          ?? null,
      ':phone'          => $data['phone']          ?? null,
      ':address_1'      => $data['address_1']      ?? null,
      ':address_2'      => $data['address_2']      ?? null,
      ':company_name'   => $data['company_name']   ?? null,
      ':vat_number'     => $data['vat_number']     ?? null,
      ':payment_method' => $data['payment_method'] ?? null,
      ':is_gift'        => !empty($data['is_gift']) ? 1 : 0,
      ':friend_name'    => $data['friend_name']    ?? null,
      ':friend_email'   => $data['friend_email']   ?? null,
    ]);

    $orderId = (int)$db->lastInsertId();

    // 3) insert line-items into order_items
    $stmtItem = $db->prepare('
      INSERT INTO order_items (order_id, item_id, quantity)
      VALUES (?, ?, ?)
    ');
    foreach ($data['items'] ?? [] as $it) {
      $itemId = isset($it['item_id']) ? (int)$it['item_id'] : null;
      $qty    = isset($it['quantity'])  ? (int)$it['quantity']  : 1;
      if ($itemId) {
        $stmtItem->execute([$orderId, $itemId, $qty]);
      }
    }

    // 4) respond
    send(['success'=>true,'order_id'=>$orderId], 201);
    exit;
}

// fallback
send(['error'=>'Endpoint not found'], 404);
