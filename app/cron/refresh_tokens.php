<?php
// public_html/app/cron/refresh_tokens.php
declare(strict_types=1);

// CLI only
if (PHP_SAPI !== 'cli') { http_response_code(403); echo "Forbidden\n"; exit(1); }

require_once __DIR__ . '/../init.php';

date_default_timezone_set('UTC');
@ini_set('memory_limit', '256M');
@set_time_limit(0);

// ---------- lock to avoid overlap ----------
$lockPath = __DIR__ . '/refresh_tokens.lock';
$lockFp = @fopen($lockPath, 'c');
if (!$lockFp) { echo "Could not open lock file.\n"; exit(1); }
if (!flock($lockFp, LOCK_EX | LOCK_NB)) { echo "Another run in progress.\n"; exit(0); }

// ---------- logging ----------
$logFile = __DIR__ . '/refresh_tokens.log';
function log_line(string $msg): void {
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg\n";
    echo $line;
    @file_put_contents($GLOBALS['logFile'], $line, FILE_APPEND);
}

// ---------- config sanity ----------
if (empty($config['twitch_client_id']) || empty($config['twitch_client_secret'])) {
    log_line('ERROR: Missing twitch_client_id/secret in app/config.php');
    exit(1);
}

// ---------- select tokens expiring within 3 hours (or expired/NULL) ----------
$sql = "
SELECT
  ut.twitch_id,
  ut.refresh_token,
  UNIX_TIMESTAMP(ut.expires_at) AS exp_unix
FROM user_tokens ut
WHERE ut.refresh_token IS NOT NULL
  AND (
        ut.expires_at IS NULL
        OR ut.expires_at < DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR)
      )
ORDER BY COALESCE(ut.expires_at, '1970-01-01') ASC
";

$stmt = $db->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
if ($total === 0) {
    log_line('No tokens require refresh.');
    exit(0);
}

log_line("Found {$total} token(s) to refresh.");

$ok = 0; $fail = 0;

// helper: update tracking columns (and optionally tokens)
function mark_status(PDO $db, string $tid, string $status, ?string $err = null, ?array $tokens = null): void {
    if ($tokens) {
        $q = $db->prepare("
            UPDATE user_tokens
            SET access_token = :at,
                refresh_token = :rt,
                expires_at    = FROM_UNIXTIME(:exp),
                last_refresh_at = UTC_TIMESTAMP(),
                last_refresh_status = :st,
                last_error = NULL,
                updated_at = NOW()
            WHERE twitch_id = :id
        ");
        $q->execute([
            ':at' => $tokens['access_token'],
            ':rt' => $tokens['refresh_token'],
            ':exp'=> $tokens['expires_unix'],
            ':st' => $status,
            ':id' => $tid,
        ]);
    } else {
        $q = $db->prepare("
            UPDATE user_tokens
            SET last_refresh_at = UTC_TIMESTAMP(),
                last_refresh_status = :st,
                last_error = :err,
                updated_at = NOW()
            WHERE twitch_id = :id
        ");
        $q->execute([
            ':st'  => $status,
            ':err' => $err,
            ':id'  => $tid,
        ]);
    }
}

foreach ($rows as $row) {
    $tid = (string)$row['twitch_id'];
    $rt  = (string)$row['refresh_token'];

    if ($rt === '') {
        log_line("WARN: {$tid} has empty refresh_token, skipping.");
        $fail++;
        mark_status($db, $tid, 'invalid_refresh', 'empty refresh_token');
        usleep(250000);
        continue;
    }

    // Call Twitch
    [$json, $err] = http_post_form('https://id.twitch.tv/oauth2/token', [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $rt,
        'client_id'     => $config['twitch_client_id'],
        'client_secret' => $config['twitch_client_secret'],
    ]);

    if ($err) {
        log_line("ERROR: {$tid} HTTP error: {$err}");
        $fail++;
        mark_status($db, $tid, 'http_error', $err);
        usleep(350000);
        continue;
    }

    // Twitch error payloads: {"status":400,"message":"invalid refresh token"}
    if (isset($json['status']) && $json['status'] >= 400) {
        $msg = $json['message'] ?? 'unknown';
        $status = (stripos($msg, 'invalid refresh token') !== false) ? 'invalid_refresh' : 'twitch_error';

        // Optional: clear unusable refresh tokens to force re-auth later
        if ($status === 'invalid_refresh') {
            $db->prepare("UPDATE user_tokens SET refresh_token=NULL, updated_at=NOW() WHERE twitch_id=:id")
               ->execute([':id' => $tid]);
        }

        log_line("ERROR: {$tid} Twitch error {$json['status']}: {$msg}");
        $fail++;
        mark_status($db, $tid, $status, $msg);
        usleep(350000);
        continue;
    }

    if (empty($json['access_token'])) {
        log_line("ERROR: {$tid} No access_token in response.");
        $fail++;
        mark_status($db, $tid, 'twitch_error', 'missing access_token');
        usleep(350000);
        continue;
    }

    $newAccess  = (string)$json['access_token'];
    $newRefresh = isset($json['refresh_token']) ? (string)$json['refresh_token'] : $rt;
    $expiresIn  = (int)($json['expires_in'] ?? 3600);
    $newExpiry  = time() + $expiresIn;

    try {
        mark_status($db, $tid, 'ok', null, [
            'access_token' => $newAccess,
            'refresh_token'=> $newRefresh,
            'expires_unix' => $newExpiry,
        ]);
        $ok++;
        log_line("OK: {$tid} refreshed; expires_in={$expiresIn}s.");
    } catch (Throwable $e) {
        $fail++;
        log_line("ERROR: {$tid} DB update failed: " . $e->getMessage());
        mark_status($db, $tid, 'db_error', $e->getMessage());
    }

    // rate-limit friendliness
    usleep(300000); // 300ms
}

log_line("Done. success={$ok}, failed={$fail}.");
exit(($fail > 0 && $ok === 0) ? 1 : 0);
