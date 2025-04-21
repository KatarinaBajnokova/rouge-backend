<?php
require_once __DIR__ . '/../utils/response.php';

// GET /product/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^product/(\d+)$#', $path, $m)) {
    $id = (int) $m[1];

    // Get main item
    $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        send(['error' => 'Item not found'], 404);
    }

    // Get image URLs
    $stmt = $db->prepare('SELECT image_url FROM item_images WHERE item_id = :id');
    $stmt->execute([':id' => $id]);
    $item['images'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'image_url');

    // Get box products
    $stmt = $db->prepare('SELECT * FROM box_products WHERE item_id = :id');
    $stmt->execute([':id' => $id]);
    $item['boxProducts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get instructions
    $stmt = $db->prepare('SELECT * FROM instructions WHERE item_id = :id');
    $stmt->execute([':id' => $id]);
    $item['instructions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get reviews
    $stmt = $db->prepare('SELECT * FROM reviews WHERE item_id = :id ORDER BY id DESC');
    $stmt->execute([':id' => $id]);
    $item['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return final assembled item
    send($item);
}
