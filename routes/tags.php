<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

if ($method === 'GET' && $path === 'tags') {
    $stmt = $db->query('SELECT * FROM tags');
    send($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($method === 'POST' && $path === 'tags') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['name'])) {
        send(['error' => 'name is required'], 400);
    }

    $stmt = $db->prepare('INSERT INTO tags (name) VALUES (:name)');
    $stmt->execute([':name' => $data['name']]);
    send(['success' => true], 201);
}

if ($method === 'DELETE' && preg_match('#^tags/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $stmt = $db->prepare('DELETE FROM tags WHERE id = :id');
    $stmt->execute([':id' => $id]);
    send(['success' => (bool)$stmt->rowCount()]);
}
