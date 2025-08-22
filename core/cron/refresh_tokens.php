<?php
require_once __DIR__ . '/../bootstrap.php';

$now = new DateTimeImmutable('now');
$q = db()->query("SELECT * FROM oauth_tokens WHERE provider='twitch' AND (expires_at IS NULL OR expires_at <= DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
$rows = $q->fetchAll();

foreach ($rows as $t) {
  if (empty($t['refresh_token'])) continue;
  $resp = http('https://id.twitch.tv/oauth2/token', [
    'method'=>'POST',
    'headers'=>['Content-Type: application/x-www-form-urlencoded'],
    'body'=>http_build_query([
      'grant_type' => 'refresh_token',
      'refresh_token' => $t['refresh_token'],
      'client_id' => TWITCH_CLIENT_ID,
      'client_secret' => TWITCH_CLIENT_SECRET,
    ])
  ]);
  if ($resp['status'] === 200) {
    $data = json_decode($resp['body'], true);
    $expiresIn = (int)($data['expires_in'] ?? 0);
    $exp = (new DateTimeImmutable('now'))->add(new DateInterval('PT' . max(0,$expiresIn-60) . 'S'));
    $stmt = db()->prepare("UPDATE oauth_tokens SET access_token=?, expires_at=? WHERE id=?");
    $stmt->execute([$data['access_token'] ?? '', $exp->format('Y-m-d H:i:s'), $t['id']]);
  }
}

function http(string $url, array $opt=[]): array { // same as above
  $method=$opt['method']??'GET'; $headers=$opt['headers']??[]; $body=$opt['body']??null;
  $ch=curl_init($url); curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_TIMEOUT=>20]);
  if ($body!==null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  $out=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return ['status'=>$status,'body'=>$out];
}
