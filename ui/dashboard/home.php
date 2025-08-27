<?php
// Dashboard view with scope picker + reauth flow (modernized)

require_once BASE_PATH . '/app/Twitch/helpers.php';
$allScopes = require BASE_PATH . '/app/Twitch/scopes.php'; // case-sensitive fix

/* --- Logged-out users: show connect CTA and stop --- */
if (!$u) {
?>
  <div class="card hero-card">
    <div class="hero-left">
      <div class="hero-title">Welcome</div>
      <p class="muted">You’re not connected yet. Connect your Twitch account to view your dashboard.</p>
      <a class="btn ghost" href="<?= htmlspecialchars(base_url('/auth/twitch.php')) ?>">Connect with Twitch</a>
      <a class="btn ghost" href="<?= htmlspecialchars(base_url('https://discord.gg/nth7y8TqMT')) ?>">Get Support</a>
    </div>
  </div>
<?php
  return; // stop rendering the rest of the page when logged out
}

$uid  = (int)$u['id'];
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
      <a class="btn ghost" href="<?= htmlspecialchars(base_url('https://discord.gg/nth7y8TqMT')) ?>">Get Support</a>
      <a class="btn ghost" href="<?= htmlspecialchars(base_url('/auth/logout.php')) ?>">Log out</a>
    </div>
  </div>
<?php
  return; // stop rendering rest of dashboard for banned users
}

$desiredScopes = ScopePrefs::getDesired($uid);
$tokRow        = User::getTokens($uid);
$activeScopes  = $tokRow ? explode(' ', trim($tokRow['scopes'] ?? '')) : [];

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

$me       = [];
$acctType = null;
$email    = $u['email'] ?? null;

if ($bearer) {
  $usersRes = $ttv('/users', [], $bearer);
  $me = $usersRes['data'][0] ?? [];
  if (!empty($me['email'])) $email = $me['email'];
  $acctType = $me['broadcaster_type'] ?? null;
}

$haveScopes     = array_flip($activeScopes);
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

<!-- Hero / Profile -->
<div class="card hero-card">
  <div class="hero-main">
    <?php if (!empty($me['profile_image_url'] ?? $u['avatar'])): ?>
      <img class="avatar-xxl" src="<?= htmlspecialchars(($me['profile_image_url'] ?? $u['avatar'])) ?>" alt="">
    <?php endif; ?>
    <div class="hero-info">
      <div class="hero-title">
        <?= htmlspecialchars(($me['display_name'] ?? $u['display'] ?? 'Unknown')) ?>
        <span class="badge soft">@<?= htmlspecialchars($u['login'] ?? '') ?></span>
      </div>
      <?php if (!empty($email)): ?>
        <div class="muted"><?= htmlspecialchars($email) ?></div>
      <?php endif; ?>
      <?php if (!empty($u['twitch_id'])): ?>
        <div class="muted tiny">Twitch ID: <?= htmlspecialchars($u['twitch_id']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-stats">
    <div class="stat-chip">
      <div class="stat-label">Account</div>
      <div class="stat-value"><?= htmlspecialchars($acctType ? ucfirst($acctType) : 'Standard') ?></div>
    </div>
    <div class="stat-chip">
      <div class="stat-label">Followers</div>
      <div class="stat-value"><?= ($followersTotal !== null ? number_format($followersTotal) : '—') ?></div>
    </div>
    <div class="stat-chip">
      <div class="stat-label">Subs</div>
      <div class="stat-value"><?= ($subsTotal !== null ? number_format($subsTotal) : '—') ?></div>
    </div>
    <?php if (!empty($activeScopes)): ?>
      <div class="stat-chip">
        <div class="stat-label">Scopes</div>
        <div class="stat-value"><?= count($activeScopes) ?></div>
      </div>
    <?php endif; ?>
  </div>
</div>
<!-- /Hero -->

<?php
// pick which token to show (prefer access, else refresh)
$tokenType  = '';
$tokenValue = '';
$expiresAt  = null;
if ($tokRow) {
  if (!empty($tokRow['access_token'])) {
    $tokenType  = 'access_token';
    $tokenValue = (string)$tokRow['access_token'];
  } elseif (!empty($tokRow['refresh_token'])) {
    $tokenType  = 'refresh_token';
    $tokenValue = (string)$tokRow['refresh_token'];
  }
  $expiresAt = $tokRow['expires_at'] ?? null;
}

// helper to mask the middle: keep first/last 4 chars
$maskMid = function (string $t): string {
  if ($t === '') return '';
  $n = strlen($t);
  if ($n <= 8) return str_repeat('•', $n);
  $first = substr($t, 0, 4);
  $last  = substr($t, -4);
  return $first . str_repeat('•', max(0, $n - 8)) . $last;
};
?>

<!-- Token -->
<div class="card token-card">
  <div class="sectionTitle">Access Token</div>

  <?php if (!empty($_GET['token_deleted'])): ?>
    <p class="muted success">✅ Token deleted.</p>
  <?php endif; ?>

  <?php if ($tokenValue): ?>
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
    <p class="muted">No token yet. Use “Permissions” below to select scopes then click <em>Save &amp; Re-authorize</em>.</p>
  <?php endif; ?>
</div>
<!-- /Token -->

<!-- Permissions (simple) -->
<div class="card perms-card" id="scopes">
  <div class="sectionTitle">Permissions</div>
  <p class="small muted">
    Click the scopes you want, then press <em>Save &amp; Re-authorize</em> to grant them on Twitch.
  </p>

  <form method="post" action="<?= htmlspecialchars(base_url('/dashboard/scopes.save.php')) ?>">
    <?= CSRF::input() ?>

    <div class="perm-groups">
      <?php foreach ($allScopes as $group => $groupScopes): ?>
        <section class="scope-group">
          <h4 class="scope-title"><?= htmlspecialchars($group) ?></h4>
          <div class="perm-list">
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
                class="scope-check" />
              <label
                for="<?= htmlspecialchars($id) ?>"
                class="badge perm-badge"
                title="<?= htmlspecialchars($label) ?>">
                <?= htmlspecialchars($scope) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="perms-actions">
      <button class="btn ghost" type="submit">Save &amp; Re-authorize</button>
      <span class="small muted">You’ll be redirected to Twitch.</span>
    </div>
  </form>
</div>
<!-- Permissions -->

<!-- Tools -->
<?php
require_once BASE_PATH . '/app/ToolsHelper.php';
$tools         = $tools         ?? (require BASE_PATH . '/app/Tools.php');
$grantedScopes = $grantedScopes ?? [];
$hasAll        = $hasAll        ?? fn(array $need, array $have) => count(scopes_missing($need, $have)) === 0;
$channelLogin  = $u['login'] ?? ($me['login'] ?? '');
?>
<div class="card tools-panel">
  <div class="tools-header">
    <div class="tools-title">
      <span>Tools</span>
    </div>
    <div class="tools-subtitle">Quick utilities for your channel</div>
  </div>

  <div class="tool-grid">
    <?php foreach ($tools as $t):
      $need     = $t['required_scopes'] ?? [];
      $enabled  = $hasAll($need, $grantedScopes);
      $missing  = scopes_missing($need, $grantedScopes);
      $rawUrl   = (string)($t['url'] ?? '');
      $finalUrl = $rawUrl !== '' ? str_replace('{self}', rawurlencode($channelLogin), $rawUrl) : '';
    ?>
      <article class="tool-card" data-enabled="<?= $enabled ? 'true' : 'false' ?>">
        <header class="tool-head">
          <div class="tool-id">
            <div class="tool-meta">
              <div class="tool-name"><?= htmlspecialchars($t['name']) ?></div>
              <div class="tool-desc"><?= htmlspecialchars($t['desc'] ?? '') ?></div>
            </div>
          </div>
          <span class="badge method <?= strtolower($t['method'] ?? 'get') ?>"><?= htmlspecialchars($t['method'] ?? 'GET') ?></span>
        </header>

        <div class="tool-reqs">
          <span class="req-label">Requires</span>
          <div class="req-badges">
            <?php foreach ($need as $ns): ?>
              <span class="badge scope"><?= htmlspecialchars($ns) ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($enabled): ?>
          <div class="tool-url">
            <code class="url-chip" title="<?= htmlspecialchars($finalUrl) ?>">
              <span class="truncate"><?= htmlspecialchars($finalUrl) ?></span>

              <button
                type="button"
                class="chip-action js-copy"
                data-copy="<?= htmlspecialchars($finalUrl, ENT_QUOTES) ?>"
                aria-label="Copy URL">
                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
                  <path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14h13a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/>
                </svg>
                <span>Copy</span>
              </button>
            </code>
          </div>
        <?php else: ?>
          <div class="tool-missing">
            <span class="badge warn">Missing scopes</span>
            <span class="missing-list"><?= htmlspecialchars(implode(', ', $missing) ?: '—') ?></span>
            <span class="hint">Add these in the <a href="#scopes">Permissions</a> section above.</span>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<!-- Tools -->
