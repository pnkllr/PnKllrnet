<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_login();
require_once BASE_PATH . '/ui/layout.php';

$users  = db()->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$tokens = db()->query("SELECT t.*, u.twitch_login FROM oauth_tokens t JOIN users u ON u.id=t.user_id ORDER BY t.id DESC")->fetchAll();

ob_start(); ?>
  <div class="card">
    <div class="sectionTitle">Users</div>
    <table class="table"><thead><tr><th>ID</th><th>Login</th><th>Display</th><th>Email</th><th>Joined</th></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
      <tr class="row"><td class="td"><?= (int)$u['id'] ?></td><td class="td">#<?= htmlspecialchars($u['twitch_login']) ?></td><td class="td"><?= htmlspecialchars($u['twitch_display']) ?></td><td class="td"><?= htmlspecialchars($u['email'] ?? '') ?></td><td class="td"><?= htmlspecialchars($u['created_at']) ?></td></tr>
      <?php endforeach; if (!$users): ?><tr class="row"><td class="td" colspan="5" style="color:#94a3b8">No users yet.</td></tr><?php endif; ?>
    </tbody></table>
  </div>

  <div class="card">
    <div class="sectionTitle">OAuth Tokens</div>
    <table class="table"><thead><tr><th>User</th><th>Provider</th><th>Scopes</th><th>Expires</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($tokens as $t): $exp=$t['expires_at']?new DateTime($t['expires_at']):null; $left=$exp?($exp->getTimestamp()-time()):null; $cls=$left===null?'warn':($left>3600?'ok':'bad'); ?>
      <tr class="row">
        <td class="td">#<?= htmlspecialchars($t['twitch_login']) ?></td>
        <td class="td"><?= htmlspecialchars($t['provider']) ?></td>
        <td class="td" style="max-width:460px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($t['scope'] ?? '') ?></td>
        <td class="td"><span class="badge <?= $cls ?>"><?= $exp? $exp->format('Y-m-d H:i') : 'unknown' ?></span></td>
        <td class="td">
          <form method="post" action="/admin/actions.php" style="display:inline"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn" name="action" value="refresh">Refresh</button></form>
          <form method="post" action="/admin/actions.php" style="display:inline" onsubmit="return confirm('Delete token?')"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn" name="action" value="delete">Delete</button></form>
        </td>
      </tr>
      <?php endforeach; if (!$tokens): ?><tr class="row"><td class="td" colspan="5" style="color:#94a3b8">No tokens saved.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
<?php
$content = ob_get_clean();
render_page('Admin', $content);
