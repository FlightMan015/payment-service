<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Aptive\Worldpay\CredentialsRepository\DynamoDbCredentialsRepository;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;

trait WorldPayCredentialsRepositoryMockingTrait
{
    private function mockWorldPayCredentialsRepository(): void
    {
        $credentialsRepositoryMock = $this->createMock(DynamoDbCredentialsRepository::class);
        $credentialsRepositoryMock->method('get')->willReturn(WorldpayCredentialsStub::make());

        $this->app->instance(CredentialsRepository::class, $credentialsRepositoryMock);
    }
}
