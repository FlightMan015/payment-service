<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostRefundPaymentRequest;

readonly class RefundPaymentCommand
{
    public const int DAYS_THE_REFUND_IS_ALLOWED = 45;

    /**
     * @param string $paymentId
     * @param int|null $amount
     * @param bool $allowManualRefund
     * @param int $daysRefundAllowed
     * @param int|null $externalRefId
     */
    public function __construct(
        public string $paymentId,
        public int|null $amount = null,
        public bool $allowManualRefund = true,
        public int $daysRefundAllowed = self::DAYS_THE_REFUND_IS_ALLOWED,
        public int|null $externalRefId = null,
    ) {
    }

    /**
     * @param PostRefundPaymentRequest $request
     * @param string $paymentId
     *
     * @return self
     */
    public static function fromRequest(PostRefundPaymentRequest $request, string $paymentId): self
    {
        return new self(
            paymentId: $paymentId,
            amount: $request->filled(key: 'amount') ? $request->integer(key: 'amount') : null,
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
        ];
    }
}
