<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use App\Models\ScheduledPayment;

class ScheduledPaymentSubmittedEvent extends AbstractScheduledPaymentEvent
{
    /**
     * @param ScheduledPayment $payment
     * @param Payment $resultPayment
     */
    public function __construct(ScheduledPayment $payment, public readonly Payment $resultPayment)
    {
        parent::__construct($payment);
    }
}
