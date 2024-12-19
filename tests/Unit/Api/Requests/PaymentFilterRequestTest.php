<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PaymentFilterRequest;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Illuminate\Support\Str;
use Tests\Helpers\AbstractApiRequestTest;

class PaymentFilterRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PaymentFilterRequest
     */
    public function getTestedRequest(): PaymentFilterRequest
    {
        return new PaymentFilterRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid account_id (string)' => [
            'data' => [
                'account_id' => 'some string',
            ],
        ];
        yield 'invalid account_id (bool)' => [
            'data' => [
                'account_id' => false,
            ],
        ];
        yield 'invalid account_id (not UUID)' => [
            'data' => [
                'account_id' => 'some string',
            ],
        ];
        yield 'invalid invoice_id (not UUID)' => [
            'data' => [
                'invoice_id' => 'some string',
            ],
        ];
        yield 'invalid payment_ids (not an array)' => [
            'data' => [
                'payment_ids' => 'some string',
            ],
        ];
        yield 'invalid payment_ids (array containing non UUID values)' => [
            'data' => [
                'payment_ids' => ['some string', 'some other string'],
            ],
        ];
        yield 'invalid payment_method_id (not UUID)' => [
            'data' => [
                'payment_method_id' => 'some string',
            ],
        ];
        yield 'invalid date_from (not a date)' => [
            'data' => [
                'date_from' => 'some string',
            ],
        ];
        yield 'invalid date_from (incorrect format)' => [
            'data' => [
                'date_from' => '01-01-2021',
            ],
        ];
        yield 'invalid date_to (not a date)' => [
            'data' => [
                'date_to' => 'some string',
            ],
        ];
        yield 'invalid date_to (incorrect format)' => [
            'data' => [
                'date_to' => '01-01-2021',
            ],
        ];
        yield 'invalid date_to (goes before date_from)' => [
            'data' => [
                'date_from' => '2021-01-01',
                'date_to' => '2020-01-01',
            ],
        ];
        yield 'invalid area_id (not an integer)' => [
            'data' => [
                'area_id' => 'some string',
            ],
        ];
        yield 'invalid payment_status (not an integer)' => [
            'data' => [
                'payment_status' => 'some string',
            ],
        ];
        yield 'invalid payment_status (not a valid value)' => [
            'data' => [
                'payment_status' => 100,
            ],
        ];
        yield 'invalid amount_from value' => [
            'data' => [
                'amount_from' => 'some string',
            ],
        ];
        yield 'invalid amount_to value' => [
            'data' => [
                'amount_to' => 'some string',
            ],
        ];
        yield 'invalid first_name (not a string)' => [
            'data' => [
                'first_name' => 100,
            ],
        ];
        yield 'invalid first_name (too long)' => [
            'data' => [
                'first_name' => str_repeat('a', 256),
            ],
        ];
        yield 'invalid last_name (not a string)' => [
            'data' => [
                'last_name' => 100,
            ],
        ];
        yield 'invalid last_name (too long)' => [
            'data' => [
                'last_name' => str_repeat('a', 256),
            ],
        ];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();
        yield 'valid data with full input' => [
            'data' => $fullValidParameters,
        ];
        yield 'valid amount_from value only' => [
            'data' => [
                'amount_from' => 10,
            ],
        ];
        yield 'valid amount_to value only' => [
            'data' => [
                'amount_to' => 10,
            ],
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'account_id' => Str::uuid()->toString(),
            'invoice_id' => Str::uuid()->toString(),
            'payment_ids' => [
                Str::uuid()->toString(),
                Str::uuid()->toString(),
            ],
            'payment_method_id' => Str::uuid()->toString(),
            'date_from' => '2021-01-01',
            'date_to' => '2021-01-02',
            'area_id' => 1001,
            'payment_status' => PaymentStatusEnum::AUTHORIZING->name,
            'amount_from' => 10,
            'amount_to' => 20,
            'first_name' => 'Jon',
            'last_name' => 'Doe',
        ];
    }
}
