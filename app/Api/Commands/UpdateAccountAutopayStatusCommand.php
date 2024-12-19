<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PatchAccountAutopayRequest;

final class UpdateAccountAutopayStatusCommand
{
    /**
     * @param string $accountId
     * @param string|null $autopayPaymentMethodId
     */
    public function __construct(
        public readonly string $accountId,
        public readonly string|null $autopayPaymentMethodId
    ) {
    }

    /**
     * @param PatchAccountAutopayRequest $request
     *
     * @return self
     */
    public static function fromRequest(PatchAccountAutopayRequest $request): self
    {
        return new self(
            accountId: $request->account_id,
            autopayPaymentMethodId: $request->autopay_method_id
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'autopay_payment_method_id' => $this->autopayPaymentMethodId,
        ];
    }
}
