<?php
// public_html/admin/index.php
declare(strict_types=1);

require_once __DIR__ . '/../_app_bootstrap.php';
require_once APP_PATH . '/auth/admin_guard.php'; // session + admin check

// ---------- helpers ----------
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set(string $type, string $msg): void { $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg]; }
function flash_get(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// CSRF
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];

// ---------- actions ----------
$action = $_POST['action'] ?? '';
if ($action !== '') {
    if (($_POST['csrf'] ?? '') !== $csrf) {
        http_response_code(400);
        flash_set('err', 'Invalid CSRF token');
        header('Location: ' . abs_url('/admin/index.php'));
        exit;
    }

    if ($action === 'refresh_one') {
        $tid = trim((string)($_POST['twitch_id'] ?? ''));
        if ($tid === '') {
            flash_set('err', 'Missing twitch_id');
        } else {
            // load current refresh token
            $stmt = $db->prepare("SELECT refresh_token FROM user_tokens WHERE twitch_id=:id LIMIT 1");
            $stmt->execute([':id'=>$tid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['refresh_token'])) {
                flash_set('err', "No refresh token stored for {$tid}");
            } else {
                // call Twitch
                [$json, $err] = http_post_form('https://id.twitch.tv/oauth2/token', [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $row['refresh_token'],
                    'client_id'     => $config['twitch_client_id'],
                    'client_secret' => $config['twitch_client_secret'],
                ]);

                if ($err) {
                    // mark status
                    $q = $db->prepare("
                        UPDATE user_tokens
                        SET last_refresh_at=UTC_TIMESTAMP(), last_refresh_status='http_error', last_error=:e, updated_at=NOW()
                        WHERE twitch_id=:id
                    "); $q->execute([':e'=>$err, ':id'=>$tid]);
                    flash_set('err', "HTTP error refreshing {$tid}: {$err}");
                } elseif (isset($json['status']) && $json['status'] >= 400) {
                    $msg = $json['message'] ?? 'unknown';
                    $status = (stripos($msg, 'invalid refresh token') !== false) ? 'invalid_refresh' : 'twitch_error';
                    if ($status === 'invalid_refresh') {
                        $db->prepare("UPDATE user_tokens SET refresh_token=NULL, updated_at=NOW() WHERE twitch_id=:id")
                           ->execute([':id'=>$tid]);
                    }
                    $q = $db->prepare("
                        UPDATE user_tokens
                        SET last_refresh_at=UTC_TIMESTAMP(), last_refresh_status=:st, last_error=:e, updated_at=NOW()
                        WHERE twitch_id=:id
                    "); $q->execute([':st'=>$status, ':e'=>$msg, ':id'=>$tid]);

                    flash_set('err', "Twitch error {$json['status']} for {$tid}: {$msg}");
                } elseif (empty($json['access_token'])) {
                    $q = $db->prepare("
                        UPDATE user_tokens
                        SET last_refresh_at=UTC_TIMESTAMP(), last_refresh_status='twitch_error', last_error='missing access_token', updated_at=NOW()
                        WHERE twitch_id=:id
                    "); $q->execute([':id'=>$tid]);
                    flash_set('err', "No access_token in response for {$tid}");
                } else {
                    $newAccess  = (string)$json['access_token'];
                    $newRefresh = isset($json['refresh_token']) ? (string)$json['refresh_token'] : (string)$row['refresh_token'];
                    $expiresIn  = (int)($json['expires_in'] ?? 3600);
                    $newExpiry  = time() + $expiresIn;

                    $q = $db->prepare("
                        UPDATE user_tokens
                        SET access_token=:at, refresh_token=:rt, expires_at=FROM_UNIXTIME(:exp),
                            last_refresh_at=UTC_TIMESTAMP(), last_refresh_status='ok', last_error=NULL, updated_at=NOW()
                        WHERE twitch_id=:id
                    ");
                    $q->execute([
                        ':at'=>$newAccess, ':rt'=>$newRefresh, ':exp'=>$newExpiry, ':id'=>$tid
                    ]);

                    flash_set('ok', "Refreshed {$tid} (expires in {$expiresIn}s)");
                }
            }
        }
        header('Location: ' . abs_url('/admin/index.php'));
        exit;
    }

    if ($action === 'refresh_expiring') {
        // refresh up to N expiring within next 3 hours
        $limit = 50; // prevent long requests
        $stmt = $db->query("
            SELECT twitch_id, refresh_token
            FROM user_tokens
            WHERE refresh_token IS NOT NULL
              AND (expires_at IS NULL OR expires_at < DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR))
            ORDER BY COALESCE(expires_at, '1970-01-01') ASC
            LIMIT {$limit}
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ok=0; $fail=0;

        foreach ($rows as $r) {
            $tid = (string)$r['twitch_id'];
            $rt  = (string)$r['refresh_token'];
            [$json, $err] = http_post_form('https://id.twitch.tv/oauth2/token', [
                'grant_type'=>'refresh_token','refresh_token'=>$rt,
                'client_id'=>$config['twitch_client_id'],'client_secret'=>$config['twitch_client_secret'],
            ]);

            if ($err || (isset($json['status']) && $json['status']>=400) || empty($json['access_token'])) {
                $status = $err ? 'http_error' : ((isset($json['status']) && stripos($json['message']??'','invalid refresh token')!==false) ? 'invalid_refresh' : 'twitch_error');
                $errMsg = $err ?: ($json['message'] ?? 'missing access_token');
                if ($status === 'invalid_refresh') {
                    $db->prepare("UPDATE user_tokens SET refresh_token=NULL, updated_at=NOW() WHERE twitch_id=:id")
                       ->execute([':id'=>$tid]);
                }
                $db->prepare("
                    UPDATE user_tokens
                    SET last_refresh_at=UTC_TIMESTAMP(), last_refresh_status=:st, last_error=:e, updated_at=NOW()
                    WHERE twitch_id=:id
                ")->execute([':st'=>$status, ':e'=>$errMsg, ':id'=>$tid]);
                $fail++;
            } else {
                $newAccess  = (string)$json['access_token'];
                $newRefresh = isset($json['refresh_token']) ? (string)$json['refresh_token'] : $rt;
                $expiresIn  = (int)($json['expires_in'] ?? 3600);
                $newExpiry  = time() + $expiresIn;

                $db->prepare("
                    UPDATE user_tokens
                    SET access_token=:at, refresh_token=:rt, expires_at=FROM_UNIXTIME(:exp),
                        last_refresh_at=UTC_TIMESTAMP(), last_refresh_status='ok', last_error=NULL, updated_at=NOW()
                    WHERE twitch_id=:id
                ")->execute([':at'=>$newAccess, ':rt'=>$newRefresh, ':exp'=>$newExpiry, ':id'=>$tid]);

                $ok++;
            }
            usleep(250000);
        }

        flash_set('ok', "Bulk refresh done: {$ok} OK, {$fail} failed (limit {$limit}). See cron log for details.");
        header('Location: ' . abs_url('/admin/index.php'));
        exit;
    }

    // Unknown action
    flash_set('err', 'Unknown action');
    header('Location: ' . abs_url('/admin/index.php'));
    exit;
}

// ---------- filters ----------
$filter = $_GET['filter'] ?? 'all';
$allowed = ['all','expiring','errors'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$where = '1=1';
if ($filter === 'expiring') {
    $where = "(t.expires_at IS NULL OR t.expires_at < DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR))";
} elseif ($filter === 'errors') {
    $where = "(t.last_refresh_status IS NULL OR t.last_refresh_status NOT IN ('ok'))";
}

$search = trim((string)($_GET['q'] ?? ''));
$params = [];
if ($search !== '') {
    $where .= " AND (u.login LIKE :q OR u.display_name LIKE :q OR u.twitch_id = :idq)";
    $params[':q'] = "%{$search}%";
    $params[':idq'] = $search;
}

// ---------- query data ----------
$sql = "
SELECT
  u.twitch_id, u.login, u.display_name,
  t.expires_at,
  t.last_refresh_status, t.last_refresh_at, t.last_error
FROM users u
JOIN user_tokens t ON t.twitch_id = u.twitch_id
WHERE {$where}
ORDER BY (t.expires_at IS NULL) DESC, t.expires_at ASC, u.login ASC
LIMIT 200
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$kpi = ['total'=>0,'expiring'=>0,'errors'=>0];
$kpi['total'] = (int)$db->query("SELECT COUNT(*) FROM user_tokens")->fetchColumn();
$kpi['expiring'] = (int)$db->query("SELECT COUNT(*) FROM user_tokens WHERE expires_at IS NULL OR expires_at < DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR)")->fetchColumn();
$kpi['errors'] = (int)$db->query("SELECT COUNT(*) FROM user_tokens WHERE last_refresh_status IS NULL OR last_refresh_status NOT IN ('ok')")->fetchColumn();

// tail the cron log (last ~200 lines)
$logTail = '';
$logPath = __DIR__ . '/../app/cron/refresh_tokens.log'; // wrong path on purpose?
// correct path:
$logPath = __DIR__ . '/../app/cron/refresh_tokens.log';
if (is_readable($logPath)) {
    $logTail = @file_get_contents($logPath);
    if ($logTail !== false) {
        $lines = explode("\n", $logTail);
        $logTail = implode("\n", array_slice($lines, -200));
    } else $logTail = '';
}
$flashes = flash_get();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin — Token Monitor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root { --bg:#0f1220; --card:#181c2f; --text:#e8ebff; --muted:#aab0d6; --accent:#7c8cff; --ok:#33d69f; --warn:#f4c542; --err:#ff5d6c; }
    html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Arial;}
    .wrap{max-width:1100px;margin:32px auto;padding:0 16px;}
    .header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .h1{font-size:22px;margin:0}
    .btn{background:var(--accent);color:#fff;padding:8px 12px;border-radius:10px;text-decoration:none;font-weight:600}
    .btn.secondary{background:#25305a}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .card{background:var(--card);border-radius:14px;padding:16px;margin:10px 0;box-shadow:0 6px 20px rgba(0,0,0,.25)}
    .kpis{display:flex;gap:12px;flex-wrap:wrap}
    .kpi{background:#25305a;border-radius:10px;padding:8px 12px}
    .muted{color:var(--muted)}
    .table{width:100%;border-collapse:collapse;margin-top:10px}
    .table th,.table td{padding:10px;border-bottom:1px solid #2a3150;text-align:left}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px}
    .ok{background:#1e3a2f;color:#9ff3c8}
    .warn{background:#3b351c;color:#ffe082}
    .err{background:#402029;color:#ff9aa5}
    .tiny{font-size:12px}
    .right{text-align:right}
    .flash{padding:10px;border-radius:10px;margin:8px 0}
    .flash.ok{background:#1e3a2f;color:#9ff3c8}
    .flash.err{background:#402029;color:#ff9aa5}
    .controls input[type=text]{padding:8px;border-radius:8px;border:1px solid #2a3150;background:#0c0f1c;color:#e8ebff}
    pre.log{background:#0c0f1c;border-radius:10px;padding:12px;overflow:auto;max-height:260px}
    form.inline{display:inline}
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1 class="h1">Token Admin</h1>
        <div class="row">
            <a class="btn secondary" href="<?= h(abs_url('/dashboard/index.php')) ?>">Back to Dashboard</a>
            <form class="inline" method="post" action="<?= h(abs_url('/admin/index.php')) ?>">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="refresh_expiring">
                <button class="btn" type="submit">Refresh Expiring Now</button>
            </form>
        </div>
    </div>

    <?php foreach ($flashes as $f): ?>
        <div class="flash <?= h($f['t']) ?>"><?= h($f['m']) ?></div>
    <?php endforeach; ?>

    <div class="card">
        <div class="kpis">
            <div class="kpi">Total: <strong><?= number_format($kpi['total']) ?></strong></div>
            <div class="kpi">Expiring &lt; 3h: <strong><?= number_format($kpi['expiring']) ?></strong></div>
            <div class="kpi">Errors: <strong><?= number_format($kpi['errors']) ?></strong></div>
        </div>
        <form class="row controls" method="get" action="<?= h(abs_url('/admin/index.php')) ?>">
            <select name="filter">
                <option value="all"      <?= $filter==='all'?'selected':'' ?>>All</option>
                <option value="expiring" <?= $filter==='expiring'?'selected':'' ?>>Expiring &lt; 3h</option>
                <option value="errors"   <?= $filter==='errors'?'selected':'' ?>>Errors</option>
            </select>
            <input type="text" name="q" placeholder="Search login/display/Twitch ID" value="<?= h($search) ?>">
            <button class="btn secondary" type="submit">Filter</button>
            <a class="btn secondary" href="<?= h(abs_url('/admin/index.php')) ?>">Clear</a>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>Twitch ID</th>
                    <th>Login</th>
                    <th>Display</th>
                    <th>Expires At (UTC)</th>
                    <th>Status</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="muted">No rows</td></tr>
                <?php else: foreach ($rows as $r):
                    $st = $r['last_refresh_status'] ?? null;
                    $cls = $st === 'ok' ? 'ok' : (($st===null || $st==='') ? 'warn' : 'err');
                ?>
                    <tr>
                        <td><?= h($r['twitch_id']) ?></td>
                        <td><?= h($r['login']) ?></td>
                        <td><?= h($r['display_name']) ?></td>
                        <td><?= $r['expires_at'] ? h($r['expires_at']) : '<span class="muted">NULL</span>' ?></td>
                        <td>
                            <span class="pill <?= $cls ?>"><?= h($st ?? '—') ?></span>
                            <?php if (!empty($r['last_refresh_at'])): ?>
                                <div class="tiny muted">at <?= h($r['last_refresh_at']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['last_error'])): ?>
                                <div class="tiny muted">err: <?= h(mb_strimwidth((string)$r['last_error'], 0, 140, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            <form class="inline" method="post" action="<?= h(abs_url('/admin/index.php')) ?>">
                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="refresh_one">
                                <input type="hidden" name="twitch_id" value="<?= h($r['twitch_id']) ?>">
                                <button class="btn" type="submit">Refresh Now</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin:0 0 6px 0;">Cron Log (last 200 lines)</h3>
        <pre class="log"><?= h($logTail) ?></pre>
    </div>
</div>
</body>
</html>
