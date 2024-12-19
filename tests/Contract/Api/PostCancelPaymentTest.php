<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\Payment;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractContractTest;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;

class PostCancelPaymentTest extends AbstractContractTest
{
    private Payment $payment;

    private const string ENDPOINT_URI = '/api/v1/payments/%s/cancel';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDynamoDbForGettingWorldPayCredentials();
    }

    #[Test]
    public function it_returns_200_success_response(): void
    {
        $this->initPaymentAndTransaction();
        $this->mockPaymentProcessorCancelResponse(isSuccess: true);

        $response = $this->makeRequest(paymentId: $this->payment->id);

        $this->assertSuccessResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_401_unauthorized_error_if_api_key_is_not_found(): void
    {
        $this->initPaymentAndTransaction();
        $response = $this->makeRequest(paymentId: $this->payment->id, headers: []); // Missing API Key header

        $response->assertStatus(HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    #[DataProvider('thirdPartyFailsProvider')]
    public function it_returns_422_unprocessable_error_for_3rd_party_fails(array $input, array $expected): void
    {
        $this->initPaymentAndTransaction();
        $this->mockPaymentProcessorCancelResponse(
            isSuccess: $input['paymentProcessor']['success'],
            errorMessage:$input['paymentProcessor']['errorMessage']
        );

        $response = $this->makeRequest(paymentId: $this->payment->id);
        $response->assertStatus(HttpStatus::UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('result.message', $expected['errorMessage']);
    }

    private function initPaymentAndTransaction(): void
    {
        $this->payment = Payment::factory()->create([
            'amount' => 500,
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'payment_type_id' => PaymentTypeEnum::CC->value,
            'external_ref_id' => null,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'processed_at' => Carbon::tomorrow(),
        ]);

        Transaction::factory()->create([
            'payment_id' => $this->payment->id,
            'transaction_type_id' => TransactionTypeEnum::CAPTURE->value,
        ]);
    }

    public static function thirdPartyFailsProvider(): \Iterator
    {
        yield 'payment processor fails' => [
            'input' => [
                'paymentProcessor' => [
                    'success' => false,
                    'errorMessage' => 'Some error here',
                ],
            ],
            'expected' => [
                'errorMessage' => static fn () => __('messages.operation.cancel.gateway_error', ['message' => 'Some error here']),
            ],
        ];
    }

    private function mockDynamoDbForGettingWorldPayCredentials(): void
    {
        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn(WorldpayCredentialsStub::make());
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);
    }

    private function mockPaymentProcessorCancelResponse(bool $isSuccess, string $errorMessage = ''): void
    {
        /** @var MockObject|PaymentProcessor $mockProcessor */
        $mockProcessor = $this->createMock(PaymentProcessor::class);
        $mockProcessor->method('cancel')->willReturn($isSuccess);
        $mockProcessor->method('getError')->willReturn($errorMessage);
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => ['status', 'payment_id', 'transaction_id'],
        ], $response->json());

        $response->assertJsonPath(
            path: 'result.message',
            expect: __('messages.payment.cancelled')
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
        unset($this->payment);
    }
}
