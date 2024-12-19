<?php

declare(strict_types=1);

return [
    'auth_url' => env(key: 'AUTH_URL'),
    'auth_target_entity' => env(key: 'CRM_TARGET_ENTITY_ID'),
    'client_id' => env(key: 'PAYMENT_SERVICE_API_ACCOUNT_CLIENT_ID'),
    'client_secret' => env(key: 'PAYMENT_SERVICE_API_ACCOUNT_CLIENT_SECRET'),
    'base_url' => env(key: 'CRM_BASE_URL'),
    'endpoints' => [
        'get_subscription' => '/v1/subscriptions/%s',
    ]
];
