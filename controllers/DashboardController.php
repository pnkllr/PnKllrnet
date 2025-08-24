<?php
require_once __DIR__.'/../app/Auth.php';
require_once __DIR__.'/../app/Twitch.php';

class DashboardController {
  static function index(){
    Auth::requireLogin();
    $pdo = DB::pdo();
    $tokens = $pdo->prepare("SELECT label, scopes, expires_at FROM twitch_tokens WHERE user_id=? ORDER BY label");
    $tokens->execute([Auth::userId()]);
    view('dashboard', ['tokens'=>$tokens->fetchAll()]);
  }

  static function createToken(){
    Auth::requireLogin();
    $label  = trim($_POST['label'] ?? 'default');
    $tools  = $_POST['tools'] ?? [];
    $map = [
      'clips'=>['clips:edit'],
      'followers'=>['moderator:read:followers'],
      'subs'=>['channel:read:subscriptions'],
      'vod'=>['channel:manage:videos'],
      'email'=>['user:read:email'],
    ];
    $scopes = [];
    foreach ($tools as $t) foreach ($map[$t] ?? [] as $s) $scopes[$s]=1;
    $scopes = array_keys($scopes);

    $state = bin2hex(random_bytes(8));
    $_SESSION['token_label'] = $label;
    $_SESSION['token_state'] = $state;
    $_SESSION['token_scopes']= $scopes;

    header('Location: '.Twitch::tokenUrl($scopes, $state)); exit;
  }

  static function tokenCallback(){
    Auth::requireLogin();
    if (($_GET['state']??'') !== ($_SESSION['token_state']??'')) { http_response_code(400); exit('Bad state'); }
    [$tok,$err] = Twitch::exchange($_GET['code'] ?? '', Config::TOKEN_REDIRECT_URI);
    if ($err || empty($tok['access_token'])) { http_response_code(400); exit('Token exchange failed'); }

    $expAt = (new DateTime('+'.($tok['expires_in']??0).' seconds'))->format('Y-m-d H:i:s');
    Twitch::saveOrUpgradeToken(Auth::userId(), $_SESSION['token_label'] ?? 'default', $tok['access_token'], $tok['refresh_token'] ?? null, $expAt);
    header('Location: /'); exit;
  }
}
