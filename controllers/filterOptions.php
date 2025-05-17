<?php

function getFilterOptions(PDO $db): array
{
  
    $occasions = $db
      ->query("SELECT name FROM tags ORDER BY name")
      ->fetchAll(PDO::FETCH_COLUMN);

   
    $detailedOccasions = $db
      ->query("SELECT name FROM subcategories ORDER BY name")
      ->fetchAll(PDO::FETCH_COLUMN);

  
    $difficulties = $db
      ->query("SELECT DISTINCT level FROM items ORDER BY level")
      ->fetchAll(PDO::FETCH_COLUMN);

    return [
      'occasions'         => $occasions,
      'detailedOccasions' => $detailedOccasions,
      'difficulties'      => $difficulties,
    ];
}
