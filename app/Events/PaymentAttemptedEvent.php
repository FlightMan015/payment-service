<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Enums\PaymentProcessingInitiator;
use App\Models\Payment;
use App\PaymentProcessor\Enums\OperationEnum;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentAttemptedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $timestamp;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     * @param PaymentProcessingInitiator $initiated_by
     * @param OperationEnum $operation
     */
    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentProcessingInitiator $initiated_by,
        public readonly OperationEnum $operation
    ) {
        $this->timestamp = Carbon::now()->getTimestampMs();
    }
}
