<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment\Refund;

use App\Api\Exceptions\PaymentRefundFailedException;
use App\Api\Exceptions\PaymentTransactionNotFoundException;
use App\Api\Repositories\Interface\FailedRefundPaymentRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\RefundPaymentFailedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;
use App\Services\Payment\Refund\RefundElectronicPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class RefundElectronicPaymentServiceTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    /** @var PaymentRepository&MockObject $paymentRepository */
    private PaymentRepository $paymentRepository;
    /** @var PaymentProcessor&MockObject $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    /** @var FailedRefundPaymentRepository&MockObject $failedRefundPaymentRepository */
    private FailedRefundPaymentRepository $failedRefundPaymentRepository;
    private RefundElectronicPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->paymentProcessor = $this->createMock(PaymentProcessor::class);
        $this->failedRefundPaymentRepository = $this->createMock(FailedRefundPaymentRepository::class);
        $this->service = new RefundElectronicPaymentService(
            paymentRepository: $this->paymentRepository,
            paymentProcessor: $this->paymentProcessor,
            failedRefundPaymentRepository: $this->failedRefundPaymentRepository
        );

        Event::fake();
    }

    #[Test]
    public function refund_method_throws_exception_if_the_payment_method_of_the_original_payment_is_not_found(): void
    {
        $payment = Payment::factory()->cc()->makeWithRelationships(relationships: ['paymentMethod' => null]);

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.original_payment_method_not_found'));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_payment_is_not_of_valid_type(): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::CREDIT->value]
        );
        $payment = Payment::factory([
            'payment_type_id' => PaymentTypeEnum::CHECK->value
        ])->makeWithRelationships(relationships: ['paymentMethod' => $paymentMethod]);
        DB::shouldReceive('transaction');

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.automatic_refund_cannot_be_processed_for_gateway'));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_original_payment_is_not_in_valid_status_to_be_refunded(): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: ['payment_status_id' => PaymentStatusEnum::DECLINED->value],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.payment_invalid_status'));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_original_payment_was_not_processed_in_gateway_yet(): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->tomorrow()->setTime(hour: 10, minute: 0),
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.not_fully_processed_in_gateway_yet'));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_original_payment_was_previously_refunded(): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->yesterday(),
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $refundPayment = Payment::factory()->withoutRelationships()->make(attributes: [
            'external_ref_id' => 1111,
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'processed_at' => Carbon::yesterday(),
            'amount' => 1000,
            'currency_code' => 'USD',
        ]);
        $this->paymentRepository->method('getCreditedChildPayments')->willReturn(collect([$refundPayment]));

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.payment_already_refunded', ['id' => $refundPayment->id]));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_refund_is_not_allowed_for_overdue_payment(): void
    {
        $paymentAllowedRefundDays = 30;
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->subDays($paymentAllowedRefundDays + 1),
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.automatic_refund_cannot_be_processed', ['days' => $paymentAllowedRefundDays]));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(
                originalPayment: $payment,
                refundAmount: $payment->amount,
                daysRefundAllowed: $paymentAllowedRefundDays
            )
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_refund_amount_exceeds_original_payment_amount(): void
    {
        $paymentAmount = 10499;
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'amount' => $paymentAmount,
                'processed_at' => Carbon::create(tz: 'MST')->yesterday(),
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.exceeds_the_original_payment_amount', ['amount' => $paymentAmount]));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(
                originalPayment: $payment,
                refundAmount: $paymentAmount + 1
            )
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_transaction_for_the_original_payment_was_not_found(): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->yesterday(),
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );
        DB::shouldReceive('transaction')->andReturnUsing(static fn ($callback) => $callback());

        $this->paymentRepository->method('transactionForOperation')->willReturn(null);

        $this->expectException(PaymentTransactionNotFoundException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.missing_capture_transaction'));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_returns_dto_with_success_if_payment_processor_returns_true(): void
    {
        $this->mockWorldPayCredentialsRepository();

        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value],
            relationships: ['account' => $account]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->yesterday(),
            ],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value
                ]),
            ]
        );

        $transaction = Transaction::factory()->withoutRelationships()->make(attributes: [
            'payment_id' => $payment->id,
            'transaction_type_id' => TransactionTypeEnum::CAPTURE->value
        ]);
        $this->paymentRepository->method('transactionForOperation')->willReturn($transaction);
        $this->paymentRepository->method('cloneAndCreateFromExistingPayment')->willReturn($payment);

        $this->paymentProcessor->method('credit')->willReturn(true);

        DB::shouldReceive('transaction')->andReturnUsing(static fn ($callback) => $callback());

        $result = $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
        $this->assertTrue($result->isSuccess);
    }

    #[Test]
    public function refund_method_returns_dto_with_success_if_payment_processor_returns_true_with_existing_refund(): void
    {
        $this->mockWorldPayCredentialsRepository();

        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value],
            relationships: ['account' => $account]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->yesterday(),
            ],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value
                ]),
            ]
        );
        $existingRefundPayment = Payment::factory()->makeWithRelationships(attributes: [
            'external_ref_id' => 12345,
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'processed_at' => Carbon::now(),
            'amount' => 1000,
            'currency_code' => 'USD',
        ], relationships: [
            'paymentMethod' => $paymentMethod,
            'account' => $account,
            'originalPayment' => $payment,
            'type' => PaymentType::factory()->make([
                'id' => PaymentTypeEnum::ACH->value
            ]),
        ]);

        $transaction = Transaction::factory()->withoutRelationships()->make(attributes: [
            'payment_id' => $payment->id,
            'transaction_type_id' => TransactionTypeEnum::CAPTURE->value
        ]);
        $this->paymentRepository->method('transactionForOperation')->willReturn($transaction);
        $this->paymentRepository->method('cloneAndCreateFromExistingPayment')->willReturn($payment);

        $this->paymentProcessor->method('credit')->willReturn(true);

        DB::shouldReceive('transaction')->andReturnUsing(static fn ($callback) => $callback());

        $result = $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(
                originalPayment: $payment,
                refundAmount: $payment->amount,
                existingRefundPayment: $existingRefundPayment
            )
        );
        $this->assertTrue($result->isSuccess);
    }

    #[Test]
    public function refund_method_returns_dto_with_error_if_payment_processor_returns_false_and_creates_refund_record_and_dispatches_event(): void
    {
        $this->mockWorldPayCredentialsRepository();

        DB::shouldReceive('transaction')->andReturnUsing(static fn ($callback) => $callback());

        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value],
            relationships: ['account' => $account]
        );
        $payment = Payment::factory()->cc()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->yesterday(),
            ],
            relationships: [
                'paymentMethod' => $paymentMethod,
                'account' => $account,
                'type' => PaymentType::factory()->make([
                    'id' => PaymentTypeEnum::ACH->value
                ])
            ]
        );

        $transaction = Transaction::factory()->withoutRelationships()->make(attributes: [
            'payment_id' => $payment->id,
            'transaction_type_id' => TransactionTypeEnum::CAPTURE->value
        ]);
        $this->paymentRepository->method('transactionForOperation')->willReturn($transaction);
        $this->paymentRepository->method('cloneAndCreateFromExistingPayment')->willReturn($payment);

        $this->paymentProcessor->method('credit')->willReturn(false);
        $this->paymentProcessor->method('getError')->willReturn('Cannot refund the payment');

        $this->failedRefundPaymentRepository->expects($this->once())->method('create');

        $result = $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(
                originalPayment: $payment,
                refundAmount: $payment->amount,
                externalRefId: 12345,
            )
        );

        $this->assertFalse($result->isSuccess);
        $this->assertSame('Cannot refund the payment', $result->errorMessage);

        Event::assertDispatched(event: RefundPaymentFailedEvent::class);
    }

    protected function tearDown(): void
    {
        unset($this->paymentRepository, $this->paymentProcessor, $this->failedRefundPaymentRepository, $this->service);

        parent::tearDown();
    }
}
