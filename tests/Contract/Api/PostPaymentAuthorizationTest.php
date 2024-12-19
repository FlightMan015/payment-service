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
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractContractTest;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;

class PostPaymentAuthorizationTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/authorization';

    private Account $account;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->paymentMethod = $this->createPaymentMethodDatabaseRecord();

        Event::fake();
    }

    private function createPaymentMethodDatabaseRecord(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::factory()->create(attributes: [
            'account_id' => $this->account->id,
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
        ] + $attributes);
    }

    #[Test]
    #[DataProvider('successfulResponseWithAllParameterProvider')]
    public function it_returns_200_success_response_for_valid_input_with_all_parameters(
        array $paymentMethodAttributes
    ): void {
        $this->paymentMethod = $this->createPaymentMethodDatabaseRecord(attributes: $paymentMethodAttributes);

        /** @var MockObject|PaymentProcessor $mockProcessor */
        $mockProcessor = $this->createMock(PaymentProcessor::class);
        $mockProcessor->method('authorize')->willReturn(true);
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $this->paymentMethod->id,
        ]);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);
        $response->assertJsonPath(path: 'result.status', expect: PaymentStatusEnum::AUTHORIZED->name);
        $this->assertDatabaseHas(table: Payment::class, data: [
            'id' => $response->json(key: 'result.payment_id'),
            'payment_status_id' => PaymentStatusEnum::AUTHORIZED->value,
        ]);

        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_returns_200_success_response_for_valid_input_without_method_id(): void
    {
        $this->paymentMethod->makePrimary();

        /** @var MockObject|PaymentProcessor $mockProcessor */
        $mockProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $mockProcessor->method('authorize')->willReturn(true);
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(inputData: ['amount' => 1234, 'account_id' => $this->account->id]);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure(response: $response);
        $response->assertJsonPath('result.status', PaymentStatusEnum::AUTHORIZED->name);
        $this->assertDatabaseHas(table: Payment::class, data: [
            'id' => $response->json(key: 'result.payment_id'),
            'payment_status_id' => PaymentStatusEnum::AUTHORIZED->value,
        ]);

        Event::assertDispatched(PaymentAttemptedEvent::class);
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
        $this->paymentMethod->delete();

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $this->paymentMethod->id,
        ]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure(response: $response);
        $response->assertJsonFragment(data: ['detail' => __('messages.operation.given_payment_method_not_found')]);
    }

    #[Test]
    public function it_returns_400_bad_request_response_when_account_does_not_have_a_primary_payment_method(): void
    {
        // we are not creating any payment method here
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
        $paymentMethod = PaymentMethod::factory(['account_id' => $anotherAccount->id])->create();

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
    public function it_returns_422_response_with_error_message_if_worldpay_return_unsuccessful_response(): void
    {
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mockProcessor->method('authorize')->willReturn(false);
        $mockProcessor->method('getError')->willReturn($expectedMessage = 'Some error message from Payment here');

        /* @var PaymentProcessor $mockProcessor */
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $inputData = [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $this->paymentMethod->id
        ];
        $response = $this->makeRequest(inputData: $inputData);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);

        $this->assertErrorResponseStructure(response: $response, hasErrors: false);
        $this->assertSame(
            expected: __('messages.operation.authorization.gateway_error', ['message' => $expectedMessage]),
            actual: $response->json(key: 'result.message')
        );
    }

    #[Test]
    public function it_returns_422_unprocessable_response_when_payment_processor_invalid_operation_exception(): void
    {
        /** @var MockObject|PaymentProcessor $mockProcessor */
        $mockProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $mockProcessor->method('authorize')->willThrowException(new InvalidOperationException(message: 'Test'));
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $this->paymentMethod->id,
        ]);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);
        $this->assertErrorResponseStructure(response: $response, hasErrors: false);
        $this->assertSame(
            expected: 'Test',
            actual: $response->json(key: 'result.message')
        );
    }

    #[Test]
    public function it_returns_422_unprocessable_entity_response_for_invalid_payment_method(): void
    {
        $this->paymentMethod->payment_type_id = PaymentTypeEnum::CC->value;
        $this->paymentMethod->cc_token = '';
        $this->paymentMethod->cc_expiration_month = null;
        $this->paymentMethod->save();

        $response = $this->makeRequest(inputData: [
            'amount' => 1234,
            'account_id' => $this->account->id,
            'method_id' => $this->paymentMethod->id,
        ]);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);
        $response->assertValid();
        $response->assertJson(value: [
            '_metadata' => ['success' => false],
            'result' => ['message' => __('messages.payment.process_validation_error', ['message' => 'No payment provided (CC or ACH).'])]
        ]);
    }

    public static function successfulResponseWithAllParameterProvider(): \Iterator
    {
        yield 'worldpay - nonACH' => [
            [
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'payment_type_id' => PaymentTypeEnum::CC->value,
            ]
        ];
        yield 'worldpay - ACH' => [
            [
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'payment_type_id' => PaymentTypeEnum::ACH->value,
            ]
        ];
        yield 'WORLDPAY_TOKENEX_TRANSPARENT - nonACH' => [
            [
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT->value,
                'payment_type_id' => PaymentTypeEnum::CC->value,
            ]
        ];
        yield 'WORLDPAY_TOKENEX_TRANSPARENT - ACH' => [
            [
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT->value,
                'payment_type_id' => PaymentTypeEnum::ACH->value,
            ]
        ];
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
                'method_id' => 'aaaa',
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
