<?php
require_once dirname(__DIR__, 2) . '/core/init.php';
require_once BASE_PATH . '/app/Twitch/helpers.php';   // <-- add this

$u = Auth::requireUser();
CSRF::check();

$scopes = $_POST['scopes'] ?? [];
if (!is_array($scopes)) $scopes = [];

ScopePrefs::saveDesired((int)$u['id'], $scopes);

// Send to reauth with selected scopes; after Twitch returns, callback saves tokens
$next = '/';
$sc   = urlencode(implode(' ', scopes_normalize($scopes)));
header('Location: ' . base_url('/auth/reauth.php?scopes=' . $sc . '&next=' . urlencode($next)));
exit;
