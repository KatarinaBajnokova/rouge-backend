<?php
require_once __DIR__ . '/../utils/cors.php';
require_once __DIR__ . '/../utils/send.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDatabaseConnection();

    $method = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim(str_replace('/api/', '', $requestUri), '/');

    if ($method === 'GET' && $path === 'items') {
        if (isset($_GET['category_group'])) {
            $stmt = $db->prepare('SELECT * FROM items WHERE category_group = :group');
            $stmt->execute([':group' => $_GET['category_group']]);
        } else {
            $stmt = $db->query('SELECT * FROM items');
        }
        send($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($method === 'GET' && preg_match('#^items/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) send(['error' => 'Not found'], 404);

        $imgStmt = $db->prepare("SELECT id, url FROM item_images WHERE item_id = ?");
        $imgStmt->execute([$id]);
        $item['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

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

        if (isset($item['tutorial_url'])) {
            $item['tutorialUrl'] = $item['tutorial_url'];
            unset($item['tutorial_url']);
        }

        send($item);
    }

    if ($method === 'POST' && $path === 'items') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        foreach (['name','category','level','price','image_url','category_group'] as $fld) {
            if (empty($data[$fld])) {
                send(['error' => "$fld is required"], 400);
            }
        }

        $stmt = $db->prepare('
            INSERT INTO items (name, category, level, price, image_url, category_group)
            VALUES (:name, :category, :level, :price, :image_url, :category_group)
        ');
        $stmt->execute([
            ':name' => $data['name'],
            ':category' => $data['category'],
            ':level' => $data['level'],
            ':price' => $data['price'],
            ':image_url' => $data['image_url'],
            ':category_group' => $data['category_group'],
        ]);
        $data['id'] = (int)$db->lastInsertId();
        send($data, 201);
    }

    if ($method === 'PUT' && preg_match('#^items/(\d+)$#', $path, $m)) {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $fields = ['name','category','level','price','image_url','category_group','description','tutorial_url'];
        $updates = []; $params = [':id' => $m[1]];
        foreach ($fields as $fld) {
            if (isset($data[$fld])) {
                $updates[] = "$fld = :$fld";
                $params[":$fld"] = $data[$fld];
            }
        }

        if (empty($updates)) {
            send(['error' => 'No valid fields to update'], 400);
        }

        $sql = 'UPDATE items SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        send(['updated' => (bool)$stmt->rowCount()]);
    }

    if ($method === 'DELETE' && preg_match('#^items/(\d+)$#', $path, $m)) {
        $stmt = $db->prepare('DELETE FROM items WHERE id = :id');
        $stmt->execute([':id' => $m[1]]);
        if ($stmt->rowCount()) {
            send(['deleted' => true]);
        } else {
            send(['error' => 'Not found'], 404);
        }
    }

    send(['error' => 'No matching items route'], 404);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => explode("\n", $e->getTraceAsString()),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}

