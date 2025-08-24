<?php
require_once __DIR__.'/../app/Config.php';
require_once __DIR__.'/../app/DB.php';
require_once __DIR__.'/../app/Auth.php';
require_once __DIR__.'/../app/Twitch.php';

class DashboardController {

  public static function index() {
    Auth::requireLogin();
    $pdo = DB::pdo();

    // user
    $st = $pdo->prepare("SELECT id, twitch_user_id, display_name, email, avatar_url FROM users WHERE id=?");
    $st->execute([Auth::userId()]);
    $me = $st->fetch() ?: [];

    // tokens (for expiry display)
    $tokSt = $pdo->prepare("SELECT scopes, expires_at FROM twitch_tokens WHERE user_id=? ORDER BY updated_at DESC, id DESC");
    $tokSt->execute([Auth::userId()]);
    $tokens = $tokSt->fetchAll() ?: [];

    // bearer
    $token_bearer = Twitch::getUsableToken(Auth::userId());

    // granted scopes
    $scopes = [];
    if ($token_bearer) {
      [$val, $valErr] = Twitch::validate($token_bearer);
      if (!$valErr && !empty($val['scopes'])) $scopes = $val['scopes'];
    }

    // refresh cached profile (also store login!)
    if ($token_bearer) {
      [$u, $uErr] = Twitch::users($token_bearer);
      $ud = $u['data'][0] ?? null;
      if (!$uErr && $ud) {
        $upd = $pdo->prepare("UPDATE users SET display_name=?, avatar_url=?, twitch_user_id=? WHERE id=?");
        $upd->execute([
          $ud['display_name'] ?? ($ud['login'] ?? ($me['display_name'] ?? '')),
          $ud['profile_image_url'] ?? ($me['avatar_url'] ?? ''),
          $ud['id'] ?? ($me['twitch_user_id'] ?? null),
          Auth::userId()
        ]);
        $me['display_name']   = $ud['display_name'] ?? $me['display_name'];
        $me['avatar_url']     = $ud['profile_image_url'] ?? $me['avatar_url'];
        $me['twitch_user_id'] = (string)($ud['id'] ?? ($me['twitch_user_id'] ?? ''));
      }
    }

    // live or last VOD
    $live = ['is_live'=>false]; $vod = null;
    if ($token_bearer && !empty($me['twitch_user_id'])) {
      [$streams, $sErr] = Twitch::helix('/streams', $token_bearer, ['user_id'=>$me['twitch_user_id']]);
      $s = (!$sErr ? ($streams['data'][0] ?? null) : null);
      if ($s) {
        $live = [
          'is_live'=>true,
          'title'=>$s['title'] ?? '', 'game_name'=>$s['game_name'] ?? '', 'started_at'=>$s['started_at'] ?? ''
        ];
      } else {
        [$vids, $vErr] = Twitch::helix('/videos', $token_bearer, ['user_id'=>$me['twitch_user_id'],'type'=>'archive','first'=>1]);
        $v = (!$vErr ? ($vids['data'][0] ?? null) : null);
        if ($v) $vod = ['id'=>$v['id'], 'url'=>$v['url']];
      }
    }

    // stats + lists
    $stats = ['followers_total'=>null, 'subs_total'=>null, 'broadcaster'=>'none'];
    $followers = []; $subs = [];

    if ($token_bearer && !empty($me['twitch_user_id'])) {
      [$u2, $u2Err] = Twitch::helix('/users', $token_bearer, ['id'=>$me['twitch_user_id']]);
      if (!$u2Err && !empty($u2['data'][0]['broadcaster_type'])) {
        $stats['broadcaster'] = (string)$u2['data'][0]['broadcaster_type'];
      }
      if (in_array('moderator:read:followers', $scopes, true)) {
        [$f, $fErr] = Twitch::helix('/channels/followers', $token_bearer, ['broadcaster_id'=>$me['twitch_user_id'],'first'=>10]);
        if (!$fErr) {
          if (isset($f['total'])) $stats['followers_total'] = (int)$f['total'];
          $fids = array_column($f['data'] ?? [], 'user_id');
          $fmap = self::mapUsers($token_bearer, $fids);
          foreach (($f['data'] ?? []) as $row) {
            $uinfo = $fmap[$row['user_id']] ?? [];
            $followers[] = [
              'name'=>$row['user_name'] ?? ($uinfo['display_name'] ?? ''),
              'avatar'=>$uinfo['profile_image_url'] ?? '',
              'date'=>isset($row['followed_at']) ? date('Y-m-d', strtotime($row['followed_at'])) : ''
            ];
          }
        }
      }
      if (in_array($stats['broadcaster'], ['affiliate','partner'], true) &&
          in_array('channel:read:subscriptions', $scopes, true)) {
        [$subRes, $subErr] = Twitch::helix('/subscriptions', $token_bearer, ['broadcaster_id'=>$me['twitch_user_id'], 'first'=>10]);
        if (!$subErr) {
          if (isset($subRes['total'])) $stats['subs_total'] = (int)$subRes['total'];
          $sids = array_column($subRes['data'] ?? [], 'user_id');
          $smap = self::mapUsers($token_bearer, $sids);
          foreach (($subRes['data'] ?? []) as $row) {
            $uinfo = $smap[$row['user_id']] ?? [];
            $subs[] = [
              'name'=>$row['user_name'] ?? ($uinfo['display_name'] ?? ''),
              'avatar'=>$uinfo['profile_image_url'] ?? '',
              'tier'=>$row['tier'] ?? ''
            ];
          }
        }
      }
    }

    $desiredScopes = $scopes;

    view('dashboard', compact(
      'me','tokens','token_bearer','scopes','channel_login',
      'live','vod','stats','followers','subs','desiredScopes'
    ));
  }

  private static function mapUsers(string $access, array $ids): array {
    $ids = array_values(array_unique(array_filter($ids)));
    if (!$ids) return [];
    $qs = implode('&', array_map(fn($id) => 'id='.urlencode($id), $ids));
    [$res, $err] = Twitch::helix('/users', $access, $qs);
    if ($err) return [];
    $map = [];
    foreach (($res['data'] ?? []) as $u) $map[$u['id']] = $u;
    return $map;
  }

  public static function createToken() {
    Auth::requireLogin();
    $scopesParam = $_GET['scopes'] ?? $_POST['scopes'] ?? '';
    if (is_array($scopesParam)) $scopes = $scopesParam;
    else $scopes = preg_split('/\s+/', trim(urldecode((string)$scopesParam))) ?: [];
    if (!$scopes) $scopes = ['user:read:email'];
    $scopes = array_values(array_filter(array_unique(array_map('strval', $scopes))));
    $state = bin2hex(random_bytes(16));
    $_SESSION['token_state']  = $state;
    $_SESSION['token_scopes'] = $scopes;
    header('Location: '.Twitch::tokenUrl($scopes, $state), true, 302);
    exit;
  }

  public static function tokenCallback() {
    Auth::requireLogin();
    $ok = isset($_GET['state'], $_SESSION['token_state']) && hash_equals($_SESSION['token_state'], $_GET['state']);
    $code = $_GET['code'] ?? '';
    if (!$ok || $code==='') { http_response_code(400); echo 'Invalid authorization response.'; return; }
    [$tok, $err] = Twitch::exchange($code, Config::TOKEN_REDIRECT_URI);
    if ($err || empty($tok['access_token'])) { http_response_code(400); echo 'Token exchange failed.'; return; }

    $expAt = (new DateTime('+'.((int)$tok['expires_in']).' seconds'))->format('Y-m-d H:i:s');

    // use actually granted scopes
    [$val, $vErr] = Twitch::validate($tok['access_token']);
    $granted = (!$vErr && !empty($val['scopes'])) ? $val['scopes'] : (array)($_SESSION['token_scopes'] ?? []);
    $granted = array_values(array_unique(array_map('strval', $granted)));
    sort($granted, SORT_STRING);
    Twitch::saveToken(Auth::userId(), $tok['access_token'], $tok['refresh_token'] ?? null, $expAt, implode(' ', $granted));

    unset($_SESSION['token_state'], $_SESSION['token_scopes']);
    header('Location: /'); exit;
  }

  public static function deleteToken() {
    header('Content-Type: application/json; charset=utf-8');
    try {
      Auth::requireLogin();
      $st = DB::pdo()->prepare("DELETE FROM twitch_tokens WHERE user_id=?");
      $st->execute([Auth::userId()]);
      echo json_encode(['ok'=>true, 'deleted'=>(int)$st->rowCount()]);
    } catch (Throwable $e) {
      error_log('[token/delete] '.$e->getMessage());
      http_response_code(500);
      echo json_encode(['ok'=>false,'error'=>'server']);
    }
  }
}
