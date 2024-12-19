<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Exceptions;

class GatewayDeclineReasonUnmapped extends \InvalidArgumentException
{
    /**
     * @param string|int $responseCode
     */
    public function __construct(int|string $responseCode)
    {
        parent::__construct(message: __('messages.gateway.unmapped_decline_reason', ['code' => $responseCode]));
    }
}
