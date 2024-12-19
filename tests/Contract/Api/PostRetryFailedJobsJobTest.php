<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Jobs\RetryFailedJobsJob;
use App\Models\FailedJob;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostRetryFailedJobsJobTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/failed-jobs/retry';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    #[DataProvider('validInputProvider')]
    public function it_returns_json_response_for_retry_successfully(array $input): void
    {
        $input['queue'] = array_values(config('queue.connections.sqs.queues'))[0];
        $job = FailedJob::factory()->create(attributes: ['queue' => $input['queue']]);
        $input['job_ids'] = [$job->uuid];

        $response = $this->makeRequest(input: $input);

        Queue::assertPushed(RetryFailedJobsJob::class);

        $response->assertStatus(status: HttpStatus::ACCEPTED);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
                'links' => [
                    'self' => [],
                ],
            ],
            'result' => [
                'message',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('result.message', 'Accepted');
    }

    public static function validInputProvider(): \Iterator
    {
        yield 'queue && job id' => [
            'input' => [
                'queue' => 'test',
                'job_id' => 'test',
            ],
        ];
    }

    #[Test]
    public function it_calls_artisan_multiple_times_and_return_202_json_response(): void
    {
        $input['queue'] = array_values(config('queue.connections.sqs.queues'))[0];
        $jobs = FailedJob::factory()->count(120)->create(attributes: ['queue' => $input['queue']]);
        $input['job_ids'] = $jobs->pluck('uuid')->toArray();

        $response = $this->makeRequest(input: $input);

        Queue::assertPushed(RetryFailedJobsJob::class);

        $response->assertStatus(status: HttpStatus::ACCEPTED);
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('result.message', 'Accepted');
    }

    #[Test]
    public function it_runs_for_all_jobs_and_response_202_json_response(): void
    {
        FailedJob::factory()->count(120)->create();

        $response = $this->makeRequest(input: []);

        Queue::assertPushed(RetryFailedJobsJob::class);

        $response->assertStatus(status: HttpStatus::ACCEPTED);
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('result.message', 'Accepted');
    }

    private function makeRequest(array $input): TestResponse
    {
        return $this->post(
            uri: self::ENDPOINT_URI,
            data: $input,
            headers: [
                'Api-Key' => config('auth.api_keys.failed_jobs_handling'),
            ]
        );
    }

    #[Test]
    #[DataProvider('invalidInputProvider')]
    public function it_returns_400_bad_request_response_for_invalid_input(array $input): void
    {
        $response = $this->makeRequest(input: $input);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
                'errors' => [
                    '*' => [
                        'detail' => [],
                    ]
                ],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.invalid_input'));
    }

    public static function invalidInputProvider(): \Iterator
    {
        yield 'job ids is not an array' => [
            'input' => [
                'job_ids' => 'asd',
            ],
        ];
        yield 'job id is not uuid' => [
            'input' => [
                'job_ids' => ['asd'],
            ],
        ];
        yield 'queue is not string' => [
            'input' => [
                'queue' => [''],
            ],
        ];
    }

    #[Test]
    #[DataProvider('unauthorizedProvider')]
    public function it_return_401_response_for_non_api_key_api(array $header, array $expected): void
    {
        $response = $this->post(
            uri: self::ENDPOINT_URI,
            data: [],
            headers: $header
        );
        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', $expected['message']);
    }

    public static function unauthorizedProvider(): \Iterator
    {
        yield 'non-api-key' => [
            'header' => [],
            'expected' => [
                'message' => static fn () => __('auth.api_key_not_found'),
            ],
        ];
        yield 'wrong-api-key' => [
            'header' => [
                'Api-Key' => Str::uuid()->toString(),
            ],
            'expected' => [
                'message' => static fn () => __('auth.invalid_api_key'),
            ],
        ];
    }
}
