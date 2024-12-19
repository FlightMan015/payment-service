<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\DataSource;

use Aptive\PestRoutesSDK\Client;
use Customer\DataSources\PestRoutesAPIDataSource;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PestRoutesAPIDataSourceTest extends UnitTestCase
{
    private PestRoutesAPIDataSource $dataSource;

    private int $officeId;
    private string $url = '';
    private string $key  = '';
    private string $token  = '';

    protected function setup(): void
    {
        parent::setUp();

        $this->setupDataSource();
    }

    private function setupDataSource(): void
    {
        $this->officeId = 1;
        Config::set('pestroutes-api.auth.offices.1.authenticationKey', 'someAuthKey');
        Config::set('pestroutes-api.auth.offices.1.authenticationToken', 'someAuthToken');
        Config::set('pestroutes-api.auth.offices.1.url', 'https://sometestpestroutesurl.com');
        $this->dataSource = new PestRoutesAPIDataSource();
    }

    #[Test]
    public function it_should_return_client_instance(): void
    {
        $client = $this->dataSource->getAPIClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->dataSource, $this->officeId, $this->url, $this->key, $this->token);
    }
}
