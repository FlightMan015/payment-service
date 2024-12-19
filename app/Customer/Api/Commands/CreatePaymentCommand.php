<?php

declare(strict_types=1);

namespace Customer\Api\Commands;

class CreatePaymentCommand
{
    public function __construct(
        public readonly int $officeId,
        public readonly int $customerId,
        public readonly bool $payoffOutstandingBalance,
        public readonly int|null $appointmentId,
        public readonly float|null $amount,
        public readonly string|null $requestOrigin = null,
    ) {
    }
}
