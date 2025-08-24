<h1>Dashboard</h1>

<h2>Create / Update Token</h2>
<form method="post" action="/token/create">
  <label>Label: <input name="label" value="default"></label>
  <div>
    <label><input type="checkbox" name="tools[]" value="clips"> Clips</label>
    <label><input type="checkbox" name="tools[]" value="followers"> Followers</label>
    <label><input type="checkbox" name="tools[]" value="subs"> Subs</label>
    <label><input type="checkbox" name="tools[]" value="vod"> VOD</label>
    <label><input type="checkbox" name="tools[]" value="email" checked> Email</label>
  </div>
  <button>Create / Refresh token</button>
</form>

<h2>Your Tokens</h2>
<table border="1" cellpadding="6"><tr><th>Label</th><th>Scopes</th><th>Expires</th></tr>
<?php foreach ($tokens as $t): ?>
<tr>
  <td><?=htmlspecialchars($t['label'])?></td>
  <td><?=htmlspecialchars($t['scopes'])?></td>
  <td><?=htmlspecialchars($t['expires_at'])?></td>
</tr>
<?php endforeach; ?>
</table>
