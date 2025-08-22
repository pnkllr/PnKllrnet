<?php
// public_html/dashboard/index.php
declare(strict_types=1);

require_once __DIR__ . '/../_app_bootstrap.php';
require_once APP_PATH . '/auth/guard.php'; // ensures session + (optional) token refresh

// ---------- Helpers ----------
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function format_expiry(int $unixTs): string {
    $now = time();
    $diff = $unixTs - $now;
    if ($diff <= 0) return 'Expired';
    $mins = intdiv($diff, 60);
    if ($mins < 60) return $mins . ' min';
    $hours = intdiv($mins, 60);
    $minsR = $mins % 60;
    return $hours . 'h ' . $minsR . 'm';
}

// ---------- Admin check ----------
$isAdmin = in_array(
    (string)($_SESSION['user']['id'] ?? ''),
    array_map('strval', $config['admin_user_ids'] ?? []),
    true
);

// ---------- Base info from session/DB ----------
$userId = (string)($_SESSION['user']['id'] ?? '');

$profile = [
    'login'             => $_SESSION['user']['login']         ?? '',
    'display_name'      => $_SESSION['user']['display_name']  ?? '',
    'email'             => $_SESSION['user']['email']         ?? '',
    'profile_image_url' => null,
    'updated_at'        => null,
];

$tokenMeta = [
    'scope'        => '',
    'expires_unix' => (int)($_SESSION['user']['twitch_expires_at'] ?? 0),
    'updated_at'   => null,
];

if ($userId !== '') {
    // users
    $stmt = $db->prepare("
        SELECT login, display_name, email, profile_image_url, updated_at
        FROM users
        WHERE twitch_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $profile['login']             = $row['login'] ?? $profile['login'];
        $profile['display_name']      = $row['display_name'] ?? $profile['display_name'];
        $profile['email']             = $row['email'] ?? $profile['email'];
        $profile['profile_image_url'] = $row['profile_image_url'] ?? null;
        $profile['updated_at']        = $row['updated_at'] ?? null;
    }

    // user_tokens
    $stmt2 = $db->prepare("
        SELECT scope, UNIX_TIMESTAMP(expires_at) AS exp_ts, updated_at
        FROM user_tokens
        WHERE twitch_id = :id
        LIMIT 1
    ");
    $stmt2->execute([':id' => $userId]);
    if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $tokenMeta['scope']        = (string)($row2['scope'] ?? '');
        $tokenMeta['expires_unix'] = (int)($row2['exp_ts'] ?? $tokenMeta['expires_unix']);
        $tokenMeta['updated_at']   = $row2['updated_at'] ?? null;
    }
}

// ---------- Live stats from Twitch (followers, subs, broadcaster, recent game, live/vod) ----------
$stats = [
    'followers'        => null,
    'followers_note'   => null,
    'subs_total'       => null,
    'subs_note'        => null,
    'broadcaster'      => '—',   // partner/affiliate/none/unknown
    'broadcaster_note' => null,
    'recent_game'      => '—',
    'recent_game_note' => null,

    // live / vod
    'live'             => false,
    'live_title'       => null,
    'live_viewers'     => null,
    'live_started_at'  => null,
    'vod_id'           => null,
    'vod_title'        => null,
    'vod_created_at'   => null,
    'vod_note'         => null,
];

$expiryText   = $tokenMeta['expires_unix'] ? format_expiry($tokenMeta['expires_unix']) : 'Unknown';
$scopeDisplay = $tokenMeta['scope'] !== '' ? h($tokenMeta['scope']) : '—';

$accessToken = $_SESSION['user']['twitch_access_token'] ?? '';
$clientId    = $config['twitch_client_id'] ?? '';

if ($userId !== '' && $accessToken !== '' && $clientId !== '') {
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Client-Id'     => $clientId,
    ];

    // (A) Broadcaster info (partner/affiliate/none) — robust version with fallback
    [$uInfo, $uErr] = http_get_json('https://api.twitch.tv/helix/users?id=' . urlencode($userId), $headers);
    if (!$uErr && !empty($uInfo['data'][0])) {
        $bt = (string)($uInfo['data'][0]['broadcaster_type'] ?? '');
        $stats['broadcaster'] = ($bt !== '') ? $bt : 'none';

        // Ensure we have the login (sanity in case DB didn’t store)
        if (empty($profile['login']) && !empty($uInfo['data'][0]['login'])) {
            $profile['login'] = (string)$uInfo['data'][0]['login'];
        }
    } else {
        // Fallback to "me" (user from token) if explicit id fails
        [$uMe, $uErr2] = http_get_json('https://api.twitch.tv/helix/users', $headers);
        if (!$uErr2 && !empty($uMe['data'][0])) {
            $bt = (string)($uMe['data'][0]['broadcaster_type'] ?? '');
            $stats['broadcaster'] = ($bt !== '') ? $bt : 'none';
            if (empty($profile['login']) && !empty($uMe['data'][0]['login'])) {
                $profile['login'] = (string)$uMe['data'][0]['login'];
            }
        } else {
            $stats['broadcaster'] = 'unknown';
            $stats['broadcaster_note'] =
                'Could not fetch user; check Client-Id matches token’s app and that this is a user access token.';
        }
    }

    // (B) Followers total (new endpoint)
    [$fRes, $fErr] = http_get_json(
        'https://api.twitch.tv/helix/channels/followers?broadcaster_id=' . urlencode($userId),
        $headers
    );
    if (!$fErr && isset($fRes['total'])) {
        $stats['followers'] = (int)$fRes['total'];
    } else {
        $stats['followers'] = null;
        $stats['followers_note'] = 'Could not fetch followers (ensure token is for this broadcaster or a moderator).';
    }

    // (C) Subscribers total (requires scope + affiliate/partner)
    [$sRes, $sErr] = http_get_json(
        'https://api.twitch.tv/helix/subscriptions?broadcaster_id=' . urlencode($userId),
        $headers
    );
    if (!$sErr) {
        if (isset($sRes['total'])) {
            $stats['subs_total'] = (int)$sRes['total'];
        } elseif (isset($sRes['data']) && is_array($sRes['data'])) {
            $stats['subs_total'] = count($sRes['data']); // page count fallback
        } else {
            $stats['subs_total'] = null;
        }
    } else {
        if (strpos($tokenMeta['scope'] ?? '', 'channel:read:subscriptions') === false) {
            $stats['subs_note'] = 'Grant scope: channel:read:subscriptions';
        } elseif (($stats['broadcaster'] ?? 'none') === 'none') {
            $stats['subs_note'] = 'Requires Affiliate or Partner status';
        } else {
            $stats['subs_note'] = 'Subscription info unavailable';
        }
    }

    // (D) Recent game played
    // 1) Try channel info (current category/game)
    [$chRes, $chErr] = http_get_json(
        'https://api.twitch.tv/helix/channels?broadcaster_id=' . urlencode($userId),
        $headers
    );
    $channelGameName = '';
    $channelGameId   = '';
    if (!$chErr && !empty($chRes['data'][0])) {
        $channelGameName = trim((string)($chRes['data'][0]['game_name'] ?? ''));
        $channelGameId   = trim((string)($chRes['data'][0]['game_id'] ?? ''));
    }
    if ($channelGameName !== '') {
        $stats['recent_game'] = $channelGameName; // current/last set category
    } else {
        // 2) Fallback: last VOD's game (latest archive)
        [$vResRG, $vErrRG] = http_get_json(
            'https://api.twitch.tv/helix/videos?user_id=' . urlencode($userId) . '&type=archive&first=1',
            $headers
        );
        if (!$vErrRG && !empty($vResRG['data'][0])) {
            $vodGameName = trim((string)($vResRG['data'][0]['game_name'] ?? ''));
            $vodGameId   = trim((string)($vResRG['data'][0]['game_id'] ?? ''));
            if ($vodGameName !== '') {
                $stats['recent_game'] = $vodGameName;
            } elseif ($vodGameId !== '') {
                [$gRes, $gErr] = http_get_json(
                    'https://api.twitch.tv/helix/games?id=' . urlencode($vodGameId),
                    $headers
                );
                if (!$gErr && !empty($gRes['data'][0]['name'])) {
                    $stats['recent_game'] = (string)$gRes['data'][0]['name'];
                } else {
                    $stats['recent_game_note'] = 'Could not resolve game name';
                }
            } else {
                $stats['recent_game_note'] = 'No game set on last VOD';
            }
        } else {
            $stats['recent_game_note'] = 'No recent VODs';
        }
    }

    // (E) Live status or latest VOD for embed
    // Live check
    [$stRes, $stErr] = http_get_json(
        'https://api.twitch.tv/helix/streams?user_id=' . urlencode($userId),
        $headers
    );
    if (!$stErr && !empty($stRes['data'][0]) && ($stRes['data'][0]['type'] ?? '') === 'live') {
        $stats['live']            = true;
        $stats['live_title']      = (string)($stRes['data'][0]['title'] ?? '');
        $stats['live_viewers']    = (int)($stRes['data'][0]['viewer_count'] ?? 0);
        $stats['live_started_at'] = (string)($stRes['data'][0]['started_at'] ?? '');
    } else {
        // Not live: fetch the latest VOD for embed
        [$vRes, $vErr] = http_get_json(
            'https://api.twitch.tv/helix/videos?user_id=' . urlencode($userId) . '&type=archive&first=1',
            $headers
        );
        if (!$vErr && !empty($vRes['data'][0])) {
            $stats['vod_id']         = (string)$vRes['data'][0]['id'];
            $stats['vod_title']      = (string)($vRes['data'][0]['title'] ?? '');
            $stats['vod_created_at'] = (string)($vRes['data'][0]['created_at'] ?? '');
        } else {
            $stats['vod_note'] = 'No recent VODs available';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard — PnKllr</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --bg: #0f1220; --card:#181c2f; --text:#e8ebff; --muted:#aab0d6; --accent:#7c8cff; --ok:#33d69f; }
        html,body { margin:0; padding:0; background:var(--bg); color:var(--text); font:16px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
        .wrap { max-width: 980px; margin: 40px auto; padding: 0 16px; }
        .header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
        .header h1 { margin:0; font-size: 24px; letter-spacing: 0.3px; }
        .card { background:var(--card); border-radius:16px; padding:20px; box-shadow: 0 6px 20px rgba(0,0,0,.25); }
        .grid { display:grid; grid-template-columns: 1fr; gap:16px; }
        @media(min-width: 800px){ .grid { grid-template-columns: 1fr 1fr; } }
        .row { display:flex; gap:10px; align-items:center; flex-wrap: wrap; }
        .avatar { width:80px; height:80px; border-radius:50%; background:#2a3150; object-fit:cover; }
        .meta dt { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.08em; }
        .meta dd { margin: 2px 0 14px; font-size: 15px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; background:#2a3150; color:#c9cff9; font-size:12px; }
        .pill { display:inline-block; padding:6px 10px; border-radius:999px; background:#25305a; color:#c9cff9; font-size:12px; margin: 3px 4px 0 0; }
        a.btn { color: var(--text); text-decoration: none; background: var(--accent); padding:10px 14px; border-radius: 10px; font-weight:600; }
        a.btn.secondary { background:#25305a; margin-right:8px; }
        .muted { color: var(--muted); }
        .tiny { font-size:12px; color: var(--muted); }
        .ok { color: var(--ok); }
        .sep { height:1px; background:#2a3150; margin:16px 0; }
        code.small { font-size: 12px; background: #0c0f1c; padding: 2px 6px; border-radius: 6px; }
        .kpi { display:flex; gap:14px; margin:8px 0 6px; flex-wrap: wrap; }
        .kpi .chip { background:#25305a; border-radius:10px; padding:6px 10px; font-size:13px; }
        /* Player */
        .player-card { margin-bottom:16px; }
        .player-wrap { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #000; border-radius: 12px; overflow: hidden; }
        .player { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .player-meta { display:flex; align-items:center; justify-content:space-between; margin-top:10px; }
        .title { font-weight:600; }
        .live-dot { width:10px; height:10px; border-radius:50%; background:#e03; display:inline-block; margin-right:6px; box-shadow: 0 0 8px rgba(255,0,60,.7); vertical-align: middle; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>Dashboard</h1>
        <div class="row">
            <?php if ($isAdmin): ?>
                <a class="btn secondary" href="/admin/">Admin Panel</a>
            <?php endif; ?>
            <a class="btn" href="/core/twitch_auth.php?action=logout">Logout</a>
        </div>
    </div>

    <!-- LIVE / VOD PLAYER -->
    <div class="card player-card">
        <?php if ($stats['live'] && !empty($profile['login'])): ?>
            <div class="player-wrap">
                <!-- If you also serve from www.pnkllr.net, add another parent=www.pnkllr.net -->
                <iframe
                    class="player"
                    src="https://player.twitch.tv/?channel=<?= urlencode($profile['login']) ?>&parent=pnkllr.net&muted=true&autoplay=false"
                    allowfullscreen
                    scrolling="no"
                    frameborder="0">
                </iframe>
            </div>
            <div class="player-meta">
                <div class="title"><span class="live-dot"></span><?= h($stats['live_title'] ?: 'Live') ?></div>
                <div class="tiny"><?= number_format((int)$stats['live_viewers']) ?> viewers • started <?= h($stats['live_started_at'] ?? '') ?></div>
            </div>
        <?php elseif (!empty($stats['vod_id'])): ?>
            <div class="player-wrap">
                <iframe
                    class="player"
                    src="https://player.twitch.tv/?video=<?= urlencode($stats['vod_id']) ?>&parent=pnkllr.net&muted=true&autoplay=false"
                    allowfullscreen
                    scrolling="no"
                    frameborder="0">
                </iframe>
            </div>
            <div class="player-meta">
                <div class="title"><?= h($stats['vod_title'] ?: 'Latest VOD') ?></div>
                <div class="tiny">Recorded <?= h($stats['vod_created_at'] ?? '') ?></div>
            </div>
        <?php else: ?>
            <div class="tiny">No live stream or recent VOD to display. <?= h($stats['vod_note'] ?? '') ?></div>
        <?php endif; ?>
        <div class="tiny">Recent game: <strong><?= h($stats['recent_game']) ?></strong>
            <?php if (!empty($stats['recent_game_note'])): ?>
                — <?= h($stats['recent_game_note']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <div class="row">
                <?php if (!empty($profile['profile_image_url'])): ?>
                    <img class="avatar" src="<?= h($profile['profile_image_url']) ?>" alt="Avatar">
                <?php else: ?>
                    <div class="avatar"></div>
                <?php endif; ?>
                <div>
                    <div class="badge">Twitch ID: <?= h($userId) ?></div>
                    <h2 style="margin:6px 0 2px; font-size:20px;"><?= h($profile['display_name'] ?: $profile['login'] ?: 'User') ?></h2>
                    <div class="muted">@<?= h($profile['login']) ?></div>
                </div>
            </div>

            <div class="sep"></div>

            <dl class="meta">
                <dt>Email</dt>
                <dd><?= $profile['email'] ? h($profile['email']) : '—' ?></dd>

                <dt>Channel status</dt>
                <dd>
                    <?= h($stats['broadcaster']) ?>
                    <?php if (!empty($stats['broadcaster_note'])): ?>
                        <span class="tiny"> — <?= h($stats['broadcaster_note']) ?></span>
                    <?php endif; ?>
                </dd>

                <dt>Token status</dt>
                <dd>
                    Expires in: <span class="<?= $expiryText === 'Expired' ? '' : 'ok' ?>"><?= h($expiryText) ?></span>
                    <?php if ($tokenMeta['expires_unix']): ?>
                        <span class="tiny"> (<?= date('Y-m-d H:i:s', $tokenMeta['expires_unix']) ?>)</span>
                    <?php endif; ?>
                </dd>

                <dt>Scopes</dt>
                <dd>
                    <?php if ($scopeDisplay !== '—'): ?>
                        <?php foreach (explode(' ', $tokenMeta['scope']) as $sc): if ($sc === '') continue; ?>
                            <span class="pill"><?= h($sc) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>

                <dt>Profile updated</dt>
                <dd><?= $profile['updated_at'] ? h($profile['updated_at']) : '—' ?></dd>

                <dt>Tokens updated</dt>
                <dd><?= $tokenMeta['updated_at'] ? h($tokenMeta['updated_at']) : '—' ?></dd>
            </dl>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Session &amp; Debug</h3>

            <div class="kpi">
                <div class="chip">Followers: <strong><?= $stats['followers'] !== null ? number_format($stats['followers']) : '—' ?></strong></div>
                <?php if (!empty($stats['followers_note'])): ?>
                    <div class="chip tiny"><?= h($stats['followers_note']) ?></div>
                <?php endif; ?>
                <div class="chip">Subscribers: <strong><?= $stats['subs_total'] !== null ? number_format($stats['subs_total']) : '—' ?></strong></div>
                <?php if (!empty($stats['subs_note'])): ?>
                    <div class="chip tiny"><?= h($stats['subs_note']) ?></div>
                <?php endif; ?>
            </div>

            <p class="tiny">We don’t display raw tokens here. Check the <code class="small">user_tokens</code> table if needed.</p>

            <p class="muted">Session user:
                <code class="small"><?= h(json_encode([
                    'id'                 => $_SESSION['user']['id'] ?? null,
                    'login'              => $_SESSION['user']['login'] ?? null,
                    'display_name'       => $_SESSION['user']['display_name'] ?? null,
                    'email'              => $_SESSION['user']['email'] ?? null,
                    'twitch_expires_at'  => $_SESSION['user']['twitch_expires_at'] ?? null,
                ], JSON_UNESCAPED_SLASHES)) ?></code>
            </p>

            <p class="tiny">SQL (manual check):<br>
                <code class="small">SELECT scope, expires_at FROM user_tokens WHERE twitch_id = <?= h($userId) ?>;</code>
            </p>
        </div>
    </div>
</div>
</body>
</html>
