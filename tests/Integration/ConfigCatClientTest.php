<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration;

use ConfigCat\ClientInterface;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigCatClientTest extends TestCase
{
    #[Test]
    public function it_resolves_configcat_client_as_a_singleton(): void
    {
        Config::set('configcat.auth.sdk_key', 'configcat-sdk-1/abcd1234abcd1234abcd12/abcd1234abcd1234abcd12');
        $client = app()->make(ClientInterface::class);
        $client2 = app()->make(ClientInterface::class);

        $this->assertSame($client, $client2);
    }
}
