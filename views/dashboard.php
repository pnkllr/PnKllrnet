<?php
// views/dashboard.php
// Expected (from DashboardController::index()):
// $me => ['id','twitch_user_id','display_name','email','avatar_url']
// $tokens => array of ['label','scopes','expires_at']
// $live => ['is_live'=>bool,'title'=>string,'game_name'=>string|null,'started_at'=>string|null]
// $vod  => ['id'=>string,'url'=>string] OR null
// $stats => ['followers_total'=>int|null,'subs_total'=>int|null,'broadcaster'=>string|null]
// $scopes => array of granted scopes on default token (or [])
// $channel_login => channel login for Twitch embed (lowercase) or ''

$followersTotal = $stats['followers_total'] ?? null;
$subsTotal      = $stats['subs_total'] ?? null;
$acctType       = $stats['broadcaster'] ?? 'none';
$channelLogin   = $channel_login ?? '';

$accessToken = null;
$expiresAt   = null;
if (!empty($tokens)) {
  // find default token if present
  foreach ($tokens as $t) {
    if (($t['label'] ?? '') === 'default') {
      $expiresAt = $t['expires_at'] ?? null;
      // we do NOT print the raw token here for safety; the controller can inject it if needed.
      break;
    }
  }
}

// allow controller to pass the actual bearer (masked in UI)
if (isset($token_bearer) && is_string($token_bearer) && $token_bearer !== '') {
  $accessToken = $token_bearer;
}

$base = rtrim(\Config::BASE_URL ?? '/', '/');

// tools config (extend as you add features)
$tools = [
  [
    'name' => '🎬 Clip It',
    'desc' => 'Create a 30s clip from your current live stream. Returns JSON by default; add &format=text for just the URL.',
    'url'  => $base . '/twitch/tool/clipit.php?channel=' . urlencode($me['twitch_user_id'] ?? ''),
    'method' => 'GET',
    'required_scopes' => ['clips:edit'],
  ],
];

$normalize = function(array $arr) {
  $x = array_map('strval', $arr);
  sort($x);
  return array_values(array_unique($x));
};
$hasAll = function(array $need, array $have) use ($normalize) {
  $n = $normalize($need); $h = $normalize($have);
  return !array_diff($n, $h);
};

// Desired scopes UI (if your app stores “desired” in DB, pass it in; else fall back to granted)
$desiredScopes = isset($desiredScopes) && is_array($desiredScopes) ? $desiredScopes : ($scopes ?? []);
$desiredScopes = $normalize($desiredScopes);

// helpers
$mask = function(string $t): string {
  if ($t === '') return '';
  $first = substr($t, 0, 4);
  $last  = substr($t, -4);
  return $first . str_repeat('•', max(0, strlen($t) - 8)) . $last;
};
$fmt = function (?string $s): string { return $s ? date('Y-m-d H:i', strtotime($s)) : '—'; };
?>
<!-- PROFILE / KPIs -->
<div class="card">
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <?php if (!empty($me['avatar_url'])): ?>
      <img class="avatar" src="<?= htmlspecialchars($me['avatar_url']) ?>" alt="">
    <?php endif; ?>
    <div>
      <div class="sectionTitle" style="margin:0"><?= htmlspecialchars($me['display_name'] ?? 'Unknown') ?></div>
      <?php if (!empty($me['email'])): ?>
        <div style="color:var(--muted)"><?= htmlspecialchars($me['email']) ?></div>
      <?php endif; ?>
      <?php if (!empty($me['twitch_user_id'])): ?>
        <div style="color:var(--muted)">Twitch ID: <?= htmlspecialchars($me['twitch_user_id']) ?></div>
      <?php endif; ?>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <span class="badge"><?= htmlspecialchars($acctType ?: 'none') ?></span>
      <span class="badge"><?= 'Followers: ' . ($followersTotal !== null ? number_format((int)$followersTotal) : '—') ?></span>
      <span class="badge"><?= 'Subs: ' . ($subsTotal !== null ? number_format((int)$subsTotal) : '—') ?></span>
    </div>
  </div>
</div>

<!-- TOKEN PANEL -->
<div class="card">
  <div class="sectionTitle">Access token</div>

  <?php if (empty($token_bearer)): ?>
    <p class="muted">No token yet. Use “Permissions” below to select scopes then click “Grant now”.</p>
  <?php else: ?>
    <?php
      $mask = function(string $t): string {
        if ($t === '') return '';
        $first = substr($t,0,4); $last = substr($t,-4);
        return $first . str_repeat('•', max(0, strlen($t) - 8)) . $last;
      };
      // try to find expiry from $tokens (newest first)
      $expiresAt = null;
      if (!empty($tokens)) {
        $expiresAt = $tokens[0]['expires_at'] ?? null;
      }
      $fmt = function (?string $s): string { return $s ? date('Y-m-d H:i', strtotime($s)) : '—'; };
    ?>
    <div class="token-line">
      <code id="tok-value"
            class="token-code"
            data-token="<?= htmlspecialchars($token_bearer) ?>"
            data-visible="false"><?= htmlspecialchars($mask($token_bearer)) ?></code>

      <div class="token-actions">
        <button type="button" class="btn ghost" id="tok-toggle">Show</button>
        <button type="button" class="btn" id="tok-copy">Copy</button>
        <button type="button" class="btn danger" id="tok-delete">Delete</button>
      </div>
    </div>

    <div class="token-meta-row">
      <span class="subtle">Expires: <strong><?= htmlspecialchars($fmt($expiresAt)) ?></strong></span>
    </div>
  <?php endif; ?>
</div>

<style>
/* Matches your Tools URL look/feel */
.token-line {
  display:flex; gap:10px; align-items:center; justify-content:space-between;
  margin-top:8px; flex-wrap:wrap;
}
.token-code {
  display:block; flex:1 1 560px; min-width:260px;
  padding:10px 12px; border-radius:10px;
  background:#111418; border:1px solid #23252b;
  color:#e7e7e9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  font-size:.95rem;
}
.token-actions { display:flex; gap:8px; align-items:center; }
.token-meta-row { margin-top:8px; color:var(--muted); display:flex; gap:8px; flex-wrap:wrap; }
.btn.danger { background:#3a1214; border:1px solid #5c2225; }
.btn.danger:hover { background:#4a1518; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const v = document.getElementById('tok-value');
  const btnShow  = document.getElementById('tok-toggle');
  const btnCopy  = document.getElementById('tok-copy');
  const btnDel   = document.getElementById('tok-delete');

  function maskToken(token) {
    if (!token) return '';
    const first = token.slice(0,4);
    const last  = token.slice(-4);
    return first + '•'.repeat(Math.max(0, token.length - 8)) + last;
  }

  if (v && btnShow) {
    btnShow.addEventListener('click', () => {
      const visible = v.dataset.visible === 'true';
      v.textContent = visible ? maskToken(v.dataset.token) : v.dataset.token;
      v.dataset.visible = String(!visible);
      btnShow.textContent = visible ? 'Show' : 'Hide';
    });
  }

  if (v && btnCopy) {
    btnCopy.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(v.dataset.token || '');
        btnCopy.textContent = 'Copied!';
        setTimeout(() => btnCopy.textContent = 'Copy', 1200);
      } catch {
        btnCopy.textContent = 'Copy failed';
        setTimeout(() => btnCopy.textContent = 'Copy', 1200);
      }
    });
  }

  if (btnDel) {
  btnDel.addEventListener('click', async () => {
    if (!confirm('Delete your access token? You’ll need to re‑authorize to use the tools again.')) return;
    try {
      const res = await fetch('/token/delete', {
        method: 'POST',
        credentials: 'same-origin',        // 🔑 send session cookie
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      if (json && json.ok) {
        location.reload();
      } else {
        alert('Delete failed.');
      }
    } catch (e) {
      alert('Delete failed.');
    }
  });
}

});
</script>

<!-- PERMISSIONS -->
<div class="card">
  <div class="sectionTitle">Permissions</div>

  <!-- Active scopes -->
  <div style="margin-bottom:10px">
    <div style="color:var(--muted);margin-bottom:6px">Active scopes</div>
    <div id="active-chips">
      <?php if (!empty($desiredScopes)): ?>
        <?php foreach ($desiredScopes as $s): ?>
          <span class="badge"><?= htmlspecialchars($s) ?></span>
        <?php endforeach; ?>
      <?php else: ?>
        <span class="badge">None</span>
      <?php endif; ?>
    </div>
  </div>

  <?php
    // load the scope catalog
    $allScopes = require __DIR__ . '/../app/twitch/scopes.php';
  ?>

  <!-- We don't actually POST anywhere; the form is just for structure/keyboard UX -->
  <form id="perm-form" onsubmit="return false;">
    <div class="perm-groups">
      <?php foreach ($allScopes as $group => $groupScopes): ?>
        <div class="scope-group">
          <div class="scope-title"><?= htmlspecialchars($group) ?></div>
          <ul class="perm-list">
            <?php foreach ($groupScopes as $s => $label): $active = in_array($s, $desiredScopes, true); ?>
              <li>
                <button type="button"
                        class="perm-badge <?= $active ? 'active' : '' ?>"
                        data-scope="<?= htmlspecialchars($s) ?>"
                        title="<?= htmlspecialchars($label) ?>">
                  <?= htmlspecialchars($s) ?>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="scopes-hidden"></div>

    <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;margin-top:10px">
      <div style="color:var(--muted)">
        <span id="active-count"><?= count($desiredScopes ?? []) ?></span> selected
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <!-- ✅ Dynamic button (no server-rendered href) -->
        <button type="button" class="btn" id="grant-now">Grant now</button>
      </div>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form       = document.getElementById('perm-form');
  const chips      = document.getElementById('active-chips');
  const hiddenWrap = document.getElementById('scopes-hidden');
  const countEl    = document.getElementById('active-count');
  const grantBtn   = document.getElementById('grant-now');

  const badges       = () => Array.from(form.querySelectorAll('.perm-badge'));
  const activeBadges = () => badges().filter(b => b.classList.contains('active'));
  const nsort        = (a,b) => a.localeCompare(b, undefined, { numeric:true, sensitivity:'base' });

  function selectedScopes() {
    return activeBadges().map(b => b.dataset.scope).sort(nsort);
  }

  function rebuildHidden() {
    hiddenWrap.innerHTML = '';
    selectedScopes().forEach(scope => {
      const i = document.createElement('input');
      i.type='hidden'; i.name='scopes[]'; i.value=scope;
      hiddenWrap.appendChild(i);
    });
  }

  function rebuildChips() {
    const scopes = selectedScopes();
    chips.innerHTML = scopes.length
      ? scopes.map(s => `<span class="badge">${s}</span>`).join('')
      : '<span class="badge">None</span>';
    countEl.textContent = String(scopes.length);
  }

  function toggle(b) {
    b.classList.toggle('active');
    rebuildHidden(); rebuildChips();
  }

  // interactions
  form.addEventListener('click', (e) => {
    const b = e.target.closest('.perm-badge'); if (b) toggle(b);
  });
  form.addEventListener('keydown', (e) => {
    if ((e.key === ' ' || e.key === 'Enter') && e.target.classList.contains('perm-badge')) {
      e.preventDefault(); toggle(e.target);
    }
  });

  // 🚀 Build OAuth URL from the CURRENT selections
  grantBtn.addEventListener('click', () => {
    const scopes = selectedScopes().join(' ');
    const url = '/token/create?scopes=' + encodeURIComponent(scopes);
    window.location.href = url;
  });

  // init
  rebuildHidden(); rebuildChips();
});
</script>

<!-- TOOLS -->
<div class="card">
  <div class="sectionTitle">Tools</div>
  <div class="tool-grid">
    <?php foreach ($tools as $t):
      $need    = $t['required_scopes'] ?? [];
      $enabled = $hasAll($need, $scopes ?? []);
      $missing = array_values(array_diff($normalize($need), $normalize($scopes ?? [])));
      $grantUrl = '/token/create?label=default&scopes=' . urlencode(implode(' ', $missing));
    ?>
      <article class="tool-card" style="<?= $enabled ? '' : 'opacity:.6' ?>">
        <div class="tool-top">
          <div class="tool-name"><?= htmlspecialchars($t['name']) ?></div>
          <span class="badge"><?= htmlspecialchars($t['method'] ?? 'GET') ?></span>
        </div>
        <p style="color:var(--muted)"><?= htmlspecialchars($t['desc']) ?></p>
        <p style="color:var(--muted)">Requires:
          <?php foreach ($need as $ns): ?>
            <span class="badge"><?= htmlspecialchars($ns) ?></span>
          <?php endforeach; ?>
        </p>
        <?php if ($enabled): ?>
          <div class="tool-url"><code><?= htmlspecialchars($t['url']) ?></code></div>
          <div class="tool-actions">
            <button class="btn"
              onclick="navigator.clipboard.writeText('<?= htmlspecialchars($t['url']) ?>').then(()=>this.textContent='Copied!')">
              Copy URL
            </button>
          </div>
        <?php else: ?>
          <div class="tool-actions">
            <a class="btn" href="<?= htmlspecialchars($grantUrl) ?>">Grant required permissions</a>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</div>

<!-- FOLLOWERS & SUBS (side by side on wide screens) -->
<div class="grid">
  <div class="card" style="grid-column:span 12;@media(min-width:640px){grid-column:span 6}">
    <div class="sectionTitle">Followers</div>
    <?php if (isset($followersTotal)): ?>
      <div class="kpi"><div class="box"><div class="num"><?= number_format((int)$followersTotal) ?></div><div style="color:var(--muted)">Total</div></div></div>
    <?php endif; ?>
    <?php if (!empty($followers) && is_array($followers)): ?>
      <table class="table" style="margin-top:10px">
        <thead><tr><th>User</th><th class="td-right">Followed</th></tr></thead>
        <tbody>
          <?php foreach ($followers as $row):
            $ava = $row['avatar'] ?? '';
          ?>
            <tr>
              <td>
                <div class="cell-user">
                  <?php if ($ava): ?><img class="avatar-sm" src="<?= htmlspecialchars($ava) ?>" alt=""><?php endif; ?>
                  <span><?= htmlspecialchars($row['name'] ?? '') ?></span>
                </div>
              </td>
              <td class="td-right"><?= htmlspecialchars($row['date'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="muted">Grant <code>moderator:read:followers</code> to view follower details.</p>
    <?php endif; ?>
  </div>

  <div class="card" style="grid-column:span 12;@media(min-width:640px){grid-column:span 6}">
    <div class="sectionTitle">Subscribers</div>
    <?php if (!in_array($acctType, ['affiliate','partner'], true)): ?>
      <p class="muted">Subscriptions require Affiliate/Partner.</p>
    <?php else: ?>
      <?php if (isset($subsTotal)): ?>
        <div class="kpi"><div class="box"><div class="num"><?= number_format((int)$subsTotal) ?></div><div style="color:var(--muted)">Total</div></div></div>
      <?php endif; ?>
      <?php if (!empty($subs) && is_array($subs)): ?>
        <table class="table" style="margin-top:10px">
          <thead><tr><th>User</th><th>Tier</th></tr></thead>
          <tbody>
            <?php foreach ($subs as $row):
              $ava = $row['avatar'] ?? '';
            ?>
              <tr>
                <td>
                  <div class="cell-user">
                    <?php if ($ava): ?><img class="avatar-sm" src="<?= htmlspecialchars($ava) ?>" alt=""><?php endif; ?>
                    <span><?= htmlspecialchars($row['name'] ?? '') ?></span>
                  </div>
                </td>
                <td><?= htmlspecialchars($row['tier'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted">Grant <code>channel:read:subscriptions</code> to view subscriptions.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
