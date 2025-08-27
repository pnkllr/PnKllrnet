<?php
require_once dirname(__DIR__) . '/core/init.php';

$pdo = $GLOBALS['_db']->pdo();
$now = new DateTimeImmutable('now');
$threshold = $now->modify('+60 minutes')->format('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT u.id uid, u.twitch_id, t.* FROM oauth_tokens t JOIN users u ON u.id=t.user_id WHERE t.expires_at <= ?");
$stmt->execute([$threshold]);
$tw = new TwitchClient();

while ($row = $stmt->fetch()) {
        $newTok = $tw->refresh($row['refresh_token']);
        $scopes = explode(' ', $row['scopes']);
        User::setTokens((int)$row['user_id'], $newTok, $scopes);
}
