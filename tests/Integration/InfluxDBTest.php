<?php

declare(strict_types=1);

namespace Tests\Integration;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use InfluxDB2\Client as InfluxDBClient;
use InfluxDB2\Point;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * This test verifies that the influxDB client is setup correctly in a provider
 * AND that we can connect to influxDB using it
 */
class InfluxDBTest extends TestCase
{
    #[Test]
    public function if_can_create_an_influx_db_client(): void
    {
        $client = App::make(InfluxDBClient::class);

        $this->assertInstanceOf(InfluxDBClient::class, $client);
    }

    #[Test]
    public function it_can_query_influx_db_using_the_client(): void
    {
        $this->expectNotToPerformAssertions(); // If no exception thrown then the test is considered passed

        $client = App::make(InfluxDBClient::class);

        $writeApi = $client->createWriteApi();

        $point = Point::measurement('test_measurement')
            ->addField('count', 1)
            ->time(Carbon::now()->getTimestampMs());

        $writeApi->write($point);
    }
}
