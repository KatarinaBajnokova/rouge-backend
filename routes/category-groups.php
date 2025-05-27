<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$db = getDatabaseConnection();

$stmt = $db->query(
    'SELECT id, name, sort_order
       FROM category_groups
      ORDER BY sort_order'
);

send($stmt->fetchAll(PDO::FETCH_ASSOC));
