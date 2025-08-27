<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/core/utils.php';
require_once BASE_PATH . '/core/Session.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/CSRF.php';
require_once BASE_PATH . '/core/Auth.php';
require_once BASE_PATH . '/core/RateLimiter.php';
require_once BASE_PATH . '/core/TwitchClient.php';

// Load .env
$envFile = BASE_PATH . '/.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', function($m) use (&$env){
            return $env[$m[1]] ?? getenv($m[1]) ?? '';
        }, $v);
        $v = trim($v, "\"' ");
        $env[$k] = $v;
        putenv("$k=$v");
    }
}

date_default_timezone_set($env['TIMEZONE'] ?? 'UTC');

// Security headers
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; script-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none';");

// Database
$db = Database::instance([
    'host' => $env['DB_HOST'] ?? '127.0.0.1',
    'port' => (int)($env['DB_PORT'] ?? 3306),
    'name' => $env['DB_NAME'] ?? '',
    'user' => $env['DB_USER'] ?? '',
    'pass' => $env['DB_PASS'] ?? '',
]);

// Session
Session::start($env['SESSION_NAME'] ?? 'pnkllr_session');

// globals
$GLOBALS['_env'] = $env;
$GLOBALS['_db'] = $db;

// Autoloader
spl_autoload_register(function($class){
    $paths = [
        BASE_PATH . '/app/Controllers/' . basename($class) . '.php',
        BASE_PATH . '/app/Models/' . basename($class) . '.php',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once $p; return; }
    }
});

function abort(int $code = 404, string $message = 'Not Found') {
    http_response_code($code);
    echo "<h1>$code</h1><p>" . htmlspecialchars($message) . "</p>";
    exit;
}
