<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\Payment;
use App\Models\Transaction;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetPaymentTransactionTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/%s/transactions/%s';

    private Transaction $transaction;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTransactionInDatabase();
    }

    private function createTransactionInDatabase(): void
    {
        $this->payment = Payment::factory()->create();
        $this->transaction = Transaction::factory()->for($this->payment)->create();
    }

    #[Test]
    public function it_returns_200_success_response_for_existing_payment_and_transactions_with_expected_structure(): void
    {
        $response = $this->makeRequest(paymentId: $this->payment->id, transactionId: $this->transaction->id);

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
                'links' => ['self'],
            ],
            'result' => [
                'id' =>
                'payment_id',
                'transaction_type' => ['id', 'name', 'description'],
                'gateway_transaction_id',
                'gateway_response_code',
                'created_at',
            ],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
    }

    #[Test]
    public function it_returns_400_bad_request_response_for_invalid_payment_id(): void
    {
        $response = $this->makeRequest(paymentId: 'string', transactionId: $this->transaction->id);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('validation.parameter_invalid_uuid', ['parameter' => 'payment id']));
    }

    #[Test]
    public function it_returns_400_bad_request_response_for_invalid_transaction_id(): void
    {
        $response = $this->makeRequest(paymentId: $this->payment->id, transactionId: 'string');

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('validation.parameter_invalid_uuid', ['parameter' => 'transaction id']));
    }

    #[Test]
    public function it_returns_404_not_found_response_for_non_existing_payment(): void
    {
        $nonExistingPaymentId = Str::uuid()->toString();
        $response = $this->makeRequest(paymentId: $nonExistingPaymentId, transactionId: $this->transaction->id);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.payment.not_found', ['id' => $nonExistingPaymentId]));
    }

    #[Test]
    public function it_returns_404_not_found_response_for_non_existing_transaction(): void
    {
        $nonExistingTransactionId = Str::uuid()->toString();
        $response = $this->makeRequest(paymentId: $this->payment->id, transactionId: $nonExistingTransactionId);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.payment_transaction.not_found', ['id' => $nonExistingTransactionId]));
    }

    #[Test]
    public function it_returns_404_not_found_response_for_existing_transaction_that_does_not_relate_to_the_given_payment(): void
    {
        $anotherExistingPayment = Payment::factory()->create();
        $response = $this->makeRequest(paymentId: $anotherExistingPayment->id, transactionId: $this->transaction->id);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.payment_transaction.not_found', ['id' => $this->transaction->id]));
    }

    private function makeRequest(string $paymentId, string $transactionId): TestResponse
    {
        return $this->get(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentId, $transactionId)),
            headers: ['Api-Key' => config('auth.api_keys.payment_processing')]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->payment, $this->transaction);
    }
}
