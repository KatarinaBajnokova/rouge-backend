<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

if ($method === 'GET' && preg_match('#^reviews/(\d+)$#', $path, $m)) {
    $itemId = (int)$m[1];
    $stmt = $db->prepare('SELECT id, author, rating, comment FROM reviews WHERE item_id = :id');
    $stmt->execute([':id' => $itemId]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send($reviews);
}

if ($method === 'POST' && $path === 'reviews') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (
        empty($data['item_id']) ||
        empty($data['author']) ||
        empty($data['rating']) ||
        empty($data['comment'])
    ) {
        send(['error' => 'Missing required review fields'], 400);
    }

    $userId = $data['user_id'] ?? null;

    $stmt = $db->prepare('
        INSERT INTO reviews (item_id, author, rating, comment, user_id)
        VALUES (:item_id, :author, :rating, :comment, :user_id)
    ');
    $stmt->execute([
        ':item_id' => $data['item_id'],
        ':author'  => $data['author'],
        ':rating'  => $data['rating'],
        ':comment' => $data['comment'],
        ':user_id' => $userId,
    ]);

    send(['success' => true, 'review_id' => (int)$db->lastInsertId()], 201);
}
