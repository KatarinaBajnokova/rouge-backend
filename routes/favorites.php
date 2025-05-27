<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../config/database.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['backendUserId'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['backendUserId'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT item_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['favorites' => $favorites]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $item_id = $data['item_id'] ?? null;
    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing item_id']);
        exit;
    }
    $stmt = $db->prepare('INSERT OR IGNORE INTO favorites (user_id, item_id) VALUES (?, ?)');
    $stmt->execute([$user_id, $item_id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $item_id = $data['item_id'] ?? null;
    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing item_id']);
        exit;
    }
    $stmt = $db->prepare('DELETE FROM favorites WHERE user_id = ? AND item_id = ?');
    $stmt->execute([$user_id, $item_id]);
    echo json_encode(['success' => true]);
    exit;
}
