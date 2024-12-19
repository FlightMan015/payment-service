<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\FailedJob;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetFailedJobsTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/failed-jobs';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFailedJobsInDatabase();
    }

    private function createFailedJobsInDatabase(int $count = 10): void
    {
        FailedJob::factory()->count(count: $count)->create([
            'queue' => array_values(config('queue.connections.sqs.queues'))[0],
        ])->toArray();
    }

    #[Test]
    #[DataProvider('unauthorizedDataProvider')]
    public function it_return_401_response_for_non_api_key_api(array $input, array $expected): void
    {
        $response = $this->makeRequest(additionalHeaders: ['Api-Key' => $input['api_key']]);

        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
            ],
            'result' => [
                'message',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', $expected['message']);
    }

    public static function unauthorizedDataProvider(): \Iterator
    {
        yield 'empty api key' => [
            'input' => [
                'api_key' => null,
            ],
            'expected' => [
                'message' => static fn () => __('auth.api_key_not_found'),
            ],
        ];
        yield 'wrong api key' => [
            'input' => [
                'api_key' => Str::uuid()->toString(),
            ],
            'expected' => [
                'message' => static fn () => __('auth.invalid_api_key'),
            ],
        ];
    }

    #[Test]
    #[DataProvider('validInputProvider')]
    public function it_returns_200_success_response_for_retrieving_successfully(array $input, array $expected): void
    {
        if (isset($input['queue'])) {
            $expected['totalSql'] .= sprintf(" where queue like '%%%s%%'", $input['queue']);
        }
        $response = $this->makeRequest(parameters: $input);

        $expectedTotalResult = DB::select($expected['totalSql'])[0]->count;
        $expectedTotalPage = (int) ceil($expectedTotalResult / $expected['per_page']);

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
                'links' => [
                    'self',
                ],
            ],
            'result' => [
                '*' => [
                    'uuid',
                    'queue',
                    'failure_reason',
                    'failed_at',
                ],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('_metadata.current_page', $expected['current_page']);
        $response->assertJsonPath('_metadata.per_page', $expected['per_page']);
        $response->assertJsonPath('_metadata.total_pages', $expectedTotalPage);
        $response->assertJsonPath('_metadata.total_results', $expectedTotalResult);
        $this->assertCount(min($expected['count_current_result'], $expectedTotalResult), $response->json()['result']);
    }

    public static function validInputProvider(): \Iterator
    {
        $sql = 'select count(id) as count from billing.failed_jobs';
        yield 'empty input' => [
            'input' => [],
            'expected' => [
                'current_page' => 1,
                'per_page' => 100,
                'count_current_result' => 100,
                'totalSql' => $sql,
            ],
        ];
        yield 'empty page' => [
            'input' => [
                'per_page' => 2,
            ],
            'expected' => [
                'current_page' => 1,
                'per_page' => 2,
                'count_current_result' => 2,
                'totalSql' => $sql,
            ],
        ];
        yield 'has page' => [
            'input' => [
                'per_page' => 2,
                'page' => 4,
            ],
            'expected' => [
                'current_page' => 4,
                'per_page' => 2,
                'count_current_result' => 2,
                'totalSql' => $sql,
            ],
        ];
        yield 'has queue' => [
            'input' => [
                'queue' => static fn () => array_values(config('queue.connections.sqs.queues'))[0],
            ],
            'expected' => [
                'current_page' => 1,
                'per_page' => 100,
                'count_current_result' => 100,
                'totalSql' => $sql,
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidInputProvider')]
    public function it_returns_400_bad_request_response_for_invalid_input(array $input): void
    {
        $response = $this->makeRequest(parameters: $input);

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
        $response->assertJsonPath('result.message', 'Invalid input');
    }

    public static function invalidInputProvider(): \Iterator
    {
        yield 'wrong page' => [
            'input' => [
                'page' => 'asd',
            ],
        ];
        yield 'wrong per page' => [
            'input' => [
                'per_page' => 'asd',
            ],
        ];
        yield 'wrong queue type' => [
            'input' => [
                'queue' => ['test'],
            ],
        ];
        yield 'wrong queue value' => [
            'input' => [
                'queue' => 'test',
            ],
        ];
    }

    protected function makeRequest(array $parameters = [], array $additionalHeaders = []): TestResponse
    {
        return $this->get(
            uri: self::ENDPOINT_URI . '?' . http_build_query(data: $parameters),
            headers: array_merge([
                'Api-Key' => config('auth.api_keys.failed_jobs_handling')
            ], $additionalHeaders)
        );
    }
}
