<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Requests;

use App\Api\Requests\RetryFailedJobRequest;
use App\Models\FailedJob;
use Illuminate\Support\Str;
use Illuminate\Validation\DatabasePresenceVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractApiRequestTest;

class RetryFailedJobRequestTest extends AbstractApiRequestTest
{
    /**
     * @return RetryFailedJobRequest
     */
    public function getTestedRequest(): RetryFailedJobRequest
    {
        return new RetryFailedJobRequest();
    }

    /** @inheritDoc */
    public static function getInvalidData(): array|\Iterator
    {
        yield 'invalid job ids (not array)' => [
            'data' => [
                'job_ids' => 'not array'
            ],
        ];
        yield 'invalid job ids (array of non-uuid)' => [
            'data' => [
                'job_ids' => [123, 'non-uuid']
            ],
        ];
        yield 'invalid job ids (array of non-existing)' => [
            'data' => [
                'job_ids' => [
                    (string) Str::uuid(),
                ]
            ],
        ];

        yield 'invalid data with array queue' => [
            'data' => [
                'queue' => [Str::uuid()->toString()],
            ],
        ];
        yield 'invalid data with incorrect queue' => [
            'data' => [
                'queue' => Str::uuid()->toString(),
            ],
        ];
    }

    #[Test]
    #[DataProvider('getInvalidData')]
    public function it_should_fail_validation_if_incorrect_data_is_provided(array $data): void
    {
        $validator = $this->makeValidator($data, $this->rules);

        if (!empty($data['job_ids'])) {
            /** @var DatabasePresenceVerifier|MockObject $presenceVerifier */
            $presenceVerifier = $this->getMockBuilder(className: DatabasePresenceVerifier::class)
                ->disableOriginalConstructor()
                ->getMock();
            $presenceVerifier->method('getCount')->willReturn(0);
            $validator->setPresenceVerifier(presenceVerifier: $presenceVerifier);
        }

        $this->assertFalse($validator->passes());
    }

    #[Test]
    #[DataProvider('getValidData')]
    public function it_should_pass_validation_if_correct_data_is_provided(array $data): void
    {
        if (!empty($data['job_ids'])) {
            $jobs = FailedJob::factory(2)->make();
            $data['job_ids'] = $jobs->pluck('uuid')->toArray();
        }

        /** @var DatabasePresenceVerifier|MockObject $presenceVerifier */
        $presenceVerifier = $this->getMockBuilder(className: DatabasePresenceVerifier::class)
            ->disableOriginalConstructor()
            ->getMock();
        $presenceVerifier->method('getCount')->willReturn(1);

        $validator = $this->makeValidator($data, $this->rules);
        $validator->setPresenceVerifier(presenceVerifier: $presenceVerifier);

        $this->assertTrue($validator->passes());
    }

    /** @inheritDoc */
    public static function getValidData(): array|\Iterator
    {
        $fullValidParameters = self::getValidDataSet();
        $fullValidParameters['job_ids'] = 'JOB_IDS';

        yield 'valid data' => [
            'data' => $fullValidParameters,
        ];

        yield 'valid data with all empty values' => [
            'data' => [],
        ];

        $input = $fullValidParameters;
        unset($input['queue']);
        yield 'valid data with only job ids' => [
            'data' => $input,
        ];

        $input = $fullValidParameters;
        unset($input['job_ids']);
        yield 'valid data with only queue' => [
            'data' => $input,
        ];
    }

    /**
     * @return array
     */
    public static function getValidDataSet(): array
    {
        return [
            'job_ids' => [],
            'queue' => static fn () => array_values(config('queue.connections.sqs.queues'))[0],
        ];
    }
}
