<?php
header('Content-Type: application/json');



$db = new SQLite3(__DIR__ . '/../database.db');


$data = json_decode(file_get_contents('php://input'), true);


if (
  !isset($data['firebase_uid']) || 
  !isset($data['full_name']) || 
  !isset($data['nickname']) || 
  !isset($data['email'])
) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing fields']);
  exit;
}


$stmt = $db->prepare('INSERT INTO users (firebase_uid, full_name, nickname, email) VALUES (?, ?, ?, ?)');
$stmt->bindValue(1, $data['firebase_uid'], SQLITE3_TEXT);
$stmt->bindValue(2, $data['full_name'], SQLITE3_TEXT);
$stmt->bindValue(3, $data['nickname'], SQLITE3_TEXT);
$stmt->bindValue(4, $data['email'], SQLITE3_TEXT);

$result = $stmt->execute();

if ($result) {
  echo json_encode(['success' => true]);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to insert user']);
}
