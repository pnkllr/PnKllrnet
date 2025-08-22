<?php
require_once __DIR__ . '/../init.php';

if (empty($_SESSION['user'])) {
    header('Location: ' . abs_url('/core/twitch_auth.php'));
    exit;
}

// Optional token refresh. If it fails, re-auth.
if (!twitch_refresh_if_needed($db, $config)) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . abs_url('/core/twitch_auth.php'));
    exit;
}
