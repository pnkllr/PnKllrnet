<?php
declare(strict_types=1);

return [
  [
    'name' => 'Create Clip',
    'desc' => 'Create a Twitch clip for your channel.',
    'url'  => base_url('/tools/clipit.php') . '?channel={self}&format=text',
    'method' => 'GET',
    'required_scopes' => ['clips:edit'],
  ],
];
