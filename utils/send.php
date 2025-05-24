<?php
function send($data, $status = 200) {
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: http://localhost:3000');
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
        header('Content-Type: application/json');
        http_response_code($status);
    }

    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
