<?php
function start_secure_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    // safer HTTPS detection (works behind proxies too)
    $isHttps = (
      (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
      || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    );

    if (defined('SESSION_NAME') && SESSION_NAME) {
      session_name(SESSION_NAME);
    }

    session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'domain'   => '',      // leave default unless you need cross‑subdomain
      'secure'   => $isHttps,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_start();
  }
}

function current_user_id(): ?int {
  return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

function current_user_row(): ?array {
  $uid = current_user_id();
  if (!$uid) return null;
  $stmt = db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$uid]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  return $u ?: null;
}

// Resolve the Twitch channel/user ID for the logged-in user
function current_user_channel_id(): ?string {
  $u = current_user_row();
  if (!$u) return null;

  // adjust this to match your schema if you know the exact column
  foreach (['twitch_id','twitch_user_id','twitch_channel_id'] as $k) {
    if (!empty($u[$k])) return (string)$u[$k];
  }

  // fallback: check latest oauth token if you store provider_user_id there
  $stmt = db()->prepare("
    SELECT provider_user_id
      FROM oauth_tokens
     WHERE user_id=? AND provider='twitch'
  ORDER BY id DESC
     LIMIT 1
  ");
  $stmt->execute([$u['id']]);
  $pid = $stmt->fetchColumn();
  if (!empty($pid)) return (string)$pid;

  return null;
}

function require_login(): void {
  if (!current_user_id()) {
    header('Location: /twitch/auth/login.php');
    exit;
  }
}

// Call this at the top of /admin/* pages & actions
function require_admin(): void {
  require_login();
  $chan = current_user_channel_id();
  $allowed = $GLOBALS['ADMIN_CHANNEL_IDS'] ?? [];

  if (!$chan || !in_array((string)$chan, $allowed, true)) {
    // Redirect non-admins to homepage
    header('Location: /');
    exit;
  }
}



function logout(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
  header('Location: /');
  exit;
}
