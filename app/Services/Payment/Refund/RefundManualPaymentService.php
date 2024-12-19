<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Exceptions\PaymentRefundFailedException;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;

class RefundManualPaymentService extends AbstractRefundPaymentService
{
    /**
     * @throws PaymentRefundFailedException
     */
    protected function checkIfPaymentCanBeRefunded(): void
    {
        $this->validateOriginalPaymentMethod();
        $this->validateOriginalPaymentStatus();
        $this->validateOriginalPaymentWasNotPreviouslyRefunded();
        $this->validateRefundAmountDoesNotExceedOriginalPaymentAmount();
    }

    /**
     * @throws PaymentRefundFailedException
     */
    private function validateOriginalPaymentMethod(): void
    {
        if (is_null($this->paymentRefundDto->originalPayment->paymentMethod)) {
            throw new PaymentRefundFailedException(
                message: __('messages.operation.refund.original_payment_method_not_found')
            );
        }

        if (
            !in_array(
                $this->paymentRefundDto->originalPayment->payment_type,
                [PaymentTypeEnum::CHECK, PaymentTypeEnum::ACH, PaymentTypeEnum::CC]
            )
        ) {
            throw new PaymentRefundFailedException(message: __('messages.operation.refund.manual_refund_not_allowed'));
        }
    }

    /**
     * @throws \Throwable
     */
    protected function processPaymentRefund(): RefundPaymentResultDto
    {
        $this->createDatabaseRefundPaymentRecord();

        return new RefundPaymentResultDto(
            isSuccess: true,
            status: PaymentStatusEnum::from($this->refundPayment->payment_status_id),
            refundPaymentId: $this->refundPayment->id
        );
    }

    protected function createDatabaseRefundPaymentRecord(): void
    {
        $overrideAttributes = [
            'amount' => $this->paymentRefundDto->refundAmount,
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'payment_gateway_id' => PaymentGatewayEnum::CHECK->value,
            'payment_type_id' => PaymentTypeEnum::CHECK->value,
            'processed_at' => now(),
        ];

        if ($this->paymentRefundDto->externalRefId) {
            $overrideAttributes['external_ref_id'] = $this->paymentRefundDto->externalRefId;
        }

        $this->refundPayment = $this->paymentRepository->cloneAndCreateFromExistingPayment(
            payment: $this->paymentRefundDto->originalPayment,
            overriddenAttributes: $overrideAttributes,
        );
    }
}
