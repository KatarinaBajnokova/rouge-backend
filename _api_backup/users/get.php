<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET");

// Connect to database
$db = new PDO("sqlite:" . __DIR__ . "/../../database.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get UID from query
$uid = $_GET['uid'] ?? null;
if (!$uid) {
    http_response_code(400);
    echo json_encode(["error" => "Missing UID"]);
    exit;
}

// Prepare query
$stmt = $db->prepare("SELECT * FROM users WHERE firebase_uid = :uid");
$stmt->bindParam(':uid', $uid, PDO::PARAM_STR);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode($user);
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}
