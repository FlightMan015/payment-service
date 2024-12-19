<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Repositories;

use App\Api\Exceptions\PaymentTransactionNotFoundException;
use App\Api\Repositories\GatewayPaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Events\PaymentReturnedEvent;
use App\Events\PaymentSettledEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\Gateways\Worldpay;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use Event;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Queue;
use Tests\Helpers\Traits\RepositoryMockingTrait;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Unit\UnitTestCase;

class GatewayPaymentProcessorRepositoryTest extends UnitTestCase
{
    use RepositoryMockingTrait;

    private GatewayPaymentProcessorRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: GatewayPaymentProcessorRepository::class);

        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn(WorldpayCredentialsStub::make());
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);

        Event::fake();
        Queue::fake();
    }

    #[Test]
    #[DataProvider('validInputAuthorizeWithoutException')]
    public function authorize_returns_result_and_transaction_id(array $input, array $expected): void
    {
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mockProcessor->method('authorize')->willReturn(true);

        if (!empty($input['transaction_id'])) {
            $transaction = Transaction::factory()->withoutRelationships()->make([
                'id' => $input['transaction_id'],
                'payment_id' => Str::uuid()->toString(),
            ]);
            $mockProcessor->method('getTransactionLog')->willReturn($transaction);
        }
        /** @var PaymentProcessor $mockProcessor */
        $mockedInterface = $this->getMockBuilder(GatewayInterface::class)->disableOriginalConstructor()->getMock();
        /** @var GatewayInterface $mockedInterface */
        $paymentTypes = PaymentTypeEnum::cases();
        $method = [
            'id' => 123456,
            'payment_type_id' => $paymentTypes[array_rand($paymentTypes)]->value,
        ];
        if (!empty($input['payment_method_attributes'])) {
            $method = array_merge($method, $input['payment_method_attributes']);
        }

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: $method,
            relationships: [
                'type' => PaymentType::factory()->make([
                    'id' => $method['payment_type_id'],
                ])
            ]
        );
        $payment = Payment::factory()->makeWithRelationships(
            attributes: ['id' => 123, 'payment_status_id' => PaymentStatusEnum::AUTH_CAPTURING->value],
            relationships: ['paymentMethod' => $paymentMethod]
        );
        $actualResult = $this->repository->authorize(
            paymentProcessor: $mockProcessor,
            payment: $payment,
            gateway: $mockedInterface
        );

        $this->assertSame($expected['result'], $actualResult->isSuccess);
        $this->assertSame($expected['transaction_id'], $actualResult->transactionId);
        $this->assertNull($actualResult->message);
    }

    public static function validInputAuthorizeWithoutException(): \Iterator
    {
        $uuid = Str::uuid()->toString();

        yield 'Authorize successfully' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize unsuccessfully' => [
            'input' => [
                'authorize_result' => false,
                'transaction_id' => $uuid,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize successfully not credit card' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
                'payment_method_attributes' => [
                    'payment_type_id' => PaymentTypeEnum::ACH->value,
                    'cc_token' => 'test-token',
                    'cc_expiration_year' => '2022',
                    'cc_expiration_month' => '12',
                ],
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize successfully credit card' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
                'payment_method_attributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                    'cc_token' => null,
                    'cc_expiration_year' => null,
                    'cc_expiration_month' => '12',
                ],
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize unsuccessfully without transaction' => [
            'input' => [
                'authorize_result' => false,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => null,
            ],
        ];
    }

    #[Test]
    public function authorize_throws_exception_in_case_authorize_throw_exception(): void
    {
        /** @var PaymentProcessor|MockObject $mockProcessor */
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mockProcessor->method('authorize')->willThrowException(new OperationValidationException(errors: [
            'error message'
        ]));

        $this->expectException(OperationValidationException::class);
        $this->expectExceptionMessage('error message');

        $payment = Payment::factory()->makeWithRelationships(
            attributes: ['id' => 11111],
            relationships: [
                'paymentMethod' => PaymentMethod::factory()->makeWithRelationships(
                    attributes: ['id' => 123],
                    relationships: ['type' => PaymentType::factory()->make()]
                )
            ]
        );

        $this->repository->authorize(
            paymentProcessor: $mockProcessor,
            payment: $payment,
            gateway: Worldpay::make(credentials: app()->make(CredentialsRepository::class)->get(1))
        );
    }

    #[Test]
    public function capture_throws_exception_in_case_transaction_does_not_exist(): void
    {
        $this->expectException(PaymentTransactionNotFoundException::class);
        $this->expectExceptionMessage('Transaction does not exist');

        $payment = Payment::factory()->withoutRelationships()->make();
        /** @var Payment|MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')->with(OperationEnum::AUTHORIZE)->andReturnNull();

        /** @var PaymentProcessor $mockProcessor */
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $this->repository->capture(
            paymentProcessor: $mockProcessor,
            payment: $payment,
            gateway: Worldpay::make(credentials: app()->make(CredentialsRepository::class)->get(1))
        );
    }

    #[Test]
    #[DataProvider('validInputCaptureWithoutException')]
    public function capture_returns_result_and_transaction_id(array $input, array $expected): void
    {
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mockProcessor->method('capture')->willReturn(true);

        if (!empty($input['transaction_id'])) {
            $transaction = Transaction::factory()->withoutRelationships()->make([
                'id' => $input['transaction_id'],
                'payment_id' => Str::uuid()->toString(),
            ]);
            $mockProcessor->method('getTransactionLog')->willReturn($transaction);
        }
        /** @var PaymentProcessor $mockProcessor */
        $mockWorldpay = $this->getMockBuilder(Worldpay::class)->disableOriginalConstructor()->getMock();
        /** @var Worldpay $mockWorldpay */
        $paymentTypes = PaymentTypeEnum::cases();
        $method = [
            'payment_type_id' => $paymentTypes[array_rand($paymentTypes)]->value,
        ];
        if (!empty($input['payment_method_attributes'])) {
            $method = array_merge($method, $input['payment_method_attributes']);
        }
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: $method,
            relationships: [
                'type' => PaymentType::factory()->make([
                    'id' => $method['payment_type_id'],
                ])
            ]
        );

        $payment = $this->createPaymentWithTransactionStatus(
            attributes: ['payment_method_id' => $paymentMethod->id],
            transactionOperation: OperationEnum::AUTHORIZE
        );
        $payment->paymentMethod()->associate($paymentMethod);

        $actualResult = $this->repository->capture(
            paymentProcessor: $mockProcessor,
            payment: $payment,
            gateway: $mockWorldpay
        );

        $this->assertSame($expected['result'], $actualResult->isSuccess);
        $this->assertSame($expected['transaction_id'], $actualResult->transactionId);
        $this->assertNull($actualResult->message);
    }

    private function createPaymentWithTransactionStatus(array $attributes, OperationEnum $transactionOperation): MockInterface|Payment
    {
        $account = Account::factory()->makeWithRelationships(relationships: [
            'area' => Area::factory()->make(),
        ]);
        $payment = Payment::factory()->makeWithRelationships(
            attributes: $attributes,
            relationships: [
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value,
                ]),
            ]
        );

        $transaction = Transaction::factory()->makeWithRelationships(
            attributes: ['type_id' => $transactionOperation->value],
            relationships: ['payment' => $payment]
        );

        /** @var MockInterface&Payment $payment */
        $payment = Mockery::mock($payment);
        $payment->allows('transactionForOperation')->andReturns($transaction);

        return $payment;
    }

    public static function validInputCaptureWithoutException(): \Iterator
    {
        $uuid = Str::uuid()->toString();

        yield 'Authorize successfully' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize unsuccessfully' => [
            'input' => [
                'authorize_result' => false,
                'transaction_id' => $uuid,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize successfully not credit card' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
                'payment_method_attributes' => [
                    'payment_type_id' => PaymentTypeEnum::ACH->value,
                    'cc_token' => 'test-token',
                    'cc_expiration_year' => '2022',
                    'cc_expiration_month' => '12',
                ],
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize successfully credit card' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
                'payment_method_attributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                    'cc_token' => null,
                    'cc_expiration_year' => null,
                    'cc_expiration_month' => '12',
                ],
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize unsuccessfully without transaction' => [
            'input' => [
                'authorize_result' => false,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => null,
            ],
        ];
    }

    #[Test]
    public function cancel_throws_exception_in_case_transaction_does_not_exist(): void
    {
        $this->expectException(PaymentTransactionNotFoundException::class);
        $this->expectExceptionMessage('Transaction does not exist');

        $payment = Payment::factory()->withoutRelationships()->make();
        /** @var Payment|MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')->with(OperationEnum::AUTHORIZE)->andReturnNull();

        /** @var PaymentProcessor $mockProcessor */
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $this->repository->cancel(
            paymentProcessor: $mockProcessor,
            payment: $payment,
            gateway: Worldpay::make(credentials: app()->make(CredentialsRepository::class)->get(1))
        );
    }

    #[Test]
    #[DataProvider('validInputCancelWithoutException')]
    public function cancel_returns_result_and_transaction_id(array $input, array $expected): void
    {
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mockProcessor->method('cancel')->willReturn(true);

        if (!empty($input['transaction_id'])) {
            $transaction = new Transaction([]);
            $transaction->id = $input['transaction_id'];
            $mockProcessor->method('getTransactionLog')->willReturn($transaction);
        }
        /** @var PaymentProcessor $mockProcessor */
        $mockWorldpay = $this->getMockBuilder(Worldpay::class)->disableOriginalConstructor()->getMock();
        /** @var Worldpay $mockWorldpay */
        $paymentTypes = PaymentTypeEnum::cases();
        $method = [
            'payment_type_id' => $paymentTypes[array_rand($paymentTypes)]->value,
        ];
        if (!empty($input['payment_method_attributes'])) {
            $method = array_merge($method, $input['payment_method_attributes']);
        }
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: $method,
            relationships: [
                'type' => PaymentType::factory()->make([
                    'id' => $method['payment_type_id'],
                ])
            ]
        );

        $payment = $this->createPaymentWithTransactionStatus(
            attributes: ['payment_method_id' => $paymentMethod->id],
            transactionOperation: OperationEnum::AUTHORIZE
        );
        $payment->paymentMethod()->associate($paymentMethod);

        $actualResult = $this->repository->cancel(
            paymentProcessor: $mockProcessor,
            payment: $payment,
            gateway: $mockWorldpay
        );

        $this->assertSame($expected['result'], $actualResult->isSuccess);
        $this->assertSame($expected['transaction_id'], $actualResult->transactionId);
        $this->assertNull($actualResult->message);
    }

    public static function validInputCancelWithoutException(): \Iterator
    {
        $uuid = Str::uuid()->toString();

        yield 'Authorize successfully' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize unsuccessfully' => [
            'input' => [
                'authorize_result' => false,
                'transaction_id' => $uuid,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize successfully not credit card' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
                'payment_method_attributes' => [
                    'payment_type_id' => PaymentTypeEnum::ACH->value,
                    'cc_token' => 'test-token',
                    'cc_expiration_year' => '2022',
                    'cc_expiration_month' => '12',
                ],
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize successfully credit card' => [
            'input' => [
                'authorize_result' => true,
                'transaction_id' => $uuid,
                'payment_method_attributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                    'cc_token' => null,
                    'cc_expiration_year' => null,
                    'cc_expiration_month' => '12',
                ],
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => $uuid,
            ],
        ];
        yield 'Authorize unsuccessfully without transaction' => [
            'input' => [
                'authorize_result' => false,
            ],
            'expected' => [
                'result' => true,
                'transaction_id' => null,
            ],
        ];
    }

    #[Test]
    public function status_throws_exception_in_case_transaction_does_not_exist(): void
    {
        $this->expectException(PaymentTransactionNotFoundException::class);
        $this->expectExceptionMessage('Transaction does not exist');

        $payment = Payment::factory()->withoutRelationships()->make([
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
        ]);

        /** @var Payment|MockInterface $payment */
        $payment = Mockery::mock($payment)->makePartial();
        $payment->shouldReceive('transactionForOperation')->with(
            OperationEnum::forPaymentStatus(
                PaymentStatusEnum::from($payment->payment_status_id)
            )
        )->andReturnNull();

        /** @var PaymentProcessor $mockProcessor */
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $this->repository->status(
            paymentProcessor: $mockProcessor,
            payment: $payment,
        );
    }

    #[Test]
    public function status_returns_result_and_create_returned_payment(): void
    {
        // Arrange
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mockProcessor->method('status')->willReturn(true);

        $transaction = Transaction::factory()->withoutRelationships()->make([
            'id' => Str::uuid()->toString(),
            'payment_id' => Str::uuid()->toString(),
        ]);

        /* @var PaymentProcessor $mockProcessor */
        $mockProcessor->method('getTransactionLog')->willReturn($transaction);
        $mockProcessor->method('getGatewayPaymentStatus')->willReturn(PaymentStatusEnum::RETURNED);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            relationships: [
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value,
                ]),
                'account' => Account::factory()->makeWithRelationships(relationships: [
                    'area' => Area::factory()->make(),
                ]),
            ]
        );

        $payment = $this->createPaymentWithTransactionStatus(
            attributes: [
                'payment_method_id' => $paymentMethod->id,
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            ],
            transactionOperation: OperationEnum::AUTHORIZE
        );
        $payment->paymentMethod()->associate($paymentMethod);

        // Assert
        $this->repositoryWillReturn(
            repositoryClass: PaymentRepository::class,
            method: 'cloneAndCreateFromExistingPayment',
            value: $payment,
        );
        $this->repositoryWillReturn(
            repositoryClass: PaymentTransactionRepository::class,
            method: 'update',
            value: $transaction,
        );

        // Act
        $this->repository = $this->app->make(abstract: GatewayPaymentProcessorRepository::class);
        $actualResult = $this->repository->status(
            paymentProcessor: $mockProcessor,
            payment: $payment,
        );

        // Assert
        Event::assertDispatched(PaymentReturnedEvent::class);
        $this->assertTrue($actualResult->isSuccess);
        $this->assertSame($transaction->id, $actualResult->transactionId);
        $this->assertNull($actualResult->message);
    }

    #[Test]
    public function status_returns_result_and_update_payment_as_settled(): void
    {
        // Arrange
        $mockProcessor = $this->getMockBuilder(PaymentProcessor::class)->getMock();
        $mockProcessor->method('status')->willReturn(true);

        $transaction = Transaction::factory()->withoutRelationships()->make([
            'id' => Str::uuid()->toString(),
            'payment_id' => Str::uuid()->toString(),
        ]);

        /* @var PaymentProcessor $mockProcessor */
        $mockProcessor->method('getTransactionLog')->willReturn($transaction);
        $mockProcessor->method('getGatewayPaymentStatus')->willReturn(PaymentStatusEnum::SETTLED);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            relationships: [
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value,
                ]),
                'account' => Account::factory()->makeWithRelationships(relationships: [
                    'area' => Area::factory()->make(),
                ]),
            ]
        );

        $payment = $this->createPaymentWithTransactionStatus(
            attributes: [
                'payment_method_id' => $paymentMethod->id,
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            ],
            transactionOperation: OperationEnum::AUTHORIZE
        );
        $payment->paymentMethod()->associate($paymentMethod);

        // Assert
        $this->repositoryWillReturn(
            repositoryClass: PaymentRepository::class,
            method: 'updateStatus',
            value: $payment,
        );

        // Act
        $this->repository = $this->app->make(abstract: GatewayPaymentProcessorRepository::class);
        $actualResult = $this->repository->status(
            paymentProcessor: $mockProcessor,
            payment: $payment,
        );

        // Assert
        Event::assertDispatched(PaymentSettledEvent::class);
        $this->assertTrue($actualResult->isSuccess);
        $this->assertSame($transaction->id, $actualResult->transactionId);
        $this->assertNull($actualResult->message);
    }

    protected function tearDown(): void
    {
        unset($this->repository);

        parent::tearDown();
    }
}
