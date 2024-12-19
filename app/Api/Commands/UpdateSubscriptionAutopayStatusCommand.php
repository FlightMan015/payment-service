<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PatchSubscriptionAutopayStatusRequest;

final class UpdateSubscriptionAutopayStatusCommand
{
    /**
     * @param string $subscriptionId
     * @param string|null $autopayPaymentMethodId
     */
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string|null $autopayPaymentMethodId
    ) {
    }

    /**
     * @param PatchSubscriptionAutopayStatusRequest $request
     *
     * @return self
     */
    public static function fromRequest(PatchSubscriptionAutopayStatusRequest $request): self
    {
        return new self(
            subscriptionId: $request->subscription_id,
            autopayPaymentMethodId: $request->autopay_method_id
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'autopay_payment_method_id' => $this->autopayPaymentMethodId,
        ];
    }
}
