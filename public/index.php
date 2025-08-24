<?php
require_once __DIR__.'/../app/bootstrap.php';
$routes = require __DIR__.'/../routes.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$key = $method.' '.$path;

if (!isset($routes[$key])) {
  if ($path === '/' && empty($_SESSION['user_id'])) { view('login'); exit; }
  http_response_code(404); echo 'Not found'; exit;
}

[$class,$func] = $routes[$key];
require_once __DIR__.'/../controllers/'.$class.'.php';
$class::$func();
