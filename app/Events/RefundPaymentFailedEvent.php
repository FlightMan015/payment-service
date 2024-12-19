<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundPaymentFailedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $timestamp;

    /**
     * Create a new event instance.
     *
     * @param Payment $refund
     */
    public function __construct(public readonly Payment $refund)
    {
        $this->timestamp = Carbon::now()->getTimestampMs();
    }
}
