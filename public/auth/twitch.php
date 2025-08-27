<?php
require_once dirname(__DIR__, 2) . '/core/init.php';
Session::regenerate();

// mark this as *initial* login, not reauth
Session::set('auth_mode', 'login');

$state = bin2hex(random_bytes(16));
Session::set('state', $state);

// keep scopes minimal here (or empty array)
$scopes = []; // or ['user:read:email']
$tw = new TwitchClient();
header('Location: ' . $tw->authUrl($state, $scopes));
exit;
