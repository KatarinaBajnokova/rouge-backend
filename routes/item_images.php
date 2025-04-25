<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/send.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

// ─── GET /api/item_images ───
if ($method === 'GET' && $path === 'item_images') {
    $stmt = $db->query('SELECT * FROM item_images');
    send($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ─── POST /api/item_images ───
if ($method === 'POST' && $path === 'item_images') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['item_id']) || empty($data['url'])) {
        send(['error' => 'item_id and url are required'], 400);
    }

    $stmt = $db->prepare('INSERT INTO item_images (item_id, url) VALUES (:item_id, :url)');
    $stmt->execute([
        ':item_id' => (int)$data['item_id'],
        ':url'     => $data['url'],
    ]);
    send(['success' => true], 201);
}

// ─── DELETE /api/item_images/:id ───
if ($method === 'DELETE' && preg_match('#^item_images/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $stmt = $db->prepare('DELETE FROM item_images WHERE id = :id');
    $stmt->execute([':id' => $id]);
    send(['success' => (bool)$stmt->rowCount()]);
}
