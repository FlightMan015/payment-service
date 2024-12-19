<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

abstract class UnitTestCase extends TestCase
{
    private array $originalDBConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Unit test must not touch the database
        $this->invalidDBConnection();
    }

    protected function tearDown(): void
    {
        // Reset database config to original state
        Config::set('database.connections.' . config('database.default'), $this->originalDBConfig);

        unset($this->originalDBConfig);

        parent::tearDown();
    }

    private function invalidDBConnection(): void
    {
        $this->originalDBConfig = config('database.connections.' . config('database.default'));

        Config::set('database.connections.' . config('database.default') . '.username', null);
        Config::set('database.connections.' . config('database.default') . '.password', null);
    }
}
