<?php
// /app/twitch/auth/login.php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../twitch/helpers.php'; // ← add this helpers file if you haven't already

start_secure_session(); // idempotent from bootstrap
// (1) CSRF state
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// (2) Decide where to go after auth
$next = '/dashboard';
if (!empty($_GET['next']) && is_string($_GET['next'])) {
  // very light sanity check; you can harden this to only allow internal paths
  $next = $_GET['next'][0] === '/' ? $_GET['next'] : '/dashboard';
}
$_SESSION['oauth_next'] = $next;

// (3) Build scope set
$scopes = ['user:read:email']; // default minimal for first-time login

if (!empty($_SESSION['user_id'])) {
  // If already logged in, try to load the user's desired scopes saved from dashboard
  $st = db()->prepare("SELECT scope_str FROM user_desired_scopes WHERE user_id=?");
  $st->execute([ (int) $_SESSION['user_id'] ]);
  $raw = $st->fetchColumn();

  if ($raw) {
    $desired = parse_scope_str($raw);

    // Ensure minimal identity scope always present
    if (!in_array('user:read:email', $desired, true)) {
      $desired[] = 'user:read:email';
    }
    $scopes = scopes_normalize($desired);
  }
}

// (4) Twitch authorize URL
$redirect = rtrim(BASE_URL, '/') . '/twitch/auth/callback.php';
$params = [
  'client_id'     => TWITCH_CLIENT_ID,
  'redirect_uri'  => $redirect,
  'response_type' => 'code',
  'scope'         => implode(' ', $scopes),
  'state'         => $state,
  // Force the consent screen so users can add/remove scopes cleanly
  'force_verify'  => 'true',
];

// (5) Go!
header('Location: https://id.twitch.tv/oauth2/authorize?' . http_build_query($params));
exit;
