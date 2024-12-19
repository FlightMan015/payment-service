<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment\Refund;

use App\Api\Exceptions\PaymentRefundFailedException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;
use App\Services\Payment\Refund\RefundManualPaymentService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class RefundManualPaymentServiceTest extends UnitTestCase
{
    /** @var PaymentRepository&MockObject $paymentRepository */
    private PaymentRepository $paymentRepository;
    private RefundManualPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->service = new RefundManualPaymentService($this->paymentRepository);
    }

    #[Test]
    public function refund_method_throws_exception_if_the_payment_method_of_the_original_payment_is_not_found(): void
    {
        $payment = Payment::factory()->makeWithRelationships(relationships: ['paymentMethod' => null]);

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.original_payment_method_not_found'));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_throws_exception_if_the_payment_method_is_not_int_the_status_available_for_manual_refund(): void
    {
        $paymentMethod = PaymentMethod::factory()->withoutRelationships()->make(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::CREDIT->value]
        );
        $payment = Payment::factory()->makeWithRelationships(
            attributes: ['payment_type_id' => PaymentTypeEnum::COUPON->value],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $this->expectException(PaymentRefundFailedException::class);
        $this->expectExceptionMessage(__('messages.operation.refund.manual_refund_not_allowed'));

        $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(originalPayment: $payment, refundAmount: $payment->amount)
        );
    }

    #[Test]
    public function refund_method_returns_dto_with_success_if_all_rules_passed(): void
    {
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value],
        );
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_type_id' => PaymentTypeEnum::CHECK->value,
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'processed_at' => Carbon::create(tz: 'MST')->yesterday(),
            ],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $transaction = Transaction::factory()->withoutRelationships()->make(attributes: [
            'payment_id' => $payment->id,
            'transaction_type_id' => TransactionTypeEnum::CAPTURE->value
        ]);
        $this->paymentRepository->method('transactionForOperation')->willReturn($transaction);
        $this->paymentRepository->method('cloneAndCreateFromExistingPayment')->willReturn($payment);

        $result = $this->service->refund(
            paymentRefundDto: new MakePaymentRefundDto(
                originalPayment: $payment,
                refundAmount: $payment->amount,
                externalRefId: 123456789
            )
        );
        $this->assertTrue($result->isSuccess);
    }
}
