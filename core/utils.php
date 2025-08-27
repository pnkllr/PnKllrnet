<?php
declare(strict_types=1);

function base_url(string $path = ''): string {
    $base = getenv('BASE_URL') ?: ($GLOBALS['_env']['BASE_URL'] ?? '');
    $base = rtrim($base, '/');
    if ($path && $path[0] !== '/') $path = '/' . $path;
    return $base . $path;
}

function now(): string {
    return (new DateTime('now'))->format('Y-m-d H:i:s');
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
