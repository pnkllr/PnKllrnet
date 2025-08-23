<?php
function load_env(string $path, bool $override = true): void {
  if (!is_file($path)) return;
  foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $k = trim($k);
    $v = trim($v, " \t\n\r\0\x0B'\"");
    if ($k === '') continue;
    if ($override || getenv($k) === false) {
      putenv("$k=$v");
      $_ENV[$k]    = $v;
      $_SERVER[$k] = $v;
    }
  }
}
