<?php
require_once __DIR__ . '/../../../core/bootstrap.php';

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Request the scopes YOU added to the app:
$scopes = [
  'user:read:email',
  'channel:read:subscriptions',
  'clips:edit'
];
$redirect = rtrim(BASE_URL, '/') . '/twitch/auth/callback.php';
$params = [
  'client_id'     => TWITCH_CLIENT_ID,
  'redirect_uri'  => $redirect,
  'response_type' => 'code',
  'scope'         => implode(' ', $scopes),
  'state'         => $state,
  'force_verify'  => 'true',
];

header('Location: https://id.twitch.tv/oauth2/authorize?' . http_build_query($params));
exit;
