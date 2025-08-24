<!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>PnKllr Dashboard</title>
<style>body{font-family:system-ui;margin:2rem;max-width:900px} nav a{margin-right:1rem}</style>
</head><body>
<nav>
  <a href="/">Dashboard</a>
  <?php if (!empty($_SESSION['user_id'])): ?>
    <a href="/logout">Logout</a>
  <?php else: ?>
    <a href="/login">Login with Twitch</a>
  <?php endif; ?>
</nav>
<hr>
<?php include __DIR__ . "/$tpl"; ?>
</body></html>
