<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../app/twitch/helpers.php';
require_login();

$uid = current_user_id();

$desired = scopes_normalize($_POST['scopes'] ?? []);
if (!in_array('user:read:email', $desired, true)) $desired[] = 'user:read:email';

$desiredStr  = scopes_to_string($desired);
$desiredHash = scopes_hash($desired);
upsert_user_desired_scopes($uid, $desiredStr, $desiredHash);

// Load current token scopes + hash
$tStmt = db()->prepare("SELECT scope FROM oauth_tokens WHERE provider='twitch' AND user_id=? LIMIT 1");
$tStmt->execute([$uid]);
$current = parse_scope_str($tStmt->fetchColumn() ?: '');
$currentHash = scopes_hash($current);

// If different, reauth to apply the new set
if ($currentHash !== $desiredHash) {
  $state = bin2hex(random_bytes(16));
  $_SESSION['oauth_state'] = $state;
  $_SESSION['oauth_next']  = '/dashboard/';
  header('Location: ' . twitch_authorize_url($desired, $state));
  exit;
}

header('Location: /dashboard/?scopes=unchanged');
exit;
