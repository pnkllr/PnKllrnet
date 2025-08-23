<?php
require_once __DIR__ . '/core/bootstrap.php'; // adjust path if needed

// Only start a session if one isn't already active
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$uid = $_SESSION['uid'] ?? null;

// Clear session array
$_SESSION = [];

// If PHP set a session cookie, delete it
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => $params['path'],
    'domain'   => $params['domain'],
    'secure'   => (bool)$params['secure'],
    'httponly' => (bool)$params['httponly'],
    'samesite' => $params['samesite'] ?? 'Lax',
  ]);
}

// Destroy session storage
session_destroy();

// Clear remember-me token in DB
if ($uid) {
  $pdo = db();
  $stmt = $pdo->prepare("
    UPDATE users
       SET remember_token_hash = NULL,
           remember_token_expires = NULL
     WHERE id = ?
  ");
  $stmt->execute([$uid]);
}

// Clear remember-me cookie
setcookie('remember_me', '', [
  'expires'  => time() - 3600,
  'path'     => '/',
  'secure'   => true,   // set to false only if you're not on HTTPS during local dev
  'httponly' => true,
  'samesite' => 'Lax',
]);

// IMPORTANT: ensure no output is sent before this point (no whitespace/BOM)
header('Location: /');
exit;
