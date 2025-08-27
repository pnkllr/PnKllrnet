<?php
declare(strict_types=1);

final class CSRF {
    public static function token(): string {
        $t = Session::get('_csrf');
        if (!$t) {
            $t = bin2hex(random_bytes(16));
            Session::set('_csrf', $t);
        }
        return $t;
    }
    public static function input(): string {
        $t = self::token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($t) . '">';
    }
    public static function check(): void {
        $t = $_POST['_csrf'] ?? $_GET['_csrf'] ?? '';
        if (!$t || !hash_equals((string)Session::get('_csrf'), (string)$t)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }
    }
}
