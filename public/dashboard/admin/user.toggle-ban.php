<?php
require_once dirname(__DIR__, 3) . '/core/init.php';
Auth::requireAdmin();
CSRF::check();

$userId = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($action === 'ban') {
  $GLOBALS['_db']->query("UPDATE users SET is_banned = 1, banned_at = NOW() WHERE id=?", [$userId]);
  $GLOBALS['_db']->query("DELETE FROM oauth_tokens WHERE user_id=?", [$userId]);
  $GLOBALS['_db']->query("DELETE FROM user_desired_scopes WHERE user_id=?", [$userId]);
} else {
  $GLOBALS['_db']->query("UPDATE users SET is_banned = 0, banned_at = NULL WHERE id=?", [$userId]);
}
header('Location: ' . base_url('/admin'));
exit;
