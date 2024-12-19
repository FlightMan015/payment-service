<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\PaymentResolutionEnum;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuspendedPaymentUpdatedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $timestamp;

    /**
     * Create a new event instance.
     *
     * @param Account $account
     * @param PaymentResolutionEnum $resolution
     * @param PaymentMethod|null $paymentMethod
     * @param Payment|null $payment
     */
    public function __construct(
        public readonly Account $account,
        public readonly PaymentResolutionEnum $resolution,
        public readonly PaymentMethod|null $paymentMethod,
        public readonly Payment|null $payment
    ) {
        $this->timestamp = Carbon::now()->getTimestampMs();
    }
}
