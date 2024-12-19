<?php

declare(strict_types=1);

namespace Customer\Api\Exceptions;

class PestroutesAPIException extends \Exception
{
    public const string PAYMENT_PROFILE_NOT_FOUND = 'There is no paymentProfile for this customer';
    public const string INVOICE_FOR_THIS_APPOINTMENT_NOT_FOUND = 'There is no invoice for this appointment';
    public const string CUSTOMER_NOT_FOUND = 'There is no customer for this appointment';
}
