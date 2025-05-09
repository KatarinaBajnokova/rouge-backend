<?php
session_start();
$_SESSION = [];
session_destroy();

setcookie('session_token', '', time() - 3600, '/', '', true, true);

echo json_encode(['message' => 'Logged out']);
