<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

// GET /api/instructions
if ($method === 'GET' && $path === 'instructions') {
    $stmt = $db->query('SELECT * FROM instructions');
    send($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// POST /api/instructions
if ($method === 'POST' && $path === 'instructions') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['item_id']) || empty($data['step_number']) || empty($data['text'])) {
        send(['error' => 'item_id, step_number, and text are required'], 400);
    }

    $stmt = $db->prepare('INSERT INTO instructions (item_id, step_number, title, text) VALUES (:item_id, :step_number, :title, :text)');
    $stmt->execute([
        ':item_id'    => (int)$data['item_id'],
        ':step_number' => (int)$data['step_number'],
        ':title'       => $data['title'] ?? null,
        ':text'        => $data['text'],
    ]);
    send(['success' => true], 201);
}

// PUT /api/instructions/:id
if ($method === 'PUT' && preg_match('#^instructions/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    $fields = ['item_id', 'step_number', 'title', 'text'];
    $updates = []; $params = [':id' => $id];

    foreach ($fields as $fld) {
        if (isset($data[$fld])) {
            $updates[]       = "$fld = :$fld";
            $params[":$fld"] = $data[$fld];
        }
    }

    if (empty($updates)) send(['error' => 'No valid fields to update'], 400);

    $sql = 'UPDATE instructions SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    send(['success' => (bool)$stmt->rowCount()]);
}

// DELETE /api/instructions/:id
if ($method === 'DELETE' && preg_match('#^instructions/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $stmt = $db->prepare('DELETE FROM instructions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    send(['success' => (bool)$stmt->rowCount()]);
}
