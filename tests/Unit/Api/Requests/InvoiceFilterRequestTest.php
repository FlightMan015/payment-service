<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\FilterRequest;
use App\Api\Requests\InvoiceFilterRequest;
use Illuminate\Support\Str;
use Tests\Helpers\AbstractApiRequestTest;

class InvoiceFilterRequestTest extends AbstractApiRequestTest
{
    /**
     * @return FilterRequest
     */
    public function getTestedRequest(): FilterRequest
    {
        return new InvoiceFilterRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid account_id (not UUID)' => [
            'data' => [
                'account_id' => 'some string',
            ],
        ];
        yield 'invalid subscription_id (not UUID)' => [
            'data' => [
                'subscription_id' => 'some string',
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
        yield 'invalid balance_from value' => [
            'data' => [
                'balance_from' => 'some string',
            ],
        ];
        yield 'invalid balance_to value' => [
            'data' => [
                'balance_to' => 'some string',
            ],
        ];
        yield 'invalid total_from value' => [
            'data' => [
                'total_from' => 'some string',
            ],
        ];
        yield 'invalid total_to value' => [
            'data' => [
                'total_to' => 'some string',
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
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'account_id' => Str::uuid()->toString(),
            'subscription_id' => Str::uuid()->toString(),
            'date_from' => '2021-01-01',
            'date_to' => '2021-01-02',
            'balance_from' => 10,
            'balance_to' => 20,
            'from_total_value' => 10,
            'to_total_value' => 20,
        ];
    }
}
