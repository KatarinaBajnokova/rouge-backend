<?php

require_once __DIR__   . '/../config/database.php';
require_once __DIR__   . '/../utils/send.php';
require_once __DIR__   . '/../controllers/itemFilters.php';

$db = getDatabaseConnection();

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api/', '', $requestUri), '/');

if ($method === 'GET' && $path === 'item-filters') {
    try {
   
        $categoryId    = $_GET['categoryId']    ?? null;
        $subcategoryId = $_GET['subcategoryId'] ?? null;

   
        $occasions = [];
        if (!empty($_GET['occasions'])) {
            $occasions = explode(',', $_GET['occasions']);
        }

      
        $detailed = [];
        if (!empty($_GET['detailed'])) {
            $detailed = explode(',', $_GET['detailed']);
        }

     
        $difficulties = [];
        if (!empty($_GET['difficulties'])) {
            $difficulties = explode(',', $_GET['difficulties']);
        }

   
        $minPrice = isset($_GET['minPrice']) ? floatval($_GET['minPrice']) : 0;
        $maxPrice = isset($_GET['maxPrice']) ? floatval($_GET['maxPrice']) : PHP_INT_MAX;

       
        $params = [
            'categoryId'    => $categoryId,
            'subcategoryId' => $subcategoryId,
            'occasions'     => $occasions,
            'detailed'      => $detailed,
            'difficulties'  => $difficulties,
            'minPrice'      => $minPrice,
            'maxPrice'      => $maxPrice,
        ];

      
        $items = getFilteredItems($db, $params);

        send($items);
    } catch (Exception $e) {
        error_log('[GET /api/item-filters ERROR] ' . $e->getMessage());
        send(['error' => 'Internal server error'], 500);
    }
}
