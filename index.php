<?php
// full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// database connection
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/send.php';
$db = getDatabaseConnection();

// routing
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

// root route
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge-Backend API']);
    exit;
}

// include routes
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('#^auth/login$#', $path)) {
    require_once __DIR__ . '/routes/auth.php';
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

if (preg_match('#^category-groups($|/)#', $path)) {
    require_once __DIR__ . '/routes/category-groups.php';
    exit;
}

if (preg_match('#^categories($|/)#', $path)) {
    require_once __DIR__ . '/routes/categories.php';
    exit;
}

if (preg_match('#^subcategories($|/)#', $path)) {
    require_once __DIR__ . '/routes/subcategories.php';
    exit;
}

// ← New filter-options route
if (preg_match('#^filter-options($|/)#', $path)) {
    require_once __DIR__ . '/routes/filter-options.php';
    exit;
}

if (preg_match('#^item-filters($|/)#', $path)) {
    require_once __DIR__ . '/routes/item-filters.php';
    exit;
}

if (preg_match('#^search(\.php)?$#', $path)) {
    require_once __DIR__ . '/routes/search.php';
    exit;
}

// fallback
send(['error' => 'Endpoint not found'], 404);
