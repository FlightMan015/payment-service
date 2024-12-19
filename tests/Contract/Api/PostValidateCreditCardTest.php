<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\Gateway;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\Credentials\Credentials;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;

class PostValidateCreditCardTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/credit-cards/validation';

    #[Test]
    public function it_returns_200_response_with_valid_request_with_cc_token_worldpay(): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials(credentials: WorldpayCredentialsStub::make());
        $this->mockPaymentProcessorAuthorize(authorizeSuccess: true);

        $response = $this->makeRequest(data: [
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'office_id' => 1,
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_200_response_with_valid_request_with_cc_token_worldpay_tokenex_transparent(): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials(credentials: WorldpayCredentialsStub::make());
        $this->mockPaymentProcessorAuthorize(authorizeSuccess: true);

        $response = $this->makeRequest(data: [
            'gateway_id' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT->value,
            'office_id' => 1,
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_200_response_with_is_valid_false_when_authorization_fails_in_gateway(): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials(credentials: WorldpayCredentialsStub::make());
        $this->mockPaymentProcessorAuthorize(authorizeSuccess: false);

        $response = $this->makeRequest(data: [
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'office_id' => 1,
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response, isValid: false);
    }

    #[Test]
    public function it_returns_400_bad_request_if_required_parameters_are_missing(): void
    {
        $response = $this->makeRequest(data: [
            // empty body
        ]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure($response);
    }

    #[Test]
    #[DataProvider('nonValidGatewayProvider')]
    public function it_returns_400_bad_request_if_gateway_is_hidden_or_disabled(bool $isHidden, bool $isEnabled): void
    {
        Gateway::whereId(PaymentGatewayEnum::WORLDPAY->value)->update([
            'is_hidden' => $isHidden,
            'is_enabled' => $isEnabled,
        ]);

        $response = $this->makeRequest(data: [
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value + 100,
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'office_id' => 1,
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
        ]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure($response);
    }

    public static function nonValidGatewayProvider(): \Iterator
    {
        yield 'is_hidden' => [
            'isHidden' => true,
            'isEnabled' => true,
        ];
        yield 'is_disabled' => [
            'isHidden' => false,
            'isEnabled' => false,
        ];
    }

    private function mockDynamoDbForGettingWorldPayCredentials(Credentials $credentials): void
    {
        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn($credentials);
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);
    }

    private function mockPaymentProcessorAuthorize(bool $authorizeSuccess): void
    {
        $mockPaymentProcessor = $this->partialMock(PaymentProcessor::class, static function (MockInterface $mock) use ($authorizeSuccess) {
            $mock->expects('authorize')->andReturn($authorizeSuccess);
            $mock->expects('getError')->andReturn($authorizeSuccess ? null : __('messages.operation.something_went_wrong'));
        });
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockPaymentProcessor);
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
            expect: $isValid ? __('messages.credit_card.validate.success') : __('messages.credit_card.validate.gateway_invalid_response')
        );
    }

    private function assertErrorResponseStructure(TestResponse $response, bool $hasError = true): void
    {
        $response->assertValid();

        $structure = [
            '_metadata' => ['success'],
            'result' => [
                'message',
            ],
        ];

        if ($hasError) {
            $structure['result']['errors'] = ['*' => ['detail']];
        }

        $response->assertJsonStructure($structure, $response->json());
    }

    private function makeRequest(array $data = [], array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
        ];

        return $this->post(
            uri: url(path: self::ENDPOINT_URI),
            data: $data,
            headers: $headers ?? $defaultHeaders
        );
    }
}
