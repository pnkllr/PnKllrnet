<?php
require_once dirname(__DIR__, 3) . '/core/init.php';
Auth::requireAdmin();
CSRF::check();

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId > 0) {
    $GLOBALS['_db']->query("DELETE FROM oauth_tokens WHERE user_id=?", [$userId]);
    $GLOBALS['_db']->query("DELETE FROM user_desired_scopes WHERE user_id=?", [$userId]);
}
header('Location: ' . base_url('/admin'));
exit;
