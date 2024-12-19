<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env(key: 'QUEUE_CONNECTION', default: 'sqs'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env(key: 'AWS_ACCESS_KEY_ID'),
            'secret' => env(key: 'AWS_SECRET_ACCESS_KEY'),
            'token' => env(key: 'AWS_SESSION_TOKEN'),
            'prefix' => env(key: 'SQS_PREFIX'),
            'queue' => env(key: 'SQS_QUEUE', default: 'default'),
            'queues' => [
                'process_payments' => env(key: 'SQS_PROCESS_PAYMENTS_QUEUE', default: 'process-payments-queue'),
                'collect_metrics' => env(key: 'SQS_COLLECT_METRICS_QUEUE', default: 'collect-metrics-queue'),
                'process_failed_jobs' => env(key: 'SQS_PROCESS_FAILED_JOBS_QUEUE', default: 'process-failed-jobs-queue'),
                'notifications' => env(key: 'SQS_NOTIFICATIONS_QUEUE', default: 'notifications-queue'),
            ],
            'suffix' => env(key: 'SQS_SUFFIX'),
            'region' => env(key: 'AWS_DEFAULT_REGION', default: 'us-east-1'),
            'after_commit' => false,
        ],

        'sqs-plain' => [
            'driver' => 'sqs-plain',
            'token' => new Aws\Token\Token(env(key: 'AWS_SESSION_TOKEN')),
            'prefix' => env(key: 'SQS_PREFIX'),
            'queue' => env(key: 'SQS_PAYMENT_ACCOUNT_UPDATER_QUEUE', default: 'account-updater-queue'),
            'suffix' => env(key: 'SQS_SUFFIX'),
            'region' => env(key: 'AWS_DEFAULT_REGION', default: 'us-east-1'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env(key: 'QUEUE_FAILED_DRIVER', default: 'database-uuids'),
        'database' => env(key: 'DB_CONNECTION', default: 'pgsql'),
        'table' => 'billing.failed_jobs',
    ],

];
