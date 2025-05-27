<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $db = getDatabaseConnection();
} catch (Throwable $e) {
    exit;
}

try {
    $method     = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path       = trim(str_replace('/api/', '', $requestUri), '/');

    //save address id
    if ($method === 'POST' && $path === 'orders/assign-address') {
        $input = json_decode(file_get_contents('php://input'), true);

        $userId = $input['user_id'] ?? null;
        $addressId = $input['address_id'] ?? null;

        if (!$userId || !$addressId) {
            send(['error' => 'Missing user_id or address_id'], 400);
        }

        //store addresses
        $_SESSION['selected_address_id'] = $addressId;

        send(['message' => 'Address assigned to session for next order']);
        exit;
    }

    if ($method === 'POST' && $path === 'orders/create') {
        $input = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['backendUserId'] ?? null;
        if (!$userId) {
            send(['error' => 'Not authenticated'], 401);
        }

        $addressId = $_SESSION['selected_address_id'] ?? null;

        if (!$addressId) {
            send(['error' => 'No address selected for this order'], 400);
        }

        $stmt = $db->prepare("INSERT INTO orders (user_id, address_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$userId, $addressId]);


        unset($_SESSION['selected_address_id']);

        send(['message' => 'Order created successfully', 'order_id' => $db->lastInsertId()], 201);
        exit;
    }


    if ($method === 'GET' && $path === 'orders/reorder') {
        $userId = $_SESSION['backendUserId'] ?? null;
        if (!$userId) {
            send(['error' => 'Not authenticated'], 401);
        }

        $stmt = $db->prepare("
          SELECT i.id, i.name, i.category, i.level, i.price, i.image_url
          FROM order_items oi
          JOIN orders o ON oi.order_id = o.id
          JOIN items i ON oi.item_id = i.id
          WHERE o.user_id = ?
          GROUP BY i.id
          ORDER BY MAX(o.created_at) DESC
        ");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        send($items);
    }

    send(['error' => 'Endpoint not found'], 404);

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ]);
    exit;
}
