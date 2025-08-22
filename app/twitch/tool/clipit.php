<?php
// assumes require_login() already called in router
header('Content-Type: application/json');

$channel = trim($_GET['channel'] ?? '');
if ($channel === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing channel']);
  exit;
}

// Example: load user’s stored token for this channel
$stmt = db()->prepare('SELECT access_token FROM oauth_tokens WHERE provider="twitch" AND channel=? LIMIT 1');
$stmt->execute([$channel]);
$row = $stmt->fetch();
if (!$row) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'No Twitch token bound for this channel']);
  exit;
}
$token = $row['access_token'];

// Create clip (simplified; add retries & error checks)
$resp = http('https://api.twitch.tv/helix/clips', [
  'method' => 'POST',
  'headers' => [
    'Authorization: Bearer ' . $token,
    'Client-Id: ' . TWITCH_CLIENT_ID,
    'Content-Type: application/json',
  ],
  'body' => json_encode(['broadcaster_id' => twitchUserId($channel)]),
]);

if ($resp['status'] !== 200) {
  http_response_code(502);
  echo json_encode(['ok'=>false,'error'=>'Twitch error','details'=>$resp['body'] ?? null]);
  exit;
}

$data = json_decode($resp['body'], true);
$clipUrl = $data['data'][0]['url'] ?? null;

// Optional: post to Discord
if ($clipUrl && DISCORD_WEBHOOK_URL) {
  http(DISCORD_WEBHOOK_URL, [
    'method' => 'POST',
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode(['content' => $clipUrl]),
  ]);
}

echo json_encode(['ok'=>true,'clip'=>$clipUrl]);

// --- helpers ---
function http(string $url, array $opt = []): array {
  $method = $opt['method'] ?? 'GET';
  $headers = $opt['headers'] ?? [];
  $body = $opt['body'] ?? null;

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 15,
  ]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

  $out = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['status'=>$status,'body'=>$out];
}

function twitchUserId(string $channel): string {
  // look up once and cache in DB in real usage
  return $channel; // placeholder if you already store the ID
}
