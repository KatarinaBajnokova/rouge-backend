<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';


$db = getDatabaseConnection();

$groupId = isset($_GET['groupId']) ? (int) $_GET['groupId'] : 0;
if ($groupId <= 0) {
    send(['error' => 'Missing or invalid groupId'], 400);
}


$stmt = $db->prepare(
    'SELECT 
        id, 
        category_group_id, 
        name, 
        icon_url,         
        sort_order
     FROM categories
    WHERE category_group_id = ?
    ORDER BY sort_order'
);
$stmt->execute([$groupId]);

send($stmt->fetchAll(PDO::FETCH_ASSOC));
