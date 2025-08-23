<?php
function scopes_normalize(array $scopes): array {
  $s = array_values(array_unique(array_filter(array_map('trim', $scopes))));
  sort($s, SORT_STRING);
  return $s;
}
function parse_scope_str(?string $scopeStr): array {
  return $scopeStr ? scopes_normalize(preg_split('/\s+/', trim($scopeStr))) : [];
}
function scopes_to_string(array $scopes): string { return implode(' ', scopes_normalize($scopes)); }
function scopes_hash(array $scopes): string { return sha1(scopes_to_string($scopes)); }

function twitch_authorize_url(array $scopes, string $state): string {
  $clientId = TWITCH_CLIENT_ID;                    // constants already in your bootstrap
  $redirect = rtrim(BASE_URL, '/') . '/twitch/auth/callback.php';
  $scope    = scopes_to_string($scopes);
  // force_verify always shows consent so users can add/remove scopes cleanly
  return 'https://id.twitch.tv/oauth2/authorize?'.http_build_query([
    'response_type'=>'code','client_id'=>$clientId,'redirect_uri'=>$redirect,
    'scope'=>$scope,'state'=>$state,'force_verify'=>'true',
  ]);
}

// DB helpers that match your style
function get_desired_scopes(int $userId): array {
  $st = db()->prepare("SELECT scope_str FROM user_desired_scopes WHERE user_id=?");
  $st->execute([$userId]);
  $raw = $st->fetchColumn();
  return $raw ? parse_scope_str($raw) : [];
}
function upsert_user_desired_scopes(int $userId, string $scopeStr, string $scopeHash): void {
  $sql = "INSERT INTO user_desired_scopes (user_id, scope_str, scope_hash)
          VALUES (?,?,?)
          ON DUPLICATE KEY UPDATE scope_str=VALUES(scope_str), scope_hash=VALUES(scope_hash)";
  db()->prepare($sql)->execute([$userId, $scopeStr, $scopeHash]);
}
function insert_scope_event(int $userId, ?string $fromHash, string $toHash, string $reason): void {
  $sql = "INSERT INTO oauth_scope_events (user_id, from_scope_hash, to_scope_hash, reason)
          VALUES (?,?,?,?)";
  db()->prepare($sql)->execute([$userId, $fromHash, $toHash, $reason]);
}