<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Commands\AuthorizeAndCaptureSuspendedPaymentHandler;
use App\Api\DTO\AuthorizePaymentResultDto;
use App\Models\Payment;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractContractTest;

class PostProcessSuspendedPaymentTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/%s/process-suspended';
    private array $validHeaders;

    private array $invalidHeaders;
    private Payment $suspendedPayment;
    private AuthorizeAndCaptureSuspendedPaymentHandler&MockObject $handlerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suspendedPayment = Payment::factory()->create([
            'payment_status_id' => PaymentStatusEnum::SUSPENDED->value,
        ]);

        $this->validHeaders = ['Api-Key' => config(key: 'auth.api_keys.payment_processing')];
        $this->invalidHeaders = ['Api-Key' => 'SomeIncorrectApiKey1234'];

        $this->handlerMock = $this->createMock(AuthorizeAndCaptureSuspendedPaymentHandler::class);
    }

    #[Test]
    public function it_returns_200_when_payment_gets_captured(): void
    {
        $transactionId = fake()->uuid();
        $message = __('messages.payment.processed');

        $this->handlerMock
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new AuthorizePaymentResultDto(
                status: PaymentStatusEnum::CAPTURED,
                paymentId: $this->suspendedPayment->id,
                transactionId: $transactionId,
                message: $message,
            ));

        $this->instance(
            abstract: AuthorizeAndCaptureSuspendedPaymentHandler::class,
            instance: $this->handlerMock
        );

        $response = $this->sendRequest(headers: $this->validHeaders);

        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJson(value: [
            '_metadata' => [
                'success' => true,
            ],
            'result' => [
                'message' => $message,
                'status' => PaymentStatusEnum::CAPTURED->name,
                'payment_id' => $this->suspendedPayment->id,
                'transaction_id' => $transactionId
            ]
        ]);
    }

    #[Test]
    public function it_returns_200_when_payment_gets_declined(): void
    {
        $transactionId = fake()->uuid();
        $message = __('messages.payment.processed');

        $this->handlerMock
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new AuthorizePaymentResultDto(
                status: PaymentStatusEnum::DECLINED,
                paymentId: $this->suspendedPayment->id,
                transactionId: $transactionId,
                message: $message,
            ));

        $this->instance(
            abstract: AuthorizeAndCaptureSuspendedPaymentHandler::class,
            instance: $this->handlerMock
        );

        $response = $this->sendRequest(headers: $this->validHeaders);

        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJson(value: [
            '_metadata' => [
                'success' => true,
            ],
            'result' => [
                'message' => $message,
                'status' => PaymentStatusEnum::DECLINED->name,
                'payment_id' => $this->suspendedPayment->id,
                'transaction_id' => $transactionId
            ]
        ]);
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
        $response = $this->sendRequest(headers: $this->invalidHeaders); // Incorrect API Key Header

        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure(response: $response);
    }

    private function sendRequest(array $headers = []): TestResponse
    {
        return $this->post(
            uri: sprintf(self::ENDPOINT_URI, $this->suspendedPayment->id),
            headers: $headers
        );
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
    }
}
