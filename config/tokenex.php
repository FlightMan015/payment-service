<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tokenex URL
    |--------------------------------------------------------------------------
    | Tokenex URL that will be used for Transparent Gateway operations
    | https://docs.tokenex.com/docs/select-a-tgapi-endpoint-1
    |
    | Example: https://test-tgapi.tokenex.com/detokenize
     */
    'url' => env(key: 'TOKENEX_URL'),

    /*
    |--------------------------------------------------------------------------
    | Tokenex ID
    |--------------------------------------------------------------------------
    | Tokenex ID can be retrieved from the Tokenex Customer Portal, used for iframe
    |
    | Example: 123456789
    */

    'iframe_tokenex_id' => env(key: 'TOKENEX_ID'),

    /*
    |--------------------------------------------------------------------------
    | Tokenex Client Secret Key
    |--------------------------------------------------------------------------
    | Tokenex Client Secret Key can be retrieved from the Tokenex Customer Portal, used for iframe
    |
    | Example: A1b2C3D4e5F6h7I8j9K0l1M2n3O4p
    */
    'iframe_client_secret_key' => env(key: 'TOKENEX_CLIENT_SECRET_KEY'),

    /*
     * Tokenex Client credential for service API.
     * Those credential can be retrieved from the Tokenex Customer Portal
     */
    'service_token_id' => env(key: 'TOKENEX_SERVICE_TOKEN_ID'),
    'service_client_secret' => env(key: 'TOKENEX_SERVICE_CLIENT_SECRET'),
];
