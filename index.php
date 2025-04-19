<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Open (or create) the SQLite database
$db = new PDO('sqlite:' . __DIR__ . '/database.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure our items table exists
$db->exec('
  CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
  )
');

// Ensure our products table exists
$db->exec('
  CREATE TABLE IF NOT EXISTS products (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    category    TEXT    NOT NULL,
    level       TEXT    NOT NULL,
    price       REAL    NOT NULL,
    image_url   TEXT    NOT NULL
  )
');

// Normalize the path: strip `/api` and any leading/trailing slashes
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api', '', $requestUri), '/');

// Helper: send JSON & exit
function send($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Welcome at GET /
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge‑Backend API']);
}

// ---- ITEMS CRUD ---- //

// GET /api/items
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === 'items') {
    $stmt = $db->query('SELECT * FROM items');
    send($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// GET /api/items/:id
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
    $stmt->execute([':id' => $m[1]]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item) send($item);
    send(['error' => 'Not found'], 404);
}

// POST /api/items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === 'items') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['name'])) {
        send(['error' => 'Name is required'], 400);
    }
    $stmt = $db->prepare('INSERT INTO items (name) VALUES (:name)');
    $stmt->execute([':name' => $data['name']]);
    send(['id' => $db->lastInsertId(), 'name' => $data['name']], 201);
}

// PUT /api/items/:id
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['name'])) {
        send(['error' => 'Name is required'], 400);
    }
    $stmt = $db->prepare('UPDATE items SET name = :name WHERE id = :id');
    $stmt->execute([':name' => $data['name'], ':id' => $m[1]]);
    if ($stmt->rowCount()) {
        send(['id' => (int)$m[1], 'name' => $data['name']]);
    }
    send(['error' => 'Not found or no change'], 404);
}

// DELETE /api/items/:id
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('#^items/(\d+)$#', $path, $m)) {
    $stmt = $db->prepare('DELETE FROM items WHERE id = :id');
    $stmt->execute([':id' => $m[1]]);
    if ($stmt->rowCount()) {
        send(['deleted' => true]);
    }
    send(['error' => 'Not found'], 404);
}

// ---- PRODUCTS CRUD ---- //

// GET /api/products
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === 'products') {
    $stmt = $db->query('SELECT * FROM products');
    send($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// POST /api/products
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === 'products') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    foreach (['name', 'category', 'level', 'price', 'image_url'] as $field) {
        if (empty($data[$field]) && $data[$field] !== 0) {
            send(['error' => "$field is required"], 400);
        }
    }
    $stmt = $db->prepare('
      INSERT INTO products (name, category, level, price, image_url)
      VALUES (:name, :category, :level, :price, :image_url)
    ');
    $stmt->execute([
      ':name'      => $data['name'],
      ':category'  => $data['category'],
      ':level'     => $data['level'],
      ':price'     => $data['price'],
      ':image_url' => $data['image_url'],
    ]);
    send(['id' => $db->lastInsertId()] + $data, 201);
}

// If nothing matched above, return 404
send(['error' => 'Endpoint not found'], 404);
