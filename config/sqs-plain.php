<?php

declare(strict_types=1);

/**
 * List of plain SQS queues and their corresponding handling classes
 */

use App\Jobs\AccountUpdaterResultHandlerJob;

return [
    'handlers' => [
        env(key: 'SQS_PAYMENT_ACCOUNT_UPDATER_QUEUE', default: 'account-updater-queue') => AccountUpdaterResultHandlerJob::class,
    ],

    'default-handler' => null,
];
