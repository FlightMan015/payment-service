<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\UpdatePaymentMethodCommand;
use App\Api\Requests\PatchPaymentMethodRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

final class UpdatePaymentMethodCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data, array $expected): void
    {
        $request = new PatchPaymentMethodRequest($data);

        $command = UpdatePaymentMethodCommand::fromRequest($request);

        $this->assertInstanceOf(UpdatePaymentMethodCommand::class, $command);
        $this->assertEquals($expected, $command->toArray());
    }

    /**
     * @return array[]
     */
    public static function commandTestData(): array
    {
        $initialDataSet = [
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

        ];
        $initialExpectation = [
            'name_on_account' => 'John Doe',
            'address_line1' => '123 Main St',
            'address_line2' => 'Apt 1',
            'email' => 'example@example.com',
            'city' => 'Any town',
            'province' => 'NY',
            'postal_code' => '12345',
            'country_code' => 'US',
            'cc_expiration_month' => null,
            'cc_expiration_year' => null,
            'is_primary' => true,
        ];

        return [
            'no cc data' => [
                $initialDataSet,
                $initialExpectation,
            ],
            'with cc data' => [
                array_merge($initialDataSet, [
                    'cc_expiration_month' => 12,
                    'cc_expiration_year' => 2023,
                ]),
                array_replace($initialExpectation, [
                    'cc_expiration_month' => 12,
                    'cc_expiration_year' => 2023,
                ]),
            ],
        ];
    }
}
