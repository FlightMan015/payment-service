<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Subdomain URL
    |--------------------------------------------------------------------------
    |
    | This option controls the subdomain url for the pestroutes api. The URL for
    | the pestroutes API should exclude the specific endpoint.
    |
    | Example: https://subdomain.pestroutes.com/api
    |
    */

    'url' => env(key: 'PESTROUTES_API_URL', default: ''),

    /*
    |--------------------------------------------------------------------------
    | Dynamo DB table which store for pestroutes API credentials
    |--------------------------------------------------------------------------
    |
    | This option controls the table name in dynamo db for the pestroutes api
    |
    | Example: my_table_name
    |
    */
    'pestroutes_credentials_table_dynamo_db' => env(key: 'PESTROUTES_CREDENTIALS_TABLE_DYNAMO_DB', default: 'pestroutes-credentials'),
];
