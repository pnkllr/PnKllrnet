<?php
require_once dirname(__DIR__, 2) . '/core/init.php';
require_once BASE_PATH . '/app/Twitch/helpers.php';   // <-- add this

$u = Auth::requireUser();

$scopesParam = $_GET['scopes'] ?? '';
$scopes = $scopesParam ? preg_split('/\s+/', $scopesParam) : ScopePrefs::getDesired((int)$u['id']);

Session::set('auth_mode', 'reauth');
Session::set('requested_scopes', scopes_normalize($scopes));
if (!empty($_GET['next'])) {
  Session::set('next_after_auth', (string)$_GET['next']);
}

$state = bin2hex(random_bytes(16));
Session::set('state', $state);

$tw = new TwitchClient();
header('Location: ' . $tw->authUrl($state, $scopes));
exit;
