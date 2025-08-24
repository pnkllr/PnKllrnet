<?php
class Twitch {
  // ---- Site login (no tokens stored)
  static function loginUrl(string $state): string {
    return 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
      'client_id'=>Config::TWITCH_CLIENT_ID,
      'redirect_uri'=>Config::LOGIN_REDIRECT_URI,
      'response_type'=>'code',
      'scope'=>'user:read:email',
      'force_verify'=>'false',
      'state'=>$state,
    ]);
  }

  // ---- Dashboard token minting (scoped)
  static function tokenUrl(array $scopes, string $state): string {
    return 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
      'client_id'=>Config::TWITCH_CLIENT_ID,
      'redirect_uri'=>Config::TOKEN_REDIRECT_URI,
      'response_type'=>'code',
      'scope'=>implode(' ',$scopes),
      'force_verify'=>'true',
      'state'=>$state,
    ]);
  }

  static function exchange(string $code, string $redirect): array {
    return Http::postForm('https://id.twitch.tv/oauth2/token', [
      'client_id'=>Config::TWITCH_CLIENT_ID,
      'client_secret'=>Config::TWITCH_CLIENT_SECRET,
      'code'=>$code,
      'grant_type'=>'authorization_code',
      'redirect_uri'=>$redirect
    ]);
  }

  static function validate(string $access): array {
    return Http::getJSON('https://id.twitch.tv/oauth2/validate', ['Authorization: OAuth '.$access]);
  }

  static function users(string $access): array {
    return Http::getJSON('https://api.twitch.tv/helix/users', [
      'Client-ID: '.Config::TWITCH_CLIENT_ID,
      'Authorization: Bearer '.$access
    ]);
  }

  static function refresh(string $refresh): array {
    return Http::postForm('https://id.twitch.tv/oauth2/token', [
      'grant_type'=>'refresh_token',
      'refresh_token'=>$refresh,
      'client_id'=>Config::TWITCH_CLIENT_ID,
      'client_secret'=>Config::TWITCH_CLIENT_SECRET,
    ]);
  }

  // ---- Token store helpers
  static function saveOrUpgradeToken(int $userId, string $label, string $access, ?string $refresh, string $expiresAt){
    $pdo = DB::pdo();
    [$val, ] = self::validate($access);
    $granted = $val['scopes'] ?? [];
    $row = $pdo->prepare("SELECT * FROM twitch_tokens WHERE user_id=? AND label=?");
    $row->execute([$userId,$label]);
    $exist = $row->fetch();

    if ($exist) {
      $old = explode(' ', $exist['scopes']);
      $downgrade = array_diff($old, $granted);
      if ($downgrade) return; // ignore narrower tokens
      $pdo->prepare("UPDATE twitch_tokens SET scopes=?, access_token=?, refresh_token=?, expires_at=?, updated_at=NOW() WHERE id=?")
          ->execute([implode(' ',$granted), enc($access), enc($refresh ?? dec($exist['refresh_token'])), $expiresAt, $exist['id']]);
    } else {
      $pdo->prepare("INSERT INTO twitch_tokens (user_id,label,scopes,access_token,refresh_token,expires_at) VALUES (?,?,?,?,?,?)")
          ->execute([$userId,$label,implode(' ',$granted),enc($access),enc($refresh ?? ''),$expiresAt]);
    }
  }

  static function getUsableToken(int $userId, string $label='default'): ?string {
    $pdo = DB::pdo();
    $st = $pdo->prepare("SELECT * FROM twitch_tokens WHERE user_id=? AND label=?");
    $st->execute([$userId,$label]);
    $r = $st->fetch();
    if (!$r) return null;

    if (new DateTime($r['expires_at']) <= new DateTime('+1 hour')) {
      [$res, $err] = self::refresh(dec($r['refresh_token']));
      if (!$err && !empty($res['access_token'])) {
        $access = $res['access_token'];
        $refresh= $res['refresh_token'] ?? dec($r['refresh_token']);
        $expAt  = (new DateTime('+'.$res['expires_in'].' seconds'))->format('Y-m-d H:i:s');
        DB::pdo()->prepare("UPDATE twitch_tokens SET access_token=?, refresh_token=?, expires_at=?, updated_at=NOW() WHERE id=?")
                 ->execute([enc($access), enc($refresh), $expAt, $r['id']]);
        return $access;
      }
    }
    return dec($r['access_token']);
  }
}
