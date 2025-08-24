<?php
require_once __DIR__.'/../app/Auth.php';
require_once __DIR__.'/../app/Twitch.php';

class AuthController {
  static function login(){
    $state = bin2hex(random_bytes(8));
    $_SESSION['oauth_state'] = $state;
    header('Location: '.Twitch::loginUrl($state)); exit;
  }

  static function loginCallback(){
    if (empty($_GET['code']) || ($_GET['state']??'') !== ($_SESSION['oauth_state']??'')) { http_response_code(400); exit('Bad state'); }
    [$tok,$err] = Twitch::exchange($_GET['code'], Config::LOGIN_REDIRECT_URI);
    if ($err || empty($tok['access_token'])) { http_response_code(400); exit('Auth failed'); }

    [$u,] = Twitch::users($tok['access_token']);
    $user = $u['data'][0] ?? null;
    if (!$user) { http_response_code(400); exit('No user'); }

    $pdo = DB::pdo();
    $pdo->prepare("INSERT INTO users (twitch_user_id, display_name, email, avatar_url)
                   VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), email=VALUES(email), avatar_url=VALUES(avatar_url)")
        ->execute([$user['id'], $user['display_name'] ?? $user['login'], $user['email'] ?? null, $user['profile_image_url'] ?? null]);

    $id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM users WHERE twitch_user_id=".$pdo->quote($user['id']))->fetchColumn();
    Auth::login((int)$id);
    header('Location: /'); exit;
  }

  static function logout(){ Auth::logout(); header('Location: /'); }
}
