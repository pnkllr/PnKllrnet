<?php
function route(string $path, callable $handler){ static $r=[]; $r[$path]=$handler; }
function dispatch(){
  $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  $routes = (new ReflectionFunction('route'))->getStaticVariables()['r'] ?? [];
  if (isset($routes[$uri])) return $routes[$uri]();
  http_response_code(404); echo 'Not Found';
}
