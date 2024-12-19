<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\DeletePaymentMethodHandler;
use App\Api\Exceptions\InvalidPaymentMethodStateException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class DeletePaymentMethodHandlerTest extends UnitTestCase
{
    private MockObject|PaymentMethodRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPaymentMethodRepository(softDeleteReturn: true);
    }

    #[Test]
    public function it_returns_true_when_delete_payment_method_success(): void
    {
        $paymentMethod = $this->makePaymentMethodObject(attributes: ['is_primary' => false]);

        $this->assertTrue($this->handler()->handle(paymentMethod: $paymentMethod));
    }

    #[Test]
    public function it_throws_invalid_payment_method_state_exception_when_method_is_primary(): void
    {
        $paymentMethod = $this->makePaymentMethodObject(attributes: ['is_primary' => true]);

        $this->expectException(InvalidPaymentMethodStateException::class);
        $this->handler()->handle(paymentMethod: $paymentMethod);
    }

    private function makePaymentMethodObject(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::factory()->withoutRelationships()->make(attributes: array_merge([
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value
        ], $attributes));
    }

    private function mockPaymentMethodRepository(bool $softDeleteReturn): void
    {
        $repository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $repository->method('softDelete')->willReturn($softDeleteReturn);

        $this->repository = $repository;
    }

    private function handler(PaymentMethodRepository|null $repository = null): DeletePaymentMethodHandler
    {
        return new DeletePaymentMethodHandler(repository: $repository ?? $this->repository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->repository);
    }
}
