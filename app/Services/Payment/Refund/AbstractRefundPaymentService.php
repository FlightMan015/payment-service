<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Exceptions\PaymentRefundFailedException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Helpers\PaymentOperationValidationHelper;
use App\Models\Payment;
use App\PaymentProcessor\Enums\OperationEnum;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;

abstract class AbstractRefundPaymentService implements RefundPaymentServiceInterface
{
    protected MakePaymentRefundDto $paymentRefundDto;
    protected Payment $refundPayment;

    /**
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(protected PaymentRepository $paymentRepository)
    {
    }

    /**
     * @inheritDoc
     *
     * @throws \Throwable
     *
     * @return RefundPaymentResultDto
     */
    public function refund(MakePaymentRefundDto $paymentRefundDto): RefundPaymentResultDto
    {
        $this->paymentRefundDto = $paymentRefundDto;
        $this->checkIfPaymentCanBeRefunded();

        return $this->processPaymentRefund();
    }

    abstract protected function checkIfPaymentCanBeRefunded(): void;
    abstract protected function processPaymentRefund(): RefundPaymentResultDto;

    /**
     * @throws PaymentRefundFailedException
     */
    protected function validateOriginalPaymentStatus(): void
    {
        $hasValidStatus = PaymentOperationValidationHelper::isValidPaymentStatusForOperation(
            payment: $this->paymentRefundDto->originalPayment,
            operation: OperationEnum::CREDIT
        );

        if (!$hasValidStatus) {
            throw new PaymentRefundFailedException(message: __('messages.operation.refund.payment_invalid_status'));
        }
    }

    /**
     * @throws PaymentRefundFailedException
     */
    protected function validateOriginalPaymentWasNotPreviouslyRefunded(): void
    {
        if (!is_null($this->paymentRefundDto->existingRefundPayment)) {
            // skip this validation if the refund payment already exists and we just re-process it
            return;
        }

        $refundedPayments = $this->paymentRepository->getCreditedChildPayments(payment: $this->paymentRefundDto->originalPayment);

        if ($refundedPayments->isNotEmpty()) {
            throw new PaymentRefundFailedException(
                message: __('messages.operation.refund.payment_already_refunded', ['id' => $refundedPayments[0]->id])
            );
        }
    }

    /**
     * @throws PaymentRefundFailedException
     */
    protected function validateRefundAmountDoesNotExceedOriginalPaymentAmount(): void
    {
        if ($this->paymentRefundDto->refundAmount > $this->paymentRefundDto->originalPayment->amount) {
            throw new PaymentRefundFailedException(
                message: __(
                    'messages.operation.refund.exceeds_the_original_payment_amount',
                    ['amount' => $this->paymentRefundDto->originalPayment->amount]
                )
            );
        }
    }
}
