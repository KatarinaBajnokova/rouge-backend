<?php

function getFilteredItems(PDO $db, array $p): array
{
  
    $categoryId    = isset($p['categoryId'])    ? (int)$p['categoryId']    : null;
    $subId         = isset($p['subcategoryId']) ? (int)$p['subcategoryId'] : null;
    $occasions     = $p['occasions']    ?? [];
    $detailed      = $p['detailed']     ?? [];
    $difficulties  = $p['difficulties'] ?? [];
    $minPrice      = isset($p['minPrice']) ? (float)$p['minPrice'] : 0;
    $maxPrice      = isset($p['maxPrice']) ? (float)$p['maxPrice'] : PHP_INT_MAX;

    
    $sqlParts = [
        'select' => 'SELECT DISTINCT i.*',
        'from'   => 'FROM items i',
        'joins'  => [],
        'where'  => ['1=1'],
        'params' => []
    ];

 
    if ($subId) {
        $sqlParts['joins'][]  = 'INNER JOIN item_subcategories isc ON isc.item_id = i.id';
        $sqlParts['where'][]  = 'isc.subcategory_id = :subId';
        $sqlParts['params'][':subId'] = $subId;
    } elseif ($categoryId) {
        $sqlParts['joins'][]  = 'INNER JOIN item_categories ic ON ic.item_id = i.id';
        $sqlParts['where'][]  = 'ic.category_id = :catId';
        $sqlParts['params'][':catId'] = $categoryId;
    }

 
    if (count($occasions)) {
        $sqlParts['joins'][] = 'INNER JOIN item_tags it ON it.item_id = i.id';
        $sqlParts['joins'][] = 'INNER JOIN tags t ON t.id = it.tag_id';
        $placeholders = [];
        foreach ($occasions as $i => $occ) {
            $ph = ":occ{$i}";
            $placeholders[] = $ph;
            $sqlParts['params'][$ph] = $occ;
        }
        $sqlParts['where'][] = 't.name IN (' . implode(', ', $placeholders) . ')';
    }

 
    if (count($detailed)) {
        $sqlParts['joins'][] = 'INNER JOIN item_subcategories isc2 ON isc2.item_id = i.id';
        $sqlParts['joins'][] = 'INNER JOIN subcategories sc ON sc.id = isc2.subcategory_id';
        $placeholders = [];
        foreach ($detailed as $i => $det) {
            $ph = ":det{$i}";
            $placeholders[] = $ph;
            $sqlParts['params'][$ph] = $det;
        }
        $sqlParts['where'][] = 'sc.name IN (' . implode(', ', $placeholders) . ')';
    }

   
    if (count($difficulties)) {
        $placeholders = [];
        foreach ($difficulties as $i => $diff) {
            $ph = ":diff{$i}";
            $placeholders[] = $ph;
            $sqlParts['params'][$ph] = $diff;
        }
        $sqlParts['where'][] = 'i.level IN (' . implode(', ', $placeholders) . ')';
    }

 
    $sqlParts['where'][] = 'i.price BETWEEN :minPrice AND :maxPrice';
    $sqlParts['params'][':minPrice'] = $minPrice;
    $sqlParts['params'][':maxPrice'] = $maxPrice;

  
    $sql = implode(' ', [
        $sqlParts['select'],
        $sqlParts['from'],
        implode(' ', $sqlParts['joins']),
        'WHERE ' . implode(' AND ', $sqlParts['where']),
        'ORDER BY i.id',
    ]);

    $stmt = $db->prepare($sql);
    $stmt->execute($sqlParts['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
