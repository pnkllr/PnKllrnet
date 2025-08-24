<?php
class Twitch {

  // --- OAuth URLs
  public static function loginUrl(string $state): string {
    return 'https://id.twitch.tv/oauth2/authorize?'.http_build_query([
      'client_id'     => Config::TWITCH_CLIENT_ID,
      'redirect_uri'  => Config::LOGIN_REDIRECT_URI,
      'response_type' => 'code',
      'scope'         => 'user:read:email',
      'force_verify'  => 'false',
      'state'         => $state,
    ]);
  }

  public static function tokenUrl(array $scopes, string $state): string {
    return 'https://id.twitch.tv/oauth2/authorize?'.http_build_query([
      'client_id'     => Config::TWITCH_CLIENT_ID,
      'redirect_uri'  => Config::TOKEN_REDIRECT_URI,
      'response_type' => 'code',
      'scope'         => implode(' ', $scopes),
      'force_verify'  => 'true', // re-prompt when scopes change
      'state'         => $state,
    ]);
  }

  // --- OAuth exchanges
  public static function exchange(string $code, string $redirect): array {
    return Http::postForm('https://id.twitch.tv/oauth2/token', [
      'client_id'     => Config::TWITCH_CLIENT_ID,
      'client_secret' => Config::TWITCH_CLIENT_SECRET,
      'code'          => $code,
      'grant_type'    => 'authorization_code',
      'redirect_uri'  => $redirect,
    ]);
  }

  public static function refresh(string $refresh): array {
    return Http::postForm('https://id.twitch.tv/oauth2/token', [
      'grant_type'    => 'refresh_token',
      'refresh_token' => $refresh,
      'client_id'     => Config::TWITCH_CLIENT_ID,
      'client_secret' => Config::TWITCH_CLIENT_SECRET,
    ]);
  }

  // --- Validation + users
  public static function validate(string $access): array {
    return Http::getJSON('https://id.twitch.tv/oauth2/validate', [
      'Authorization: OAuth '.$access
    ]);
  }

  public static function users(string $access, array $query = []): array {
    $qs  = $query ? ('?'.http_build_query($query)) : '';
    $url = 'https://api.twitch.tv/helix/users'.$qs;
    return Http::getJSON($url, [
      'Client-ID: '.Config::TWITCH_CLIENT_ID,
      'Authorization: Bearer '.$access
    ]);
  }

  // --- Helix helpers
  public static function helix(string $path, string $access, $query = []): array {
    $q   = is_array($query) ? (empty($query) ? '' : '?'.http_build_query($query))
                            : ($query ? ('?'.$query) : '');
    $url = 'https://api.twitch.tv/helix'.$path.$q;
    return Http::getJSON($url, [
      'Client-ID: '.Config::TWITCH_CLIENT_ID,
      'Authorization: Bearer '.$access
    ]);
  }

  public static function helixPost(string $path, string $access, array $fields = []): array {
    $url = 'https://api.twitch.tv/helix'.$path;
    return Http::postForm($url, $fields, [
      'Client-ID: '.Config::TWITCH_CLIENT_ID,
      'Authorization: Bearer '.$access
    ]);
  }

  // --- Single-token store (one row per user)
  public static function saveToken(int $userId, string $access, ?string $refresh, string $expiresAt, string $scopeStr): void {
    $pdo = DB::pdo();
    $st  = $pdo->prepare("SELECT id FROM twitch_tokens WHERE user_id=? LIMIT 1");
    $st->execute([$userId]);
    $id = $st->fetchColumn();

    if ($id) {
      $u = $pdo->prepare("UPDATE twitch_tokens
        SET access_token=?, refresh_token=?, expires_at=?, scopes=?, updated_at=NOW()
        WHERE id=?");
      $u->execute([$access, $refresh, $expiresAt, $scopeStr, $id]);
    } else {
      $i = $pdo->prepare("INSERT INTO twitch_tokens
        (user_id, access_token, refresh_token, expires_at, scopes, created_at, updated_at)
        VALUES (?,?,?,?,?,NOW(),NOW())");
      $i->execute([$userId, $access, $refresh, $expiresAt, $scopeStr]);
    }
  }

  public static function getUsableToken(int $userId): ?string {
    $pdo = DB::pdo();
    $st  = $pdo->prepare("SELECT id, access_token, refresh_token, expires_at FROM twitch_tokens WHERE user_id=? LIMIT 1");
    $st->execute([$userId]);
    $r = $st->fetch();
    if (!$r) return null;

    // refresh if under 1h
    if (new DateTime($r['expires_at']) <= new DateTime('+1 hour')) {
      [$res, $err] = self::refresh($r['refresh_token'] ?? '');
      if (!$err && !empty($res['access_token'])) {
        $access = $res['access_token'];
        $refresh= $res['refresh_token'] ?? ($r['refresh_token'] ?? '');
        $expAt  = (new DateTime('+'.((int)$res['expires_in']).' seconds'))->format('Y-m-d H:i:s');
        $pdo->prepare("UPDATE twitch_tokens SET access_token=?, refresh_token=?, expires_at=?, updated_at=NOW() WHERE id=?")
            ->execute([$access, $refresh, $expAt, $r['id']]);
        return $access;
      }
    }
    return $r['access_token'] ?: null;
  }
}
