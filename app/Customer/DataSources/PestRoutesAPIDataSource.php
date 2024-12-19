<?php

declare(strict_types=1);

namespace Customer\DataSources;

use Aptive\PestRoutesSDK\Client;
use Aptive\PestRoutesSDK\DynamoDbCredentialsRepository;

class PestRoutesAPIDataSource
{
    /**
     * @return Client
     */
    public function getAPIClient(): Client
    {
        $repository = new DynamoDbCredentialsRepository(config('pestroutes-api.pestroutes_credentials_table_dynamo_db'));

        return new Client(
            baseUrl: rtrim(config('pestroutes-api.url'), '/') . '/',
            repository: $repository,
            logger: null
        );
    }
}
