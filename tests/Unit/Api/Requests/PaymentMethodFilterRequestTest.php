<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PaymentMethodFilterRequest;
use App\Rules\AccountExists;
use App\Rules\GatewayExistsAndActive;
use Illuminate\Support\Str;
use Tests\Helpers\AbstractApiRequestTest;

class PaymentMethodFilterRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PaymentMethodFilterRequest
     */
    public function getTestedRequest(): PaymentMethodFilterRequest
    {
        $this->mockGatewayAlwaysExists();
        $this->mockAccountAlwaysExists();
        return new PaymentMethodFilterRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid account_id (string)' => [
            'data' => [
                'account_id' => 'John\n123',
            ],
        ];
        yield 'invalid account_ids (integer)' => [
            'data' => [
                'account_ids' => 1223,
            ],
        ];
        yield 'invalid account_ids item (int, string)' => [
            'data' => [
                'account_ids' => [123, 'sdf'],
            ],
        ];
        yield 'invalid have both account_id and account_ids' => [
            'data' => [
                'account_ids' => [Str::uuid()->toString(), Str::uuid()->toString()],
                'account_id' => Str::uuid()->toString(),
            ],
        ];
        yield 'invalid does not have both account_id and account_ids' => [
            'data' => [
                'is_valid' => true
            ],
        ];
        yield 'invalid empty input' => [
            'data' => [],
        ];
        yield 'invalid cc_expire_from_date (int)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'cc_expire_from_date' => 123
            ],
        ];
        yield 'invalid cc_expire_from_date (wrong format)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'cc_expire_from_date' => '15/06/1993',
            ],
        ];
        yield 'invalid cc_expire_from_date (invalid date value)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'cc_expire_from_date' => '1993-13-13',
            ],
        ];
        yield 'invalid cc_expire_to_date (int)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'cc_expire_to_date' => 123
            ],
        ];
        yield 'invalid cc_expire_to_date (wrong format)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'cc_expire_to_date' => '15/06/1993',
            ],
        ];
        yield 'invalid cc_expire_to_date (invalid date value)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'cc_expire_to_date' => '1993-13-13',
            ],
        ];
        yield 'invalid gateway_id (string)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'gateway_id' => 'some string',
            ],
        ];
        yield 'invalid is_valid (string)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'is_valid' => 'some string',
            ],
        ];
        yield 'invalid type (integer)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'type' => 123,
            ],
        ];
        yield 'invalid type (unavailable type)' => [
            'data' => [
                'account_id' => Str::uuid()->toString(),
                'type' => 'VASA',
            ],
        ];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();
        yield 'valid data with full input and account_id' => [
            'data' => $fullValidParameters,
        ];

        $input = $fullValidParameters;
        unset($input['account_id']);
        $input['account_ids'] = [Str::uuid()->toString()];
        yield 'valid data with full input and account_ids array' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['cc_expire_from_date']);
        yield 'valid data without cc_expire_from_date' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['cc_expire_to_date']);
        yield 'valid data without cc_expire_to_date' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['gateway_id']);
        yield 'valid data without gateway_id' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['is_valid']);
        yield 'valid data without is_valid' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['type']);
        yield 'valid data without type' => [
            'data' => $input,
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'amount' => 100,
            'account_id' => Str::uuid()->toString(),
            'cc_expire_from_date' => '2023-10-01',
            'cc_expire_to_date' => '2025-10-01',
            'gateway_id' => 1,
            'is_valid' => true,
            'type' => 'CC',
        ];
    }

    private function mockGatewayAlwaysExists(): void
    {
        $gatewayExistsRule = $this->createMock(GatewayExistsAndActive::class);
        $gatewayExistsRule->method('passes')->willReturn(true);
        $this->app->instance(abstract: GatewayExistsAndActive::class, instance: $gatewayExistsRule);
    }

    private function mockAccountAlwaysExists(): void
    {
        $accountExists = $this->createMock(AccountExists::class);
        $accountExists->method('passes')->willReturn(true);
        $this->app->instance(abstract: AccountExists::class, instance: $accountExists);
    }
}
