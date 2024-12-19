<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\Payment;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PatchPaymentTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/%s';

    #[Test]
    #[DataProvider('successfulProvider')]
    public function it_returns_200_response_for_successful_scenario(array $input): void
    {
        $payment = $this->createPayment(attributes: $input['paymentAttributes'] ?? []);

        $response = $this->makeRequest(paymentId: $payment->id, input: $input['updateData'] ?? []);

        $this->assertSuccessResponseStructure($response);
        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.payment.updated'));
    }

    #[Test]
    #[DataProvider('unauthorizedProvider')]
    public function it_returns_401_response_for_empty_api_key(array $headers): void
    {
        $response = $this->makeRequest(paymentId: Str::uuid()->toString(), headers: $headers);

        $response->assertStatus(HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_404_response_for_non_existing_id(): void
    {
        $response = $this->makeRequest(paymentId: Str::uuid()->toString());

        $response->assertStatus(HttpStatus::NOT_FOUND);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    #[DataProvider('nonLedgerOnlyProvider')]
    public function it_returns_422_response_when_worldpay_fails(array $input): void
    {
        $payment = $this->createPayment(attributes: ['payment_type_id' => PaymentTypeEnum::CC->value]);

        $response = $this->makeRequest(paymentId: $payment->id, input: ['amount' => 100]);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);
        $this->assertErrorResponseStructure($response);
    }

    public static function successfulProvider(): \Iterator
    {
        yield 'Check payment - empty data' => [
            'input' => [
                'paymentAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CHECK,
                ],
                'updateData' => [],
            ],
        ];
        yield 'Check payment - non-empty data' => [
            'input' => [
                'paymentAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CHECK,
                ],
                'updateData' => [
                    'amount' => 100,
                    'check_date' => '2021-01-01',
                ],
            ],
        ];
    }

    public static function unauthorizedProvider(): \Iterator
    {
        yield 'empty api key' => [
            'headers' => ['Some Key' => '123'],
        ];
        yield 'wrong api key' => [
            'headers' => ['Api-Key' => 'wrong value'],
        ];
    }

    public static function nonLedgerOnlyProvider(): \Iterator
    {
        yield 'payment with ledger type not found' => [
            'input' => [
                'payment_type_id' => PaymentTypeEnum::CC,
            ],
        ];
    }

    private function makeRequest(string $paymentId, array $input = [], array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
            'Origin' => 'some_service_name',
        ];

        return $this->patch(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentId)),
            data: $input,
            headers: $headers ?? $defaultHeaders
        );
    }

    private function createPayment(array $attributes = []): Payment
    {
        return Payment::factory()->create(attributes: array_merge([
            'payment_gateway_id' => PaymentGatewayEnum::CHECK->value,
        ], $attributes));
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => ['message'],
        ], $response->json());
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());
    }
}
