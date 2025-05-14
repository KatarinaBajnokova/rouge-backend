<?php

function getFilteredItems(PDO $db, array $query): array
{
    $categoryId = isset($query['categoryId'])
                ? (int)$query['categoryId']
                : null;
    $subId      = isset($query['subcategoryId'])
                ? (int)$query['subcategoryId']
                : null;


    if ($subId) {
        $sql = '
          SELECT DISTINCT i.*
            FROM items i
       LEFT JOIN item_subcategories isc
              ON isc.item_id = i.id
           WHERE isc.subcategory_id = :subId
        ORDER BY i.id
           LIMIT 3
        ';
        $params = [':subId' => $subId];
    }
 
    elseif ($categoryId) {
        $sql = '
          SELECT DISTINCT i.*
            FROM items i
       LEFT JOIN item_categories ic
              ON ic.item_id = i.id
           WHERE ic.category_id = :catId
        ORDER BY i.id
           LIMIT 3
        ';
        $params = [':catId' => $categoryId];
    }

    else {
        return [];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
