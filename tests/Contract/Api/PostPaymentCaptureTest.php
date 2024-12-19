<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Events\PaymentAttemptedEvent;
use App\Models\Payment;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Component\Http\HttpStatus;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;

class PostPaymentCaptureTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/%s/capture';

    private Payment $payment;

    #[Test]
    #[DataProvider('validProvider')]
    public function it_returns_200_response_with_expected_status(array $input, array $expected): void
    {
        Event::fake();

        $this->initPaymentAndTransaction(paymentAttributes: [
            'payment_status_id' => PaymentStatusEnum::AUTHORIZED,
            'processed_at' => date('Y-m-d H:i:s', strtotime($input['processedPaymentAt'])),
        ], transactionOperation: OperationEnum::AUTHORIZE);

        $mockProcessor = $this->getMockBuilder(className: PaymentProcessor::class)->getMock();
        $mockProcessor->method('capture')->willReturn($input['worldpayResult']);
        if (isset($input['worldpayMessage'])) {
            $mockProcessor->method('getError')->willReturn($input['worldpayMessage']);
        }
        /* @var PaymentProcessor $mockProcessor */
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(paymentId: $this->payment->id);

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
                'links' => [
                    'self',
                ]
            ],
            'result' => [
                'message',
                'status',
                'payment_id',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('result.status', $expected['status']->name);

        if (isset($expected['message'])) {
            $response->assertJsonPath('result.message', $expected['message']);
        }

        $resultData = $response->json()['result'];
        $this->assertDatabaseHas(table: Payment::class, data: [
            'id' => $resultData['payment_id'],
            'payment_status_id' => $expected['status']->value,
        ]);

        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    #[DataProvider('invalidResponseProvider')]
    public function it_returns_400_invalid_response_for_invalid_payment_status(array $input, array $expected): void
    {
        $this->initPaymentAndTransaction(
            paymentAttributes: $input['paymentAttributes'],
            transactionOperation: OperationEnum::AUTHORIZE
        );

        $mockProcessor = $this->getMockBuilder(className: PaymentProcessor::class)->getMock();
        $mockProcessor->method('capture')->willReturn(true);
        /* @var PaymentProcessor $mockProcessor */
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(paymentId: $this->payment->id);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
            ],
            'result' => [
                'message',
                'errors' => [
                    '*' => [
                        'detail',
                    ]
                ]
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', $expected['message']);

        $json = $response->json();
        $messages = array_column($json['result']['errors'], 'detail');
        $this->assertSame($expected['errors'], $messages);
    }

    #[Test]
    public function it_returns_404_not_found_response_for_not_existing_payment(): void
    {
        $id = Str::uuid()->toString();

        $response = $this->makeRequest(paymentId: $id);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
            ],
            'result' => [
                'message',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.payment.not_found', ['id' => $id]));
    }

    #[Test]
    public function it_returns_422_unprocessable_error_response_for_not_found_account(): void
    {
        $this->initPaymentAndTransaction(paymentAttributes: ['payment_status_id' => PaymentStatusEnum::AUTHORIZED], transactionOperation: OperationEnum::AUTHORIZE);
        $this->payment->paymentMethod->account->delete();

        $mockProcessor = $this->getMockBuilder(className: PaymentProcessor::class)->getMock();
        $mockProcessor->method('capture')->willReturn(true);
        /* @var PaymentProcessor $mockProcessor */
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(paymentId: $this->payment->id);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);

        $this->assertSame(
            __('messages.account.not_found'),
            $response->json(key: 'result.message')
        );
    }

    #[Test]
    #[DataProvider('expiredPaymentProvider')]
    public function it_returns_422_response_for_expired_payment(array $input, array $expected): void
    {
        $this->initPaymentAndTransaction(
            paymentAttributes: $input['paymentAttributes'],
            transactionOperation: OperationEnum::AUTHORIZE
        );

        $mockProcessor = $this->getMockBuilder(className: PaymentProcessor::class)->getMock();
        $mockProcessor->method('cancel')->willReturn($input['worldpayResult']);
        /* @var PaymentProcessor $mockProcessor */
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(paymentId: $this->payment->id);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
            ],
            'result' => [
                'message',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.operation.capture.payment_expired'));

        $this->assertDatabaseHas(table: $this->payment->getTable(), data: [
            'id' => $this->payment->id,
            'payment_status_id' => $expected['status']->value,
        ]);
    }

    #[Test]
    public function it_returns_422_response_when_capture_operation_throws_exception(): void
    {
        Event::fake();

        $this->initPaymentAndTransaction(paymentAttributes: [
            'payment_status_id' => PaymentStatusEnum::AUTHORIZED,
            'processed_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ], transactionOperation: OperationEnum::AUTHORIZE);

        $mockProcessor = $this->getMockBuilder(className: PaymentProcessor::class)->getMock();
        $mockProcessor->method('capture')->willReturn(false);
        $mockProcessor->method('getException')->willReturn(
            new OperationValidationException(errors: ['Some message here'])
        );

        /* @var PaymentProcessor $mockProcessor */
        $this->app->instance(abstract: PaymentProcessor::class, instance: $mockProcessor);

        $response = $this->makeRequest(paymentId: $this->payment->id);

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
            ],
            'result' => [
                'message',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath(
            'result.message',
            __('messages.operation.payment_cannot_processed_through_gateway', ['message' => ''])
        );

        $this->assertDatabaseHas(table: Payment::class, data: [
            'id' => $this->payment->id,
            'payment_status_id' => PaymentStatusEnum::DECLINED->value,
        ]);
    }

    #[Test]
    #[DataProvider('paymentHasNoTransactionProvider')]
    public function it_returns_500_error_response_for_capturing_a_payment_has_no_transaction(array $input): void
    {
        $this->initPaymentAndTransaction(
            paymentAttributes: $input['paymentAttributes'],
            transactionOperation: OperationEnum::CAPTURE
        );

        $response = $this->makeRequest(paymentId: $this->payment->id);

        $response->assertStatus(status: HttpStatus::INTERNAL_SERVER_ERROR);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
            ],
            'result' => [
                'message',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.operation.capture.missing_authorize_transaction'));
    }

    public static function paymentHasNoTransactionProvider(): \Iterator
    {
        yield 'expired payment' => [
            'input' => [
                'paymentAttributes' => [
                    'payment_status_id' => PaymentStatusEnum::AUTHORIZED,
                    'processed_at' => date('Y-m-d H:i:s', strtotime('-8 days')),
                ],
            ],
        ];
        yield 'non-expired payment' => [
            'input' => [
                'paymentAttributes' => [
                    'payment_status_id' => PaymentStatusEnum::AUTHORIZED,
                    'processed_at' => date('Y-m-d H:i:s', strtotime('-6 days')),
                ],
            ],
        ];
    }

    public static function invalidResponseProvider(): \Iterator
    {
        yield 'invalid status' => [
            'input' => [
                'paymentAttributes' => [
                    'payment_status_id' => PaymentStatusEnum::DECLINED,
                ],
            ],
            'expected' => [
                'message' => 'Invalid input',
                'errors' => ['Payment status is invalid.'],
            ],
        ];
    }

    public static function expiredPaymentProvider(): \Iterator
    {
        yield 'invalid status' => [
            'input' => [
                'paymentAttributes' => [
                    'payment_status_id' => PaymentStatusEnum::AUTHORIZED,
                    'processed_at' => date('Y-m-d H:i:s', strtotime('-8 days')),
                ],
                'worldpayResult' => true,
            ],
            'expected' => [
                'status' => PaymentStatusEnum::CANCELLED,
            ],
        ];

        yield 'expired payment' => [
            'input' => [
                'paymentAttributes' => [
                    'payment_status_id' => PaymentStatusEnum::AUTHORIZED,
                    'processed_at' => date('Y-m-d H:i:s', strtotime('-8 days')),
                ],
                'worldpayResult' => false,
            ],
            'expected' => [
                'status' => PaymentStatusEnum::DECLINED,
            ],
        ];
    }

    public static function validProvider(): \Iterator
    {
        yield 'worldpay returns successful response' => [
            'input' => [
                'processedPaymentAt' => '-2 days',
                'worldpayResult' => true,
            ],
            'expected' => [
                'status' => PaymentStatusEnum::CAPTURED,
            ],
        ];
    }

    private function initPaymentAndTransaction(array $paymentAttributes, OperationEnum $transactionOperation): void
    {
        $this->payment = Payment::factory()->create(attributes: $paymentAttributes);

        Transaction::factory()->create(attributes: [
            'payment_id' => $this->payment->id,
            'transaction_type_id' => $transactionOperation->value
        ]);
    }

    private function mockDynamoDbForGettingWorldPayCredentials(): void
    {
        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn(WorldpayCredentialsStub::make());
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);
    }

    private function makeRequest(string $paymentId): TestResponse
    {
        $this->mockDynamoDbForGettingWorldPayCredentials();

        return $this->post(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentId)),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
                'Origin' => 'some_service_name',
            ]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->payment);
    }
}
