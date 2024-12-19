<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\FailedJobFilterRequest;
use Tests\Helpers\AbstractApiRequestTest;

class FailedJobFilterRequestTest extends AbstractApiRequestTest
{
    /**
     * @return FailedJobFilterRequest
     */
    public function getTestedRequest(): FailedJobFilterRequest
    {
        return new FailedJobFilterRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid queue' => [
            'data' => [
                'queue' => 'some random queue'
            ],
        ];

        yield 'invalid job ids' => [
            'data' => [
                'job_ids' => 'string'
            ],
        ];

        yield 'invalid job ids (2)' => [
            'data' => [
                'job_ids' => ['non-uuid']
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

        yield 'valid data with all empty values' => [
            'data' => [],
        ];

        $input = $fullValidParameters;
        unset($input['page'], $input['per_page'], $input['job_ids']);
        yield 'valid data with only queue' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['page'], $input['per_page'], $input['queue']);
        yield 'valid data with only job_ids' => [
            'data' => $input,
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
            'queue' => static fn () => array_values(config('queue.connections.sqs.queues'))[0],
            'job_ids' => [
                '2402271e-03ed-46d9-bd32-69d886b55877',
                '3d3fb986-636f-3260-94f3-b7f6488d6a64',
                '78e9cda3-b7d3-4c04-90ce-ab6d4cf5490d',
            ],
        ];
    }
}
