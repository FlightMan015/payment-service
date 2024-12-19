<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostScheduledPaymentRequest;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;

readonly class CreateScheduledPaymentCommand
{
    /**
     * @param string $accountId
     * @param int $amount
     * @param string $paymentMethodId
     * @param ScheduledPaymentTriggerEnum $trigger
     * @param array|null $metadata
     */
    public function __construct(
        public string $accountId,
        public int $amount,
        public string $paymentMethodId,
        public ScheduledPaymentTriggerEnum $trigger,
        public array|null $metadata = null,
    ) {
    }

    /**
     * @param PostScheduledPaymentRequest $request
     *
     * @return self
     */
    public static function fromRequest(PostScheduledPaymentRequest $request): self
    {
        return new self(
            accountId: $request->input(key: 'account_id'),
            amount:  $request->integer(key: 'amount'),
            paymentMethodId: $request->input(key: 'method_id'),
            trigger: ScheduledPaymentTriggerEnum::tryFrom($request->integer(key: 'trigger_id')),
            metadata: $request->input(key: 'metadata'),
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'amount' => $this->amount,
            'payment_method_id' => $this->paymentMethodId,
            'trigger_id' => $this->trigger->value,
            'metadata' => $this->metadata,
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
        ];
    }
}
