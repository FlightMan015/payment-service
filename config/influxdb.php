<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Influx DB Client
    |--------------------------------------------------------------------------
    |
    | This is the configuration for connecting to an influxDB database via the
    | php client
    |
    */

    'connection' => [
        'host' => env(key: 'INFLUXDB_HOST'),
        'organization' => env(key: 'INFLUXDB_ORGANIZATION', default: 'Application Metrics'),
        'bucket' => env(key: 'INFLUXDB_BUCKET'),
        'token' => env(key: 'INFLUXDB_TOKEN'),
    ],
];
