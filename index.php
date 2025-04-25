<?php
// 1) DEV: full error reporting
// I set this up for debugging purposes. If something goes wrong, PHP will display the error messages.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2) CORS + JSON headers
// This is to handle cross-origin requests. Basically, it allows my API to be accessed from other origins (like the frontend).
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Handling preflight requests for CORS. It's like a handshake that makes sure the client is allowed to access the API.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// 3) Load database connection
// Here, I'm including the database connection file so I can use the database throughout my app.
require_once __DIR__ . '/config/database.php';
$db = getDatabaseConnection(); // Initializes the database connection.

require_once __DIR__ . '/utils/send.php';

// 4) Routing
// This is the part where I check the URL path and decide what to do with the incoming request.
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace('/api', '', $requestUri), '/');

// ─── ROOT ROUTE ─────────────────────────────────────────────
// If the user goes to the root URL or index.php, send a welcome message.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($path === '' || $path === 'index.php')) {
    send(['message' => 'Welcome to Rouge‑Backend API']); // Simple welcome message for the root route.
}

// ─── INCLUDE ROUTES ─────────────────────────────────────────
// If the path matches one of the predefined API routes, load the corresponding PHP file.
if (preg_match('#^items($|/)#', $path)) {
    require_once __DIR__ . '/routes/items.php'; // Includes items API route file.
    exit;
}

if (preg_match('#^basket($|/)#', $path)) {
    require_once __DIR__ . '/routes/basket.php'; // Includes basket API route file.
    exit;
}

if (preg_match('#^checkout$#', $path)) {
    require_once __DIR__ . '/routes/checkout.php'; // Includes checkout API route file.
    exit;
}

if (preg_match('#^orders($|/)#', $path)) {
  require_once __DIR__ . '/routes/orders.php'; // Includes orders API route file.
  exit;
}

if (preg_match('#^reviews($|/)#', $path)) {
  require_once __DIR__ . '/routes/reviews.php'; // Includes reviews API route file.
  exit;
}

if (preg_match('#^item_images($|/)#', $path)) {
  require_once __DIR__ . '/routes/item_images.php'; // Includes item images API route file.
  exit;
}

if (preg_match('#^tags($|/)#', $path)) {
  require_once __DIR__ . '/routes/tags.php'; // Includes tags API route file.
  exit;
}

if (preg_match('#^instructions($|/)#', $path)) {
  require_once __DIR__ . '/routes/instructions.php'; // Includes instructions API route file.
  exit;
}

if (preg_match('#^users($|/)#', $path)) {
  require_once __DIR__ . '/routes/users.php'; // Includes users API route file.
  exit;
}

if (preg_match('#^item_detail(\.php)?$#', $path)) {
  require_once __DIR__ . '/routes/item_detail.php';
  exit;
}


// ─── FALLBACK 404 ──────────────────────────────────────────
// If no route matches, send a 404 error with a message.
send(['error' => 'Endpoint not found'], 404);

