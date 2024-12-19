<?php

declare(strict_types=1);

namespace Tests\Integration;

use Aptive\PestRoutesSDK\Client as PestroutesClient;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PestroutesSdkClientProviderTest extends TestCase
{
    #[Test]
    public function it_can_make_a_pestroutes_sdk_client(): void
    {
        $client = App::make(PestroutesClient::class);

        $this->assertInstanceOf(PestroutesClient::class, $client);
    }

    #[Test]
    public function it_uses_a_singleton_for_the_pestroutes_sdk_client(): void
    {
        $client1 = App::make(PestroutesClient::class);
        $client2 = App::make(PestroutesClient::class);

        $this->assertSame($client1, $client2);
    }
}
