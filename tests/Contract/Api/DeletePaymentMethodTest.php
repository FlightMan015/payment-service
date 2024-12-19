<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class DeletePaymentMethodTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payment-methods/%s';

    #[Test]
    public function it_returns_200_response_for_successful_scenario(): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: ['is_primary' => false]);

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id);

        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.payment_method.deleted'));
        $this->assertSuccessResponseStructure($response);

        $this->assertSoftDeleted($paymentMethod);
    }

    #[Test]
    public function it_returns_404_response_for_non_existing_id(): void
    {
        $response = $this->makeRequest(paymentMethodId: Str::uuid()->toString());

        $response->assertStatus(HttpStatus::NOT_FOUND);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_404_response_for_deleted_payment_method(): void
    {
        $paymentMethod = $this->createPaymentMethod();
        $paymentMethod->delete();
        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id);

        $response->assertStatus(HttpStatus::NOT_FOUND);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_422_response_when_trying_to_delete_primary_payment_method(): void
    {
        $paymentMethod = $this->createPaymentMethod();
        $paymentMethod->makePrimary();

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('result.message', __('messages.payment_method.primary.cannot_delete'));
        $this->assertErrorResponseStructure($response);
    }

    private function makeRequest(string $paymentMethodId, array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
            'Origin' => 'some_service_name',
        ];

        return $this->delete(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentMethodId)),
            headers: $headers ?? $defaultHeaders
        );
    }

    private function createPaymentMethod(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::factory()->create(attributes: array_merge([
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value
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
