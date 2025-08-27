<?php
// expects: $users, $tokens, $totalUsers, $totalTokens, $expSoon, $missingClips, $title

function badgeClassForExpiry(?string $expiresAt): string
{
  if (!$expiresAt) return 'warn';
  $left = strtotime($expiresAt) - time();
  if ($left <= 0)   return 'bad';   // expired
  if ($left < 3600) return 'warn';  // < 1 hour
  return 'ok';
}
function badgeText(?string $expiresAt): string
{
  if (!$expiresAt) return 'Unknown';
  $left = strtotime($expiresAt) - time();
  if ($left <= 0) return 'Expired';

  if ($left > 3 * 3600) {
    return 'Expires ' . date('H:iA', strtotime($expiresAt));
  }
  $h = (int) floor($left / 3600);
  $m = (int) floor(($left % 3600) / 60);
  if ($left > 2 * 3600) return $m ? "Expiring in {$h}h {$m}m" : "Expiring in {$h}h";
  if ($h >= 1)         return $m ? "Expiring in {$h}h {$m}m" : "Expiring in {$h}h";
  $m = max(1, $m);
  return "Expiring in {$m}m";
}
?>

<!-- KPIs -->
<div class="admin-kpis">
  <div class="kpi">
    <div class="kpi-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4 0-8 2-8 6v2h16v-2c0-4-4-6-8-6Z"/></svg>
    </div>
    <div class="kpi-meta">
      <div class="kpi-label">Users</div>
      <div class="kpi-value"><?= (int)$totalUsers ?></div>
    </div>
  </div>

  <div class="kpi">
    <div class="kpi-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M17 3H7a2 2 0 0 0-2 2v14l7-3 7 3V5a2 2 0 0 0-2-2Z"/></svg>
    </div>
    <div class="kpi-meta">
      <div class="kpi-label">Tokens</div>
      <div class="kpi-value"><?= (int)$totalTokens ?></div>
    </div>
  </div>

  <div class="kpi">
    <div class="kpi-icon warn" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M1 21h22L12 2 1 21zm12-3h-2v2h2v-2zm0-6h-2v5h2v-5z"/></svg>
    </div>
    <div class="kpi-meta">
      <div class="kpi-label">Expiring &lt; 1h</div>
      <div class="kpi-value"><?= (int)$expSoon ?></div>
    </div>
  </div>

  <div class="kpi">
    <div class="kpi-icon bad" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M12 22a10 10 0 1 1 10-10 10.011 10.011 0 0 1-10 10Zm1-9v-6h-2v8h6v-2Z"/></svg>
    </div>
    <div class="kpi-meta">
      <div class="kpi-label">Missing clips:edit</div>
      <div class="kpi-value"><?= (int)$missingClips ?></div>
    </div>
  </div>
</div>

<!-- Users -->
<div class="section">
  <div class="toolbar">
    <div class="sectionTitle">Users</div>
    <div class="search">
      <input id="userSearch" class="input" type="text" placeholder="Search users by display/login/email…"
             aria-label="Search users">
    </div>
  </div>

  <div id="users" class="user-grid">
    <?php foreach ($users as $u):
      $isBanned = !empty($u['banned_at']);
      $q = strtolower(trim(
        ($u['twitch_display'] ?? '') . ' ' .
        ($u['twitch_login']   ?? '') . ' ' .
        ($u['email']          ?? '')
      ));
    ?>
      <article class="user-card<?= $isBanned ? ' banned' : '' ?>" data-q="<?= htmlspecialchars($q) ?>">
        <div class="user-main">
          <img class="uAv" src="<?= htmlspecialchars($u['avatar_url'] ?: 'https://static-cdn.jtvnw.net/jtv_user_pictures/x.png') ?>" alt="">
          <div class="uInfo">
            <div class="uName">
              <?= htmlspecialchars($u['twitch_display'] ?: $u['twitch_login']) ?>
              <?php if ($isBanned): ?><span class="badge bad">Banned</span><?php endif; ?>
            </div>
            <div class="uLogin">@<?= htmlspecialchars($u['twitch_login']) ?></div>
            <div class="uMeta tiny">Joined <?= htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))) ?></div>
          </div>
        </div>

        <div class="uActions">
          <form method="post" action="<?= htmlspecialchars(base_url('/dashboard/admin/user.toggle-ban.php')) ?>">
            <?= CSRF::input() ?>
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="action" value="<?= $isBanned ? 'unban' : 'ban' ?>">
            <button class="btn <?= $isBanned ? '' : 'destructive' ?>" type="submit">
              <?= $isBanned ? 'Unban' : 'Ban' ?>
            </button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$users): ?>
      <div class="uMeta">No users yet.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Tokens -->
<div class="section">
  <div class="toolbar">
    <div class="sectionTitle">OAuth Tokens</div>
    <div class="search">
      <input id="tokenSearch" class="input" type="text" placeholder="Filter tokens by user/scope…"
             aria-label="Filter tokens">
    </div>
  </div>

  <div id="tokens" class="token-list">
    <?php foreach ($tokens as $t):
      $cls = badgeClassForExpiry($t['expires_at'] ?? null);
      $scopeStr = trim((string)($t['scopes'] ?? ($t['scope'] ?? '')));
      $scopes = $scopeStr !== '' ? preg_split('/\s+/', $scopeStr, -1, PREG_SPLIT_NO_EMPTY) : [];
      $q = strtolower(
        ($t['twitch_display'] ?? '') . ' ' .
        ($t['twitch_login']   ?? '') . ' ' .
        $scopeStr
      );
    ?>
      <article class="token-card" data-q="<?= htmlspecialchars($q) ?>">
        <div class="tTop">
          <div class="tWho">
            <img class="tAv" src="<?= htmlspecialchars($t['avatar_url'] ?: 'https://static-cdn.jtvnw.net/jtv_user_pictures/x.png') ?>" alt="">
            <div class="tMeta">
              <div class="tName"><?= htmlspecialchars($t['twitch_display'] ?? $t['twitch_login'] ?? 'Unknown') ?></div>
              <?php if (!empty($t['twitch_login'])): ?>
                <div class="tLogin tiny">@<?= htmlspecialchars($t['twitch_login']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="badges">
            <span class="badge <?= $cls ?>"><?= htmlspecialchars(badgeText($t['expires_at'] ?? null)) ?></span>
          </div>
        </div>

        <details class="scopeWrap">
          <summary>Scopes</summary>
          <?php if ($scopes): ?>
            <div class="scopes">
              <?php foreach ($scopes as $s): ?><span class="scope"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="uMeta">No scopes stored.</div>
          <?php endif; ?>
        </details>

        <div class="tFoot">
          <form method="post" action="<?= htmlspecialchars(base_url('/dashboard/admin/token.refresh.php')) ?>">
            <?= CSRF::input() ?>
            <input type="hidden" name="user_id" value="<?= (int)$t['user_id'] ?>">
            <button class="btn" type="submit">Refresh</button>
          </form>

          <!-- inline JS removed for CSP; use data-confirm -->
          <form method="post"
                action="<?= htmlspecialchars(base_url('/dashboard/admin/token.delete.php')) ?>"
                data-confirm="Delete token for @<?= htmlspecialchars($t['twitch_login'] ?? '') ?>?">
            <?= CSRF::input() ?>
            <input type="hidden" name="user_id" value="<?= (int)$t['user_id'] ?>">
            <button class="btn destructive" type="submit">Delete</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$tokens): ?>
      <div class="uMeta">No tokens saved.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Styles -->
<style>
/* ===== KPIs ===== */
.admin-kpis{
  display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; margin-bottom:14px;
}
.kpi{
  display:flex; gap:10px; align-items:center;
  border:1px solid var(--border,#2b2b2b); border-radius:14px; padding:12px;
  background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(0,0,0,.02));
  box-shadow:0 0 0 1px rgba(255,255,255,.03) inset, 0 8px 16px rgba(0,0,0,.15);
}
.kpi-icon{
  width:36px; height:36px; border-radius:10px; display:grid; place-items:center;
  background:linear-gradient(135deg, rgba(127,86,217,.25), rgba(37,99,235,.25));
  border:1px solid rgba(255,255,255,.06);
}
.kpi-icon.warn{ background:linear-gradient(135deg, rgba(200,140,46,.25), rgba(255,184,108,.2)); }
.kpi-icon.bad{  background:linear-gradient(135deg, rgba(200,46,46,.25), rgba(255,120,120,.2)); }
.kpi-meta{display:flex; flex-direction:column; gap:2px}
.kpi-label{font-size:12px; opacity:.75}
.kpi-value{font-weight:800; font-size:18px}

/* ===== toolbars ===== */
.section{margin-top:18px}
.toolbar{display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px}
.search .input{padding:8px 10px; border-radius:8px; border:1px solid var(--border,#2b2b2b); background:rgba(255,255,255,.02); color:inherit}

/* ===== Users ===== */
.user-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px}
.user-card{
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  border:1px solid var(--border,#2b2b2b); border-radius:14px; padding:12px;
  background:linear-gradient(180deg,rgba(255,255,255,.015),rgba(0,0,0,.02));
}
.user-card.banned{filter:grayscale(.3) opacity(.8)}
.user-main{display:flex; gap:10px; align-items:center}
.uAv{width:44px; height:44px; border-radius:50%; object-fit:cover; border:1px solid rgba(255,255,255,.06)}
.uInfo{min-width:0}
.uName{font-weight:700; display:flex; gap:8px; align-items:center}
.uLogin{opacity:.8; font-size:12px}
.uMeta{opacity:.8; font-size:12px}
.tiny{font-size:11px; opacity:.75}
.uActions{display:flex; gap:8px}

/* ===== Tokens ===== */
.token-list{display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:12px}
.token-card{
  border:1px solid var(--border,#2b2b2b); border-radius:14px; padding:12px;
  background:linear-gradient(180deg,rgba(255,255,255,.015),rgba(0,0,0,.02));
}
.tTop{display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; gap:8px}
.tWho{display:flex; gap:10px; align-items:center}
.tAv{width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid rgba(255,255,255,.06)}
.tMeta{display:flex; flex-direction:column; gap:2px}
.tName{font-weight:700}
.tLogin{opacity:.75}
.badges .badge{font-size:11px; padding:3px 8px; border-radius:999px; border:1px solid var(--border,#2b2b2b)}
.badge.ok{color:#8be28f; border-color:#315e38; background:rgba(40,120,50,.12)}
.badge.warn{color:#ffd78e; border-color:#80692b; background:rgba(200,140,46,.12)}
.badge.bad{color:#ff9ea0; border-color:#7d2b2b; background:rgba(200,46,46,.12)}

.scopeWrap summary{cursor:pointer; font-weight:600; margin:6px 0}
.scopes{display:flex; flex-wrap:wrap; gap:6px}
.scope{font-size:11px; padding:4px 8px; border-radius:999px; border:1px solid var(--border,#2b2b2b); background:rgba(255,255,255,.02)}

.tFoot{margin-top:10px; display:flex; gap:8px; flex-wrap:wrap}

/* ===== buttons / inputs (reuse your styles if you have them) ===== */
.btn{display:inline-flex; align-items:center; gap:6px; padding:8px 10px; border-radius:8px; border:1px solid var(--border,#2b2b2b); background:rgba(255,255,255,.02); color:inherit; cursor:pointer}
.btn:hover{background:rgba(255,255,255,.06)}
.btn.destructive{border-color:#7d2b2b; color:#ff9ea0; background:rgba(200,46,46,.08)}
.badge{display:inline-flex; align-items:center; gap:6px; padding:3px 8px; border-radius:999px; border:1px solid var(--border,#2b2b2b); font-size:11px; white-space:nowrap}
</style>
