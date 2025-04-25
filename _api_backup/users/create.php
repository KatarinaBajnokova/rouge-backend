<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json");

require_once '../../db_connection.php'; // update path if needed

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['firebase_uid'], $data['full_name'], $data['nickname'], $data['email'])) {
  echo json_encode(['error' => 'Missing fields']);
  exit;
}

$uid = $data['firebase_uid'];
$name = $data['full_name'];
$nickname = $data['nickname'];
$email = $data['email'];

$stmt = $conn->prepare("INSERT INTO users (firebase_uid, full_name, nickname, email) VALUES (?, ?, ?, ?)");

if ($stmt->execute([$uid, $name, $nickname, $email])) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['error' => 'Insert failed']);
}
