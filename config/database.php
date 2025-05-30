<?php
function getDatabaseConnection(): PDO {
    $db = new PDO('sqlite:' . __DIR__ . '/../database.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}
