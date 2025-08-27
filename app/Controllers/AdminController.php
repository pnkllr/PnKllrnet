<?php

declare(strict_types=1);

final class AdminController
{
  public static function home(): void
  {
    $me = Auth::requireUser();
    // Verify role straight from DB (avoids stale session)
    $uid = (int)($me['id'] ?? 0);
    $role = (string)($GLOBALS['_db']->query("SELECT role FROM users WHERE id=?", [$uid])->fetchColumn() ?: ($me['role'] ?? ''));
    if ($role !== 'admin') abort(403, 'Admins only');

    // Users
    $users = $GLOBALS['_db']->query("SELECT * FROM users ORDER BY id DESC")->fetchAll() ?: [];

    // Tokens (latest row per user; join user for avatar/login)
    $sql = "
  SELECT t.*, u.twitch_login, u.twitch_display, u.avatar_url
  FROM oauth_tokens t
  JOIN users u ON u.id = t.user_id
  JOIN (
    SELECT user_id, MAX(id) AS max_id      -- use latest by id (no provider/updated_at needed)
    FROM oauth_tokens
    GROUP BY user_id
  ) x ON x.user_id = t.user_id AND x.max_id = t.id
  -- no provider filter since your schema doesn't have it
  ORDER BY (t.expires_at IS NULL) ASC, t.expires_at ASC, t.id DESC
";
    $tokens = $GLOBALS['_db']->query($sql)->fetchAll() ?: [];

    // Metrics
    $totalUsers  = count($users);
    $totalTokens = count($tokens);
    $expSoon = 0;         // expiry in < 1h
    $missingClips = 0;    // doesn't have clips:edit

    $now = time();
    foreach ($tokens as $t) {
      $expAt = !empty($t['expires_at']) ? strtotime($t['expires_at']) : null;
      if ($expAt && ($expAt - $now) <= 3600) $expSoon++;
      $scopesStr = trim((string)($t['scopes'] ?? ($t['scope'] ?? ''))); // accept either column name
      $scopesPad = ' ' . $scopesStr . ' ';
      if (strpos($scopesPad, ' clips:edit ') === false) $missingClips++;
    }

    // View
    $title = "Dashboard";
        include BASE_PATH . '/ui/layout/header.php';
        include BASE_PATH . '/ui/admin/home.php';
        include BASE_PATH . '/ui/layout/footer.php';
  }
}
