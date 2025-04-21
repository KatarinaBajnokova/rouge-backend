<?php
$db->exec('
  CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    level TEXT NOT NULL,
    price REAL NOT NULL,
    image_url TEXT NOT NULL,
    category_group TEXT NOT NULL,
    description TEXT DEFAULT \'\',
    tutorial_url TEXT DEFAULT \'\'
  );

  CREATE TABLE IF NOT EXISTS basket (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    quantity INTEGER DEFAULT 1,
    added_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
  );

  CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    author TEXT NOT NULL,
    rating INTEGER NOT NULL,
    comment TEXT,
    FOREIGN KEY(item_id) REFERENCES items(id) ON DELETE CASCADE
  );
');
