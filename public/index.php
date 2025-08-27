<?php
require_once dirname(__DIR__) . '/core/init.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = rtrim($path, '/');
if ($path === '') $path = '/';

if ($path === '/') {
    DashboardController::home();
    exit;
}

abort(404);
