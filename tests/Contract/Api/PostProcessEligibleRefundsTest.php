<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Jobs\RetrieveAreaEligibleRefundsJob;
use App\Models\CRM\FieldOperations\Area;
use Aptive\Component\Http\HttpStatus;
use ConfigCat\ClientInterface;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostProcessEligibleRefundsTest extends AbstractContractTest
{
    private string $endpoint = 'api/v1/process-eligible-refunds';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake(jobsToFake: RetrieveAreaEligibleRefundsJob::class);
    }

    #[Test]
    public function it_returns_202_success_and_dispatch_jobs(): void
    {
        Area::query()->delete(); // Clear all areas (if any)
        $this->mockConfigCatClient();

        $response = $this->sendRequest(
            data: ['area_id' => Area::factory()->create(attributes: ['is_active' => true])->id],
            headers: ['Api-Key' => config(key: 'auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::ACCEPTED);
        $response->assertJson(value: [
            '_metadata' => [
                'success' => true,
            ],
            'result' => [
                'message' => __('messages.payment.eligible_refunds_processing.started'),
            ]
        ]);

        Queue::assertPushedOn(
            queue: config(key: 'queue.connections.sqs.queues.process_payments'),
            job: RetrieveAreaEligibleRefundsJob::class
        );
    }

    #[Test]
    public function it_returns_a_400_bad_request_error_if_passing_non_existing_area_id(): void
    {
        $response = $this->sendRequest(
            data: ['area_ids' => -1],
            headers: ['Api-Key' => config(key: 'auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_not_found(): void
    {
        $response = $this->sendRequest(headers: []); // Missing API Key header

        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_incorrect(): void
    {
        $response = $this->sendRequest(headers: ['Api-Key' => 'SomeIncorrectApiKey1234']); // Incorrect API Key Header

        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure(response: $response);
    }

    private function sendRequest(array $data = [], array $headers = []): TestResponse
    {
        return $this->post(uri: $this->endpoint, data: $data, headers: $headers);
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
    }

    private function mockConfigCatClient(bool $isEligibleRefundsProcessingEnabled = true): void
    {
        $configCatClient = $this->createMock(ClientInterface::class);

        $configCatClient->method('getValue')->willReturn($isEligibleRefundsProcessingEnabled);

        $this->app->instance(ClientInterface::class, $configCatClient);
    }
}
