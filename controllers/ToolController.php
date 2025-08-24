<?php
require_once __DIR__.'/../app/Config.php';
require_once __DIR__.'/../app/DB.php';
require_once __DIR__.'/../app/Auth.php';
require_once __DIR__.'/../app/Twitch.php';

class ToolController {
  // GET /tool/clipit?channel=<twitch_login>&format=text|json
  public static function clipit() {
    header('Content-Type: application/json; charset=utf-8');

    $format  = ($_GET['format'] ?? 'json') === 'text' ? 'text' : 'json';
    $login   = strtolower(trim($_GET['channel'] ?? ''));

    try {
      // If no channel given, default to current user (if logged in)
      if ($login === '') {
        Auth::requireLogin();
        $st = DB::pdo()->prepare("SELECT twitch_login FROM users WHERE id=?");
        $st->execute([Auth::userId()]);
        $login = strtolower((string)($st->fetchColumn() ?: ''));
        if ($login === '') throw new RuntimeException('No channel specified.');
      }

      // Find the user + token for that channel (must have clips:edit)
      $pdo = DB::pdo();
      $row = $pdo->prepare("SELECT u.id uid, u.twitch_user_id tid, t.access_token tok, t.scopes scopes
                            FROM users u
                            JOIN twitch_tokens t ON t.user_id = u.id
                            WHERE LOWER(u.twitch_login)=?
                            LIMIT 1");
      $row->execute([$login]);
      $rec = $row->fetch();
      if (!$rec) throw new RuntimeException('Channel not registered.');
      if (strpos(' '.$rec['scopes'].' ', ' clips:edit ') === false) throw new RuntimeException('Missing clips:edit.');

      $access = $rec['tok'];
      $broadcasterId = $rec['tid'];

      // If broadcaster_id missing, fetch it
      if (!$broadcasterId) {
        [$u, $uErr] = Twitch::users($access, ['login'=>$login]);
        if ($uErr || empty($u['data'][0]['id'])) throw new RuntimeException('Unable to resolve broadcaster id.');
        $broadcasterId = (string)$u['data'][0]['id'];
        $pdo->prepare("UPDATE users SET twitch_user_id=? WHERE id=?")->execute([$broadcasterId, (int)$rec['uid']]);
      }

      // POST /clips
      [$res, $err] = Twitch::helixPost('/clips', $access, ['broadcaster_id'=>$broadcasterId, 'has_delay'=>'false']);
      if ($err || empty($res['data'][0]['id'])) throw new RuntimeException('Clip failed.');

      $clipId  = $res['data'][0]['id'];
      $clipUrl = 'https://clips.twitch.tv/'.$clipId;

      if ($format === 'text') {
        header('Content-Type: text/plain; charset=utf-8');
        echo $clipUrl;
      } else {
        echo json_encode(['ok'=>true,'id'=>$clipId,'url'=>$clipUrl]);
      }
    } catch (Throwable $e) {
      if ($format === 'text') {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(400);
        echo 'error: '.$e->getMessage();
      } else {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
      }
    }
  }
}
