<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDatabaseConnection();

    $method = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim(str_replace('/api/', '', $requestUri), '/');

    if ($method === 'GET' && $path === 'basket') {
        $stmt = $db->query(
            'SELECT b.id AS basketId, b.quantity, i.id AS itemId, i.name, i.image_url, i.category, i.level, i.price
             FROM basket b
             JOIN items i ON i.id = b.item_id'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(function($r) {
            return [
                'id'        => (int)$r['basketId'],
                'item_id'   => (int)$r['itemId'],
                'name'      => $r['name'],
                'image_url' => $r['image_url'],
                'category'  => $r['category'],
                'level'     => $r['level'],
                'price'     => (float)$r['price'],
                'quantity'  => (int)$r['quantity'],
            ];
        }, $rows);
        $total = array_reduce($items, fn($sum, $it) => $sum + $it['price'] * $it['quantity'], 0.0);
        send(['items' => $items, 'total_price' => round($total, 2)]);
    }

    if ($method === 'POST' && $path === 'basket') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        if (empty($data['item_id'])) {
            send(['error' => 'item_id is required'], 400);
        }

        $itemId = (int)$data['item_id'];
        $qty = isset($data['quantity']) && is_int($data['quantity']) ? $data['quantity'] : 1;

        $sel = $db->prepare('SELECT id, quantity FROM basket WHERE item_id = :iid');
        $sel->execute([':iid' => $itemId]);
        if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
            $newQty = $row['quantity'] + $qty;
            $upd = $db->prepare('UPDATE basket SET quantity = :q WHERE id = :bid');
            $upd->execute([':q' => $newQty, ':bid' => $row['id']]);
        } else {
            $ins = $db->prepare('INSERT INTO basket (item_id, quantity) VALUES (:iid, :q)');
            $ins->execute([':iid' => $itemId, ':q' => $qty]);
        }

        send(['success' => true]);
    }

    if ($method === 'PUT' && preg_match('#^basket/(\d+)$#', $path, $m)) {
        $bid = (int)$m[1];
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        if (!isset($data['quantity']) || !is_int($data['quantity'])) {
            send(['error' => 'quantity must be integer'], 400);
        }

        $stmt = $db->prepare('UPDATE basket SET quantity = :q WHERE id = :bid');
        $stmt->execute([':q' => $data['quantity'], ':bid' => $bid]);
        send(['success' => (bool)$stmt->rowCount()]);
    }

    if ($method === 'DELETE' && $path === 'basket') {
        $stmt = $db->prepare('DELETE FROM basket');
        $stmt->execute();
        send(['success' => true]);
    }

    if ($method === 'DELETE' && preg_match('#^basket/(\d+)$#', $path, $m)) {
        $bid = (int)$m[1];
        $stmt = $db->prepare('DELETE FROM basket WHERE id = :bid');
        $stmt->execute([':bid' => $bid]);
        send(['success' => (bool)$stmt->rowCount()]);
    }

    send(['error' => 'Unsupported basket route'], 404);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => explode("\n", $e->getTraceAsString()),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}

