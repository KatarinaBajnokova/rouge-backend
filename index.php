<?php
// Set CORS headers first
require_once __DIR__ . '/utils/cors.php';

// Full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Global error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => "$errstr in $errfile on line $errline",
    ]);
    exit;
});

// Global exception handler
set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit;
});

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Database connection and utilities
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/send.php';
$db = getDatabaseConnection();

// Routing logic
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api/', '', $requestUri), '/');

// Default route
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge-Backend API']);
    exit;
}

// Routes
$routes = [
    'items' => 'items.php',
    'basket' => 'basket.php',
    'checkout' => 'checkout.php',
    'orders' => 'orders.php',
    'reviews' => 'reviews.php',
    'item_images' => 'item_images.php',
    'tags' => 'tags.php',
    'instructions' => 'instructions.php',
    'auth/login' => 'auth.php',
    'users' => 'users.php',
    'item_detail' => 'item_detail.php',
    'category-groups' => 'category-groups.php',
    'categories' => 'categories.php',
    'subcategories' => 'subcategories.php',
    'filter-options' => 'filter-options.php',
    'item-filters' => 'item-filters.php',
    'search' => 'search.php',
    'favorites' => 'favorites.php',
];

foreach ($routes as $route => $file) {
    if (preg_match("#^$route($|/)#", $path)) {
        require_once __DIR__ . "/routes/$file";
        exit;
    }
}

// 404 fallback
send(['error' => 'Endpoint not found'], 404);