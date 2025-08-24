<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/Twitch.php';

$st = DB::pdo()->query("SELECT * FROM twitch_tokens WHERE expires_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)");
foreach ($st as $row) {
  [$res,$err] = Twitch::refresh(dec($row['refresh_token']));
  if ($err || empty($res['access_token'])) continue;
  $access = $res['access_token'];
  $refresh= $res['refresh_token'] ?? dec($row['refresh_token']);
  $expAt  = (new DateTime('+'.$res['expires_in'].' seconds'))->format('Y-m-d H:i:s');
  DB::pdo()->prepare("UPDATE twitch_tokens SET access_token=?, refresh_token=?, expires_at=?, updated_at=NOW() WHERE id=?")
           ->execute([enc($access), enc($refresh), $expAt, $row['id']]);
}
