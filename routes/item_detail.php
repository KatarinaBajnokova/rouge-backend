<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

$db = getDatabaseConnection();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
  send(['error' => 'Missing item ID'], 400);
}

$id = (int) $_GET['id'];

$stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
  send(['error' => 'Item not found'], 404);
}

$imgStmt = $db->prepare("SELECT id, url FROM item_images WHERE item_id = ?");
$imgStmt->execute([$id]);
$item['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

$boxStmt = $db->prepare("SELECT id, title, image_url, description FROM box_products WHERE item_id = ?");
$boxStmt->execute([$id]);
$item['boxProducts'] = $boxStmt->fetchAll(PDO::FETCH_ASSOC);

$instructionStmt = $db->prepare("
  SELECT step_number, title, text 
  FROM instructions 
  WHERE item_id = ? 
  ORDER BY step_number ASC
");
$instructionStmt->execute([$id]);
$item['instructions'] = $instructionStmt->fetchAll(PDO::FETCH_ASSOC);

$reviewStmt = $db->prepare("SELECT id, author, rating, comment FROM reviews WHERE item_id = ?");
$reviewStmt->execute([$id]);
$item['reviews'] = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

$tagStmt = $db->prepare("
  SELECT t.name
  FROM tags t
  JOIN item_tags it ON t.id = it.tag_id
  WHERE it.item_id = ?
");
$tagStmt->execute([$id]);
$item['tags'] = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

if (isset($item['thumbnail'])) {
  $item['thumbnailUrl'] = $item['thumbnail'];
  unset($item['thumbnail']);
}

send($item);
