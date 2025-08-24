<?php
// views/layout.php
// expects: $tpl (e.g., "dashboard") and any extracted $vars from the controller
$tplFile = __DIR__ . '/' . basename($tpl) . '.php';
$isAuthed = !empty($_SESSION['user_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>PnKllr Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="/assets/app.css" rel="stylesheet">
</head>
<body>
  <header class="header">
    <div class="brand">
      <span>🟣</span>
      <span>PnKllr</span>
    </div>
    <nav class="nav">
      <a href="/">Dashboard</a>
      <?php if ($isAuthed): ?>
        <a href="/logout">Logout</a>
      <?php else: ?>
        <a href="/login">Login</a>
      <?php endif; ?>
    </nav>
  </header>

  <main class="container">
    <?php
      if (is_file($tplFile)) {
        include $tplFile;
      } else {
        echo '<div class="card"><div class="sectionTitle">Template not found</div><p class="muted">'
           . htmlspecialchars($tplFile)
           . '</p></div>';
      }
    ?>
  </main>
</body>
</html>
