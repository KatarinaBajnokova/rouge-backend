<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

session_start();

//echo "✅ orders.php loaded<br>";

if (!function_exists('getDatabaseConnection')) {
    //echo "❌ getDatabaseConnection is not defined<br>";
    exit;
}

try {
    $db = getDatabaseConnection();
    //echo "✅ DB connected<br>";
} catch (Throwable $e) {
    //echo "❌ DB connection failed: " . $e->getMessage();
    exit;
}

// Optional: remove this line once you're ready to proceed
// exit;

try {
    $method     = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path       = trim(str_replace('/api/', '', $requestUri), '/');

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
