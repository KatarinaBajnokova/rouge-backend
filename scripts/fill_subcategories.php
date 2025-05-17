<?php
// scripts/fill_subcategories.php

require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

try {
    $db->beginTransaction();

    // 1) get all subcategories and their counts
    $subcats = $db->query("
        SELECT s.id,
               COALESCE(cnt.cnt,0) AS current_count
          FROM subcategories s
     LEFT JOIN (
            SELECT subcategory_id, COUNT(*) AS cnt
              FROM item_subcategories
             GROUP BY subcategory_id
         ) cnt
           ON cnt.subcategory_id = s.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // prepare statements
    $getCandidates = $db->prepare("
        SELECT id FROM items
         WHERE id NOT IN (
             SELECT item_id
               FROM item_subcategories
              WHERE subcategory_id = :sid
         )
         ORDER BY id
         LIMIT :need
    ");
    $insertStmt = $db->prepare("
        INSERT OR IGNORE INTO item_subcategories
            (item_id, subcategory_id)
        VALUES
            (:iid, :sid)
    ");

    foreach ($subcats as $sub) {
        $sid   = (int)$sub['id'];
        $have  = (int)$sub['current_count'];
        $need  = 4 - $have;
        if ($need <= 0) continue;

        // fetch candidate items
        $getCandidates->bindValue(':sid', $sid, PDO::PARAM_INT);
        $getCandidates->bindValue(':need', $need, PDO::PARAM_INT);
        $getCandidates->execute();
        $candidates = $getCandidates->fetchAll(PDO::FETCH_COLUMN);

        // insert each
        foreach ($candidates as $iid) {
            $insertStmt->execute([
                ':iid' => $iid,
                ':sid' => $sid,
            ]);
        }
    }

    $db->commit();
    echo "All subcategories now have at least 4 items.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
