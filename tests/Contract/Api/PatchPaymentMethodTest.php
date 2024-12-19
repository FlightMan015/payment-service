<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractContractTest;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;

class PatchPaymentMethodTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payment-methods/%s';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    #[Test]
    #[DataProvider('successfulProvider')]
    public function it_returns_200_response_for_successful_scenario(array $input, array $expected = []): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: $input['paymentMethodAttributes'] ?? []);

        $this->mockDynamoDbForGettingWorldPayCredentials();
        $this->mockWorldPayGuzzleUpdatePayment(isSuccess: true, hasPaymentAccount: true);
        $this->mockProcessorAuthorizeResult(isSuccess: true);

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id, input: $input['updateData'] ?? []);

        $this->assertSuccessResponseStructure($response);
        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.payment_method.updated'));

        if (!empty($expected['paymentMethod'])) {
            $this->assertDatabaseHas(
                table: PaymentMethod::class,
                data: array_merge($expected['paymentMethod'], ['id' => $paymentMethod->id])
            );
        }
    }

    #[Test]
    public function it_returns_200_response_for_successful_making_payment_method_primary(): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: ['payment_type_id' => PaymentTypeEnum::ACH->value, 'is_primary' => false]);

        $this->mockDynamoDbForGettingWorldPayCredentials();
        $this->mockWorldPayGuzzleUpdatePayment(isSuccess: true, hasPaymentAccount: true);
        $this->mockProcessorAuthorizeResult(isSuccess: true);

        $this->assertPaymentMethodPrimaryStatus(paymentMethod: $paymentMethod, isPrimary: false);

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id, input: ['is_primary' => 1]);

        $this->assertSuccessResponseStructure($response);
        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.payment_method.updated'));

        $this->assertPaymentMethodPrimaryStatus(paymentMethod: $paymentMethod, isPrimary: true);

        // Test that passing "null" will do nothing
        $this->makeRequest(paymentMethodId: $paymentMethod->id, input: ['is_primary' => null]);
        $this->assertPaymentMethodPrimaryStatus(paymentMethod: $paymentMethod, isPrimary: true);
    }

    #[Test]
    public function it_returns_400_response_for_cc_expiration_in_the_past(): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: ['payment_type_id' => PaymentTypeEnum::CC->value]);
        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id, input: [
            'cc_expiration_month' => 12,
            'cc_expiration_year' => 22,
        ]);
        $response->assertStatus(HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    public function it_returns_400_response_for_updating_cc_fields_ach_payment_method(): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials();

        $paymentMethod = PaymentMethod::factory()->ach()->create(['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]);
        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id, input: [
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date('Y', strtotime('next year')),
        ]);

        $response->assertStatus(HttpStatus::BAD_REQUEST);
        $response->assertJsonPath('result.message', __('messages.payment_method.update.cannot_update_cc_fields_on_ach'));
        $this->assertErrorResponseStructure(response: $response);
    }

    #[Test]
    #[DataProvider('unauthorizedProvider')]
    public function it_returns_401_response_for_empty_api_key(array|null $headers): void
    {
        $response = $this->makeRequest(paymentMethodId: Str::uuid()->toString(), headers: $headers);

        $response->assertStatus(HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure(response: $response);
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
    #[DataProvider('gatewayFailProvider')]
    public function it_returns_422_response_when_worldpay_fails(array $input, array $expected): void
    {
        $paymentMethod = $this->createPaymentMethod(attributes: ['payment_type_id' => PaymentTypeEnum::CC->value]);

        $this->mockDynamoDbForGettingWorldPayCredentials();
        $this->mockWorldPayGuzzleUpdatePayment(
            isSuccess: $input['isUpdatePaymentAccountSuccess'],
            hasPaymentAccount: $input['hasPaymentAccount']
        );
        $this->mockProcessorAuthorizeResult(isSuccess: $input['isAuthorizeSuccess']);

        $response = $this->makeRequest(paymentMethodId: $paymentMethod->id, input: ['address_line1' => 'Test']);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);
        $this->assertErrorResponseStructure($response);
        $this->assertStringContainsString(
            needle: sprintf($expected['message'], $paymentMethod->id),
            haystack: $response->json('result.message')
        );
    }

    public static function successfulProvider(): \Iterator
    {
        yield 'CreditCard - empty data' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                    'cc_expiration_month' => 11,
                    'cc_expiration_year' => date('Y', strtotime('next year')),
                ],
                'updateData' => [],
            ],
        ];
        yield 'CreditCard - non-empty data' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                ],
                'updateData' => [
                    'address_line1' => 'UpdatedData',
                    'cc_expiration_month' => 1,
                    'cc_expiration_year' => date('Y', strtotime('next year')),
                ],
            ],
            'expected' => [
                'paymentMethod' => [
                    'address_line1' => 'UpdatedData',
                    'cc_expiration_month' => 1,
                    'cc_expiration_year' => date('Y', strtotime('next year')),
                ],
            ],
        ];
        yield 'CreditCard - non-empty data with string month' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                ],
                'updateData' => [
                    'address_line1' => 'UpdatedData',
                    'cc_expiration_month' => '03',
                    'cc_expiration_year' => date('Y', strtotime('+ 10 years')),
                ],
            ],
            'expected' => [
                'paymentMethod' => [
                    'address_line1' => 'UpdatedData',
                    'cc_expiration_month' => 3,
                    'cc_expiration_year' => date('Y', strtotime('+ 10 years')),
                ],
            ],
        ];
        yield 'ACH - empty data' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::ACH->value,
                ],
                'updateData' => [],
            ],
        ];
        yield 'ACH - non-empty data' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::ACH->value,
                ],
                'updateData' => [
                    'address_line1' => 'UpdatedData',
                    'first_name' => 'Test',
                    'last_name' => 'Test',
                ],
            ],
            'expected' => [
                'paymentMethod' => [
                    'address_line1' => 'UpdatedData',
                    'name_on_account' => 'Test Test',
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

    public static function gatewayFailProvider(): \Iterator
    {
        yield 'worldpay retrieving account fails' => [
            'input' => [
                'hasPaymentAccount' => false,
                'isAuthorizeSuccess' => true,
                'isUpdatePaymentAccountSuccess' => true,
            ],
            'expected' => [
                'message' => static fn () => __('messages.payment_method.update.cannot_retrieve_payment_account'),
            ]
        ];
        yield 'authorize fails' => [
            'input' => [
                'hasPaymentAccount' => true,
                'isAuthorizeSuccess' => false,
                'isUpdatePaymentAccountSuccess' => true,
            ],
            'expected' => [
                'message' => 'Payment Method %s is invalid',
            ]
        ];
        yield 'worldpay update account fails' => [
            'input' => [
                'hasPaymentAccount' => true,
                'isAuthorizeSuccess' => true,
                'isUpdatePaymentAccountSuccess' => false,
            ],
            'expected' => [
                'message' => 'Could not update payment account to gateway',
            ]
        ];
    }

    private function makeRequest(string $paymentMethodId, array $input = [], array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
            'Origin' => 'some_service_name',
        ];

        return $this->patch(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentMethodId)),
            data: $input,
            headers: $headers ?? $defaultHeaders
        );
    }

    private function createPaymentMethod(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::factory()->cc()->create(attributes: array_merge([
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value
        ], $attributes));
    }

    private function mockDynamoDbForGettingWorldPayCredentials(): void
    {
        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn(WorldpayCredentialsStub::make());
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);
    }

    private function mockWorldPayGuzzleUpdatePayment(bool $isSuccess = true, bool $hasPaymentAccount = true): void
    {
        /**
         * @var Client|MockObject
         */
        $guzzle = $this->createMock(Client::class);

        $guzzle->method('post')->willReturnOnConsecutiveCalls(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $hasPaymentAccount ? WorldpayResponseStub::getPaymentAccountSuccess() : WorldpayResponseStub::getPaymentAccountUnsuccess(),
            ),
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $isSuccess ? WorldpayResponseStub::updatePaymentAccountSuccess() : WorldpayResponseStub::updatePaymentAccountUnsuccess(),
            )
        );

        $this->app->instance(abstract: Client::class, instance: $guzzle);
    }

    private function mockProcessorAuthorizeResult(bool $isSuccess): void
    {
        $mocked = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mocked->method('authorize')->willReturn($isSuccess);

        $this->app->instance(abstract: PaymentProcessor::class, instance: $mocked);
    }

    private function assertPaymentMethodPrimaryStatus(PaymentMethod $paymentMethod, bool $isPrimary): void
    {
        $this->assertDatabaseHas(
            table: PaymentMethod::class,
            data: ['id' => $paymentMethod->id, 'is_primary' => $isPrimary]
        );
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
