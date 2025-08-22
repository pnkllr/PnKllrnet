<?php
function render_page(string $title, string $content): void { $base = defined('BASE_URL')?BASE_URL:''; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($title) ?></title><link rel="stylesheet" href="<?= $base ?>/assets/app.css"></head>
<body>
<header class="header"><div class="brand">🛠️ PnKllr.net</div>
  <nav class="nav"><a href="<?= $base ?>/dashboard/">Dashboard</a><a href="<?= $base ?>/admin/">Admin</a><a href="<?= $base ?>/logout">Logout</a></nav>
</header>
<main class="container"><?= $content ?></main>
<footer class="container" style="color:#9aa4b2;font-size:13px;padding:20px 0;border-top:1px solid #1f2937;margin-top:20px">
  © <?= date('Y') ?> PnKllr.net · <?= number_format((microtime(true)-APP_START)*1000,1) ?>ms
</footer>
</body></html><?php }
