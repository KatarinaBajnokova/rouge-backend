<?php
require_once __DIR__ . '/../utils/response.php';

// POST /basket → Add or update quantity (max 10)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === 'basket') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['item_id'])) send(['error' => 'item_id is required'], 400);

    $itemId = (int)$data['item_id'];

    // Check if item is already in the basket
    $stmt = $db->prepare('SELECT id, quantity FROM basket WHERE item_id = :item_id');
    $stmt->execute([':item_id' => $itemId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['quantity'] >= 10) {
            send(['error' => 'Maximum quantity reached for this item'], 400);
        }

        $stmt = $db->prepare('UPDATE basket SET quantity = quantity + 1 WHERE id = :id');
        $stmt->execute([':id' => $existing['id']]);
        send(['updated' => true, 'id' => $existing['id']]);
    } else {
        $stmt = $db->prepare('INSERT INTO basket (item_id, quantity) VALUES (:item_id, 1)');
        $stmt->execute([':item_id' => $itemId]);
        send(['created' => true, 'id' => $db->lastInsertId()], 201);
    }
}

// GET /basket → All basket items + total price
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === 'basket') {
    $stmt = $db->query('
        SELECT
            b.id AS id,
            b.item_id,
            b.quantity,
            i.name,
            i.price,
            i.image_url,
            i.level,
            i.category
        FROM basket b
        JOIN items i ON i.id = b.item_id
        ORDER BY b.id DESC
    ');
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total price
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    send([
        'items' => $items,
        'total_price' => round($total, 2)
    ]);
}

// PUT /basket/:id → Update quantity (min 1, max 10)
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('#^basket/(\d+)$#', $path, $m)) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $quantity = (int)($data['quantity'] ?? 1);
    $quantity = max(1, min($quantity, 10)); // clamp value between 1–10

    $stmt = $db->prepare('UPDATE basket SET quantity = :q WHERE id = :id');
    $stmt->execute([':q' => $quantity, ':id' => $m[1]]);
    send(['updated' => true]);
}

// DELETE /basket?id=... → Remove a single item
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $path === 'basket' && isset($_GET['id'])) {
    $stmt = $db->prepare('DELETE FROM basket WHERE id = :id');
    $stmt->execute([':id' => $_GET['id']]);
    send(['deleted' => true]);
}

// DELETE /basket → Clear all items
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $path === 'basket') {
    $db->exec('DELETE FROM basket');
    send(['cleared' => true]);
}
