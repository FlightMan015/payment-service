<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\DTO;

use App\Api\DTO\GatewayInitializationDTO;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\PaymentMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class GatewayInitializationDTOTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('paymentMethodProvider')]
    public function it_creates_dto_from_payment_method_as_expected(PaymentMethod $paymentMethod): void
    {
        $dto = GatewayInitializationDTO::fromPaymentMethod($paymentMethod);

        self::assertEquals($paymentMethod->payment_gateway_id, $dto->gatewayId);
        self::assertEquals($paymentMethod->account->area->external_ref_id, $dto->officeId);
        self::assertEquals($paymentMethod->cc_token, $dto->creditCardToken);
        self::assertEquals($paymentMethod->cc_expiration_month, $dto->creditCardExpirationMonth);
        self::assertEquals($paymentMethod->cc_expiration_year, $dto->creditCardExpirationYear);
    }

    public static function paymentMethodProvider(): iterable
    {
        yield 'cc payment method with all data' => [
            'paymentMethod' => static fn () => PaymentMethod::factory()->cc()->makeWithRelationships(
                relationships: [
                    'account' => Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()])
                ]
            ),
        ];
        yield 'cc payment method with no expiration date' => [
            'paymentMethod' => static fn () => PaymentMethod::factory()->cc()->makeWithRelationships(
                attributes: ['cc_expiration_month' => null, 'cc_expiration_year' => null],
                relationships: [
                    'account' => Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()])
                ]
            ),
        ];
        yield 'ach payment method' => [
            'paymentMethod' => static fn () => PaymentMethod::factory()->ach()->makeWithRelationships(
                relationships: [
                    'account' => Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()])
                ]
            ),
        ];
    }
}
