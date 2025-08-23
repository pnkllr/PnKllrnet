<?php
return [
  'clipit' => [
    'label' => 'Clip‑It',
    'url' => '/twitch/tool/clipit.php',
    'required_scopes' => ['clips:edit'],
  ],
  'subs' => [
    'label' => 'Subscribers',
    'url' => '/twitch/tool/subs.php',
    'required_scopes' => ['channel:read:subscriptions'],
  ],
  'redemptions' => [
    'label' => 'Point Redemptions',
    'url' => '/twitch/tool/redemptions.php',
    'required_scopes' => ['channel:read:redemptions'],
  ],
  'overlays' => [
    'label' => 'Overlays Manager',
    'url' => '/twitch/tool/overlays.php',
    'required_scopes' => ['channel:manage:broadcast'],
  ],
];
