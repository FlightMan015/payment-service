<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetQueuesTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/failed-jobs/queues';

    #[Test]
    #[DataProvider('unauthorizedDataProvider')]
    public function it_return_401_response_for_non_api_key_api(array $input, array $expected): void
    {
        $response = $this->makeRequest(additionalHeaders: [
            'Api-Key' => $input['api_key'],
        ]);
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
    public function it_returns_json_200_response_for_retrieving_successfully(): void
    {
        $response = $this->makeRequest();

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
                'links' => [
                    'self',
                ],
            ],
            'result' => [
                'message',
                'items',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('result.message', 'Success');
        $this->assertSame($response->json()['result']['items'], array_values(config('queue.connections.sqs.queues')));
    }

    protected function makeRequest(array $additionalHeaders = []): TestResponse
    {
        return $this->get(
            uri: self::ENDPOINT_URI,
            headers: array_merge([
                'Api-Key' => config('auth.api_keys.failed_jobs_handling')
            ], $additionalHeaders)
        );
    }
}
