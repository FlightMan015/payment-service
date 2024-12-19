<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Traits;

use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Gateways\Worldpay;
use App\PaymentProcessor\Gateways\WorldpayTokenexTransparent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class RetrieveGatewayForPaymentMethodTraitTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    private mixed $gatewayImplementation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gatewayImplementation = new class () {
            use RetrieveGatewayForPaymentMethodTrait;

            public PaymentMethod $paymentMethod;

            public function callMethod(string $methodName, array $args): mixed
            {
                return $this->$methodName(...$args);
            }

            public function getGateway(): GatewayInterface|null
            {
                return $this->gateway;
            }
        };

        $this->mockWorldPayCredentialsRepository();
    }

    #[Test]
    #[DataProvider('correctGatewayProvider')]
    public function it_returns_corresponding_gateway_as_expected(PaymentGatewayEnum $gateway, string $expectedClass): void
    {
        $this->gatewayImplementation->paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => $gateway->value],
            relationships: ['account' => Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()])]
        );

        $this->gatewayImplementation->callMethod(
            methodName: 'getGatewayInstanceBasedOnPaymentMethod',
            args: [],
        );

        $this->assertInstanceOf($expectedClass, $this->gatewayImplementation->getGateway());
    }

    #[Test]
    public function it_throws_exception_for_non_supporting_gateway(): void
    {
        $this->gatewayImplementation->paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => max(array_column(PaymentGatewayEnum::cases(), 'value')) + 100],
            relationships: ['account' => Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()])]
        );

        $this->expectException(UnsupportedValueException::class);
        $this->expectExceptionMessage(__('messages.gateway.not_implemented'));

        $this->gatewayImplementation->callMethod(
            methodName: 'getGatewayInstanceBasedOnPaymentMethod',
            args: [],
        );
    }

    public static function correctGatewayProvider(): \Iterator
    {
        yield 'worldpay' => [
            'gateway' => PaymentGatewayEnum::WORLDPAY,
            'expectedClass' => Worldpay::class,
        ];
        yield 'worldpay tokenex transparent' => [
            'gateway' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT,
            'expectedClass' => WorldpayTokenexTransparent::class,
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->gatewayImplementation);
    }
}
