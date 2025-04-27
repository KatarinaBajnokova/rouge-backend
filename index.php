<?php
// 1) Full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2) CORS Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// 3) Database connection
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/send.php';
$db = getDatabaseConnection();

// 4) Routing
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/'); // ✅ FIXED HERE

// ─── Root route ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge‑Backend API']);
}

// ─── Include routes ───
if (preg_match('#^items($|/)#', $path)) {
    require_once __DIR__ . '/routes/items.php';
    exit;
}

if (preg_match('#^basket($|/)#', $path)) {
    require_once __DIR__ . '/routes/basket.php';
    exit;
}

if (preg_match('#^checkout$#', $path)) {
    require_once __DIR__ . '/routes/checkout.php';
    exit;
}

if (preg_match('#^orders($|/)#', $path)) {
    require_once __DIR__ . '/routes/orders.php';
    exit;
}

if (preg_match('#^reviews($|/)#', $path)) {
    require_once __DIR__ . '/routes/reviews.php';
    exit;
}

if (preg_match('#^item_images($|/)#', $path)) {
    require_once __DIR__ . '/routes/item_images.php';
    exit;
}

if (preg_match('#^tags($|/)#', $path)) {
    require_once __DIR__ . '/routes/tags.php';
    exit;
}

if (preg_match('#^instructions($|/)#', $path)) {
    require_once __DIR__ . '/routes/instructions.php';
    exit;
}

if (preg_match('#^users($|/)#', $path)) {
    require_once __DIR__ . '/routes/users.php';
    exit;
}

if (preg_match('#^item_detail(\.php)?$#', $path)) {
    require_once __DIR__ . '/routes/item_detail.php';
    exit;
}

// ─── Fallback ───
send(['error' => 'Endpoint not found'], 404);
?>
