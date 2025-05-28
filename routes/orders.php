<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('🟢 Session started.');
} else {
    error_log('🟢 Session already active.');
}

try {
    $db = getDatabaseConnection();
    error_log('🟢 DB connection established.');
} catch (Throwable $e) {
    error_log('🔴 DB connection failed: ' . $e->getMessage());
    exit;
}

try {
    $method     = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path       = trim(str_replace('/api/', '', $requestUri), '/');
    error_log("🟢 Request received: METHOD = $method, PATH = $path, URI = $requestUri");

    // Save address id
    if ($method === 'POST' && $path === 'orders/assign-address') {
        error_log('🔵 Handling assign-address endpoint');
        $input = json_decode(file_get_contents('php://input'), true);

        $userId = $input['user_id'] ?? null;
        $addressId = $input['address_id'] ?? null;
        error_log("🔵 assign-address: userId = " . print_r($userId, true) . ", addressId = " . print_r($addressId, true));

        if (!$userId || !$addressId) {
            error_log('🔴 Missing user_id or address_id');
            send(['error' => 'Missing user_id or address_id'], 400);
        }

        $_SESSION['selected_address_id'] = $addressId;
        error_log("🟢 Address $addressId assigned to session for user $userId");

        send(['message' => 'Address assigned to session for next order']);
        exit;
    }

    // Create new order
    if ($method === 'POST' && $path === 'orders/create') {
        error_log('🔵 Handling create order endpoint');
        $input = json_decode(file_get_contents('php://input'), true);

        $userId = $_SESSION['backendUserId'] ?? null;
        error_log("🔵 create: userId from session = " . print_r($userId, true));
        if (!$userId) {
            error_log('🔴 Not authenticated');
            send(['error' => 'Not authenticated'], 401);
        }

        $addressId = $_SESSION['selected_address_id'] ?? null;
        error_log("🔵 create: addressId from session = " . print_r($addressId, true));
        if (!$addressId) {
            error_log('🔴 No address selected for this order');
            send(['error' => 'No address selected for this order'], 400);
        }

        $stmt = $db->prepare("INSERT INTO orders (user_id, address_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$userId, $addressId]);
        $orderId = $db->lastInsertId();

        error_log("🟢 Order created successfully for user $userId, address $addressId, orderId $orderId");

        unset($_SESSION['selected_address_id']);

        send(['message' => 'Order created successfully', 'order_id' => $orderId], 201);
        exit;
    }

    // Reorder: Get purchased looks for user
    if ($method === 'GET' && $path === 'orders/reorder') {
        error_log('🔵 Handling reorder endpoint');
        $userId = $_SESSION['backendUserId'] ?? null;
        error_log("🔵 reorder: userId from session = " . print_r($userId, true));
        if (!$userId) {
            error_log('🔴 Not authenticated');
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

        error_log("🟢 reorder: items fetched = " . print_r($items, true));

        send(['looks' => $items]);
        exit;
    }

    error_log('🔴 Endpoint not found');
    send(['error' => 'Endpoint not found'], 404);

} catch (Throwable $e) {
    error_log('🔴 Exception: ' . $e->getMessage());
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
