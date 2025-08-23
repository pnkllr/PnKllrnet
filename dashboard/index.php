<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_login();
require_once BASE_PATH . '/ui/layout.php';
require_once __DIR__ . '/../app/twitch/helpers.php'; // scope helpers

// Load current user + token
$uid = current_user_id();
$uStmt = db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$uStmt->execute([$uid]);
$user = $uStmt->fetch();
if (!$user) { http_response_code(500); echo "User not found"; exit; }

$tStmt = db()->prepare("
  SELECT * FROM oauth_tokens
  WHERE provider='twitch' AND user_id=?
  ORDER BY expires_at DESC, id DESC
  LIMIT 1
");
$tStmt->execute([$uid]);
$tok    = $tStmt->fetch() ?: [];
$bearer = $tok['access_token'] ?? '';
$scopes = preg_split('/\s+/', trim($tok['scope'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

// Helix helper
function ttv(string $path, $q = [], ?string $bearer = null): array {
  $url = 'https://api.twitch.tv/helix' . $path;
  if (is_string($q) && $q !== '')      $url .= '?' . $q;
  elseif (is_array($q) && !empty($q))  $url .= '?' . http_build_query($q);

  $ch = curl_init($url);
  $h = ['Client-Id: ' . TWITCH_CLIENT_ID, 'Accept: application/json'];
  if ($bearer) $h[] = 'Authorization: Bearer ' . $bearer;
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$h, CURLOPT_TIMEOUT=>15]);
  $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $json = json_decode($res ?: '[]', true) ?: [];
  $json['_status'] = $code;
  return $json;
}

// Get Users (account type, email)
$usersRes = ttv('/users', ['id'=>$user['twitch_id']], $bearer);
$uInfo    = $usersRes['data'][0] ?? [];
$acctType = $uInfo['broadcaster_type'] ?? ''; // '', 'affiliate', 'partner'
$email    = $uInfo['email'] ?? ($user['email'] ?? null);

// Followers (needs moderator:read:followers)
$canFollowers = in_array('moderator:read:followers', $scopes, true);
$followersRes = $canFollowers
  ? ttv('/channels/followers', [
      'broadcaster_id'=>$user['twitch_id'],
      'moderator_id'  =>$user['twitch_id'],
      'first'=>10
    ], $bearer)
  : ['data'=>[], 'total'=>null, '_status'=>401];

// Subs (needs channel:read:subscriptions + affiliate/partner)
$canSubs = in_array('channel:read:subscriptions', $scopes, true) && in_array($acctType, ['affiliate','partner'], true);
$subsRes = $canSubs
  ? ttv('/subscriptions', ['broadcaster_id'=>$user['twitch_id'], 'first'=>10], $bearer)
  : ['data'=>[], 'total'=>null, '_status'=>401];

// Totals
$followersTotal = ($followersRes['_status'] ?? 0) === 200 ? (int)($followersRes['total'] ?? 0) : null;
$subsTotal      = ($subsRes['_status'] ?? 0) === 200 ? (int)($subsRes['total'] ?? 0) : null;

// Fetch avatars
function map_users_by_id(array $ids, string $bearer): array {
  if (!$ids) return [];
  $ids = array_values(array_unique(array_filter($ids)));
  $q = implode('&', array_map(fn($id) => 'id=' . urlencode($id), $ids));
  $res = ttv('/users', $q, $bearer);
  $map = [];
  foreach ($res['data'] ?? [] as $u) { $map[$u['id']] = $u; }
  return $map;
}
$followerIds = array_column($followersRes['data'] ?? [], 'user_id');
$fUsersMap   = map_users_by_id($followerIds, $bearer);

$subIds      = array_column($subsRes['data'] ?? [], 'user_id');
$sUsersMap   = map_users_by_id($subIds, $bearer);

// ---------------------------------------------------------------------
// Permissions (Scopes) UI + Tools config
// ---------------------------------------------------------------------
$allScopes = require BASE_PATH . '/app/twitch/scopes.php';

$desiredScopes = (function($uid, $scopes) {
  $st = db()->prepare("SELECT scope_str FROM user_desired_scopes WHERE user_id=?");
  $st->execute([$uid]);
  $raw = $st->fetchColumn();
  if ($raw) return parse_scope_str($raw);
  return $scopes;
})($uid, $scopes);

$base  = rtrim(BASE_URL, '/');
$tools = [
  [
  'name'   => '🎬 Clip It',
  'desc'   => 'Create a 30-second clip from your current live stream. Returns JSON by default; add `&format=text` if you just want the clip URL.',
  'url'    => $base . '/twitch/tool/clipit.php?channel=' . urlencode($user['twitch_login']),
  'method' => 'GET',
  'required_scopes' => ['clips:edit'],
],
];

$hasAll = function(array $need, array $have): bool {
  $need = scopes_normalize($need); $have = scopes_normalize($have);
  return !array_diff($need, $have);
};

// ---------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------
ob_start(); ?>
  <div class="grid">
    <!-- HEADER -->
    <div class="card" style="grid-column:1 / -1">
      <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
        <img src="<?= htmlspecialchars($user['avatar_url'] ?: ($uInfo['profile_image_url'] ?? '')) ?>" alt=""
             style="width:64px;height:64px;border-radius:50%;border:1px solid #2a2a31;object-fit:cover">
        <div>
          <div class="sectionTitle" style="margin:0">
            <?= htmlspecialchars($user['twitch_display'] ?: $user['twitch_login']) ?>
            <span class="badge">@<?= htmlspecialchars($user['twitch_login']) ?></span>
          </div>
          <?php if ($email): ?><div class="subtle"><?= htmlspecialchars($email) ?></div><?php endif; ?>
        </div>
        <div style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap; align-items:center">
          <span class="badge"><?= $acctType ? ucfirst($acctType) : 'Standard' ?></span>
          <span class="badge"><?= 'Followers: ' . ($followersTotal !== null ? number_format($followersTotal) : '—') ?></span>
          <span class="badge"><?= 'Subs: ' . ($subsTotal !== null ? number_format($subsTotal) : '—') ?></span>
        </div>
      </div>
    </div>

        <!-- Permissions -->
<div class="card" style="grid-column:1 / -1">
  <div class="sectionTitle">Permissions</div>

  <form id="perm-form" method="post" action="<?= htmlspecialchars($base . '/dashboard/scopes.save.php') ?>">
    <!-- make this BLOCK, not flex -->
    <div class="perm-groups">
      <?php foreach ($allScopes as $group => $groupScopes): ?>
        <div class="scope-group">
          <h3 class="scope-title"><?= htmlspecialchars($group) ?></h3>
          <div class="perm-list">
            <?php foreach ($groupScopes as $s => $label): ?>
              <span class="badge perm-badge <?= in_array($s, $desiredScopes, true) ? 'active' : '' ?>"
                    data-scope="<?= htmlspecialchars($s) ?>">
                <?= htmlspecialchars($s) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:10px">
      <button class="btn">Save & Re-authorize</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form   = document.getElementById('perm-form');
  const badges = Array.from(form.querySelectorAll('.perm-badge'));

  function updateInputs() {
    // remove previously added scope inputs
    form.querySelectorAll('input[name="scopes[]"]').forEach(el => el.remove());

    // add one hidden input per active badge
    badges
      .filter(b => b.classList.contains('active'))
      .forEach(b => {
        const hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = 'scopes[]';
        hidden.value = b.dataset.scope;
        form.appendChild(hidden);
      });
  }

  // click to toggle active state
  badges.forEach(badge => {
    badge.addEventListener('click', (e) => {
      e.preventDefault();
      badge.classList.toggle('active');
      updateInputs();
    });
  });

  // also rebuild right before submit (covers any edge case)
  form.addEventListener('submit', () => {
    updateInputs();
  });

  // initialize once for server-selected badges
  updateInputs();
});
</script>

<style>
  /* Stronger specificity and !important to beat base .badge styles */
  .card .perm-badge {
    background: transparent !important;
    border: 1px solid #bbb !important;
    color: #bbb !important;
    transition: color .15s ease, border-color .15s ease;
  }
  .card .perm-badge.active {
    border-color: #22c55e !important; /* green-500 */
    color: #22c55e !important;
  }
</style>

    <!-- Tools -->
    <div class="card" style="grid-column:1 / -1">
      <div class="sectionTitle">Tools</div>
      <div class="tool-grid">
        <?php foreach ($tools as $t):
          $need    = $t['required_scopes'] ?? [];
          $enabled = $hasAll($need, $scopes);
          $missing = array_values(array_diff(scopes_normalize($need), scopes_normalize($scopes)));
          $grantUrl = $base . '/twitch/auth/reauth.php?add=' . urlencode(implode(' ', $missing))
                    . '&next=' . urlencode('/dashboard/');
        ?>
          <article class="tool-card" style="<?= $enabled ? '' : 'opacity:.5' ?>">
            <div class="tool-top">
              <div class="tool-name"><?= htmlspecialchars($t['name']) ?></div>
              <span class="badge"><?= htmlspecialchars($t['method'] ?? 'GET') ?></span>
            </div>
            <p class="subtle"><?= htmlspecialchars($t['desc']) ?></p>
            <p class="subtle">Requires:
              <?php foreach (($t['required_scopes'] ?? []) as $ns): ?>
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
              <div class="subtle">Missing: <?= htmlspecialchars(implode(', ', $missing) ?: '—') ?></div>
              <div class="tool-actions">
                <a class="btn" href="<?= htmlspecialchars($grantUrl) ?>">Grant required permissions</a>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Followers -->
    <div class="card" style="grid-column:1 / span 6">
      <div class="sectionTitle">Followers</div>
      <?php if (($followersRes['_status'] ?? 0) === 200): ?>
        <div class="row"><div class="subtle">Total</div><div><strong><?= number_format((int)($followersRes['total'] ?? 0)) ?></strong></div></div>
        <table class="table" style="margin-top:10px">
          <thead><tr><th>User</th><th class="td-right">Followed</th></tr></thead>
          <tbody>
            <?php foreach (($followersRes['data'] ?? []) as $f):
              $fu  = $fUsersMap[$f['user_id']] ?? null;
              $ava = $fu['profile_image_url'] ?? '';
            ?>
              <tr class="row">
                <td class="td">
                  <div class="cell-user">
                    <?php if ($ava): ?><img class="avatar-sm" src="<?= htmlspecialchars($ava) ?>" alt=""><?php endif; ?>
                    <span><?= htmlspecialchars($f['user_name']) ?></span>
                  </div>
                </td>
                <td class="td td-right"><?= date('Y-m-d', strtotime($f['followed_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif (!$canFollowers): ?>
        <div class="subtle">Grant <code>moderator:read:followers</code> to view follower details.</div>
      <?php else: ?>
        <div class="subtle">Couldn’t load followers (HTTP <?= (int)($followersRes['_status'] ?? 0) ?>).</div>
      <?php endif; ?>
    </div>

    <!-- Subscriptions -->
    <div class="card" style="grid-column:7 / -1">
      <div class="sectionTitle">Subscribers</div>
      <?php if (!in_array($acctType, ['affiliate','partner'], true)): ?>
        <div class="subtle">Subscriptions require Affiliate/Partner.</div>
      <?php elseif (($subsRes['_status'] ?? 0) === 200): ?>
        <div class="row"><div class="subtle">Total</div><div><strong><?= number_format((int)($subsRes['total'] ?? 0)) ?></strong></div></div>
        <table class="table" style="margin-top:10px">
          <thead><tr><th>User</th><th>Tier</th></tr></thead>
          <tbody>
            <?php foreach (($subsRes['data'] ?? []) as $s):
              $su  = $sUsersMap[$s['user_id']] ?? null;
              $ava = $su['profile_image_url'] ?? '';
              $tier = $s['tier'] ?? '';
            ?>
              <tr class="row">
                <td class="td">
                  <div class="cell-user">
                    <?php if ($ava): ?><img class="avatar-sm" src="<?= htmlspecialchars($ava) ?>" alt=""><?php endif; ?>
                    <span><?= htmlspecialchars($s['user_name']) ?></span>
                  </div>
                </td>
                <td class="td"><?= htmlspecialchars($tier) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif (!$canSubs): ?>
        <div class="subtle">Grant <code>channel:read:subscriptions</code> to view subscriptions.</div>
      <?php else: ?>
        <div class="subtle">Couldn’t load subscriptions (HTTP <?= (int)($subsRes['_status'] ?? 0) ?>).</div>
      <?php endif; ?>
    </div>
  </div>
<?php
$content = ob_get_clean();
render_page('Dashboard', $content);
