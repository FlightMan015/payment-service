<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\FilterRequest;
use Tests\Helpers\AbstractApiRequestTest;

class FilterRequestTest extends AbstractApiRequestTest
{
    /**
     * @return FilterRequest
     */
    public function getTestedRequest(): FilterRequest
    {
        return new FilterRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid per_page value (string' => [
            'data' => [
                'per_page' => 'some random string'
            ],
        ];

        yield 'invalid page value (float)' => [
            'data' => [
                'page' => 5.6
            ],
        ];

        yield 'invalid page value (array)' => [
            'data' => [
                'page' => [1]
            ],
        ];

        yield 'invalid direction value (array)' => [
            'data' => [
                'direction' => 'no-direction'
            ],
        ];
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();
        yield 'valid data' => [
            'data' => $fullValidParameters,
        ];

        $input = $fullValidParameters;
        unset($input['direction']);
        yield 'valid data without direction' => [
            'data' => $fullValidParameters,
        ];

        yield 'valid data with all empty values' => [
            'data' => [],
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'page' => 100,
            'per_page' => 100,
            'sort' => 'some-sort',
            'direction' => 'asc',
        ];
    }
}
