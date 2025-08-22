<?php
define('BASE_PATH', realpath(__DIR__ . '/..'));
define('APP_START', microtime(true));
$env = fn($k,$d=null)=> getenv($k)!==false?getenv($k):$d;

define('APP_ENV',    $env('APP_ENV','prod'));
define('APP_DEBUG', (bool)$env('APP_DEBUG','0'));
date_default_timezone_set($env('TIMEZONE','Australia/Sydney'));

define('BASE_URL',     rtrim($env('BASE_URL',''), '/'));
define('SESSION_NAME', $env('SESSION_NAME','pnkllr'));

define('DB_DSN',  $env('DB_DSN',''));
define('DB_USER', $env('DB_USER',''));
define('DB_PASS', $env('DB_PASS',''));

define('TWITCH_CLIENT_ID',     $env('TWITCH_CLIENT_ID',''));
define('TWITCH_CLIENT_SECRET', $env('TWITCH_CLIENT_SECRET',''));
