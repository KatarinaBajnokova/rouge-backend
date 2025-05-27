<?php
$file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (php_sapi_name()==='cli-server' && is_file($file)) {
  return false;
}

require __DIR__ . '/index.php';
