<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostAuthorizePaymentRequest;

final class AuthorizePaymentCommand
{
    /**
     * @param int $amount
     * @param string $accountId
     * @param string|null $paymentMethodId
     * @param string|null $notes
     */
    public function __construct(
        public readonly int $amount,
        public readonly string $accountId,
        public readonly string|null $paymentMethodId,
        public readonly string|null $notes,
    ) {
    }

    /**
     * @param PostAuthorizePaymentRequest $request
     *
     * @return self
     */
    public static function fromRequest(PostAuthorizePaymentRequest $request): self
    {
        return new self(
            amount: $request->integer(key: 'amount'),
            accountId: $request->get(key: 'account_id'),
            paymentMethodId: $request->get(key: 'method_id'),
            notes: $request->get(key: 'notes'),
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
        ];
    }
}
