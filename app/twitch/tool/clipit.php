<?php
require_once __DIR__ . '/../../../core/bootstrap.php';

// -------- output helpers --------
$asText = (isset($_GET['format']) && $_GET['format'] === 'text')
       || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/plain'));
header('Cache-Control: no-store');
header('Content-Type: ' . ($asText ? 'text/plain; charset=utf-8' : 'application/json'));

function out($data, int $code = 200, bool $asText = false) {
  http_response_code($code);
  if ($asText) {
    echo is_array($data) && isset($data['clip_url']) ? $data['clip_url'] : (is_string($data) ? $data : 'error');
  } else {
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
  }
  exit;
}
function http_req(string $url, array $opt = []): array {
  $method  = $opt['method']  ?? 'GET';
  $headers = $opt['headers'] ?? [];
  $body    = $opt['body']    ?? null;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 20,
  ]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  $out  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['status' => $code, 'body' => $out];
}
function rate_limit(string $channel, int $window = 20): void {
  $dir = BASE_PATH . '/storage';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $f = $dir . '/clipit_' . strtolower($channel) . '.lock';
  $now = time();
  if (is_file($f) && ($now - (int)@filemtime($f)) < $window) {
    out(['ok'=>false,'error'=>'Too many requests'], 429, false);
  }
  @touch($f, $now);
}

// -------- input --------
$channel = trim($_GET['channel'] ?? '');
if ($channel === '' || !preg_match('/^[A-Za-z0-9_]{3,25}$/', $channel)) {
  out(['ok'=>false,'error'=>'Missing or invalid ?channel'], 400, $asText);
}

// -------- require that channel exists in DB and has a token --------
$pdo = db();
$sel = $pdo->prepare("
  SELECT u.id AS uid, u.twitch_id, u.twitch_login,
         t.id AS tok_id, t.access_token, t.scope, t.expires_at
  FROM users u
  LEFT JOIN oauth_tokens t ON t.user_id = u.id AND t.provider = 'twitch'
  WHERE u.twitch_login = ?
  LIMIT 1
");
$sel->execute([$channel]);
$row = $sel->fetch();

if (!$row) {
  out(['ok'=>false,'error'=>'Channel not registered'], 404, $asText);
}
if (empty($row['access_token'])) {
  out(['ok'=>false,'error'=>'No Twitch token on file for this channel'], 403, $asText);
}

// Optional: quick expiry hint (no refresh here)
if (!empty($row['expires_at']) && strtotime($row['expires_at']) <= time()) {
  out(['ok'=>false,'error'=>'Token expired; try again after refresh cron or reconnect Twitch'], 401, $asText);
}

// -------- ensure clips:edit scope was granted --------
$scopeStr = ' ' . (string)($row['scope'] ?? '') . ' ';
if (strpos($scopeStr, ' clips:edit ') === false) {
  out(['ok'=>false,'error'=>'clips:edit not granted; please reconnect Twitch with this scope'], 403, $asText);
}

// -------- throttle per channel --------
rate_limit($channel, 20);

// -------- create clip --------
$broadcasterId = $row['twitch_id']; // saved at login
$url = "https://api.twitch.tv/helix/clips?broadcaster_id=" . urlencode($broadcasterId);
$resp = http_req($url, [
  'method'  => 'POST',
  'headers' => [
    'Authorization: Bearer ' . $row['access_token'],
    'Client-Id: ' . TWITCH_CLIENT_ID,
  ],
]);

if ($resp['status'] === 401) {
  // No refresh here—cron should handle it
  out(['ok'=>false,'error'=>'Unauthorized (token stale). Wait for cron refresh or reconnect Twitch.','status'=>401], 401, $asText);
}
if ($resp['status'] < 200 || $resp['status'] >= 300) {
  out(['ok'=>false,'error'=>'Twitch error','status'=>$resp['status'],'details'=>json_decode($resp['body'] ?? '', true)], 502, $asText);
}

$body   = json_decode($resp['body'], true) ?: [];
$clipId = $body['data'][0]['id']       ?? null;
$edit   = $body['data'][0]['edit_url'] ?? null;
$clip   = $clipId ? ("https://clips.twitch.tv/" . $clipId) : null;

if (!$clipId) out(['ok'=>false,'error'=>'Clip not created','details'=>$body], 502, $asText);

out(['ok'=>true,'clip_id'=>$clipId,'clip_url'=>$clip,'edit_url'=>$edit,'channel'=>$channel], 200, $asText);
