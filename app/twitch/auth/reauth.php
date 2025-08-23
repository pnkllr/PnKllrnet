<?php
// /app/twitch/auth/reauth.php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../helpers.php'; // scope helpers
require_login();

$uid  = current_user_id();

// 1) Read the scopes the tool says are missing (from the dashboard button)
$add = parse_scope_str($_GET['add'] ?? '');

// 2) Where to go after consent
$next = '/dashboard/';
if (!empty($_GET['next']) && is_string($_GET['next']) && $_GET['next'][0] === '/') {
  // keep it on-site (defensive); keeps query if present
  $next = $_GET['next'];
}
$_SESSION['oauth_next'] = $next;

// 3) Union the current token scopes with the additions
$tStmt = db()->prepare("SELECT scope FROM oauth_tokens WHERE provider='twitch' AND user_id=? LIMIT 1");
$tStmt->execute([$uid]);
$currentScopes = parse_scope_str($tStmt->fetchColumn() ?: '');

$union = scopes_normalize(array_merge($currentScopes, $add));
// always keep minimal identity scope
if (!in_array('user:read:email', $union, true)) {
  $union[] = 'user:read:email';
}

// 4) CSRF state + send to Twitch
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

header('Location: ' . twitch_authorize_url($union, $state));
exit;
