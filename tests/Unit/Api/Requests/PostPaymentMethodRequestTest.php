<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PostPaymentMethodRequest;
use App\Rules\GatewayExistsAndActive;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractApiRequestTest;

class PostPaymentMethodRequestTest extends AbstractApiRequestTest
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGatewayAlwaysExists();
    }

    /**
     * @return PostPaymentMethodRequest
     */
    public function getTestedRequest(): PostPaymentMethodRequest
    {
        return new PostPaymentMethodRequest();
    }

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        if (!empty($data['gateway_id'])) {
            $gatewayExistsActive = Mockery::mock(GatewayExistsAndActive::class)->makePartial();
            $gatewayExistsActive->shouldReceive('passes')->with('gateway_id', $data['gateway_id'])->andReturn(false);

            $validator->setRules(array_merge($this->rules, [
                'gateway_id' => ['bail', 'required', 'integer', $gatewayExistsActive],
            ]));
        }

        $this->assertFalse($validator->passes());
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        if (!empty($data['gateway_id'])) {
            $gatewayExistsActive = Mockery::mock(GatewayExistsAndActive::class)->makePartial();
            $gatewayExistsActive->shouldReceive('passes')->with('gateway_id', $data['gateway_id'])->andReturn(true);

            $validator->setRules(array_merge($this->rules, [
                'gateway_id' => ['bail', 'required', 'integer', $gatewayExistsActive],
            ]));
        }

        $this->assertTrue($validator->passes());
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();

        yield 'invalid empty input' => [
            'data' => [],
        ];

        $input = $fullValidParameters;
        $input['account_id'] = 'John\n123';
        yield 'invalid account_id (string)' => [
            'data' => $input,
        ];
        $input['account_id'] = null;
        yield 'missing account_id' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['gateway_id'] = 'John123';
        yield 'invalid gateway_id (string)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['type'] = 123;
        yield 'invalid type (integer)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['type'] = 'VASSA';
        yield 'invalid type (VASSA)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['first_name'] = 'John `';
        yield 'invalid first_name (includes backtick)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['address_line1'] = 'string ` backtick';
        yield 'invalid address_line1 format' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['country_code'] = 'SAMPLE';
        yield 'invalid country_code' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['email'] = 'non-email';
        yield 'invalid email' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_month'] = 123;
        yield 'invalid cc_expiration_month' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_month'] = 123;
        yield 'invalid cc_expiration_month (int)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_month'] = 'non';
        yield 'invalid cc_expiration_month (string)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_month'] = 0;
        yield 'invalid cc_expiration_month (0)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_year'] = 'string';
        yield 'invalid cc_expiration_year' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_year'] = -111;
        yield 'invalid cc_expiration_year (negative)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_expiration_year'] = 25;
        yield 'invalid cc_expiration_year (format)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_token'] = 25;
        yield 'invalid cc_token (format)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['cc_type'] = 'XYZ';
        yield 'invalid cc_type' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['city'] = 25;
        yield 'invalid city (integer)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['province'] = 'ACG';
        yield 'invalid province (size 3)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['province'] = 1;
        yield 'invalid province (int)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['postal_code'] = 'AqweqweqweqweCG';
        yield 'invalid postal_code (max chars)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['postal_code'] = 1;
        yield 'invalid postal_code (int)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['country_code'] = 'AqweqweqweqweCG';
        yield 'invalid country_code (max chars)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['country_code'] = 1;
        yield 'invalid country_code (int)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        $input['is_primary'] = 3333;
        yield 'invalid is_primary (int)' => [
            'data' => $input,
        ];

        $validACHInput = $fullValidParameters;
        $validACHInput['type'] = 'ACH';
        $validACHInput['ach_account_number'] = '123123';
        $validACHInput['ach_routing_number'] = '111000025';
        $validACHInput['ach_account_last_four'] = '2443';

        $input = $validACHInput;
        $input['ach_account_number'] = 'jashdbE$^&*';
        yield 'invalid ACH ach_account_number format' => [
            'data' => $input,
        ];

        $input = $validACHInput;
        $input['ach_routing_number'] = '123';
        yield 'invalid ACH ach_routing_number format' => [
            'data' => $input,
        ];

        $input = $validACHInput;
        $input['ach_account_last_four'] = '1A23';
        yield 'invalid ACH ach_account_last_four format' => [
            'data' => $input,
        ];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();
        yield 'valid data with full input (CC)' => [
            'data' => $fullValidParameters,
        ];

        $input = $fullValidParameters;
        $input['type'] = 'ACH';
        $input['ach_account_number'] = '123123';
        $input['ach_routing_number'] = '111000025';
        $input['ach_account_last_four'] = '2443';
        $input['ach_bank_name'] = 'ASDA Bank';
        yield 'valid data with full input (ACH)' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['address_line2']);
        yield 'valid data without address_line2' => [
            'data' => $input,
        ];

        unset($input['address_line3']);
        yield 'valid data without address_line3' => [
            'data' => $input,
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'account_id' => Str::uuid()->toString(),
            'gateway_id' => 1,
            'type' => 'CC',
            'first_name' => 'First',
            'last_name' => 'Last Name',
            'description' => 'Some description',
            'address_line1' => '322',
            'address_line2' => 'Broadway',
            'address_line3' => 'Non-line',
            'email' => 'sample@email.sample',
            'city' => 'Kingston',
            'province' => 'GA',
            'postal_code' => '12401',
            'country_code' => 'US',
            'is_primary' => false,
            'should_skip_gateway_validation' => false,
            'cc_type' => 'VISA',
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date('Y', strtotime('+20 years')),
        ];
    }

    private function mockGatewayAlwaysExists(): void
    {
        $gatewayExistsRule = $this->createMock(GatewayExistsAndActive::class);
        $gatewayExistsRule->method('passes')->willReturn(true);
        $this->app->instance(abstract: GatewayExistsAndActive::class, instance: $gatewayExistsRule);
    }
}
