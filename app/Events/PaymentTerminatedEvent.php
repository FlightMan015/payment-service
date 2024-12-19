<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentTerminatedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $timestamp;

    /**
     * Create a new event instance.
     *
     * @param Account $account
     * @param PaymentMethod|null $paymentMethod
     * @param Payment $terminatedPayment
     * @param Payment $originalPayment
     */
    public function __construct(
        public readonly Account $account,
        public readonly PaymentMethod|null $paymentMethod,
        public readonly Payment $terminatedPayment,
        public readonly Payment $originalPayment
    ) {
        $this->timestamp = Carbon::now()->getTimestampMs();
    }
}
