<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Jobs\FailedPaymentRefundsReportJob;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostPaymentsFailedRefundsReportTest extends AbstractContractTest
{
    private string $endpoint = 'api/v1/payments/failed-refunds-report';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake(jobsToFake: FailedPaymentRefundsReportJob::class);
    }

    #[Test]
    public function it_returns_202_success_and_dispatch_job(): void
    {
        $response = $this->sendRequest(
            headers: ['Api-Key' => config(key: 'auth.api_keys.payment_processing')]
        );

        $response->assertStatus(status: HttpStatus::ACCEPTED);
        $response->assertJson(value: [
            '_metadata' => [
                'success' => true,
            ],
            'result' => [
                'message' => __(key: 'messages.reports.failed_refunds.started'),
            ]
        ]);

        Queue::assertPushedOn(
            queue: config(key: 'queue.connections.sqs.queues.notifications'),
            job: FailedPaymentRefundsReportJob::class
        );
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
}
