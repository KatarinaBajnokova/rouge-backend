<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$db    = getDatabaseConnection();
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if ($query === '') {
 
    send([], 200);
}


$stmt = $db->prepare(
    'SELECT DISTINCT name
       FROM items
      WHERE LOWER(name) LIKE :q
      ORDER BY name
      LIMIT 10'
);
$stmt->execute([':q' => '%' . strtolower($query) . '%']);

send($stmt->fetchAll(PDO::FETCH_ASSOC));
