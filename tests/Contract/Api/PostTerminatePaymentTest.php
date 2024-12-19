<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Commands\TerminatePaymentHandler;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Models\Payment;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Str;
use Tests\Helpers\AbstractContractTest;

class PostTerminatePaymentTest extends AbstractContractTest
{
    protected const string ENDPOINT_URI = 'api/v1/payments/%s/terminate';

    protected MockObject&TerminatePaymentHandler $terminatePaymentHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->terminatePaymentHandler = $this->createMock(TerminatePaymentHandler::class);

        $this->instance(
            abstract: TerminatePaymentHandler::class,
            instance: $this->terminatePaymentHandler
        );
    }

    #[Test]
    public function it_returns_a_200_when_a_payment_is_terminated_successfully(): void
    {
        $paymentId = Str::uuid()->toString();

        $this
            ->terminatePaymentHandler
            ->expects($this->once())
            ->method('handle')
            ->with($paymentId)
            ->willReturn(Payment::factory()->create(['id' => $paymentId]));

        $this->sendRequest(headers: ['Api-Key' => config('auth.api_keys.payment_processing')], data: ['payment_id' => $paymentId])
            ->assertStatus(HttpStatus::OK)
            ->assertExactJson([
                '_metadata' => [
                    'success' => true,
                    'links' => [
                        'self' => route('payments.find', ['paymentId' => $paymentId]),
                    ]
                ],
                'result' => [
                    'message' => __('messages.payment.terminated', ['id' => $paymentId]),
                    'payment_id' => $paymentId,
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_when_payment_not_found(): void
    {
        $paymentId = Str::uuid()->toString();

        $this
            ->terminatePaymentHandler
            ->expects($this->once())
            ->method('handle')
            ->with($paymentId)
            ->willThrowException(new ResourceNotFoundException(__('messages.payment.not_found', ['id' => $paymentId])));

        $this->sendRequest(headers: ['Api-Key' => config('auth.api_keys.payment_processing')], data: ['payment_id' => $paymentId])
            ->assertStatus(HttpStatus::NOT_FOUND)
            ->assertJson([
                '_metadata' => [
                    'success' => false
                ],
                'result' => [
                    'message' => __('messages.payment.not_found', ['id' => $paymentId])
                ],
            ]);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_incorrect(): void
    {
        $paymentId = Str::uuid()->toString();

        $this
            ->terminatePaymentHandler
            ->expects($this->never())
            ->method('handle');

        $response = $this->sendRequest(headers: ['Api-Key' => 'SomeIncorrectApiKey1234'], data: ['payment_id' => $paymentId])
            ->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_missing(): void
    {
        $paymentId = Str::uuid()->toString();

        $this
            ->terminatePaymentHandler
            ->expects($this->never())
            ->method('handle');

        $response = $this->sendRequest(data: ['payment_id' => $paymentId])
            ->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_throws_a_422_when_payment_is_already_terminated(): void
    {
        $paymentId = Str::uuid()->toString();

        $this
            ->terminatePaymentHandler
            ->expects($this->once())
            ->method('handle')
            ->with($paymentId)
            ->willThrowException(new UnprocessableContentException(__('messages.payment.already_terminated', ['id' => $paymentId])));

        $this->sendRequest(headers: ['Api-Key' => config('auth.api_keys.payment_processing')], data: ['payment_id' => $paymentId])
            ->assertStatus(HttpStatus::UNPROCESSABLE_ENTITY)
            ->assertJson([
                '_metadata' => [
                    'success' => false
                ],
                'result' => [
                    'message' => __('messages.payment.already_terminated', ['id' => $paymentId])
                ],
            ]);
    }

    #[Test]
    public function it_throws_a_422_when_payment_is_not_a_suspended_payment(): void
    {
        $paymentId = Str::uuid()->toString();

        $this
            ->terminatePaymentHandler
            ->expects($this->once())
            ->method('handle')
            ->with($paymentId)
            ->willThrowException(new UnprocessableContentException(__('messages.payment.suspended_payments_only')));

        $this->sendRequest(headers: ['Api-Key' => config('auth.api_keys.payment_processing')], data: ['payment_id' => $paymentId])
            ->assertStatus(HttpStatus::UNPROCESSABLE_ENTITY)
            ->assertJson([
                '_metadata' => [
                    'success' => false
                ],
                'result' => [
                    'message' => __('messages.payment.suspended_payments_only')
                ],
            ]);
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
    }

    private function sendRequest(array $headers = [], array $data = []): TestResponse
    {
        return $this
            ->withHeaders(array_merge(['Accept' => 'application/json'], $headers))
            ->json(
                method: 'POST',
                uri: sprintf(self::ENDPOINT_URI, $data['payment_id']),
                data: $data
            );
    }
}
