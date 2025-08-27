<?php
declare(strict_types=1);

/** Normalize an array of scopes: trim, uniq, preserve case (Twitch is case-sensitive in docs but treats lowercase fine). */
function scopes_normalize_array(array $scopes): array {
  $map = [];
  foreach ($scopes as $s) {
    $s = trim((string)$s);
    if ($s !== '') $map[$s] = true;
  }
  return array_keys($map);
}

/** Missing = required - granted */
function scopes_missing(array $required, array $granted): array {
  $r = scopes_normalize_array($required);
  $g = scopes_normalize_array($granted);
  return array_values(array_diff($r, $g));
}

/** Read granted scopes for the current user from oauth_tokens */
function get_granted_scopes_for_user(int $userId): array {
  $row = $GLOBALS['_db']->query("SELECT scopes FROM oauth_tokens WHERE user_id=?", [$userId])->fetch();
  $s = trim((string)($row['scopes'] ?? ''));
  return $s === '' ? [] : preg_split('/\\s+/', $s);
}

/** Build a reauth URL to ask only for the missing scopes and bounce back to /dashboard */
function build_grant_url(array $missing, ?string $next = '/'): string {
  // default & allow-list internal paths only
  $next = $next ?: '/';
  if ($next[0] !== '/') $next = '/';

  $qs = http_build_query([
    'scopes' => implode(' ', scopes_normalize_array($missing)),
    'next'   => $next,
  ]);
  return base_url('/auth/reauth.php?' . $qs);
}
