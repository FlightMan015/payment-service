<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Models\Payment;
use Carbon\Carbon;

trait ChecksThatPaymentWasProcessedInGatewayTrait
{
    private Payment $payment;

    private const string GATEWAY_PROCESSING_TIME = '20:00:00';
    private const string GATEWAY_PROCESSING_TIMEZONE = 'MST';

    private function paymentWasAlreadyProcessedInGateway(): bool
    {
        $paymentProcessedAt = Carbon::parse(time: $this->payment->processed_at)->setTimezone(self::GATEWAY_PROCESSING_TIMEZONE);
        $paymentProcessedAtGatewayAt = $this->getNextGatewayPaymentProcessingTime(paymentProcessedAt: $paymentProcessedAt);

        return $paymentProcessedAtGatewayAt->isPast();
    }

    private function getNextGatewayPaymentProcessingTime(Carbon $paymentProcessedAt): Carbon
    {
        $nextProcessingTime = $paymentProcessedAt->clone()->setTimeFromTimeString(time: self::GATEWAY_PROCESSING_TIME);

        if ($paymentProcessedAt->gt($nextProcessingTime)) {
            $nextProcessingTime->addDay();
        }

        return $nextProcessingTime;
    }
}
