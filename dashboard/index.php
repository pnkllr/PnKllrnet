<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_login();
require_once BASE_PATH . '/ui/layout.php';

// Load current user + token
$uid = current_user_id();
$uStmt = db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$uStmt->execute([$uid]);
$user = $uStmt->fetch();
if (!$user) { http_response_code(500); echo "User not found"; exit; }

$tStmt = db()->prepare("SELECT * FROM oauth_tokens WHERE provider='twitch' AND user_id=? LIMIT 1");
$tStmt->execute([$uid]);
$tok   = $tStmt->fetch() ?: [];
$bearer = $tok['access_token'] ?? '';
$scopes = preg_split('/\s+/', trim($tok['scope'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

// Helix helper (supports string query for repeated id params)
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

// Emotes
$emotesRes = ttv('/chat/emotes', ['broadcaster_id'=>$user['twitch_id']], $bearer);
$emoteList = $emotesRes['data'] ?? [];
$emoteTpl  = $emotesRes['template'] ?? 'https://static-cdn.jtvnw.net/emoticons/v2/{{id}}/static/light/2.0';

// Followers (needs moderator:read:followers; moderator_id required)
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

// Totals for header
$followersTotal = ($followersRes['_status'] ?? 0) === 200 ? (int)($followersRes['total'] ?? 0) : null;
$subsTotal      = ($subsRes['_status'] ?? 0) === 200 ? (int)($subsRes['total'] ?? 0) : null;
$discordInvite = trim(getenv('DISCORD_INVITE') ?: '');

// Fetch avatars for follower/sub user_ids (one /users call each)
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

// Tools list
$base = rtrim(BASE_URL, '/');
$tools = [
  [
    'name'   => '🎬 Clip It',
    'desc'   => 'Create a 30-second clip from your current live stream. Returns JSON by default; append `&format=text` to get just the clip URL.',
    'url'    => $base . '/twitch/tool/clipit.php?channel=' . urlencode($user['twitch_login']),
    'method' => 'GET',
  ],
];

ob_start(); ?>
  <div class="grid">
    <!-- HEADER: profile + status + counts -->
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

    <!-- Tools -->
    <div class="card" style="grid-column:1 / -1">
  <div class="sectionTitle">Tools</div>
  <div class="tool-grid">
    <?php foreach ($tools as $t): ?>
      <article class="tool-card">
        <div class="tool-top">
          <div class="tool-name"><?= htmlspecialchars($t['name']) ?></div>
          <span class="badge"><?= htmlspecialchars($t['method'] ?? 'GET') ?></span>
        </div>
        <p class="subtle"><?= htmlspecialchars($t['desc']) ?></p>
        <div class="tool-url">
  <code title="<?= htmlspecialchars($t['url']) ?>"><?= htmlspecialchars($t['url']) ?></code>
</div>
        <div class="tool-actions">
          <button class="btn"
            onclick="navigator.clipboard.writeText('<?= htmlspecialchars($t['url']) ?>').then(()=>this.textContent='Copied!')">
            Copy URL
          </button>
          <!-- <a class="btn" href="<?= htmlspecialchars($t['url']) ?>" target="_blank" rel="noopener">Open</a> -->
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>


    <!-- Emotes -->
    <div class="card" style="grid-column:1 / -1">
      <div class="sectionTitle">Your Channel Emotes</div>
      <style>
        .emotes { display:flex; flex-wrap:wrap; gap:10px }
        .emote { position:relative; text-align:center }
        .emote img { width:36px; height:36px; image-rendering:-webkit-optimize-contrast }
        .emote figcaption {
          position:absolute; left:50%; transform:translateX(-50%); bottom:-26px;
          background:rgba(0,0,0,.7); color:#fff; padding:3px 6px; border-radius:6px;
          font-size:12px; line-height:1; white-space:nowrap; opacity:0; pointer-events:none; transition:opacity .12s ease;
        }
        .emote:hover figcaption { opacity:1 }
      </style>
      <?php if ($emoteList): ?>
        <div class="emotes">
          <?php foreach ($emoteList as $e):
            $src = strtr($emoteTpl, [
              '{{id}}' => $e['id'],
              '{{format}}' => in_array('animated', $e['format'] ?? [], true) ? 'animated' : 'static',
              '{{theme_mode}}' => 'light',
              '{{scale}}' => '2.0',
            ]);
          ?>
            <figure class="emote">
              <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($e['name']) ?>">
              <figcaption><?= htmlspecialchars($e['name']) ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="subtle">No custom emotes found or request failed.</div>
      <?php endif; ?>
    </div>

    <!-- Followers -->
    <div class="card" style="grid-column:1 / span 6">
  <div class="sectionTitle">Followers</div>
  <?php if (($followersRes['_status'] ?? 0) === 200): ?>
    <div class="row"><div class="subtle">Total</div><div><strong><?= number_format((int)($followersRes['total'] ?? 0)) ?></strong></div></div>
    <table class="table" style="margin-top:10px">
      <thead>
        <tr><th>User</th><th class="td-right">Followed</th></tr>
      </thead>
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
      <thead>
        <tr><th>User</th><th>Tier</th></tr>
      </thead>
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
