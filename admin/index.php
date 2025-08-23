<?php
// admin/index.php (modern UI)
require_once __DIR__ . '/../core/bootstrap.php';
require_admin();
require_once BASE_PATH . '/ui/layout.php';

$pdo = db();

// Load data
$users  = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$tokens = $pdo->query("
  SELECT t.*, u.twitch_login, u.twitch_display, u.avatar_url
  FROM oauth_tokens t
  JOIN users u ON u.id = t.user_id
  WHERE t.provider='twitch'
  ORDER BY (t.expires_at IS NULL) ASC, t.expires_at ASC, t.id DESC
")->fetchAll();

// Metrics
$totalUsers   = count($users);
$totalTokens  = count($tokens);
$expSoon      = 0; // < 1h
$missingClips = 0;

$now = time();
foreach ($tokens as $t) {
  $expAt = $t['expires_at'] ? strtotime($t['expires_at']) : null;
  if ($expAt && ($expAt - $now) <= 3600) $expSoon++;   // 3600s = 1 hour
  $scopes = ' ' . (string)($t['scope'] ?? '') . ' ';
  if (strpos($scopes, ' clips:edit ') === false) $missingClips++;
}

function badgeClassForExpiry(?string $expiresAt): string {
  if (!$expiresAt) return 'warn';
  $left = strtotime($expiresAt) - time();
  if ($left <= 0)        return 'bad';   // expired
  if ($left < 3600)      return 'warn';  // < 1 hour
  return 'ok';
}
function badgeText(?string $expiresAt): string {
  if (!$expiresAt) return 'Unknown';

  $left = strtotime($expiresAt) - time();
  if ($left <= 0) return 'Expired';

  // > 3 hours: show absolute time
  if ($left > 3 * 3600) {
    return 'Expires ' . date('H:iA', strtotime($expiresAt)); // e.g., "Expires 14:25"
  }

  // > 2 hours (≤ 3h): show "Expiring in Hh Mm"
  if ($left > 2 * 3600) {
    $h = (int) floor($left / 3600);
    $m = (int) floor(($left % 3600) / 60);
    return $m ? "Expiring in {$h}h {$m}m" : "Expiring in {$h}h";
  }

  // 0–2 hours: minutes-focused
  $h = (int) floor($left / 3600);
  $m = (int) floor(($left % 3600) / 60);
  if ($h >= 1) {
    return $m ? "Expiring in {$h}h {$m}m" : "Expiring in {$h}h";
  }
  $m = max(1, $m);
  return "Expiring in {$m}m";
}

ob_start(); ?>

<style>
  /* --- Admin modern styles (scoped) --- */
  .kpis{display:grid;gap:14px;grid-template-columns:repeat(12,1fr)}
  .kpis .k{grid-column:span 3;background:#14141a;border:1px solid #292932;border-radius:16px;padding:14px}
  .k .L{color:#9aa4b2;font-size:.85rem}
  .k .V{font-weight:800;font-size:1.6rem}
  @media(max-width:900px){.kpis .k{grid-column:span 6}}
  @media(max-width:580px){.kpis .k{grid-column:span 12}}

  .section{background:#17171c;border:1px solid #292932;border-radius:16px;padding:16px;margin-top:14px}
  .sectionTitle{font-weight:700;font-size:1rem;margin:0 0 10px}

  /* Search */
  .toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:8px}
  .search{flex:1;min-width:220px}
  .search input{width:100%;padding:10px 12px;border-radius:12px;border:1px solid #2b2b31;background:#0f0f14;color:#fff}

  /* User cards */
  .grid{display:grid;gap:14px;grid-template-columns:repeat(12,1fr)}
  .uCard{grid-column:span 3;background:#14141a;border:1px solid #292932;border-radius:16px;padding:14px;display:flex;gap:12px}
  .uAv{width:44px;height:44px;border-radius:50%;border:1px solid #2a2a31;object-fit:cover}
  .uMain{display:flex;flex-direction:column;gap:4px;min-width:0}
  .uName{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .uLogin{color:#9aa4b2;font-size:.85rem}
  .uMeta{color:#9aa4b2;font-size:.8rem}
  @media(max-width:1100px){.uCard{grid-column:span 4}}
  @media(max-width:900px){.uCard{grid-column:span 6}}
  @media(max-width:580px){.uCard{grid-column:span 12}}

  /* Token cards */
  .tList{display:grid;gap:14px;grid-template-columns:repeat(12,1fr)}
  .tCard{grid-column:span 4;background:#14141a;border:1px solid #292932;border-radius:16px;padding:14px;display:flex;flex-direction:column;gap:10px}
  .tTop{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .tWho{display:flex;align-items:center;gap:10px;min-width:0}
  .tAv{width:36px;height:36px;border-radius:50%;border:1px solid #2a2a31;object-fit:cover}
  .tName{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .tLogin{color:#9aa4b2;font-size:.85rem}
  .badges{display:flex;gap:8px;flex-wrap:wrap}
  .badge{display:inline-flex;gap:6px;padding:6px 10px;border-radius:999px;font-size:.8rem;background:#0b1220;border:1px solid #1f2937}
  .badge.ok{color:#22c55e;border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.08)}
  .badge.warn{color:#eab308;border-color:rgba(234,179,8,.3);background:rgba(234,179,8,.08)}
  .badge.bad{color:#ef4444;border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.08)}
  .tFoot{display:flex;gap:8px;flex-wrap:wrap}
  .btn{display:inline-flex;gap:8px;padding:10px 12px;border-radius:12px;background:#0b1220;border:1px solid #1f2937;color:#fff;text-decoration:none}
  .btn.destructive{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.08)}
  .btn:hover{filter:brightness(1.08)}
  .scopeWrap{background:#0f0f14;border:1px dashed #2a2a31;border-radius:10px;padding:8px}
  .scopeWrap details{cursor:pointer}
  .scopeWrap summary{color:#9aa4b2}
  .scopes{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .scope{font-size:.75rem;background:#0b0f17;border:1px solid #1f2937;border-radius:999px;padding:4px 8px}
  @media(max-width:1200px){.tCard{grid-column:span 6}}
  @media(max-width:700px){.tCard{grid-column:span 12}}
</style>

<div class="kpis">
  <div class="k"><div class="L">Users</div><div class="V"><?= (int)$totalUsers ?></div></div>
  <div class="k"><div class="L">Tokens</div><div class="V"><?= (int)$totalTokens ?></div></div>
  <div class="k"><div class="L">Expiring &lt; 1h</div><div class="V"><?= (int)$expSoon ?></div></div>
  <div class="k"><div class="L">Missing clips:edit</div><div class="V"><?= (int)$missingClips ?></div></div>
</div>

<div class="section">
  <div class="toolbar">
    <div class="sectionTitle">Users</div>
    <div class="search"><input id="userSearch" type="text" placeholder="Search users by display/login/email…"></div>
  </div>

  <div id="users" class="grid">
    <?php foreach ($users as $u): ?>
      <article class="uCard" data-q="<?= htmlspecialchars(strtolower(($u['twitch_display'] ?? '').' '.$u['twitch_login'].' '.($u['email'] ?? ''))) ?>">
        <img class="uAv" src="<?= htmlspecialchars($u['avatar_url'] ?: 'https://static-cdn.jtvnw.net/jtv_user_pictures/x.png') ?>" alt="">
        <div class="uMain">
          <div class="uName"><?= htmlspecialchars($u['twitch_display'] ?: $u['twitch_login']) ?></div>
          <div class="uLogin">@<?= htmlspecialchars($u['twitch_login']) ?></div>
          <div class="uMeta">Joined: <?= htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))) ?></div>
        </div>
      </article>
    <?php endforeach; if (!$users): ?>
      <div class="uMeta">No users yet.</div>
    <?php endif; ?>
  </div>
</div>

<div class="section">
  <div class="toolbar">
    <div class="sectionTitle">OAuth Tokens</div>
    <div class="search"><input id="tokenSearch" type="text" placeholder="Filter tokens by user/scope…"></div>
  </div>

  <div id="tokens" class="tList">
    <?php foreach ($tokens as $t):
      $cls = badgeClassForExpiry($t['expires_at']);
      $scopes = preg_split('/\s+/', trim($t['scope'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
      $scopePreview = $scopes ? implode(', ', array_slice($scopes, 0, 3)) . (count($scopes) > 3 ? '…' : '') : 'none';
      $q = strtolower(($t['twitch_display'] ?? '').' '.$t['twitch_login'].' '.($t['scope'] ?? ''));
    ?>
      <article class="tCard" data-q="<?= htmlspecialchars($q) ?>">
        <div class="tTop">
          <div class="tWho">
            <img class="tAv" src="<?= htmlspecialchars($t['avatar_url'] ?: 'https://static-cdn.jtvnw.net/jtv_user_pictures/x.png') ?>" alt="">
            <div>
              <div class="tName"><?= htmlspecialchars($t['twitch_display'] ?? $t['twitch_login']) ?></div>
              <div class="tLogin">@<?= htmlspecialchars($t['twitch_login']) ?></div>
            </div>
          </div>
          <div class="badges">
            <span class="badge <?= $cls ?>">
              <?= htmlspecialchars(badgeText($t['expires_at'])) ?>
            </span>
          </div>
        </div>

        <div class="scopeWrap">
          <details>
            <summary>Scopes</summary>
            <?php if ($scopes): ?>
              <div class="scopes">
                <?php foreach ($scopes as $s): ?><span class="scope"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="uMeta">No scopes stored.</div>
            <?php endif; ?>
          </details>
        </div>

        <div class="tFoot">
          <form method="post" action="/admin/actions.php">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="btn" name="action" value="refresh" type="submit">Refresh</button>
          </form>
          <form method="post" action="/admin/actions.php" onsubmit="return confirm('Delete token for @<?= htmlspecialchars($t['twitch_login']) ?>?')">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="btn destructive" name="action" value="delete" type="submit">Delete</button>
          </form>
        </div>
      </article>
    <?php endforeach; if (!$tokens): ?>
      <div class="uMeta">No tokens saved.</div>
    <?php endif; ?>
  </div>
</div>

<script>
  const f = (id) => {
    const inp = document.getElementById(id+'Search');
    const list = document.getElementById(id==='user'?'users':'tokens');
    if (!inp || !list) return;
    inp.addEventListener('input', () => {
      const q = inp.value.trim().toLowerCase();
      list.querySelectorAll('[data-q]').forEach(card => {
        const hit = card.getAttribute('data-q').includes(q);
        card.style.display = hit ? '' : 'none';
      });
    });
  };
  f('user'); f('token');
</script>

<?php
$content = ob_get_clean();
render_page('Admin', $content);
