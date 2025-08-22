<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_login();
require_once BASE_PATH . '/ui/layout.php';

$uid = current_user_id();
$u = db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1"); $u->execute([$uid]); $user = $u->fetch();
$t = db()->prepare("SELECT * FROM oauth_tokens WHERE user_id=? AND provider='twitch' LIMIT 1"); $t->execute([$uid]); $tok = $t->fetch();

$hasToken = !!$tok;
$grantedScopes = $hasToken && !empty($tok['scope']) ? explode(' ', $tok['scope']) : [];

ob_start(); ?>
  <div class="card">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <img class="avatar" src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://static-cdn.jtvnw.net/jtv_user_pictures/x.png') ?>" alt="">
      <div>
        <div class="sectionTitle" style="margin:0"><?= htmlspecialchars($user['twitch_display']) ?> <span class="badge">#<?= htmlspecialchars($user['twitch_login']) ?></span></div>
        <div style="color:#94a3b8;font-size:13px"><?= htmlspecialchars($user['email'] ?? '') ?></div>
      </div>
      <div style="margin-left:auto">
        <?php if (!$hasToken): ?>
          <a class="btn" href="/twitch/auth/login.php">Connect Twitch</a>
        <?php else: ?>
          <span class="badge ok">Connected to Twitch</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="grid">
    <div class="card" style="grid-column:1/-1">
      <div class="sectionTitle">Granted Scopes</div>
      <?php if ($grantedScopes): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php foreach ($grantedScopes as $s): ?><span class="badge"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="color:#94a3b8">No scopes saved yet. Click “Connect Twitch”.</div>
      <?php endif; ?>
    </div>

    <div class="card" style="grid-column:1/-1">
      <div class="sectionTitle">Stream Preview</div>
      <div class="embedWrap">
        <iframe src="https://player.twitch.tv/?channel=<?= urlencode($user['twitch_login']) ?>&parent=<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>&muted=true"
                frameborder="0" allowfullscreen="true" scrolling="no"></iframe>
      </div>
    </div>
  </div>
<?php
$content = ob_get_clean();
render_page('Dashboard', $content);
