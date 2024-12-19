<?php

declare(strict_types=1);

namespace App\Providers;

use Aptive\PestRoutesSDK\Client;
use Aptive\PestRoutesSDK\CredentialsRepository;
use Aptive\PestRoutesSDK\DynamoDbCredentialsRepository;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class PestRoutesServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $dynamoDbRepository = new DynamoDbCredentialsRepository(
            config(key: 'pestroutes-api.pestroutes_credentials_table_dynamo_db')
        );
        $this->app->singleton(abstract: CredentialsRepository::class, concrete: static fn () => $dynamoDbRepository);

        $pestRoutesClient = new Client(
            baseUrl: $this->addSlashToUrlIfMissing(url: config('pestroutes-api.url')),
            repository: $this->app->get(CredentialsRepository::class),
            logger: $this->app->get(LoggerInterface::class),
        );
        $this->app->bind(abstract: Client::class, concrete: static fn () => $pestRoutesClient);
    }

    /**
     * It will add the slash to the given url if that URL doesn't have a slash in the end
     *
     * @param string $url
     *
     * @return string
     */
    private function addSlashToUrlIfMissing(string $url): string
    {
        return rtrim(string: $url, characters: '/') . '/';
    }
}
