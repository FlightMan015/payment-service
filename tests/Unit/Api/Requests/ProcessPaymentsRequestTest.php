<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\ProcessPaymentsRequest;
use Tests\Helpers\AbstractApiRequestTest;

class ProcessPaymentsRequestTest extends AbstractApiRequestTest
{
    /**
     * @return ProcessPaymentsRequest
     */
    public function getTestedRequest(): ProcessPaymentsRequest
    {
        return new ProcessPaymentsRequest();
    }

    public static function getInvalidData(): array|\Iterator
    {
        yield 'area_ids is not array' => [self::getInvalidDataSet(field: 'area_ids', value: 'some string')];
        yield 'area_ids contains not an integer' => [self::getInvalidDataSet(field: 'area_ids', value: ['some string'])];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        yield 'valid data' => [self::getValidDataSet()];
        yield 'valid data with null area_ids' => [['area_ids' => null]];
    }

    public static function getValidDataSet(): array
    {
        return ['area_ids' => [49, 39]];
    }

    protected function getDefaultsValues(): array
    {
        return ['area_ids' => null];
    }
}
