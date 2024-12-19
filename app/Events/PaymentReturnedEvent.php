<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReturnedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $timestamp;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     */
    public function __construct(public readonly Payment $payment)
    {
        $this->timestamp = Carbon::now()->getTimestampMs();
    }
}
