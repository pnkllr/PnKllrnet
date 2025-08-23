<?php
// core/cron/refresh_tokens.php
// Refresh only Twitch tokens expiring in <= 1 hour (or already expired)

require_once __DIR__ . '/../bootstrap.php';

if (!TWITCH_CLIENT_ID || !TWITCH_CLIENT_SECRET) {
  fwrite(STDERR, "[refresh] Missing TWITCH_CLIENT_ID/SECRET\n");
  exit(1);
}

// --- simple single-run lock (prevents overlap) ---
$lockDir = BASE_PATH . '/storage';
if (!is_dir($lockDir)) @mkdir($lockDir, 0775, true);
$lockFile = $lockDir . '/refresh_tokens.lock';
$fp = fopen($lockFile, 'c+');
if ($fp && !flock($fp, LOCK_EX | LOCK_NB)) {
  fwrite(STDERR, "[refresh] Another refresh is running. Exiting.\n");
  exit(0);
}

// --- logging helper ---
$logFile = $lockDir . '/refresh.log';
function logmsg($s) {
  global $logFile;
  $ts = date('Y-m-d H:i:s');
  @file_put_contents($logFile, "[$ts] $s\n", FILE_APPEND);
}

// --- http helper ---
function http_req(string $url, array $opt=[]): array {
  $method=$opt['method']??'GET'; $headers=$opt['headers']??[]; $body=$opt['body']??null;
  $ch=curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>25,
  ]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  $out=curl_exec($ch);
  $code=curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  return ['status'=>$code, 'body'=>$out, 'err'=>$err];
}

$pdo = db();

// Select only tokens with <= 60 minutes left (or NULL/expired), and that have a refresh_token
// Using NOW() assumes your app stores local time consistently; that’s what the app does elsewhere.
$sql = "
  SELECT id, user_id, access_token, refresh_token, scope, expires_at
  FROM oauth_tokens
  WHERE provider='twitch'
    AND refresh_token IS NOT NULL
    AND (expires_at IS NULL OR expires_at <= DATE_ADD(NOW(), INTERVAL 60 MINUTE))
  ORDER BY COALESCE(expires_at, NOW()) ASC
  LIMIT 200
";
$rows = $pdo->query($sql)->fetchAll();

if (!$rows) {
  logmsg("No tokens due (<=1h).");
  if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
  exit(0);
}

$refUrl = 'https://id.twitch.tv/oauth2/token';
$ok = 0; $fail = 0;

foreach ($rows as $tok) {
  $rid = (int)$tok['id'];

  // sanity guard: if DB clock drifted, double-check in PHP
  $left = null;
  if (!empty($tok['expires_at'])) {
    $ts = strtotime($tok['expires_at']);
    if ($ts !== false) $left = $ts - time();
  }
  if ($left !== null && $left > 3600) {
    // Skip — not actually due (shouldn’t happen with the SQL filter, but safe)
    continue;
  }

  if (empty($tok['refresh_token'])) {
    logmsg("id=$rid missing refresh_token; skipping");
    $fail++; continue;
  }

  $resp = http_req($refUrl, [
    'method'=>'POST',
    'headers'=>['Content-Type: application/x-www-form-urlencoded'],
    'body'=>http_build_query([
      'grant_type'    => 'refresh_token',
      'refresh_token' => $tok['refresh_token'],
      'client_id'     => TWITCH_CLIENT_ID,
      'client_secret' => TWITCH_CLIENT_SECRET,
    ])
  ]);

  if ($resp['status'] !== 200) {
    $body = json_decode($resp['body'] ?? '', true);
    $msg  = $body['message'] ?? $resp['body'] ?? $resp['err'] ?? 'unknown error';
    // Common Twitch response on invalid refresh_token: 400 invalid_grant
    logmsg("id=$rid refresh FAILED (HTTP {$resp['status']}): $msg");
    $fail++; 
    continue;
  }

  $d = json_decode($resp['body'], true) ?: [];
  $newAccess  = $d['access_token'] ?? null;
  $newRefresh = $d['refresh_token'] ?? null; // may rotate or be omitted
  $scopes     = isset($d['scope']) && is_array($d['scope']) ? implode(' ', $d['scope']) : ($tok['scope'] ?? '');
  $expiresIn  = (int)($d['expires_in'] ?? 0);
  // buffer 60s to avoid edge cases
  $expAt = (new DateTimeImmutable('now'))->add(new DateInterval('PT' . max(0, $expiresIn - 60) . 'S'))->format('Y-m-d H:i:s');

  $stmt = $pdo->prepare("
    UPDATE oauth_tokens
    SET access_token = COALESCE(?, access_token),
        refresh_token = COALESCE(?, refresh_token),
        scope = ?,
        expires_at = ?
    WHERE id = ?
  ");
  $stmt->execute([$newAccess, $newRefresh, $scopes, $expAt, $rid]);

  logmsg("id=$rid refreshed OK; new expiry $expAt");
  $ok++;

  // small jitter to avoid hitting limits in bursts
  usleep(120000); // 120ms
}

logmsg("Done: ok=$ok fail=$fail");

// release lock
if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
