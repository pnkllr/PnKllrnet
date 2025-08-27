<?php
// Dashboard view with scope picker + reauth flow

require_once BASE_PATH . '/app/Twitch/helpers.php';
$allScopes = require BASE_PATH . '/app/Twitch/scopes.php';

/* --- Logged-out users: show connect CTA and stop --- */
if (!$u) {
?>
  <div class="card">
    <div class="sectionTitle">Welcome</div>
    <p class="muted">You’re not connected yet. Connect your Twitch account to view your dashboard.</p>
    <a class="btn" href="<?= htmlspecialchars(base_url('/auth/twitch.php')) ?>">Connect with Twitch</a>
  </div>
<?php
  return; // stop rendering the rest of the page when logged out
}

$uid = (int)$u['id'];
$uRow = User::getUser($uid);
/* --- Banned users: show banned card and stop --- */
if (((int)$uRow['is_banned'] ?? 0) === 1) {
?>
  <div class="card danger">
    <div class="sectionTitle">Account banned</div>
    <p class="muted">
      Your account has been banned<?= $uRow['banned_at'] ? ' since <strong>' . htmlspecialchars($uRow['banned_at']) . '</strong>' : '' ?>.
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn" href="<?= htmlspecialchars(base_url('https://discord.gg/nth7y8TqMT')) ?>">Get Support</a>
      <a class="btn ghost" href="<?= htmlspecialchars(base_url('/auth/logout.php')) ?>">Log out</a>
    </div>
  </div>
<?php
  return; // stop rendering rest of dashboard for banned users
}

$desiredScopes = ScopePrefs::getDesired($uid);
$tokRow = User::getTokens($uid);
$activeScopes = $tokRow ? explode(' ', trim($tokRow['scopes'] ?? '')) : [];

$bearer = '';
if (!empty($tokRow)) {
  $t = trim((string)($tokRow['access_token'] ?? ''));
  if ($t !== '') $bearer = $t;
}

$ttv = function (string $path, array $q = [], ?string $bearer = null): array {
  $url = 'https://api.twitch.tv/helix' . $path;
  if ($q) $url .= '?' . http_build_query($q);
  $h = [
    'Client-Id: ' . (getenv('TWITCH_CLIENT_ID') ?: ''),
    'Accept: application/json',
  ];
  if ($bearer) $h[] = 'Authorization: Bearer ' . $bearer;

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $h,
    CURLOPT_TIMEOUT        => 15,
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $json = json_decode($res ?: '[]', true) ?: [];
  $json['_status'] = $code;
  return $json;
};

$me = [];
$acctType = null;
$email = $u['email'] ?? null;

if ($bearer) {
  $usersRes = $ttv('/users', [], $bearer);
  $me = $usersRes['data'][0] ?? [];
  if (!empty($me['email'])) $email = $me['email'];
  $acctType = $me['broadcaster_type'] ?? null;
}
$haveScopes = array_flip($activeScopes);
$followersTotal = null;
$subsTotal      = null;

if ($bearer && isset($haveScopes['moderator:read:followers'])) {
  $followersRes = $ttv('/channels/followers', [
    'broadcaster_id' => $u['twitch_id'],
    'moderator_id'   => $u['twitch_id'],
    'first'          => 1,
  ], $bearer);
  if (($followersRes['_status'] ?? 0) === 200) {
    $followersTotal = (int)($followersRes['total'] ?? 0);
  }
}

if ($bearer && isset($haveScopes['channel:read:subscriptions']) && in_array($acctType, ['affiliate', 'partner'], true)) {
  $subsRes = $ttv('/subscriptions', [
    'broadcaster_id' => $u['twitch_id'],
    'first'          => 1,
  ], $bearer);
  if (($subsRes['_status'] ?? 0) === 200) {
    $subsTotal = (int)($subsRes['total'] ?? 0);
  }
}
?>

<!-- Profile Information -->
<div class="card">
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <?php if (!empty($me['profile_image_url'] ?? $u['avatar'])): ?>
      <img class="avatar" src="<?= htmlspecialchars(($me['profile_image_url'] ?? $u['avatar'])) ?>" alt="">
    <?php endif; ?>
    <div>
      <div class="sectionTitle" style="margin:0">
        <?= htmlspecialchars(($me['display_name'] ?? $u['display'] ?? 'Unknown')) ?>
        <span class="badge">@<?= htmlspecialchars($u['login'] ?? '') ?></span>
      </div>
      <?php if (!empty($email)): ?>
        <div class="muted"><?= htmlspecialchars($email) ?></div>
      <?php endif; ?>
      <?php if (!empty($u['twitch_id'])): ?>
        <div class="muted">Twitch ID: <?= htmlspecialchars($u['twitch_id']) ?></div>
      <?php endif; ?>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <span class="badge"><?= htmlspecialchars($acctType ? ucfirst($acctType) : 'Standard') ?></span>
      <span class="badge">Followers: <?= ($followersTotal !== null ? number_format($followersTotal) : '—') ?></span>
      <span class="badge">Subs: <?= ($subsTotal !== null ? number_format($subsTotal) : '—') ?></span>
    </div>
  </div>
</div>
<!-- End Profile Info -->


<!-- Token Display -->
<?php
// pick which token to show (prefer access, else refresh)
$tokenType  = '';
$tokenValue = '';
$expiresAt  = null;
if ($tokRow) {
  if (!empty($tokRow['access_token'])) {
    $tokenType = 'access_token';
    $tokenValue = (string)$tokRow['access_token'];
  } elseif (!empty($tokRow['refresh_token'])) {
    $tokenType = 'refresh_token';
    $tokenValue = (string)$tokRow['refresh_token'];
  }
  $expiresAt = $tokRow['expires_at'] ?? null;
}

// helper to mask the middle: keep first/last 4 chars
$maskMid = function (string $t): string {
  if ($t === '') return '';
  $n = strlen($t);
  if ($n <= 8) return str_repeat('•', $n); // tiny token, just obfuscate fully
  $first = substr($t, 0, 4);
  $last  = substr($t, -4);
  return $first . str_repeat('•', max(0, $n - 8)) . $last;
};
?>

<div class="card">
  <div class="sectionTitle">Access Token</div>

  <?php if (!empty($_GET['token_deleted'])): ?>
    <p class="muted" style="color:#22c55e;margin:0 0 10px 0">✅ Token deleted.</p>
  <?php endif; ?>

  <?php if ($tokenValue): ?>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if (!empty($activeScopes)): ?>
        <span class="badge"><?= count($activeScopes) ?> scope<?= count($activeScopes) === 1 ? '' : 's' ?></span>
      <?php endif; ?>
    </div>

    <div class="token-line">
      <code id="tok-value"
        class="token-code"
        data-token="<?= htmlspecialchars($tokenValue) ?>"
        data-visible="false"><?= htmlspecialchars($maskMid($tokenValue)) ?></code>

      <div class="token-actions">
        <button type="button" class="btn ghost" id="tok-toggle">Show</button>
        <button type="button" class="btn ghost" id="tok-copy">Copy</button>

        <form id="tok-delete-form" method="post" action="<?= htmlspecialchars(base_url('/dashboard/token.delete.php')) ?>">
          <?= CSRF::input() ?>
          <button type="submit" class="btn danger">Delete</button>
        </form>
      </div>
    </div>

    <div class="token-meta-row">
      <span class="subtle">Expires: <strong><?= $expiresAt ? htmlspecialchars(date('Y-m-d H:i', strtotime($expiresAt))) : '—' ?></strong></span>
    </div>
  <?php else: ?>
    <p class="muted">No token yet. Use “Permissions” below to select scopes then click “Save &amp; Re‑authorize”.</p>
  <?php endif; ?>
</div>
<!-- End Token Display -->

<!-- Scope selection -->
<div class="card">
  <h3 class="sectionTitle">Permissions</h3>
  <p class="small">
    Pick the permissions you want your token to have. Click badges to toggle. Then click
    <em>Save &amp; Re‑authorize</em> to go to Twitch and approve them.
  </p>

  <form method="post" action="<?= htmlspecialchars(base_url('/dashboard/scopes.save.php')) ?>">
    <?= CSRF::input() ?>

    <div class="perm-groups" style="display:flex; flex-direction:column; gap:12px">
      <?php foreach ($allScopes as $group => $groupScopes): ?>
        <section class="scope-group">
          <h4 style="margin:0 0 8px 0"><?= htmlspecialchars($group) ?></h4>
          <div class="perm-list" style="display:flex; flex-wrap:wrap; gap:8px">
            <?php foreach ($groupScopes as $scope => $label):
              $isSelected = in_array($scope, $desiredScopes, true);
              $id = 'sc_' . preg_replace('/[^a-z0-9_]/i', '_', $scope);
            ?>
              <input
                type="checkbox"
                name="scopes[]"
                id="<?= htmlspecialchars($id) ?>"
                value="<?= htmlspecialchars($scope) ?>"
                <?= $isSelected ? 'checked' : '' ?>
                style="display:none" />
              <label
                for="<?= htmlspecialchars($id) ?>"
                class="badge perm-badge"
                title="<?= htmlspecialchars($label) ?>"
                style="user-select:none; cursor:pointer">
                <?= htmlspecialchars($scope) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:12px; display:flex; gap:10px; align-items:center">
      <button class="btn ghost" type="submit">Save &amp; Re‑authorize</button>
      <span class="small">You’ll be redirected to Twitch to grant the selected scopes.</span>
    </div>
  </form>
</div>
<!-- Scope End -->

<!-- Tools -->
<div class="card">
  <div class="sectionTitle">Tools</div>
  <div class="tool-grid">
    <?php foreach ($tools as $t):
      $need     = $t['required_scopes'] ?? [];
      $enabled  = $hasAll($need, $scopes);
      $missing  = array_values(array_diff(scopes_normalize($need), scopes_normalize($scopes)));
      $grantUrl = grant_url($base, $missing, '/dashboard/');
      $opacity  = $enabled ? '' : 'opacity:.5';
    ?>
      <article class="tool-card" style="<?= $opacity ?>" aria-disabled="<?= $enabled ? 'false' : 'true' ?>">
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
          <div class="tool-url">
            <code><?= htmlspecialchars($t['url']) ?></code>
          </div>
          <div class="tool-actions">
            <button class="btn"
              onclick="navigator.clipboard.writeText('<?= htmlspecialchars($t['url']) ?>').then(()=>this.textContent='Copied!')">
              Copy URL
            </button>
          </div>
        <?php else: ?>
          <div class="subtle" title="You don’t currently have these scopes">
            Missing: <?= htmlspecialchars(implode(', ', $missing) ?: '—') ?>
          </div>
          <div class="tool-actions">
            <a class="btn" href="<?= htmlspecialchars($grantUrl) ?>">Grant required permissions</a>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<!-- Tool End -->


<style>
  /* Badge look */
  .perm-badge {
    background: transparent !important;
    border: 1px solid #bbb !important;
    color: #bbb !important;
  }

  /* Highlight when the preceding checkbox is checked */
  input[type="checkbox"]:checked+.perm-badge {
    border-color: #22c55e !important;
    color: #22c55e !important;
  }
</style>