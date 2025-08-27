<?php
declare(strict_types=1);

final class User {
    public static function upsertFromTwitch(array $tu): array {
        $db = $GLOBALS['_db'];
        $existing = $db->query("SELECT * FROM users WHERE twitch_id=?", [$tu['id']])->fetch();
        if ($existing) {
            $db->query("UPDATE users SET twitch_login=?, twitch_display=?, email=?, avatar_url=?, updated_at=NOW() WHERE id=?",
                [$tu['login'], $tu['display_name'], $tu['email'] ?? null, $tu['profile_image_url'] ?? null, $existing['id']]);
            return $db->query("SELECT * FROM users WHERE id=?", [$existing['id']])->fetch();
        }
        $id = $db->insert("INSERT INTO users (twitch_id, twitch_login, twitch_display, email, avatar_url) VALUES (?,?,?,?,?)",
            [$tu['id'], $tu['login'], $tu['display_name'], $tu['email'] ?? null, $tu['profile_image_url'] ?? null]);
        return $db->query("SELECT * FROM users WHERE id=?", [$id])->fetch();
    }

    public static function setTokens(int $userId, array $tok, array $scopes): void {
        $db = $GLOBALS['_db'];
        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . intval($tok['expires_in'] ?? 0) . ' seconds')->format('Y-m-d H:i:s');
        $exists = $db->query("SELECT id FROM oauth_tokens WHERE user_id=?", [$userId])->fetch();
        if ($exists) {
            $db->query("UPDATE oauth_tokens SET access_token=?, refresh_token=?, scopes=?, expires_at=?, updated_at=NOW() WHERE user_id=?",
                [$tok['access_token'], $tok['refresh_token'] ?? '', implode(' ', $scopes), $expiresAt, $userId]);
        } else {
            $db->insert("INSERT INTO oauth_tokens (user_id, access_token, refresh_token, scopes, expires_at) VALUES (?,?,?,?,?)",
                [$userId, $tok['access_token'], $tok['refresh_token'] ?? '', implode(' ', $scopes), $expiresAt]);
        }
    }

    public static function getTokens(int $userId): ?array {
        $db = $GLOBALS['_db'];
        return $db->query("SELECT * FROM oauth_tokens WHERE user_id=?", [$userId])->fetch() ?: null;
    }
    
     public static function getUser(int $userId): ?array {
        $db = $GLOBALS['_db'];
        return $db->query("SELECT * FROM users WHERE id=?", [$userId])->fetch() ?: null;
    }

    public static function allUsersPaged(int $page=1, int $per=25): array {
        $db = $GLOBALS['_db'];
        $offset = max(0, ($page-1)*$per);
        $rows = $db->query("SELECT * FROM users ORDER BY id DESC LIMIT $per OFFSET $offset")->fetchAll();
        return $rows;
    }

    public static function count(): int {
        $db = $GLOBALS['_db'];
        $row = $db->query("SELECT COUNT(*) c FROM users")->fetch();
        return (int)($row['c'] ?? 0);
    }
}
