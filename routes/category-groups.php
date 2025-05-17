<?php
// routes/category-groups.php

// Always return JSON
header('Content-Type: application/json');

// Load your DB connector and response helper
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/send.php';

// Get a PDO instance
$db = getDatabaseConnection();

// Fetch all category groups in order
$stmt = $db->query(
    'SELECT id, name, sort_order
       FROM category_groups
      ORDER BY sort_order'
);

// Send the results as JSON
send($stmt->fetchAll(PDO::FETCH_ASSOC));
