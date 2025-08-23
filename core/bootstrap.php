<?php
// core/bootstrap.php
require_once __DIR__ . '/env.php';

// Load .env *before* config so getenv() has values
$BASE = realpath(__DIR__ . '/..');           // repo root
load_env($BASE . '/.env');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/router.php';

start_secure_session();

if (APP_DEBUG) {
  ini_set('display_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}
// --- AUTO-LOGIN VIA REMEMBER COOKIE ---
if (empty($_SESSION['uid']) && !empty($_COOKIE['remember_me'])) {
  $raw = $_COOKIE['remember_me'];

  if (ctype_xdigit($raw) && strlen($raw) === 64) {
    $hash = hash('sha256', $raw);

    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare("
      SELECT id, twitch_login, remember_token_expires
        FROM users
       WHERE remember_token_hash = ?
       LIMIT 1
    ");
    $stmt->execute([$hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && strtotime($user['remember_token_expires']) > time()) {
      // Rebuild session
      $_SESSION['uid']    = (int)$user['id'];
      $_SESSION['tlogin'] = $user['twitch_login'] ?? null;

      // Optional: rotate token for security (recommended)
      $newRaw  = bin2hex(random_bytes(32));
      $newHash = hash('sha256', $newRaw);
      $newExp  = date('Y-m-d H:i:s', time() + 30*24*60*60);

      $up = $pdo->prepare("
        UPDATE users
           SET remember_token_hash = ?, remember_token_expires = ?
         WHERE id = ?
      ");
      $up->execute([$newHash, $newExp, $user['id']]);

      setcookie('remember_me', $newRaw, [
        'expires'  => time() + 30*24*60*60,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
    } else {
      // Invalid/expired -> clear cookie
      setcookie('remember_me', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
    }
  } else {
    // Bad format -> clear cookie
    setcookie('remember_me', '', [
      'expires'  => time() - 3600,
      'path'     => '/',
      'secure'   => true,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }
}
