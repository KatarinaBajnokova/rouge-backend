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

// 4) Ensure all tables exist
$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS items (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  name           TEXT    NOT NULL,
  category       TEXT    NOT NULL,
  level          TEXT    NOT NULL,
  price          REAL    NOT NULL,
  image_url      TEXT    NOT NULL,
  category_group TEXT    NOT NULL,
  description    TEXT    DEFAULT '',
  tutorial_url   TEXT    DEFAULT ''
);

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

CREATE TABLE IF NOT EXISTS basket (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id   INTEGER NOT NULL,
  quantity  INTEGER NOT NULL DEFAULT 1,
  added_at  TEXT    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
);
SQL
);

// Helper to send JSON + status
function send($data, $status = 200) {
    if (!headers_sent()) header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

// Routing
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api', '', $requestUri), '/');

// ─── ROOT ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge‑Backend API']);
}

// ─── ITEMS ROUTES ────────────────────────────────────────

// GET /api/items or /api/items?category_group=…
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === 'items') {
    try {
        if (isset($_GET['category_group'])) {
            $stmt = $db->prepare('SELECT * FROM items WHERE category_group = :group');
            $stmt->execute([':group' => $_GET['category_group']]);
        } else {
            $stmt = $db->query('SELECT * FROM items');
        }
        send($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log('[API ERROR] ' . $e->getMessage());
        send(['error' => 'Internal server error'], 500);
    }
}

// GET /api/items/:id (with images, tags, boxProducts, instructions, reviews)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    try {
        // main item
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

        // images
        $stmt = $db->prepare('SELECT url FROM item_images WHERE item_id = :id ORDER BY id');
        $stmt->execute([':id' => $id]);
        $item['images'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'url');

        // tags
        $stmt = $db->prepare('
          SELECT t.name
            FROM tags t
            JOIN item_tags it ON it.tag_id = t.id
           WHERE it.item_id = :id
        ');
        $stmt->execute([':id' => $id]);
        $item['tags'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');

        // boxProducts
        $stmt = $db->prepare('SELECT id,title,image_url,description FROM box_products WHERE item_id = :id');
        $stmt->execute([':id' => $id]);
        $item['boxProducts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // instructions
        $stmt = $db->prepare('
          SELECT step_number,title,text
            FROM instructions
           WHERE item_id = :id
           ORDER BY step_number
        ');
        $stmt->execute([':id' => $id]);
        $item['instructions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // reviews
        $stmt = $db->prepare('SELECT id,author,rating,comment FROM reviews WHERE item_id = :id');
        $stmt->execute([':id' => $id]);
        $item['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // camelCase tutorialUrl
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
    $data['id'] = (int)$db->lastInsertId();
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
    send(['updated' => (bool)$stmt->rowCount()]);
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

// ─── BASKET ROUTES ────────────────────────────────────────

// GET /api/basket
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === 'basket') {
    $stmt = $db->query(
      'SELECT b.id AS basketId, b.quantity, i.id AS itemId, i.name, i.image_url, i.category, i.level, i.price
         FROM basket b
         JOIN items i ON i.id = b.item_id'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = array_map(function($r) {
        return [
          'id'        => (int)$r['basketId'],
          'item_id'   => (int)$r['itemId'],
          'name'      => $r['name'],
          'image_url' => $r['image_url'],
          'category'  => $r['category'],
          'level'     => $r['level'],
          'price'     => (float)$r['price'],
          'quantity'  => (int)$r['quantity'],
        ];
    }, $rows);
    $total = array_reduce($items, fn($sum,$it) => $sum + $it['price'] * $it['quantity'], 0.0);
    send(['items' => $items, 'total_price' => round($total, 2)]);
}

// POST /api/basket (add or increment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === 'basket') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['item_id'])) {
        send(['error' => 'item_id is required'], 400);
    }
    $itemId = (int)$data['item_id'];
    $qty    = isset($data['quantity']) && is_int($data['quantity']) ? $data['quantity'] : 1;

    // increment if exists
    $sel = $db->prepare('SELECT id, quantity FROM basket WHERE item_id = :iid');
    $sel->execute([':iid' => $itemId]);
    if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
        $newQty = $row['quantity'] + $qty;
        $upd = $db->prepare('UPDATE basket SET quantity = :q WHERE id = :bid');
        $upd->execute([':q' => $newQty, ':bid' => $row['id']]);
    } else {
        $ins = $db->prepare('INSERT INTO basket (item_id, quantity) VALUES (:iid, :q)');
        $ins->execute([':iid' => $itemId, ':q' => $qty]);
    }

    // return the updated basket snapshot
    // (duplicate GET logic)
    $stmt = $db->query(
      'SELECT b.id AS basketId, b.quantity, i.id AS itemId, i.name, i.image_url, i.category, i.level, i.price
         FROM basket b
         JOIN items i ON i.id = b.item_id'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = array_map(function($r) {
        return [
          'id'        => (int)$r['basketId'],
          'item_id'   => (int)$r['itemId'],
          'name'      => $r['name'],
          'image_url' => $r['image_url'],
          'category'  => $r['category'],
          'level'     => $r['level'],
          'price'     => (float)$r['price'],
          'quantity'  => (int)$r['quantity'],
        ];
    }, $rows);
    $total = array_reduce($items, fn($sum,$it) => $sum + $it['price'] * $it['quantity'], 0.0);
    send(['items' => $items, 'total_price' => round($total, 2)], 201);
}

// PUT /api/basket/:id (set exact quantity)
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('#^basket/(\d+)$#', $path, $m)) {
    $bid = (int)$m[1];
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!isset($data['quantity']) || !is_int($data['quantity'])) {
        send(['error' => 'quantity must be integer'], 400);
    }
    $stmt = $db->prepare('UPDATE basket SET quantity = :q WHERE id = :bid');
    $stmt->execute([':q' => $data['quantity'], ':bid' => $bid]);
    send(['success' => (bool)$stmt->rowCount()]);
}

// DELETE /api/basket/:id
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('#^basket/(\d+)$#', $path, $m)) {
    $bid = (int)$m[1];
    $stmt = $db->prepare('DELETE FROM basket WHERE id = :bid');
    $stmt->execute([':bid' => $bid]);
    send(['success' => (bool)$stmt->rowCount()]);
}

// POST /api/checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === 'checkout') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    $stmt = $db->prepare('
        INSERT INTO orders (
          full_name, email, phone, address_1, address_2,
          company_name, vat_number, payment_method, is_gift,
          friend_name, friend_email
        )
        VALUES (
          :full_name, :email, :phone, :address_1, :address_2,
          :company_name, :vat_number, :payment_method, :is_gift,
          :friend_name, :friend_email
        )
    ');

    $stmt->execute([
        ':full_name'       => $data['full_name'] ?? null,
        ':email'           => $data['email'] ?? null,
        ':phone'           => $data['phone'] ?? null,
        ':address_1'       => $data['address_1'] ?? null,
        ':address_2'       => $data['address_2'] ?? null,
        ':company_name'    => $data['company_name'] ?? null,
        ':vat_number'      => $data['vat_number'] ?? null,
        ':payment_method'  => $data['payment_method'] ?? null,
        ':is_gift'         => !empty($data['is_gift']) ? 1 : 0,
        ':friend_name'     => $data['friend_name'] ?? null,
        ':friend_email'    => $data['friend_email'] ?? null,
    ]);

    send(['success' => true, 'order_id' => (int)$db->lastInsertId()], 201);
}

// ─── FALLBACK 404 ────────────────────────────────────────
send(['error' => 'Endpoint not found'], 404);
