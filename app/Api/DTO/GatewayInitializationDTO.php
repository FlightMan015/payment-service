<?php

declare(strict_types=1);

namespace App\Api\DTO;

use App\Models\PaymentMethod;

readonly class GatewayInitializationDTO
{
    /**
     * @param int $gatewayId
     * @param int|null $officeId
     * @param string|null $creditCardToken
     * @param int|null $creditCardExpirationMonth
     * @param int|null $creditCardExpirationYear
     */
    public function __construct(
        public int $gatewayId,
        public int|null $officeId,
        public string|null $creditCardToken,
        public int|null $creditCardExpirationMonth,
        public int|null $creditCardExpirationYear,
    ) {
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return GatewayInitializationDTO
     */
    public static function fromPaymentMethod(PaymentMethod $paymentMethod): GatewayInitializationDTO
    {
        return new self(
            gatewayId: $paymentMethod->payment_gateway_id,
            officeId: $paymentMethod->account->area->external_ref_id,
            creditCardToken: $paymentMethod->cc_token,
            creditCardExpirationMonth: is_null($paymentMethod->cc_expiration_month) ? null : (int)$paymentMethod->cc_expiration_month,
            creditCardExpirationYear: is_null($paymentMethod->cc_expiration_year) ? null : (int)$paymentMethod->cc_expiration_year,
        );
    }
}
