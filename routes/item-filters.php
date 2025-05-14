<?php

require_once __DIR__   . '/../config/database.php';
require_once __DIR__   . '/../utils/send.php';
require_once __DIR__   . '/../controllers/itemFilters.php';

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api/', '', $requestUri), '/');

$db = getDatabaseConnection();


if ($method === 'GET' && $path === 'item-filters') {
    try {
        $items = getFilteredItems($db, $_GET);
        send($items);
    } catch (Exception $e) {
        error_log('[GET /api/item-filters ERROR] ' . $e->getMessage());
        send(['error' => 'Internal server error'], 500);
    }
}
