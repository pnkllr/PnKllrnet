<?php
require_once __DIR__.'/../app/Config.php';
require_once __DIR__.'/../app/DB.php';
require_once __DIR__.'/../app/Auth.php';

class AdminController {
  public static function index() {
    Auth::requireLogin();
    // your simple policy here; or check a table/flag for admin
    $pdo = DB::pdo();

    // minimal data if you want it in the template
    $users = $pdo->query("SELECT id, twitch_user_id, twitch_login, display_name, email FROM users ORDER BY id DESC")->fetchAll();
    $tokens= $pdo->query("SELECT user_id, scopes, expires_at, updated_at FROM twitch_tokens ORDER BY updated_at DESC")->fetchAll();

    // include your old template under /admin (kept as-is)
    // you can make these arrays available:
    $GLOBALS['__admin_users__'] = $users;
    $GLOBALS['__admin_tokens__']= $tokens;
    require __DIR__.'/../admin/index.php';
  }
}
