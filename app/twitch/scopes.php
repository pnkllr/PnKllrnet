<?php
// app/twitch/scopes.php
// Grouped Twitch OAuth scopes (Helix + IRC chat). Use what you need in the dashboard.

return [

  // -------------------------
  // User
  // -------------------------
  'User' => [
    'user:read:email'              => 'Get your email address',
    'user:read:blocked_users'      => 'View blocked users',
    'user:manage:blocked_users'    => 'Manage blocked users',
    'user:read:broadcast'          => 'View broadcast settings',
    'user:manage:broadcast'        => 'Manage broadcast settings',
    'user:read:follows'            => 'View followed channels',
    'user:read:subscriptions'      => 'View subscriptions',
    'user:read:moderated_channels' => 'View channels you moderate',
    'user:read:emotes'             => 'View available emotes',
  ],

  // -------------------------
  // Channel
  // -------------------------
  'Channel' => [
    'channel:read:subscriptions'   => 'View channel subscribers',
    'channel:read:goals'           => 'View creator goals',
    'channel:read:polls'           => 'View polls',
    'channel:manage:polls'         => 'Manage polls',
    'channel:read:predictions'     => 'View predictions',
    'channel:manage:predictions'   => 'Manage predictions',
    'channel:manage:raids'         => 'Manage raids',
    'channel:read:redemptions'     => 'View channel point redemptions',
    'channel:manage:redemptions'   => 'Manage channel point redemptions',
    'channel:edit:commercial'      => 'Run commercials',
    'channel:manage:broadcast'     => 'Edit broadcast info (title, category)',
    'channel:read:editors'         => 'View channel editors',
    'channel:manage:moderators'    => 'Add/remove moderators',
    'channel:manage:vips'          => 'Add/remove VIPs',
    'channel:read:vips'            => 'View VIPs',
    'channel:manage:videos'        => 'Manage/delete videos',
    'channel:read:stream_key'      => 'View stream key',
    'channel:read:schedule'        => 'View stream schedule',
    'channel:manage:schedule'      => 'Manage stream schedule',
    'channel:read:guest_star'      => 'View Guest Star info',
    'channel:manage:guest_star'    => 'Manage Guest Star',
    'channel:read:ads'             => 'View ads schedule info',
    'channel:manage:ads'           => 'Manage ad schedule',
    'channel:read:charity'         => 'View charity campaigns',
    'channel:read:hype_train'      => 'View Hype Train info',
  ],

  // -------------------------
  // Moderator
  // -------------------------
  'Moderator' => [
    'channel:moderate'                 => 'Moderate a channel (IRC/Chat)',
    'moderator:read:chat_settings'     => 'View chat settings',
    'moderator:manage:chat_settings'   => 'Manage chat settings',
    'moderator:read:chatters'          => 'View chatters',
    'moderator:manage:banned_users'    => 'Ban/unban users',
    'moderator:read:banned_users'      => 'View banned users',
    'moderator:read:blocked_terms'     => 'View blocked terms',
    'moderator:manage:blocked_terms'   => 'Manage blocked terms',
    'moderator:read:followers'         => 'View followers',
    'moderator:read:automod_settings'  => 'View AutoMod settings',
    'moderator:manage:automod_settings'=> 'Manage AutoMod settings',
    'moderator:read:shoutouts'         => 'View shoutouts',
    'moderator:manage:shoutouts'       => 'Send shoutouts',
    'moderator:manage:announcements'   => 'Send announcements',
    'moderator:read:moderators'        => 'View moderators',
    'moderator:read:vips'              => 'View VIPs',
    'moderator:manage:chat_messages'   => 'Delete chat messages',
    'moderator:read:shield_mode'       => 'View Shield Mode status',
    'moderator:manage:shield_mode'     => 'Toggle Shield Mode',
    'moderator:read:unban_requests'    => 'View unban requests',
    'moderator:manage:unban_requests'  => 'Act on unban requests',
    'moderator:read:suspicious_users'  => 'View suspicious users',
  ],

  // -------------------------
  // Chat & Messaging (IRC/Chat APIs)
  // -------------------------
  'Chat & Messaging' => [
    'chat:read'      => 'Read chat messages (IRC)',
    'chat:edit'      => 'Send chat messages (IRC)',
    'whispers:read'  => 'Read whispers',
    'whispers:edit'  => 'Send whispers',
  ],

  // -------------------------
  // Analytics & Insights
  // -------------------------
  'Analytics' => [
    'analytics:read:extensions'  => 'View extensions analytics',
    'analytics:read:games'       => 'View games analytics',
  ],

  // -------------------------
  // Clips & Media
  // -------------------------
  'Clips & Media' => [
    'clips:edit' => 'Create clips',
  ],

  // -------------------------
  // Monetization
  // -------------------------
  'Monetization' => [
    'bits:read' => 'View Bits info',
  ],
];
