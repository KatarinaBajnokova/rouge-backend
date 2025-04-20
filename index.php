<?php
// rouge-backend/index.php

// ——————————————————————————————————————————
// 1) DEV: full error reporting (remove or dial down in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ——————————————————————————————————————————
// 2) CORS + JSON headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Handle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// ——————————————————————————————————————————
// 3) Open or create the SQLite DB
try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not open database: ' . $e->getMessage()]);
    exit;
}

// Ensure the items table exists
$db->exec('
  CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    level TEXT NOT NULL,
    price REAL NOT NULL,
    image_url TEXT NOT NULL,
    category_group TEXT NOT NULL
  )
');

// ——————————————————————————————————————————
// 4) Routing setup
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api', '', $requestUri), '/');

// Helper to send JSON + HTTP status
function send($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// ——————————————————————————————————————————
// 5) Root endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge‑Backend API']);
}

// ——————————————————————————————————————————
// 6) ITEMS CRUD

// GET /api/items?category_group=…
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
    } catch (PDOException $e) {
        error_log('[DB ERROR] ' . $e->getMessage());
        send(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        error_log('[API ERROR] ' . $e->getMessage());
        send(['error' => 'Internal server error: ' . $e->getMessage()], 500);
    }
}

// GET /api/items/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
    $stmt->execute([':id' => $m[1]]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item) {
        send($item);
    } else {
        send(['error' => 'Not found'], 404);
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
      INSERT INTO items (name, category, level, price, image_url, category_group)
      VALUES (:name, :category, :level, :price, :image_url, :category_group)
    ');
    $stmt->execute([
      ':name'           => $data['name'],
      ':category'       => $data['category'],
      ':level'          => $data['level'],
      ':price'          => $data['price'],
      ':image_url'      => $data['image_url'],
      ':category_group' => $data['category_group'],
    ]);
    $newId = $db->lastInsertId();
    send(['id' => $newId] + $data, 201);
}

// PUT /api/items/:id
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $data   = json_decode(file_get_contents('php://input'), true) ?: [];
    $fields = ['name','category','level','price','image_url','category_group'];
    $updates = []; $params = [':id' => $m[1]];
    foreach ($fields as $fld) {
        if (isset($data[$fld])) {
            $updates[]        = "$fld = :$fld";
            $params[":$fld"]  = $data[$fld];
        }
    }
    if (empty($updates)) send(['error' => 'No valid fields to update'], 400);
    $sql  = 'UPDATE items SET ' . implode(', ', $updates) . ' WHERE id = :id';
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

// ——————————————————————————————————————————
// 7) Fallback 404
send(['error' => 'Endpoint not found'], 404);
