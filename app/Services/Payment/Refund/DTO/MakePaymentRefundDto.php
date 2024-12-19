<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund\DTO;

use App\Models\Payment;

readonly class MakePaymentRefundDto
{
    /**
     * @param Payment $originalPayment payment that needs to be refunded
     * @param int $refundAmount amount to refund
     * @param int|null $daysRefundAllowed number of days the refund is allowed
     * @param int|null $externalRefId set this in case of the payment refund was already processed in the external
     *                                system or you want to bypass the sync
     * @param Payment|null $existingRefundPayment set existing refund payment record  if you want to process the refund
     *                                            for the given original payment without creating a new one (could be
     *                                            needed if you want to refund the external refund payment)
     */
    public function __construct(
        public Payment $originalPayment,
        public int $refundAmount,
        public int|null $daysRefundAllowed = null,
        public int|null $externalRefId = null,
        public Payment|null $existingRefundPayment = null,
    ) {
    }
}
