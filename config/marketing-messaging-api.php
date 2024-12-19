<?php

declare(strict_types=1);

return [
    'key' => env(key: 'MARKETING_API_KEY'),
    'url' => env(key: 'MARKETING_MESSAGING_API_URL'),
    'endpoint' => '/v1/send-message',
    'customer_support_email' => env(key: 'CUSTOMER_SUPPORT_EMAIL', default: 'customersupport@goaptive.com'),
    'receivers' => [
        'failed_payment_refunds' => env(key: 'FAILED_REFUNDS_REPORT_RECEIVER'),
    ]
];
