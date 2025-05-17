<?php
require_once __DIR__   . '/../config/database.php';
require_once __DIR__   . '/../utils/send.php';
require_once __DIR__   . '/../controllers/filterOptions.php';

$db = getDatabaseConnection();

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path       = trim(str_replace('/api/', '', $requestUri), '/');

if ($method === 'GET' && $path === 'filter-options') {
    try {
        $opts = getFilterOptions($db);
        send($opts);
        exit;
    } catch (Exception $e) {
     
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        exit;
    }
}
