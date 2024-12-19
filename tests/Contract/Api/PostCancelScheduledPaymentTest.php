<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostCancelScheduledPaymentTest extends AbstractContractTest
{
    private ScheduledPayment $scheduledPayment;

    private const string ENDPOINT_URI = '/api/v1/scheduled-payments/%s/cancel';

    #[Test]
    public function it_returns_200_success_response(): void
    {
        $this->initScheduledPayment();
        $response = $this->makeRequest(paymentId: $this->scheduledPayment->id);

        $this->assertSuccessResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_401_unauthorized_error_if_api_key_is_not_found(): void
    {
        $this->initScheduledPayment();
        $response = $this->makeRequest(paymentId: $this->scheduledPayment->id, headers: []); // Missing API Key header

        $response->assertStatus(HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_404_not_found_if_scheduled_payment_id_not_found(): void
    {
        $response = $this->makeRequest(paymentId: Str::uuid()->toString());

        $response->assertStatus(HttpStatus::NOT_FOUND);
        $this->assertErrorResponseStructure(response: $response);
    }

    private function initScheduledPayment(): void
    {
        $this->scheduledPayment = ScheduledPayment::factory()->create([
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'payment_id' => null,
        ]);
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => ['status', 'scheduled_payment_id'],
        ], $response->json());

        $response->assertJsonPath(
            path: 'result.message',
            expect: __('messages.scheduled_payment.cancelled')
        );
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());
    }

    private function makeRequest(string $paymentId, array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
        ];

        return $this->post(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentId)),
            headers: $headers ?? $defaultHeaders,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->scheduledPayment);
    }
}
