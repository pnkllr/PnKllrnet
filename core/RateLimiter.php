<?php
declare(strict_types=1);

final class RateLimiter {
    // Simple token bucket stored in DB
    public static function allow(int $userId, string $key, int $capacity=3, int $refillSeconds=60): bool {
        $db = $GLOBALS['_db'];
        $row = $db->query("SELECT * FROM rate_limits WHERE user_id=? AND key_name=?", [$userId, $key])->fetch();
        $now = new DateTimeImmutable('now');
        if (!$row) {
            $db->insert("INSERT INTO rate_limits (user_id, key_name, tokens, refreshed_at) VALUES (?,?,?,?)",
                [$userId, $key, $capacity-1, $now->format('Y-m-d H:i:s')]);
            return true;
        }
        $last = new DateTimeImmutable($row['refreshed_at']);
        $elapsed = $now->getTimestamp() - $last->getTimestamp();
        $tokens = (int)$row['tokens'] + intdiv($elapsed, $refillSeconds) * $capacity;
        if ($tokens > $capacity) $tokens = $capacity;
        if ($tokens <= 0) {
            $db->query("UPDATE rate_limits SET tokens=?, refreshed_at=? WHERE id=?",
                [0, $now->format('Y-m-d H:i:s'), $row['id']]);
            return False;
        }
        $tokens -= 1;
        $db->query("UPDATE rate_limits SET tokens=?, refreshed_at=? WHERE id=?",
            [$tokens, $now->format('Y-m-d H:i:s'), $row['id']]);
        return true;
    }
}
