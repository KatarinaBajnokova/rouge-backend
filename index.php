<?php
// rouge-backend/index.php

// 1) DEV: full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2) CORS + JSON headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// 3) Open the SQLite DB
try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not open database: ' . $e->getMessage()]);
    exit;
}

// 4) Ensure core items table exists
$db->exec('
  CREATE TABLE IF NOT EXISTS items (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    name           TEXT    NOT NULL,
    category       TEXT    NOT NULL,
    level          TEXT    NOT NULL,
    price          REAL    NOT NULL,
    image_url      TEXT    NOT NULL,
    category_group TEXT    NOT NULL,
    description    TEXT    DEFAULT \'\',
    tutorial_url   TEXT    DEFAULT \'\'
  )
');

// 5) Ensure the six supporting tables exist
$db->exec('
  CREATE TABLE IF NOT EXISTS item_images (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    url     TEXT    NOT NULL,
    FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
  );
  CREATE TABLE IF NOT EXISTS tags (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT    UNIQUE NOT NULL
  );
  CREATE TABLE IF NOT EXISTS item_tags (
    item_id INTEGER NOT NULL,
    tag_id  INTEGER NOT NULL,
    PRIMARY KEY(item_id, tag_id),
    FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY(tag_id)  REFERENCES tags(id) ON DELETE CASCADE
  );
  CREATE TABLE IF NOT EXISTS box_products (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id     INTEGER NOT NULL,
    title       TEXT    NOT NULL,
    image_url   TEXT    NOT NULL,
    description TEXT    NOT NULL,
    FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
  );
  CREATE TABLE IF NOT EXISTS instructions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id     INTEGER NOT NULL,
    step_number INTEGER NOT NULL,
    title       TEXT,
    text        TEXT    NOT NULL,
    FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
  );
  CREATE TABLE IF NOT EXISTS reviews (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    author  TEXT    NOT NULL,
    rating  INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    comment TEXT    NOT NULL,
    FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
  );
');

// Helper to send JSON + status
function send($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// 6) Routing
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api', '', $requestUri), '/');

// Root
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge‑Backend API']);
}

// GET /api/items or /api/items?category_group=…
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === 'items') {
    try {
        if (isset($_GET['category_group'])) {
            $stmt = $db->prepare('SELECT * FROM items WHERE category_group = :group');
            $stmt->execute([':group' => $_GET['category_group']]);
        } else {
            $stmt = $db->query('SELECT * FROM items');
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send($rows);
    } catch (Exception $e) {
        error_log('[API ERROR] ' . $e->getMessage());
        send(['error' => 'Internal server error'], 500);
    }
}

// GET /api/items/:id  ← enriched response
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    try {
        // 1) Main item
        $stmt = $db->prepare('
          SELECT id,name,category,level,price,image_url,category_group,description,tutorial_url
            FROM items
           WHERE id = :id
        ');
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            send(['error' => 'Not found'], 404);
        }

        // 2) Carousel images
        $stmt = $db->prepare('SELECT url FROM item_images WHERE item_id = :id ORDER BY id');
        $stmt->execute([':id' => $id]);
        $item['images'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'url');

        // 3) Tags
        $stmt = $db->prepare('
          SELECT t.name
            FROM tags t
            JOIN item_tags it ON it.tag_id = t.id
           WHERE it.item_id = :id
        ');
        $stmt->execute([':id' => $id]);
        $item['tags'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');

        // 4) Box products
        $stmt = $db->prepare('
          SELECT id,title,image_url,description
            FROM box_products
           WHERE item_id = :id
        ');
        $stmt->execute([':id' => $id]);
        $item['boxProducts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5) Instructions
        $stmt = $db->prepare('
          SELECT step_number,title,text
            FROM instructions
           WHERE item_id = :id
           ORDER BY step_number
        ');
        $stmt->execute([':id' => $id]);
        $item['instructions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6) Reviews
        $stmt = $db->prepare('SELECT id,author,rating,comment FROM reviews WHERE item_id = :id');
        $stmt->execute([':id' => $id]);
        $item['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 7) Camel‑case the tutorial_url field
        $item['tutorialUrl'] = $item['tutorial_url'];
        unset($item['tutorial_url']);

        send($item);
    } catch (Exception $e) {
        error_log('[API ERROR] ' . $e->getMessage());
        send(['error' => 'Internal server error'], 500);
    }
}

// POST /api/items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === 'items') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    foreach (['name','category','level','price','image_url','category_group'] as $fld) {
        if (empty($data[$fld])) {
            send(['error' => "$fld is required"], 400);
        }
    }
    $stmt = $db->prepare('
      INSERT INTO items (name,category,level,price,image_url,category_group)
      VALUES (:name,:category,:level,:price,:image_url,:category_group)
    ');
    $stmt->execute([
      ':name'           => $data['name'],
      ':category'       => $data['category'],
      ':level'          => $data['level'],
      ':price'          => $data['price'],
      ':image_url'      => $data['image_url'],
      ':category_group' => $data['category_group'],
    ]);
    $data['id'] = $db->lastInsertId();
    send($data, 201);
}

// PUT /api/items/:id
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $data   = json_decode(file_get_contents('php://input'), true) ?: [];
    $fields = ['name','category','level','price','image_url','category_group','description','tutorial_url'];
    $updates = []; $params = [':id' => $m[1]];
    foreach ($fields as $fld) {
        if (isset($data[$fld])) {
            $updates[]       = "$fld = :$fld";
            $params[":$fld"] = $data[$fld];
        }
    }
    if (empty($updates)) send(['error' => 'No valid fields to update'], 400);
    $sql = 'UPDATE items SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    send(['updated' => $stmt->rowCount() > 0]);
}

// DELETE /api/items/:id
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $stmt = $db->prepare('DELETE FROM items WHERE id = :id');
    $stmt->execute([':id' => $m[1]]);
    if ($stmt->rowCount()) {
        send(['deleted' => true]);
    } else {
        send(['error' => 'Not found'], 404);
    }
}

// Fallback 404
send(['error' => 'Endpoint not found'], 404);
