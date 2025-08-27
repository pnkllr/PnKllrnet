<?php
$u        = Auth::user();
$brand    = getenv('APP_NAME') ?: 'PnKllrnet';
$titleStr = $title ?? $brand;
$path     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAdmin  = $u && (($u['role'] ?? 'user') === 'admin');

function nav_active(string $current, string $target): string {
  if ($target === '/') return $current === '/' ? 'active' : '';
  return str_starts_with($current, $target) ? 'active' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($titleStr) ?></title>

  <!-- Theme color for mobile address bar -->
  <meta name="theme-color" content="#0a0c12">

  <!-- Main styles (includes header styles already) -->
  <link rel="stylesheet" href="/assets/css/style.css">

  <!-- If you still have a separate header.css, keep it; otherwise you can delete this line -->
  <link rel="stylesheet" href="/assets/css/header.css">

  <link rel="icon" href="data:,">
</head>
<body>
  <!-- Site Header -->
  <header class="site-header">
    <nav class="nav-inner" aria-label="Primary">
      <!-- Brand -->
      <a class="brand" href="/" aria-label="<?= htmlspecialchars($brand) ?> Home">
        <span class="brand-glyph" aria-hidden="true">
          <!-- Simple spark/bolt glyph that fits the theme -->
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
            <path fill="currentColor" d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/>
          </svg>
        </span>
        <span class="brand-name"><?= htmlspecialchars($brand) ?></span>
      </a>

      <!-- Desktop nav -->
      <div class="nav-links" role="navigation">
        <a class="nav-link <?= nav_active($path,'/') ?>" href="/">Dashboard</a>
        <?php if ($isAdmin): ?>
          <a class="nav-link <?= nav_active($path,'/admin') ?>" href="/admin">Admin</a>
        <?php endif; ?>
        <a class="nav-link" href="https://discord.gg/nth7y8TqMT" target="_blank" rel="noopener">Discord</a>
      </div>

      <!-- User area (desktop) -->
      <div class="nav-user">
        <?php if ($u): ?>
          <details class="user-menu">
            <summary aria-label="Account menu">
              <?php if (!empty($u['avatar'])): ?>
                <img class="u-av" src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
              <?php else: ?>
                <span class="u-avatar-fallback" aria-hidden="true">
                  <?= strtoupper(substr((string)($u['display'] ?? 'U'),0,1)) ?>
                </span>
              <?php endif; ?>
              <span class="u-name"><?= htmlspecialchars($u['display'] ?? 'User') ?></span>
              <svg class="chev" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                <path fill="currentColor" d="M7 10l5 5 5-5z"/>
              </svg>
            </summary>
            <div class="menu" role="menu">
              <a class="menu-item danger" href="/auth/logout.php">Logout</a>
            </div>
          </details>
        <?php endif; ?>
      </div>

      <!-- Mobile menu (no JS) -->
      <details class="nav-mobile">
        <summary aria-label="Open menu">
          <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
            <path fill="currentColor" d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/>
          </svg>
        </summary>
        <div class="sheet">
          <a class="sheet-link <?= nav_active($path,'/') ?>" href="/">Dashboard</a>
          <?php if ($isAdmin): ?>
            <a class="sheet-link <?= nav_active($path,'/admin') ?>" href="/admin">Admin</a>
          <?php endif; ?>
          <a class="sheet-link" href="https://discord.gg/nth7y8TqMT" target="_blank" rel="noopener">Discord</a>

          <?php if ($u): ?>
            <hr>
            <div class="sheet-user">
              <?php if (!empty($u['avatar'])): ?>
                <img class="u-av" src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
              <?php else: ?>
                <span class="u-avatar-fallback" aria-hidden="true">
                  <?= strtoupper(substr((string)($u['display'] ?? 'U'),0,1)) ?>
                </span>
              <?php endif; ?>
              <div class="u-col">
                <div class="u-name"><?= htmlspecialchars($u['display'] ?? 'User') ?></div>
                <?php if (!empty($u['login'])): ?>
                  <div class="u-login">@<?= htmlspecialchars($u['login']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <a class="sheet-link danger" href="/auth/logout.php">Logout</a>
          <?php endif; ?>
        </div>
      </details>
    </nav>
  </header>

  <div class="container">
