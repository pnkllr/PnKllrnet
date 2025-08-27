<?php
// Master list of Twitch OAuth scopes.
// Each scope => human-readable description for dashboard UI.

return [
  'Clips' => [
    'clips:edit' => 'Manage clips. Create clips on your channel.',
  ],

  'Channel Management' => [
    'channel:edit:commercial'      => 'Run commercials on your channel.',
    'channel:manage:broadcast'     => 'Update channel title, category, and stream settings.',
    'channel:manage:extensions'    => 'Manage channel extensions.',
    'channel:manage:moderators'    => 'Add or remove channel moderators.',
    'channel:manage:polls'         => 'Manage channel polls.',
    'channel:manage:predictions'   => 'Manage channel predictions.',
    'channel:manage:raids'         => 'Manage and cancel raids.',
    'channel:manage:redemptions'   => 'Manage custom channel points redemptions.',
    'channel:manage:schedule'      => 'Manage channel stream schedule.',
    'channel:manage:videos'        => 'Manage (delete) channel videos.',
    'channel:read:editors'         => 'View a list of channel editors.',
    'channel:read:goals'           => 'View creator goals.',
    'channel:read:hype_train'      => 'View Hype Train information.',
    'channel:read:polls'           => 'View channel polls.',
    'channel:read:predictions'     => 'View channel predictions.',
    'channel:read:redemptions'     => 'View custom channel points redemptions.',
    'channel:read:stream_key'      => 'View your stream key.',
    'channel:read:subscriptions'   => 'View channel subscriptions (Affiliate/Partner).',
    'channel:read:vips'            => 'View VIP status.',
    'channel:manage:vips'          => 'Add or remove channel VIPs.',
  ],

  'Moderation' => [
    'moderation:read'                 => 'View moderation data for a channel.',
    'moderator:manage:announcements'  => 'Send announcements in chat.',
    'moderator:manage:automod'        => 'Manage Automod settings.',
    'moderator:manage:automod_settings' => 'Manage Automod filters.',
    'moderator:manage:banned_users'   => 'Ban and unban users.',
    'moderator:read:blocked_terms'    => 'View blocked terms.',
    'moderator:manage:blocked_terms'  => 'Manage blocked terms.',
    'moderator:manage:chat_messages'  => 'Delete chat messages.',
    'moderator:read:chat_settings'    => 'View chat settings.',
    'moderator:manage:chat_settings'  => 'Manage chat settings.',
    'moderator:read:chatters'         => 'View list of chatters in channel.',
    'moderator:read:followers'        => 'View channel followers.',
    'moderator:read:shield_mode'      => 'View Shield Mode status.',
    'moderator:manage:shield_mode'    => 'Manage Shield Mode status.',
    'moderator:read:shoutouts'        => 'View outgoing and incoming shoutouts.',
    'moderator:manage:shoutouts'      => 'Send shoutouts.',
  ],

  'User' => [
    'user:edit'             => 'Edit your account description.',
    'user:edit:broadcast'   => 'Edit your broadcast settings (stream title, category).',
    'user:edit:follows'     => 'Follow/unfollow channels for you.',
    'user:manage:blocked_users' => 'Block and unblock other users.',
    'user:read:blocked_users'   => 'View the block list of your account.',
    'user:read:broadcast'       => 'View your broadcast settings.',
    'user:read:email'           => 'View the email address on your account.',
    'user:read:follows'         => 'View the list of channels you follow.',
    'user:read:subscriptions'   => 'View your subscriptions to other channels.',
  ],

  'Analytics' => [
    'analytics:read:extensions' => 'View analytics for your extensions.',
    'analytics:read:games'      => 'View analytics for games you manage.',
  ],

  'Bits' => [
    'bits:read' => 'View bits transactions for a channel.',
  ],

  'Whispers' => [
    'whispers:read'   => 'Read your whispers.',
    'whispers:edit'   => 'Send whispers as you.',
  ],

  'Ads' => [
    'channel:edit:commercial' => 'Run commercials on your channel.',
    'channel:manage:ads'      => 'Manage ads scheduling and preferences.',
  ],
];
