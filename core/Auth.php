<?php
declare(strict_types=1);

final class Auth {
    public static function user(): ?array {
        return Session::get('user');
    }

    public static function requireUser(): array {
        $u = self::user();
        if (!$u) { header('Location: ' . base_url('/auth/twitch.php')); exit; }
        return $u;
    }

    public static function requireAdmin(): array {
        $u = self::requireUser();
        if (($u['role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            exit('Admins only');
        }
        return $u;
    }
}
