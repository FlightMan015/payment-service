<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Api\DTO\GatewayInitializationDTO;
use App\Api\Exceptions\UnsupportedValueException;
use App\Factories\PaymentGatewayFactory;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Gateways\Worldpay;
use App\PaymentProcessor\Gateways\WorldpayTokenexTransparent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class PaymentGatewayFactoryTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWorldPayCredentialsRepository();
    }

    #[Test]
    #[DataProvider('gatewayProvider')]
    public function make_for_payment_method_method_returns_correct_gateway(
        PaymentGatewayEnum $gatewayType,
        string $expectedGatewayClass
    ): void {
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => $gatewayType->value],
            relationships: ['account' => Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()])]
        );
        $gateway = PaymentGatewayFactory::makeForPaymentMethod($paymentMethod);

        $this->assertInstanceOf(expected: $expectedGatewayClass, actual: $gateway);
    }

    #[Test]
    public function make_method_throws_exception_when_attributes_has_invalid_gateway_id_value(): void
    {
        $this->expectException(UnsupportedValueException::class);
        $this->expectExceptionMessage(__('messages.gateway.not_implemented'));

        PaymentGatewayFactory::make(new GatewayInitializationDTO(
            gatewayId: -1,
            officeId: 1,
            creditCardToken: null,
            creditCardExpirationMonth: null,
            creditCardExpirationYear: null,
        ));
    }

    public static function gatewayProvider(): iterable
    {
        yield 'WorldPay' => ['gatewayType' => PaymentGatewayEnum::WORLDPAY, 'expectedGatewayClass' => Worldpay::class];
        yield 'WorldPay TokenEx Transparent' => [
            'gatewayType' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT,
            'expectedGatewayClass' => WorldpayTokenexTransparent::class
        ];
    }
}
