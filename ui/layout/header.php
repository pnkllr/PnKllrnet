<?php $u = Auth::user(); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? getenv('APP_NAME') ?? 'PnKllrnet') ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" href="data:,">
</head>
<body>
  <nav class="nav">
    <div class="brand"><?= htmlspecialchars(getenv('APP_NAME') ?: 'PnKllrnet') ?></div>
    <a href="/">Dashboard</a>
    <?php if ($u && ($u['role'] ?? 'user') === 'admin'): ?>
      <a href="/admin">Admin</a>
    <?php endif; ?>
    <a href="https://discord.gg/nth7y8TqMT">Discord</a>
    <div class="right">
    <?php if ($u): ?>
      <span class="flex">
        <?php if (!empty($u['avatar'])): ?>
          <img class="avatar" src="<?= htmlspecialchars($u['avatar']) ?>" alt="avatar">
        <?php endif; ?>
        <span><?= htmlspecialchars($u['display']) ?></span>
        <a class="badge" href="/auth/logout.php">Logout</a>
      </span>
    <?php endif; ?>
    </div>
  </nav>
  <div class="container">
