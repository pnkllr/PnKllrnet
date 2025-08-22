<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_login();

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
if (!$id || !in_array($action, ['refresh','delete'], true)) { http_response_code(400); echo 'Bad request'; exit; }

$sel = db()->prepare("SELECT * FROM oauth_tokens WHERE id=?"); $sel->execute([$id]); $tok = $sel->fetch();
if (!$tok) { http_response_code(404); echo 'Not found'; exit; }

if ($action==='delete') { $del=db()->prepare("DELETE FROM oauth_tokens WHERE id=?"); $del->execute([$id]); header('Location: /admin/?ok=deleted'); exit; }

if ($action==='refresh') {
  if ($tok['provider']!=='twitch' || empty($tok['refresh_token'])) { header('Location:/admin/?err=norefresh'); exit; }
  $resp = curl_init('https://id.twitch.tv/oauth2/token');
  $body = http_build_query(['grant_type'=>'refresh_token','refresh_token'=>$tok['refresh_token'],'client_id'=>TWITCH_CLIENT_ID,'client_secret'=>TWITCH_CLIENT_SECRET]);
  curl_setopt_array($resp,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
  $out = curl_exec($resp); $code=curl_getinfo($resp,CURLINFO_HTTP_CODE); curl_close($resp);
  if ($code===200){ $d=json_decode($out,true);
    $expiresIn=(int)($d['expires_in']??0); $exp=(new DateTimeImmutable('now'))->add(new DateInterval('PT'.max(0,$expiresIn-60).'S'))->format('Y-m-d H:i:s');
    $upd=db()->prepare("UPDATE oauth_tokens SET access_token=?, refresh_token=COALESCE(?,refresh_token), scope=?, expires_at=? WHERE id=?");
    $upd->execute([$d['access_token']??'', $d['refresh_token']??null, isset($d['scope'])&&is_array($d['scope'])?implode(' ',$d['scope']):$tok['scope'], $exp, $tok['id']]);
    header('Location:/admin/?ok=refreshed'); exit;
  }
  header('Location:/admin/?err=refresh'); exit;
}
