<?php
// public_html/app/auth/admin_guard.php
require_once __DIR__ . '/guard.php'; // ensures session + (optional) token refresh

// Only allow if the logged-in user's Twitch ID is in config
$admins = array_map('strval', $config['admin_user_ids'] ?? []);
$uid    = (string)($_SESSION['user']['id'] ?? '');

if ($uid === '' || empty($admins) || !in_array($uid, $admins, true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}