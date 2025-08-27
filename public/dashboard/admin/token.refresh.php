<?php
require_once dirname(__DIR__, 3) . '/core/init.php';
Auth::requireAdmin();
CSRF::check();

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId > 0) {
    $row = $GLOBALS['_db']->query(
        "SELECT refresh_token, scopes FROM oauth_tokens WHERE user_id=? ORDER BY updated_at DESC, id DESC LIMIT 1",
        [$userId]
    )->fetch();
    if ($row && !empty($row['refresh_token'])) {
        $tw  = new TwitchClient();
        $tok = $tw->refresh($row['refresh_token']);
        $scs = preg_split('/\s+/', trim((string)($row['scopes'] ?? ''))) ?: [];
        User::setTokens($userId, $tok, $scs); // rotates refresh if Twitch returns a new one
    }
}
header('Location: ' . base_url('/admin'));
exit;
