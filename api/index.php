<?php

require __DIR__ . '/../config.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$public = __DIR__ . '/..';
$file = $public . $uri;

if ($uri !== '/' && is_file($file) && !is_dir($file)) {
    require $file;
    return;
}

require $public . '/index.php';
