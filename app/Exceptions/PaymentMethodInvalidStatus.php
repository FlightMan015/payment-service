<?php

declare(strict_types=1);

namespace App\Exceptions;

use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfileStatus;

class PaymentMethodInvalidStatus extends AbstractPaymentProcessingException
{
    /**
     * @param string $accountId
     * @param PaymentProfileStatus $status
     */
    public function __construct(string $accountId, PaymentProfileStatus $status)
    {
        parent::__construct(
            message: __('messages.payment.batch_processing.autopay_payment_method_invalid_status'),
            context: ['account_id' => $accountId, 'status' => $status->name]
        );
    }
}
