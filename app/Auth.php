<?php
class Auth {
  static function requireLogin(){
    if (!self::userId() && !self::tryRestore()) {
      header('Location: /login'); exit;
    }
  }
  static function userId(): ?int { return $_SESSION['user_id'] ?? null; }

  static function login(int $userId){
    $_SESSION['user_id'] = $userId;
    self::issueRemember($userId); // long-lived
  }

  static function logout(){
    $_SESSION = [];
    setcookie('remember_me','',time()-3600,'/');
  }

  private static function issueRemember(int $userId){
    $pdo = DB::pdo();
    $selector = bin2hex(random_bytes(6));
    $token    = bin2hex(random_bytes(32));
    $hash     = hash('sha256',$token);
    $expires  = (new DateTime('+'.Config::REMEMBER_DAYS.' days'))->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?,?,?,?)")
        ->execute([$userId,$selector,$hash,$expires]);
    setcookie('remember_me', $selector.':'.$token, [
      'expires'=> time()+60*60*24*Config::REMEMBER_DAYS,
      'path'=>'/', 'secure'=>Config::COOKIE_SECURE, 'httponly'=>true, 'samesite'=>Config::COOKIE_SAMESITE
    ]);
  }

  private static function tryRestore(): bool {
    if (empty($_COOKIE['remember_me'])) return false;
    [$selector,$token] = explode(':', $_COOKIE['remember_me'], 2) + [null,null];
    if (!$selector || !$token) return false;
    $pdo = DB::pdo();
    $row = $pdo->prepare("SELECT user_id, token_hash, expires_at FROM user_remember_tokens WHERE selector=?");
    $row->execute([$selector]);
    $r = $row->fetch();
    if (!$r) return false;
    if (new DateTime($r['expires_at']) < new DateTime()) return false;
    if (!hash_equals($r['token_hash'], hash('sha256',$token))) return false;
    $_SESSION['user_id'] = (int)$r['user_id'];
    // rotate
    $pdo->prepare("DELETE FROM user_remember_tokens WHERE selector=?")->execute([$selector]);
    self::issueRemember((int)$r['user_id']);
    return true;
  }
}
