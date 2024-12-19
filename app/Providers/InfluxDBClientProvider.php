<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use InfluxDB2\Client as InfluxDB2Client;
use InfluxDB2\Model\WritePrecision;

class InfluxDBClientProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(InfluxDB2Client::class, static function (Application $app) {
            return new InfluxDB2Client([
                'url' => config('influxdb.connection.host'),
                'token' => config('influxdb.connection.token'),
                'bucket' => config('influxdb.connection.bucket'),
                'org' => config('influxdb.connection.organization'),
                'precision' => WritePrecision::MS,
                'debug' => false,
                'tags' => [
                    'environment' => config('app.env')
                ]
            ]);
        });
    }

    /**
     * @return array
     */
    public function provides(): array
    {
        return [
            InfluxDB2Client::class,
        ];
    }
}
