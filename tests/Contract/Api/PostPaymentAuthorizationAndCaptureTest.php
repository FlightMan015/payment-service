<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Events\PaymentAttemptedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use GuzzleHttp\Client as GuzzleClient;
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

class PostPaymentAuthorizationAndCaptureTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/authorization-and-capture';

    private Account $account;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->paymentMethod = $this->createPaymentMethodDatabaseRecord();
        Event::fake();
    }

    #[Test]
    public function it_returns_200_success_response_for_valid_input_with_all_parameters(): void
    {
        $this->mockWorldPayGuzzleAuthAndCapture(isSuccess: true);

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $this->paymentMethod->id,
            'notes' => 'some notes',
        ]);

        $response->assertJsonPath(path: 'result.message', expect: __('messages.payment.authorized_and_captured'));
        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);
        $response->assertJsonPath(path: 'result.status', expect: PaymentStatusEnum::CAPTURED->name);
        $this->assertDatabaseHas(table: Payment::class, data: [
            'id' => $response->json(key: 'result.payment_id'),
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'notes' => 'some notes',
        ]);
        Event::assertDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_returns_200_success_response_for_valid_input_without_method_id(): void
    {
        $this->mockWorldPayGuzzleAuthAndCapture(isSuccess: true);

        $paymentMethod = PaymentMethod::factory()->cc()->create(['account_id' => $this->account->id]);
        $paymentMethod->makePrimary();

        $response = $this->makeRequest(inputData: ['amount' => 1234, 'account_id' => $this->account->id]);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);
        $response->assertJsonPath('result.status', PaymentStatusEnum::CAPTURED->name);
        $this->assertDatabaseHas(table: Payment::class, data: [
            'id' => $response->json(key: 'result.payment_id'),
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
        ]);
        Event::assertDispatched(event: PaymentAttemptedEvent::class);
    }

    #[Test]
    #[DataProvider('invalidInputProvider')]
    public function it_returns_400_bad_request_response_for_invalid_input(array $input, array $expected): void
    {
        $response = $this->makeRequest(inputData: $input);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
        $this->assertSame($expected['messages'], $response->json(key: 'result.errors.*.detail'));
    }

    #[Test]
    public function it_returns_400_bad_request_response_for_deleted_payment_method(): void
    {
        $paymentMethod = PaymentMethod::factory()->create(['account_id' => $this->account->id]);
        $paymentMethod->delete(); // soft delete

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $paymentMethod->id,
        ]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
        $response->assertJsonFragment(data: ['detail' => __('messages.operation.given_payment_method_not_found')]);
    }

    #[Test]
    public function it_returns_400_bad_request_response_for_invalid_account_id(): void
    {
        $response = $this->makeRequest(inputData: ['amount' => 123, 'account_id' => Str::uuid()->toString()]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
        $this->assertSame(expected: __('messages.account.not_found_in_db'), actual: $response->json(key: 'result.errors.0.detail'));
    }

    #[Test]
    public function it_returns_400_bad_request_response_when_account_does_not_have_a_primary_payment_method(): void
    {
        PaymentMethod::whereAccountId($this->account->id)->delete();
        $response = $this->makeRequest(inputData: ['amount' => 1234, 'account_id' => $this->account->id]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
        $this->assertSame(
            expected: __('messages.operation.primary_payment_method_not_found'),
            actual: $response->json(key: 'result.errors.0.detail')
        );
    }

    #[Test]
    public function it_returns_400_bad_request_response_when_payment_method_does_not_belong_to_the_account(): void
    {
        $anotherAccount = Account::factory()->create();

        $paymentMethod = PaymentMethod::factory()->create(attributes: ['account_id' => $anotherAccount->id]);

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $paymentMethod->id,
        ]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
        $this->assertSame(
            expected: __('messages.operation.given_payment_method_not_belong_to_account'),
            actual: $response->json(key: 'result.errors.0.detail')
        );
    }

    #[Test]
    public function it_returns_422_unprocessable_response_when_worldpay_returns_unsuccessful_response(): void
    {
        $this->mockWorldPayGuzzleAuthAndCapture(isSuccess: false, errorMessage: 'TransactionAmount invalid');

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $this->paymentMethod->id
        ]);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);
        $this->assertErrorResponseStructure(response: $response, hasErrors: false);
        $this->assertSame(
            expected: __('messages.operation.authorization_and_capture.gateway_error', ['message' => 'TransactionAmount invalid']),
            actual: $response->json(key: 'result.message')
        );
    }

    public static function invalidInputProvider(): \Iterator
    {
        yield 'empty body' => [
            'input' => [],
            'expected' => [
                'messages' => [
                    'The amount field is required.',
                    'The account id field is required.',
                ],
            ],
        ];

        yield 'non-existing method id' => [
            'input' => [
                'amount' => 100,
                'account_id' => static fn () => Account::factory()->create()->id,
                'method_id' => Str::uuid()->toString(),
            ],
            'expected' => [
                'messages' => [
                    'The selected method id is invalid.',
                ],
            ],
        ];

        yield 'wrong format' => [
            'input' => [
                'amount' => 'non-numeric',
                'account_id' => 'some string',
                'method_id' => 12345,
            ],
            'expected' => [
                'messages' => [
                    'The amount must be an integer.',
                    'The account id must be a valid UUID.',
                    'The method id must be a valid UUID.',
                ],
            ],
        ];

        yield 'float amount format' => [
            'input' => [
                'account_id' => static fn () => Account::factory()->create()->id,
                'amount' => 123.4456,
            ],
            'expected' => [
                'messages' => [
                    'The amount must be an integer.',
                ],
            ],
        ];
    }

    private function createPaymentMethodDatabaseRecord(): PaymentMethod
    {
        return PaymentMethod::factory()->cc()->create([
            'account_id' => $this->account,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
        ]);
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => [
                'message',
                'status',
                'payment_id',
                'transaction_id',
            ],
        ], $response->json());

        $response->assertJsonPath('_metadata.success', true);
    }

    private function assertErrorResponseStructure(TestResponse $response, bool $hasErrors = true): void
    {
        $response->assertValid();

        $errors = $hasErrors ? ['errors' => ['*' => ['detail']]] : [];

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
                ...$errors
            ],
        ], $response->json());

        $response->assertJsonPath('_metadata.success', false);
    }

    private function mockDynamoDbForGettingWorldPayCredentials(): void
    {
        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn(WorldpayCredentialsStub::make());
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);
    }

    private function mockWorldPayGuzzleAuthAndCapture(
        bool $isSuccess = true,
        int|string|null $transactionId = '123457',
        string|null $errorMessage = null,
        \Throwable|null $throwException = null
    ): void {
        /**
         * @var GuzzleClient|MockObject $guzzle
         */
        $guzzle = $this->createMock(GuzzleClient::class);

        if (!is_null($throwException)) {
            $guzzle->method('post')->willThrowException($throwException);
        } else {
            $guzzle->method('post')->willReturnOnConsecutiveCalls(
                new Response(
                    status: 200,
                    headers: ['Content-Type' => 'application/json'],
                    body: $isSuccess ?
                        WorldpayResponseStub::authCaptureSuccess(transactionId: $transactionId) :
                        WorldpayResponseStub::authCaptureUnsuccess($errorMessage),
                ),
            );
        }

        $this->app->instance(abstract: GuzzleClient::class, instance: $guzzle);
    }

    private function makeRequest(array $inputData): TestResponse
    {
        $this->mockDynamoDbForGettingWorldPayCredentials();

        return $this->post(
            uri: url(path: self::ENDPOINT_URI),
            data: $inputData,
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
                'Origin' => 'test service',
            ]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->account, $this->paymentMethod);
    }
}
