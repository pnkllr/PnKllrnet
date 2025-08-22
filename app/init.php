<?php
$config = require __DIR__ . '/config.php';

function is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    return false;
}

session_name($config['session_name'] ?? 'pnkllr_session');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => is_https(),
        'samesite' => 'Lax',
        'lifetime' => 0,
        'path'     => '/',
    ]);
    session_start();
}

try {
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/http/http.php';

function abs_url(string $path): string {
    $scheme = is_https() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'pnkllr.net';
    if ($path && $path[0] !== '/') $path = "/$path";
    return "{$scheme}://{$host}{$path}";
}

/**
 * Optional: auto-refresh Twitch token if near expiry.
 * Expects $_SESSION['user'] keys:
 *   twitch_access_token, twitch_refresh_token, twitch_expires_at (unix time)
 */
function twitch_refresh_if_needed(PDO $db, array $config): bool {
    if (empty($_SESSION['user'])) return false;

    $now = time();
    $skew = 120;
    $expiresAt = (int)($_SESSION['user']['twitch_expires_at'] ?? 0);

    if ($expiresAt > ($now + $skew)) {
        return true; // still valid
    }

    $refresh = $_SESSION['user']['twitch_refresh_token'] ?? null;
    if (!$refresh) return false;

    [$json, $err] = http_post_form('https://id.twitch.tv/oauth2/token', [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh,
        'client_id'     => $config['twitch_client_id'],
        'client_secret' => $config['twitch_client_secret'],
    ]);

    if ($err || empty($json['access_token'])) {
        return false;
    }

    $newAccess  = $json['access_token'];
    $newRefresh = $json['refresh_token'] ?? $refresh; // Twitch may rotate
    $expiresIn  = (int)($json['expires_in'] ?? 3600);
    $newExpiry  = $now + $expiresIn;

    // Update session
    $_SESSION['user']['twitch_access_token']  = $newAccess;
    $_SESSION['user']['twitch_refresh_token'] = $newRefresh;
    $_SESSION['user']['twitch_expires_at']    = $newExpiry;

    // Mirror to DB
    try {
        $stmt = $db->prepare("
            UPDATE user_tokens
            SET access_token = :at,
                refresh_token = :rt,
                expires_at = FROM_UNIXTIME(:exp),
                updated_at = NOW()
            WHERE twitch_id = :id
        ");
        $stmt->execute([
            ':at'  => $newAccess,
            ':rt'  => $newRefresh,
            ':exp' => $newExpiry,
            ':id'  => (string)($_SESSION['user']['id'] ?? ''),
        ]);
    } catch (Throwable $e) {
        // If DB update fails, we still continue with refreshed session tokens
    }

    return true;
}
