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

ob_start(); ?>

<div class="kpis">
  <div class="k">
    <div class="L">Users</div>
    <div class="V"><?= (int)$totalUsers ?></div>
  </div>
  <div class="k">
    <div class="L">Tokens</div>
    <div class="V"><?= (int)$totalTokens ?></div>
  </div>
  <div class="k">
    <div class="L">Expiring &lt; 1h</div>
    <div class="V"><?= (int)$expSoon ?></div>
  </div>
  <div class="k">
    <div class="L">Missing clips:edit</div>
    <div class="V"><?= (int)$missingClips ?></div>
  </div>
</div>

<div class="section">
  <div class="toolbar">
    <div class="sectionTitle">Users</div>
    <div class="search"><input id="userSearch" type="text" placeholder="Search users by display/login/email…"></div>
  </div>

  <div id="users" class="grid">
    <?php foreach ($users as $u):
      $isBanned = !empty($u['banned_at']);
      $q = strtolower(trim(($u['twitch_display'] ?? '')));
    ?>
      <article class="uCard<?= $isBanned ? ' banned' : '' ?>" data-q="<?= htmlspecialchars($q) ?>">
        <img class="uAv" src="<?= htmlspecialchars($u['avatar_url'] ?: 'https://static-cdn.jtvnw.net/jtv_user_pictures/x.png') ?>" alt="">
        <div class="uMain">
          <div class="uName">
            <?= htmlspecialchars($u['twitch_display'] ?: $u['twitch_login']) ?>
          </div>
          <div class="uLogin">@<?= htmlspecialchars($u['twitch_login']) ?></div>
          <div class="uMeta">Joined: <?= htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))) ?></div>
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
    <?php endforeach;
    if (!$users): ?>
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
      $cls = badgeClassForExpiry($t['expires_at'] ?? null);
      $scopeStr = trim((string)($t['scopes'] ?? ($t['scope'] ?? '')));
      $scopes = $scopeStr !== '' ? preg_split('/\s+/', $scopeStr, -1, PREG_SPLIT_NO_EMPTY) : [];
      $q = strtolower(($t['twitch_display'] ?? '') . ' ' . $scopeStr);
    ?>
      <article class="tCard" data-q="<?= htmlspecialchars($q) ?>">
        <div class="tTop">
          <div class="tWho">
            <img class="tAv" src="<?= htmlspecialchars($t['avatar_url'] ?: 'https://static-cdn.jtvnw.net/jtv_user_pictures/x.png') ?>" alt="">
            <div>
              <div class="tName"><?= htmlspecialchars($t['twitch_display'] ?? $t['twitch_login'] ?? 'Unknown') ?></div>
              <?php if (!empty($t['twitch_login'])): ?>
                <div class="tLogin">@<?= htmlspecialchars($t['twitch_login']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="badges">
            <span class="badge <?= $cls ?>">
              <?= htmlspecialchars(badgeText($t['expires_at'] ?? null)) ?>
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
          <form method="post" action="<?= htmlspecialchars(base_url('/dashboard/admin/token.refresh.php')) ?>">
            <?= CSRF::input() ?>
            <input type="hidden" name="user_id" value="<?= (int)$t['user_id'] ?>">
            <button class="btn" type="submit">Refresh</button>
          </form>
          <form method="post" action="<?= htmlspecialchars(base_url('/dashboard/admin/token.delete.php')) ?>"
            onsubmit="return confirm('Delete token for @<?= htmlspecialchars($t['twitch_login'] ?? '') ?>?')">
            <?= CSRF::input() ?>
            <input type="hidden" name="user_id" value="<?= (int)$t['user_id'] ?>">
            <button class="btn destructive" type="submit">Delete</button>
          </form>
        </div>
      </article>
    <?php endforeach;
    if (!$tokens): ?>
      <div class="uMeta">No tokens saved.</div>
    <?php endif; ?>
  </div>
</div>