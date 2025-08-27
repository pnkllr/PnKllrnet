<?php
declare(strict_types=1);

final class Session {
    public static function start(string $name = 'sid'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($name);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function regenerate(): void {
        session_regenerate_id(true);
    }

    public static function set(string $key, $value): void { $_SESSION[$key] = $value; }
    public static function get(string $key, $default=null) { return $_SESSION[$key] ?? $default; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
    public static function destroy(): void { $_SESSION = []; session_destroy(); }
}
