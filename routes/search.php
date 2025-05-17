<?php
header('Content-Type: application/json; charset=utf-8');

// 1) Grab & trim the query param
$query = trim($_GET['query'] ?? '');
if ($query === '') {
    // no text → return empty so React can show categories instead
    echo json_encode([]);
    exit;
}

// 2) (Optional) Grab a category_group filter if you want to scope
$group = trim($_GET['group'] ?? '');

// 3) Open your SQLite DB
$dbFile = realpath(__DIR__ . '/../database.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 4) Build the SQL with multiple LIKEs
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

// 5) Prepare & bind
$stmt = $db->prepare($sql);
$stmt->bindValue(':q', "%{$query}%", PDO::PARAM_STR);
if ($group) {
  $stmt->bindValue(':group', $group, PDO::PARAM_STR);
}

// 6) Execute & return JSON
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
