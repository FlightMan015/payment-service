<?php

declare(strict_types=1);

return [
    'batch_payment_processing_api_account_id' => env(key: 'BATCH_PAYMENT_PROCESSING_API_ACCOUNT_ID'),
    'scheduled_payments_processing_api_account_id' => env(key: 'SCHEDULED_PAYMENTS_PROCESSING_API_ACCOUNT_ID'),
    'payment_service_api_account_id' => env(key: 'PAYMENT_SERVICE_API_ACCOUNT_CLIENT_ID'),
];
