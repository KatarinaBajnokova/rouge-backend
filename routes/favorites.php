<?php

require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

session_start();
$pdo = getDatabaseConnection();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$userId = $_SESSION['backendUserId'] ?? 0;

if (!$userId && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE')) {
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
}

$token = $input['auth_token'] ?? '';
if (!$userId && $token) {
    $userId = validateToken($token);
}

if (!$userId) {
    send(['error' => 'User not authenticated or missing user_id'], 401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;

    if (!$itemId) {
        error_log("❌ Missing item_id in POST");
        send(['error' => 'Missing item_id'], 400);
        exit;
    }

    error_log("✅ Favoriting item $itemId for user $userId");

    $pdo->beginTransaction();
    try {
        $chk = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = :uid AND item_id = :iid");
        $chk->execute([':uid' => $userId, ':iid' => $itemId]);

        if ($chk->fetch()) {
            $del = $pdo->prepare("DELETE FROM favorites WHERE user_id = :uid AND item_id = :iid");
            $del->execute([':uid' => $userId, ':iid' => $itemId]);
            $pdo->commit();
            send(['favorited' => false]);
        } else {
            $ins = $pdo->prepare("INSERT INTO favorites (user_id, item_id) VALUES (:uid, :iid)");
            $ins->execute([':uid' => $userId, ':iid' => $itemId]);
            $pdo->commit();
            send(['favorited' => true]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("❌ DB error on favoriting: " . $e->getMessage());
        send(['error' => 'Database error'], 500);
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT i.* FROM favorites f JOIN items i ON i.id = f.item_id WHERE f.user_id = :uid ORDER BY f.id DESC");
    $stmt->execute([':uid' => $userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send($items);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;

    if (!$itemId) {
        send(['error' => 'Missing item_id'], 400);
        exit;
    }

    $chk = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = :uid AND item_id = :iid");
    $chk->execute([':uid' => $userId, ':iid' => $itemId]);

    if ($chk->fetch()) {
        $del = $pdo->prepare("DELETE FROM favorites WHERE user_id = :uid AND item_id = :iid");
        $del->execute([':uid' => $userId, ':iid' => $itemId]);
        send(['message' => 'Item removed from favorites']);
    } else {
        send(['error' => 'Item not found in favorites'], 404);
    }

    exit;
}

send(['error' => 'Method not allowed'], 405);

function validateToken($token) {
    return $token === 'validToken123' ? 1 : 0;
}
