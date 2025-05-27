<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$db = getDatabaseConnection();

$categoryId = isset($_GET['categoryId']) ? (int) $_GET['categoryId'] : 0;
if ($categoryId <= 0) {
  send(['error'=>'Missing or invalid categoryId'], 400);
}

$stmt = $db->prepare(
  'SELECT id, category_id, name, sort_order
     FROM subcategories
    WHERE category_id = ?
    ORDER BY sort_order'
);
$stmt->execute([$categoryId]);

send($stmt->fetchAll(PDO::FETCH_ASSOC));
