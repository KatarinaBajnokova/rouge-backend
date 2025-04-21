<?php
require_once __DIR__ . '/../utils/response.php';

// GET /reviews?item_id=#
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === 'reviews') {
    if (!isset($_GET['item_id'])) {
        send(['error' => 'item_id is required'], 400);
    }

    $itemId = (int)$_GET['item_id'];

    $stmt = $db->prepare('SELECT * FROM reviews WHERE item_id = :item_id ORDER BY id DESC');
    $stmt->execute([':item_id' => $itemId]);
    send($stmt->fetchAll(PDO::FETCH_ASSOC));
}
