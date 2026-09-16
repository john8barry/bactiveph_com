<?php
// Router for the isolated local PHP development server only.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$site = realpath('/var/www/html' . $path);
if ($path !== '/' && $site !== false && (is_file($site) || (is_dir($site) && is_file($site . '/index.php')))) {
    return false;
}
require '/var/www/html/index.php';
