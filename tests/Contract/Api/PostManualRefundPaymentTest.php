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
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostManualRefundPaymentTest extends AbstractContractTest
{
    private Payment $payment;

    private const string ENDPOINT_URI = '/api/v1/payments/%s/manual-refund';

    #[Test]
    public function it_returns_200_success_response(): void
    {
        $this->initPaymentAndTransaction();

        $response = $this->makeRequest(paymentId: $this->payment->id, amount: 100);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);

        $this->assertDatabaseHas(
            table: $this->payment->getTable(),
            data: [
                'original_payment_id' => $this->payment->id,
                'payment_status_id' => PaymentStatusEnum::CREDITED->value,
                'amount' => 100,
            ]
        );
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
    public function it_returns_422_unprocessable_error_if_refunding_amount_is_greater_than_payment_amount(): void
    {
        $this->initPaymentAndTransaction();
        $response = $this->makeRequest(paymentId: $this->payment->id, amount: $this->payment->amount + 100);

        $response->assertStatus(HttpStatus::UNPROCESSABLE_ENTITY);
        $response->assertJsonPath(
            path: 'result.message',
            expect: __('messages.operation.refund.exceeds_the_original_payment_amount', ['amount' => $this->payment->amount]),
        );
    }

    private function initPaymentAndTransaction(
        PaymentStatusEnum $paymentStatus = PaymentStatusEnum::CAPTURED,
        TransactionTypeEnum $transactionType = TransactionTypeEnum::CAPTURE,
        PaymentTypeEnum $paymentType = PaymentTypeEnum::CHECK,
    ): void {
        $this->payment = Payment::factory()->create([
            'amount' => 500,
            'payment_status_id' => $paymentStatus->value,
            'payment_type_id' => $paymentType->value,
            'external_ref_id' => null,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'processed_at' => Carbon::yesterday(),
        ]);

        Transaction::factory()->create([
            'payment_id' => $this->payment->id,
            'transaction_type_id' => $transactionType->value,
        ]);
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => ['status', 'refund_payment_id', 'transaction_id'],
        ], $response->json());

        $response->assertJsonPath(
            path: 'result.message',
            expect: __('messages.payment.refunded')
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

    private function makeRequest(string $paymentId, int|null $amount = null, array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
        ];

        return $this->post(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentId)),
            data: array_filter(['amount' => $amount]),
            headers: $headers ?? $defaultHeaders,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->payment);
    }
}
