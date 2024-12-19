<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\LostConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;
use Tests\Helpers\Traits\RepositoryMockingTrait;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;

class PostValidatePaymentMethodTest extends AbstractContractTest
{
    use RepositoryMockingTrait;

    private const string ENDPOINT_URI = '/api/v1/payment-methods/%s/validation';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    #[Test]
    public function it_returns_200_success_response_for_valid_non_ach_payment_method(): void
    {
        // since validation assume calling authorize() that is not allowed to be called independently
        // for ACH type payment method we need to set payment type to credit card here
        $paymentMethod = PaymentMethod::factory()->cc()->create(attributes: [
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
        ]);
        $this->mockDynamoDbForGettingWorldPayCredentials();
        $this->mockWorldPayGuzzleAuthorizeCall(isSuccess: true);

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_200_success_response_for_invalid_payment_method(): void
    {
        $paymentMethod = PaymentMethod::factory()->cc()->create(attributes: [
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value
        ]);
        $this->mockDynamoDbForGettingWorldPayCredentials();
        $this->mockWorldPayGuzzleAuthorizeCall(isSuccess: false);

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response, isValid: false);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_not_found(): void
    {
        $response = $this->makeRequest(paymentMethodId: Str::uuid()->toString(), headers: []); // Missing API Key header

        $response->assertStatus(HttpStatus::UNAUTHORIZED);
    }

    #[Test]
    public function it_returns_404_not_found_response_for_soft_deleted_payment_method(): void
    {
        $paymentMethod = PaymentMethod::factory()->create();
        $paymentMethod->delete();

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);
        $this->assertErrorResponseStructure(response: $response);
        $response->assertJsonFragment(data: ['message' => __('messages.payment_method.not_found', ['id' => $paymentMethod->id])]);
    }

    #[Test]
    public function it_returns_404_not_found_response_for_non_existing_payment_method(): void
    {
        $nonExistingPaymentMethodId = Str::uuid()->toString();

        $response = $this->makeRequest(paymentMethodId: $nonExistingPaymentMethodId);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);
        $this->assertErrorResponseStructure(response: $response);
        $response->assertJsonFragment(data: ['message' => __('messages.payment_method.not_found', ['id' => $nonExistingPaymentMethodId])]);
    }

    #[Test]
    public function it_returns_500_server_error_if_db_throws_exception(): void
    {
        $paymentMethod = PaymentMethod::factory()->create();
        $this->mockDynamoDbForGettingWorldPayCredentials();
        $this->mockWorldPayGuzzleAuthorizeCall(isSuccess: true);

        $this->repositoryWillThrowException(
            repositoryClass: PaymentRepository::class,
            method: 'create',
            exception: new LostConnectionException(message: 'Connection issues')
        );

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id);

        $response->assertStatus(status: HttpStatus::INTERNAL_SERVER_ERROR);
        $this->assertErrorResponseStructure($response);
    }

    private function mockDynamoDbForGettingWorldPayCredentials(): void
    {
        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn(WorldpayCredentialsStub::make());
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);
    }

    private function mockWorldPayGuzzleAuthorizeCall(bool $isSuccess): void
    {
        $guzzle = $this->createMock(GuzzleClient::class);
        $guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $isSuccess ? WorldpayResponseStub::authorizeSuccess() : WorldpayResponseStub::authorizeUnsuccessful(errorMessage: 'Invalid Token'),
            )
        );
        $this->app->instance(abstract: GuzzleClient::class, instance: $guzzle);
    }

    private function assertSuccessResponseStructure(TestResponse $response, bool $isValid = true): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => ['message', 'is_valid'],
        ], $response->json());

        $response->assertJsonPath(path: 'result.is_valid', expect: $isValid);
        $response->assertJsonPath(
            path: 'result.message',
            expect: $isValid ? __('messages.payment_method.validated') : __('messages.payment_method.gateway_invalid_response')
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

    private function makeRequest(string $paymentMethodId, array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
            'Origin' => 'some_service_name',
        ];

        return $this->post(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentMethodId)),
            headers: $headers ?? $defaultHeaders
        );
    }
}
