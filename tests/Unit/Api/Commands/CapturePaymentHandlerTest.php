<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CapturePaymentHandler;
use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Exceptions\InconsistentDataException;
use App\Api\Exceptions\InvalidPaymentStateException;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentTransactionNotFoundException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException as PaymentNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentAttemptedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\FakeEventDispatcherTrait;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class CapturePaymentHandlerTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;
    use FakeEventDispatcherTrait;

    private CapturePaymentHandler|null $handler;

    private MockInterface|MockObject|PaymentRepository $repository;
    private MockInterface|MockObject|PaymentProcessorRepository $paymentProcessorRepository;
    private MockInterface|MockObject|PaymentProcessor $paymentProcessor;
    /** @var MockObject&AccountRepository $accountRepository */
    private AccountRepository $accountRepository;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initPayment();

        $this->paymentProcessorRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);

        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $this->accountRepository = $this->createMock(originalClassName: AccountRepository::class);

        $this->mockWorldPayCredentialsRepository();

        $this->fakeEvents();
    }

    #[Test]
    public function handler_throws_not_found_exception_for_non_existing_payment_id(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        $paymentId = Str::uuid()->toString();

        $this->repository = $this->createMock(originalClassName: PaymentRepository::class);
        $this->repository->method('find')->willThrowException(new PaymentNotFoundException());

        $this->createHandler();
        $this->handler->handle(paymentId: $paymentId);
    }

    #[Test]
    public function handler_throws_unprocessable_error_for_non_existing_account(): void
    {
        $this->expectException(exception: UnprocessableContentException::class);
        $this->expectExceptionMessage(message: __('messages.account.not_found'));

        $this->accountRepository->method('exists')->willReturn(value: false);

        $this->createHandler();
        $this->handler->handle(paymentId: $this->payment->id);
    }

    #[Test]
    public function handler_throws_invalid_exception_for_incorrect_status(): void
    {
        $this->expectException(exception: PaymentValidationException::class);
        $this->expectExceptionMessage(message: __('messages.invalid_input'));

        $this->accountRepository->method('exists')->willReturn(value: true);
        $this->initPayment(['payment_status_id' => PaymentStatusEnum::CANCELLED->value]);

        $this->createHandler();
        $this->handler->handle(paymentId: $this->payment->id);
    }

    #[Test]
    public function handle_method_throws_exception_if_the_payment_method_of_authorized_payment_was_not_found(): void
    {
        $this->expectException(exception: InvalidPaymentStateException::class);
        $this->expectExceptionMessage(message: __('messages.operation.capture.original_payment_method_not_found'));

        $this->accountRepository->method('exists')->willReturn(value: true);
        $this->initPayment(paymentMethodExists: false);

        $this->createHandler();
        $this->handler->handle(paymentId: $this->payment->id);
    }

    #[Test]
    #[DataProvider('cancelExpiredPaymentProvider')]
    public function handler_cancels_from_worldpay_then_throws_invalid_exception_for_expired_payment(array $input, array $expected): void
    {
        $this->expectException(exception: UnprocessableContentException::class);
        $this->expectExceptionMessage(message: __('messages.operation.capture.payment_expired'));

        $this->accountRepository->method('exists')->willReturn(value: true);
        $this->initPayment([
            'processed_at' => date(format: 'Y-m-d 00:00:00', timestamp: strtotime('-8 days')),
        ]);

        $mockWorldpayRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $mockWorldpayRepository->method('cancel')->willReturn(new PaymentProcessorResultDto(
            isSuccess: $input['worldpaySuccess'],
            transactionId: Str::uuid()->toString(),
            message: null
        ));
        $this->paymentProcessorRepository = $mockWorldpayRepository;

        $this->createHandler();
        $this->handler->handle(paymentId: $this->payment->id);

        $this->assertDatabaseHas(table: Payment::class, data: [
            'id' => $this->payment->id,
            'payment_status_id' => $expected['paymentStatus']->value,
        ]);
    }

    public static function cancelExpiredPaymentProvider(): \Iterator
    {
        yield 'Cancelling Worldpay Successfully' => [
            'input' => [
                'worldpaySuccess' => true,
            ],
            'expected' => [
                'paymentStatus' => PaymentStatusEnum::CANCELLED,
            ],
        ];
        yield 'Cancelling Worldpay Unsuccessfully' => [
            'input' => [
                'worldpaySuccess' => false,
            ],
            'expected' => [
                'paymentStatus' => PaymentStatusEnum::DECLINED,
            ],
        ];
    }

    #[Test]
    public function handler_returns_expected_result_dto_and_dispatch_event(): void
    {
        $this->accountRepository->method('exists')->willReturn(value: true);
        $this->initPayment();

        $mockWorldpayRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $mockWorldpayRepository->method('capture')->willReturn(new PaymentProcessorResultDto(
            isSuccess: true,
            transactionId: Str::uuid()->toString(),
            message: null
        ));
        $this->paymentProcessorRepository = $mockWorldpayRepository;

        $this->createHandler();

        $this->assertTrue($this->handler->handle(paymentId: $this->payment->id)->isSuccess);
        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    #[DataProvider('unprocessableExceptionProvider')]
    public function handler_throws_unprocessable_exception_when_worldpay_not_success(array $input, array $expected): void
    {
        $this->accountRepository->method('exists')->willReturn(value: true);
        $this->initPayment();

        $mockWorldpayRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        if ($input['worldpayResult'] instanceof \Exception) {
            $mockWorldpayRepository->method('capture')->willThrowException($input['worldpayResult']);
        } else {
            $mockWorldpayRepository->method('capture')->willReturn($input['worldpayResult']);
        }
        $this->paymentProcessorRepository = $mockWorldpayRepository;

        $this->expectException($expected['exceptionClass']);
        $this->expectExceptionMessage($expected['message']);

        $this->createHandler();
        $this->handler->handle(paymentId: $this->payment->id);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_throws_exception_when_operation_validation_fails(): void
    {
        $this->accountRepository->method('exists')->willReturn(value: true);
        $this->initPayment();

        $mockWorldpayRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $mockWorldpayRepository->method('capture')
            ->willThrowException(new OperationValidationException(errors: ['Wrong name on account']));

        $this->paymentProcessorRepository = $mockWorldpayRepository;

        $this->expectException(PaymentProcessingValidationException::class);
        $this->expectExceptionMessage(__('messages.payment.process_validation_error', ['message' => 'Wrong name on account']));

        $this->createHandler();
        $this->handler->handle(paymentId: $this->payment->id);
    }

    #[Test]
    public function it_throws_unprocessable_content_exception_when_credit_card_validation_fails(): void
    {
        $this->accountRepository->method('exists')->willReturn(value: true);
        $this->initPayment();

        $mockWorldpayRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $mockWorldpayRepository->method('capture')
            ->willThrowException(new CreditCardValidationException(message: __('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required')));

        $this->paymentProcessorRepository = $mockWorldpayRepository;

        $this->expectException(UnprocessableContentException::class);
        $this->expectExceptionMessage(__('messages.worldpay_tokenex_transparent.validation.credit_card_expiration_data_required'));

        $this->createHandler();
        $this->handler->handle(paymentId: $this->payment->id);
    }

    public static function unprocessableExceptionProvider(): \Iterator
    {
        yield 'worldpay returns false result' => [
            'input' => [
                'worldpayResult' => new PaymentProcessorResultDto(
                    isSuccess: false,
                    transactionId: null,
                    message: null
                ),
            ],
            'expected' => [
                'message' => static fn () => __('messages.operation.payment_cannot_processed_through_gateway', ['message' => '']),
                'exceptionClass' => UnprocessableContentException::class,
            ],
        ];
        yield 'worldpay throws exception' => [
            'input' => [
                'worldpayResult' => new InvalidOperationException(message: 'test message'),
            ],
            'expected' => [
                'message' => 'test message',
                'exceptionClass' => UnprocessableContentException::class,
            ],
        ];
        yield 'worldpay throws exception for not existing transaction' => [
            'input' => [
                'worldpayResult' => new PaymentTransactionNotFoundException(message: 'test message'),
            ],
            'expected' => [
                'message' => static fn () => __('messages.operation.capture.missing_authorize_transaction'),
                'exceptionClass' => InconsistentDataException::class,
            ],
        ];
    }

    private function initPayment(
        array $paymentAttributes = [],
        bool $mockRepository = true,
        bool $paymentMethodExists = true
    ): void {
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => Area::factory()->make()]);

        $paymentMethod = $paymentMethodExists ? PaymentMethod::factory()->makeWithRelationships(
            attributes: ['id' => 123],
            relationships: ['account' => $account]
        ) : null;

        $payment = Payment::factory()->makeWithRelationships(
            attributes: array_merge([
                'id' => Str::uuid()->toString(),
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'payment_method_id' => $paymentMethod,
                'payment_status_id' => PaymentStatusEnum::AUTHORIZED->value,
                'processed_at' => date('Y-m-d 00:00:00', strtotime('-6 days')),
            ], $paymentAttributes),
            relationships: [
                'paymentMethod' => $paymentMethod,
            ]
        );
        $this->payment = $payment;

        if ($mockRepository) {
            $this->repository = $this->createMock(originalClassName: PaymentRepository::class);
            $this->repository->method('find')->willReturn($this->payment);
        }
    }

    private function createHandler(MockInterface|null $repository = null): void
    {
        $this->handler = new CapturePaymentHandler(
            repository: $repository ?? $this->repository,
            paymentProcessorRepository: $this->paymentProcessorRepository,
            paymentProcessor: $this->paymentProcessor,
            accountRepository: $this->accountRepository,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset(
            $this->handler,
            $this->payment,
            $this->repository,
            $this->paymentProcessorRepository,
            $this->paymentProcessor,
            $this->accountRepository
        );
    }
}
