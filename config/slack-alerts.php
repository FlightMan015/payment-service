<?php

declare(strict_types=1);

return [
    /*
     * The webhook URLs that we'll use to send a message to Slack.
     */
    'webhook_urls' => [
        'default' => env(key: 'SLACK_NOTIFICATION_WEBHOOK'),
        'data-sync' => env(key: 'SLACK_DATA_SYNC_NOTIFICATION_WEBHOOK'),
    ],

    /*
     * This job will send the message to Slack. You can extend this
     * job to set timeouts, retries, etc...
     */
    'job' => App\Jobs\FailedPaymentSlackAlertJob::class,
];
