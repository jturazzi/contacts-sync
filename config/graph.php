<?php

return [

    'base_url' => env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),

    'scopes' => [
        'openid',
        'profile',
        'email',
        'offline_access',
        'User.Read',
        'User.Read.All',
        'Contacts.ReadWrite',
    ],

    'directory_refresh_interval_minutes' => env('GRAPH_DIRECTORY_REFRESH_MINUTES', 15),

    'sync_interval_minutes' => env('GRAPH_SYNC_INTERVAL_MINUTES', 15),

    'sync_log_retention_days' => env('SYNC_LOG_RETENTION_DAYS', 90),

];
