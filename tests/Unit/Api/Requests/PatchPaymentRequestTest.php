<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\PatchPaymentRequest;
use Tests\Helpers\AbstractApiRequestTest;

class PatchPaymentRequestTest extends AbstractApiRequestTest
{
    /**
     * @return PatchPaymentRequest
     */
    public function getTestedRequest(): PatchPaymentRequest
    {
        return new PatchPaymentRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid amount (string)' => [
            'data' => [
                'amount' => 'some string',
            ],
        ];
        yield 'invalid amount (less than zero)' => [
            'data' => [
                'amount' => -1,
            ],
        ];
        yield 'invalid check_date (not a date)' => [
            'data' => [
                'check_date' => 'some string',
            ],
        ];
        yield 'invalid check_date (wrong format)' => [
            'data' => [
                'check_date' => '01-01-2021',
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
            'amount' => 100,
            'check_date' => '2021-01-01',
        ];
    }
}
