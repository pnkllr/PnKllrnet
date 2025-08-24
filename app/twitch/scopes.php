<?php
// app/twitch/scopes.php
return [

  'Clips' => [
    'clips:edit' => 'Create new clips',
  ],

  'Channel (Read)' => [
    'channel:read:ads'            => 'Read ad schedule',
    'channel:read:ads_manager'    => 'Read Ads Manager settings',
    'channel:read:charity'        => 'Read charity activity',
    'channel:read:editors'        => 'See channel editors',
    'channel:read:goals'          => 'Read channel goals',
    'channel:read:hype_train'     => 'Read Hype Train',
    'channel:read:polls'          => 'Read polls',
    'channel:read:predictions'    => 'Read predictions',
    'channel:read:redemptions'    => 'Read Channel Points redemptions',
    'channel:read:stream_key'     => 'Read stream key',
    'channel:read:subscriptions'  => 'Read subscribers',
    'channel:read:vips'           => 'Read VIPs',
  ],

  'Channel (Manage)' => [
    'channel:manage:ads'          => 'Run ads',
    'channel:manage:broadcast'    => 'Manage channel broadcast info',
    'channel:manage:extensions'   => 'Configure channel extensions',
    'channel:manage:moderators'   => 'Manage moderators',
    'channel:manage:polls'        => 'Manage polls',
    'channel:manage:predictions'  => 'Manage predictions',
    'channel:manage:raids'        => 'Send/Cancel raids',
    'channel:manage:redemptions'  => 'Manage Channel Points redemptions',
    'channel:manage:schedule'     => 'Manage stream schedule',
    'channel:manage:videos'       => 'Manage channel videos',
    'channel:manage:vips'         => 'Manage VIPs',
  ],

  'Moderation (Read)' => [
    'moderator:read:announcements'   => 'Read announcements',
    'moderator:read:blocked_terms'   => 'Read blocked terms',
    'moderator:read:chat_settings'   => 'Read chat settings',
    'moderator:read:chatters'        => 'Read chatters list',
    'moderator:read:chat_messages'   => 'Read chat messages',
    'moderator:read:followers'       => 'Read channel followers',
    'moderator:read:shoutouts'       => 'Read shoutouts',
    'moderator:read:suspicious_users'=> 'Read suspicious users',
  ],

  'Moderation (Manage)' => [
    'moderator:manage:announcements' => 'Send announcements',
    'moderator:manage:automod'       => 'Manage AutoMod settings',
    'moderator:manage:blocked_terms' => 'Manage blocked terms',
    'moderator:manage:banned_users'  => 'Ban/timeout users',
    'moderator:manage:chat_messages' => 'Delete chat messages',
    'moderator:manage:chat_settings' => 'Manage chat settings',
    'moderator:manage:shoutouts'     => 'Send shoutouts',
  ],

  'User' => [
    'user:read:email'           => 'Read verified email',
    'user:read:broadcast'       => 'Read user’s broadcast settings',
    'user:read:blocked_users'   => 'Read blocked users',
    'user:manage:blocked_users' => 'Manage blocked users',
    'user:manage:chat_color'    => 'Change chat color',
    'user:edit'                 => 'Edit user description',
    'user:edit:follows'         => 'Follow/Unfollow channels',
  ],

  'Commerce / Bits' => [
    'bits:read' => 'Read Bits leaderboard',
  ],
];
