<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostAuthorizeAndCapturePaymentRequest;

final class AuthorizeAndCapturePaymentCommand
{
    /**
     * @param int $amount
     * @param string $accountId
     * @param string|null $paymentMethodId
     * @param string|null $notes
     * @param string|null $paymentId required when payment already exists. e.g. passing a suspended payment to be processed. Not required when capturing a new payment
     */
    public function __construct(
        public readonly int $amount,
        public readonly string $accountId,
        public readonly string|null $paymentMethodId,
        public readonly string|null $notes,
        public readonly string|null $paymentId = null,
    ) {
    }

    /**
     * @param PostAuthorizeAndCapturePaymentRequest $request
     *
     * @return self
     */
    public static function fromRequest(PostAuthorizeAndCapturePaymentRequest $request): self
    {
        return new self(
            amount: $request->integer(key: 'amount'),
            accountId: $request->get(key: 'account_id'),
            paymentMethodId: $request->get(key: 'method_id'),
            notes: $request->get(key: 'notes'),
            paymentId: null,
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'account_id' => $this->accountId,
            'method_id' => $this->paymentMethodId,
            'notes' => $this->notes,
            'payment_id' => $this->paymentId,
        ];
    }
}
