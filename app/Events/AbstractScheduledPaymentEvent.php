<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ScheduledPayment;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class AbstractScheduledPaymentEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $timestamp;

    /**
     * Create a new event instance.
     *
     * @param ScheduledPayment $payment
     */
    public function __construct(public ScheduledPayment $payment)
    {
        $this->timestamp = Carbon::now()->getTimestampMs();
    }
}
