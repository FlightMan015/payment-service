<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CreatePaymentMethodCommand;
use App\Api\Requests\PostPaymentMethodRequest;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\CreditCardTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

final class CreatePaymentMethodCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data, array $expected): void
    {
        $request = new PostPaymentMethodRequest($data);

        $command = CreatePaymentMethodCommand::fromRequest($request);

        $this->assertInstanceOf(CreatePaymentMethodCommand::class, $command);
        $this->assertEquals($expected, $command->toArray());
    }

    /**
     * @return array[]
     */
    public static function commandTestData(): array
    {
        $uuid = Str::uuid()->toString();
        $initialDataSet = [
            'account_id' => $uuid,
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'type' => PaymentTypeEnum::CC->name,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line1' => '123 Main St',
            'address_line2' => 'Apt 1',
            'email' => 'example@example.com',
            'city' => 'Any town',
            'province' => 'NY',
            'postal_code' => '12345',
            'country_code' => 'US',
            'is_primary' => true,
            'should_skip_gateway_validation' => false,
        ];

        return [
            'CC data' => [
                array_merge($initialDataSet, [
                    'type' => PaymentTypeEnum::CC->name,
                    'cc_token' => 'some token',
                    'cc_type' => CreditCardTypeEnum::VISA->value,
                    'cc_expiration_month' => 12,
                    'cc_expiration_year' => 2023,
                    'cc_expiration' => '2029-01-01',
                    'cc_last_four' => '1234',
                ]),

                array_merge($initialDataSet, [
                    'type' => PaymentTypeEnum::fromName(PaymentTypeEnum::CC->name),
                    'ach_account_number' => null,
                    'ach_routing_number' => null,
                    'ach_account_last_four' => '',
                    'ach_account_type_id' => null,
                    'ach_bank_name' => null,
                    'cc_token' => 'some token',
                    'cc_type' => CreditCardTypeEnum::VISA->value,
                    'cc_expiration_month' => 12,
                    'cc_expiration_year' => 2023,
                    'cc_last_four' => '1234',
                ]),
            ],
            'ACH data' => [
                array_merge($initialDataSet, [
                    'type' => PaymentTypeEnum::ACH->name,
                    'ach_account_number' => 'abc123',
                    'ach_routing_number' => '123456789',
                    'ach_account_last_four' => '1234',
                    'ach_account_type_id' => AchAccountTypeEnum::PERSONAL_CHECKING->value,
                    'ach_bank_name' => 'Bank Name',
                ]),
                array_merge($initialDataSet, [
                    'type' => PaymentTypeEnum::fromName(PaymentTypeEnum::ACH->name),
                    'ach_account_number' => 'abc123',
                    'ach_routing_number' => '123456789',
                    'ach_account_last_four' => '1234',
                    'ach_account_type_id' => AchAccountTypeEnum::PERSONAL_CHECKING->value,
                    'ach_bank_name' => 'Bank Name',
                    'cc_token' => null,
                    'cc_type' => null,
                    'cc_expiration_month' => null,
                    'cc_expiration_year' => null,
                    'cc_last_four' => '',
                ]),
            ],
        ];
    }
}
