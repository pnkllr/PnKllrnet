<?php
require_once __DIR__ . '/../../../core/bootstrap.php';

// --- tiny HTTP helper ---
function http(string $url, array $opt=[]): array {
  $method=$opt['method']??'GET'; $headers=$opt['headers']??[]; $body=$opt['body']??null;
  $ch=curl_init($url); curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_TIMEOUT=>20
  ]); if ($body!==null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  $out=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return ['status'=>$code,'body'=>$out];
}

$bad = fn($why)=> (header('Location: /admin/?err='.$why) || exit);

if (!isset($_GET['state'], $_GET['code'])) $bad('missing_params');
if (!hash_equals($_SESSION['oauth_state'] ?? '', $_GET['state'])) $bad('bad_state');
unset($_SESSION['oauth_state']);

$redirect = rtrim(BASE_URL, '/') . '/twitch/auth/callback.php';

// 1) exchange code -> tokens  (Twitch returns access_token, refresh_token, expires_in, scope[])
$tok = http('https://id.twitch.tv/oauth2/token', [
  'method'=>'POST',
  'headers'=>['Content-Type: application/x-www-form-urlencoded'],
  'body'=>http_build_query([
    'client_id'=>TWITCH_CLIENT_ID,'client_secret'=>TWITCH_CLIENT_SECRET,
    'code'=>$_GET['code'],'grant_type'=>'authorization_code','redirect_uri'=>$redirect
  ])
]);
if ($tok['status']!==200) $bad('token');
$tdata = json_decode($tok['body'], true) ?: [];
$access  = $tdata['access_token']  ?? '';
$refresh = $tdata['refresh_token'] ?? '';
$expires = (int)($tdata['expires_in'] ?? 0);
$scope   = isset($tdata['scope']) && is_array($tdata['scope']) ? implode(' ', $tdata['scope']) : '';

// 2) identify the Twitch user
$u = http('https://api.twitch.tv/helix/users', [
  'headers'=>[
    'Authorization: Bearer '.$access,
    'Client-Id: '.TWITCH_CLIENT_ID,
  ]
]);
if ($u['status']!==200) $bad('user');
$ud = json_decode($u['body'], true); if (empty($ud['data'][0])) $bad('nouser');
$tw = $ud['data'][0]; // fields include: id, login, display_name, profile_image_url, email (if scoped)

// 3) ensure local user
$pdo = db();
$sel = $pdo->prepare("SELECT id FROM users WHERE twitch_id=? LIMIT 1");
$sel->execute([$tw['id']]);
$row = $sel->fetch();
if ($row) { $uid = (int)$row['id']; }
else {
  $ins = $pdo->prepare("INSERT INTO users (twitch_id, twitch_login, twitch_display, avatar_url, email)
                        VALUES (?,?,?,?,?)");
  $ins->execute([$tw['id'], $tw['login'], $tw['display_name'] ?? $tw['login'],
                 $tw['profile_image_url'] ?? '', $tw['email'] ?? null]);
  $uid = (int)$pdo->lastInsertId();
}

// 4) upsert token (store scopes + expiry)
$expAt = (new DateTimeImmutable('now'))->add(new DateInterval('PT'.max(0,$expires-60).'S'))->format('Y-m-d H:i:s');
$up = $pdo->prepare("
  INSERT INTO oauth_tokens (user_id, provider, channel, access_token, refresh_token, scope, expires_at)
  VALUES (?, 'twitch', ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    access_token=VALUES(access_token),
    refresh_token=VALUES(refresh_token),
    scope=VALUES(scope),
    expires_at=VALUES(expires_at)
");
$up->execute([$uid, $tw['login'], $access, $refresh, $scope, $expAt]);

// 5) sign into your site & go to dashboard
$_SESSION['uid']    = $uid;
$_SESSION['tlogin'] = $tw['login'];

header('Location: /dashboard/');
exit;
