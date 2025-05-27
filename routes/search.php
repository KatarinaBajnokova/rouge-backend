<?php
require_once __DIR__ . '/../utils/cors.php';
header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['query'] ?? '');
if ($query === '') {
    echo json_encode([]);
    exit;
}

$group = trim($_GET['group'] ?? '');

$dbFile = realpath(__DIR__ . '/../database.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
  SELECT
    id,
    name AS title,
    category,
    level AS difficulty,
    price,
    image_url AS thumbnail_url,
    description,
    tutorial_url
  FROM items
  WHERE (
      name            LIKE :q
   OR category        LIKE :q
   OR level           LIKE :q
   OR category_group  LIKE :q
   OR description     LIKE :q
  )
  " . ($group
      ? " AND category_group = :group "
      : ""
  ) . "
  ORDER BY name
  LIMIT 50
";

$stmt = $db->prepare($sql);
$stmt->bindValue(':q', "%{$query}%", PDO::PARAM_STR);
if ($group) {
  $stmt->bindValue(':group', $group, PDO::PARAM_STR);
}

$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
