<?php
require_once dirname(__DIR__) . '/core/init.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/admin';
$path = rtrim($path, '/');

if ($path === '/admin') {
    AdminController::home();
    exit;
}

abort(404);
