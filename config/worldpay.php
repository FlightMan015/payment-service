<?php

declare(strict_types=1);

return [
    'dynamo_db_credentials_table' => env(key: 'WORLDPAY_CREDENTIALS_TABLE_DYNAMO_DB'),
    'application_name' => env(key: 'WORLDPAY_APPLICATION_NAME', default: 'Aptive'),
];
