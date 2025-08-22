<?php
return [
    // Twitch OAuth
    'twitch_client_id'     => 'client_id',
    'twitch_client_secret' => 'client_secret',
    
    // Admins: Twitch IDs who can access /admin
    'admin_user_ids' => [
        '15624184',
    ],
    
    // Session
    'session_name'  => 'pnkllr_session',

    // MySQL DB
    'db_host' => 'localhost',
    'db_name' => 'db_name',
    'db_user' => 'db_user',
    'db_pass' => 'dp_pass',

    // Scopes you need (adjust as required)
    'twitch_scopes' => [
        'openid',
        'user:read:email',
        'user:read:follows',
        'clips:edit',
        'channel:read:subscriptions',
        'channel:read:goals',
        'channel:read:redemptions',
        'analytics:read:games'
    ],
];