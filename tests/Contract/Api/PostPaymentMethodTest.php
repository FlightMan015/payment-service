<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Repositories\DatabasePaymentMethodRepository;
use App\Models\CRM\Customer\Account;
use App\Models\Gateway;
use App\PaymentProcessor\Enums\CreditCardTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\Credentials\Credentials;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;

class PostPaymentMethodTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payment-methods';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    #[Test]
    public function it_returns_201_created_response_with_valid_request_with_cc_payment_type(): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials(credentials: WorldpayCredentialsStub::make());
        $this->mockWorldPayGuzzleCalls(withPaymentAccountRetrieving: true);
        $account = Account::factory()->create();

        $response = $this->makeRequest(data: [
            'account_id' => $account->id,
            'type' => PaymentTypeEnum::CC->name,
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'first_name' => 'Ivan',
            'last_name' => 'Vasechko',
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'cc_type' => CreditCardTypeEnum::MASTERCARD->value,
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
            'cc_last_four' => '9182',
            'address_line1' => 'Address line 1',
            'email' => 'ivan.vasechko@goaptive.com',
            'city' => 'Utah',
            'province' => 'UT',
            'postal_code' => '01103',
            'country_code' => 'US',
            'is_primary' => true
        ]);

        $response->assertStatus(status: HttpStatus::CREATED);
    }

    #[Test]
    public function it_returns_201_created_response_with_valid_request_with_ach_payment_type(): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials(credentials: WorldpayCredentialsStub::make());
        $this->mockWorldPayGuzzleCalls(withPaymentAccountRetrieving: false);
        $account = Account::factory()->create();

        $response = $this->makeRequest(data: [
            'account_id' => $account->id,
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'type' => PaymentTypeEnum::ACH->name,
            'first_name' => 'Ivan',
            'last_name' => 'Vasechko',
            'ach_account_number' => '123456789',
            'ach_routing_number' => '987654321',
            'ach_account_last_four' => '6789',
            'address_line1' => 'Address line 1',
            'email' => 'ivan.vasechko@goaptive.com',
            'city' => 'Utah',
            'province' => 'UT',
            'postal_code' => '01103',
            'country_code' => 'US',
            'is_primary' => true
        ]);

        $response->assertStatus(status: HttpStatus::CREATED);
        $this->assertSuccessResponseStructure($response);
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
    public function it_returns_400_bad_request_if_gateway_type_id_does_not_exists(): void
    {
        $response = $this->makeRequest(data: [
            'account_id' => Str::uuid()->toString(),
            'type' => PaymentTypeEnum::CC->name,
            'gateway_id' => max(array_column(PaymentGatewayEnum::cases(), 'value')) + 10,
            'first_name' => 'Ivan',
            'last_name' => 'Vasechko',
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'address_line1' => 'Address line 1',
            'email' => 'ivan.vasechko@goaptive.com',
            'city' => 'Utah',
            'province' => 'UT',
            'postal_code' => '01103',
            'country_code' => 'US',
            'is_primary' => true
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
            'account_id' => Str::uuid()->toString(),
            'type' => PaymentTypeEnum::CC->name,
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'first_name' => 'Ivan',
            'last_name' => 'Vasechko',
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'address_line1' => 'Address line 1',
            'email' => 'ivan.vasechko@goaptive.com',
            'city' => 'Utah',
            'province' => 'UT',
            'postal_code' => '01103',
            'country_code' => 'US',
            'is_primary' => true
        ]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure($response);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_not_found(): void
    {
        $response = $this->makeRequest(headers: []); // Missing API Key header

        $response->assertStatus(HttpStatus::UNAUTHORIZED);
    }

    #[Test]
    public function it_returns_422_unprocessable_response_with_not_found_account(): void
    {
        $response = $this->makeRequest(data: [
            'account_id' => Str::uuid()->toString(),
            'type' => PaymentTypeEnum::CC->name,
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'first_name' => 'Ivan',
            'last_name' => 'Vasechko',
            'cc_token' => 'XXXXXXX-Xxxxxx-XXXXXXXX',
            'cc_expiration_month' => 1,
            'cc_expiration_year' => date(format: 'Y', timestamp: strtotime(datetime: 'next year')),
            'cc_last_four' => 3523,
            'address_line1' => 'Address line 1',
            'email' => 'ivan.vasechko@goaptive.com',
            'city' => 'Utah',
            'province' => 'UT',
            'postal_code' => '01103',
            'country_code' => 'US',
            'is_primary' => true
        ]);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function it_returns_500_server_error_if_db_throws_exception(): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials(credentials: WorldpayCredentialsStub::make());
        $this->mockWorldPayGuzzleCalls(withPaymentAccountRetrieving: false);
        $account = Account::factory()->create();

        $repository = $this->getMockBuilder(className: DatabasePaymentMethodRepository::class)->getMock();
        $repository->method('create')->willThrowException(exception: new ConnectionException(message: 'Connection issues'));
        $this->app->instance(abstract: DatabasePaymentMethodRepository::class, instance: $repository);

        $response = $this->makeRequest(data: [
            'account_id' => $account->id,
            'gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
            'type' => PaymentTypeEnum::ACH->name,
            'first_name' => 'Ivan',
            'last_name' => 'Vasechko',
            'ach_account_number' => '123456789',
            'ach_routing_number' => '987654321',
            'ach_account_last_four' => '6789',
            'address_line1' => 'Address line 1',
            'email' => 'ivan.vasechko@goaptive.com',
            'city' => 'Utah',
            'province' => 'UT',
            'postal_code' => '01103',
            'country_code' => 'US',
            'is_primary' => true
        ]);

        $response->assertStatus(status: HttpStatus::INTERNAL_SERVER_ERROR);
        $this->assertErrorResponseStructure(response: $response, hasError: false);
    }

    public static function gatewayFailProvider(): \Iterator
    {
        yield 'could not found payment account' => [
            'input' => [
                'foundPaymentAccount' => false,
                'withPaymentBrand' => 'Amex',
            ],
            'expected' => [
                'message' => 'Payment Account was not found in gateway',
            ],
        ];
        yield 'payment type does not match with payment account' => [
            'input' => [
                'foundPaymentAccount' => true,
                'withPaymentBrand' => 'Visa',
            ],
            'expected' => [
                'message' => 'Payment type does not match with gateway payment account',
            ],
        ];
    }

    public static function nonValidGatewayProvider(): \Iterator
    {
        yield 'is_hidden' => [
            'isHidden' => true,
            'isEnabled' => true,
        ];
        yield 'is_enabled' => [
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

    private function mockWorldPayGuzzleCalls(bool $withPaymentAccountRetrieving = true, string $withPaymentAccountBrand = 'Amex'): void
    {
        $responses = [
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::authorizeSuccess(),
            )
        ];

        if ($withPaymentAccountRetrieving) {
            array_unshift($responses, new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: WorldpayResponseStub::paymentAccountQuerySuccess(paymentBrand: $withPaymentAccountBrand),
            ));
        }

        $guzzle = $this->createMock(GuzzleClient::class);
        $guzzle->method('post')->willReturnOnConsecutiveCalls(...$responses);
        $this->app->instance(abstract: GuzzleClient::class, instance: $guzzle);
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => [
                'message',
                'payment_method_id',
            ],
        ], $response->json());
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
            'Origin' => 'some_service_name',
        ];

        return $this->post(
            uri: url(path: self::ENDPOINT_URI),
            data: $data,
            headers: $headers ?? $defaultHeaders
        );
    }
}
