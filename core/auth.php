<?php
function start_secure_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),
      'httponly'=>true,'samesite'=>'Lax']);
    session_start();
  }
}
function current_user_id(): ?int { return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null; }
function require_login(): void {
  if (!current_user_id()) { header('Location: /twitch/auth/login.php'); exit; }
}
function logout(): void { $_SESSION = []; if (ini_get('session.use_cookies')) {
  $p=session_get_cookie_params(); setcookie(session_name(),'',(time()-42000),$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
  session_destroy(); header('Location: /'); exit;
}
