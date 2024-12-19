<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostValidateCreditCardTokenRequest;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;

final class ValidateCreditCardTokenCommand
{
    /**
     * @param PaymentGatewayEnum $gateway
     * @param int $officeId
     * @param string $creditCardToken
     * @param int $creditCardExpirationMonth
     * @param int $creditCardExpirationYear
     */
    public function __construct(
        public readonly PaymentGatewayEnum $gateway,
        public readonly int $officeId,
        public readonly string $creditCardToken,
        public readonly int $creditCardExpirationMonth,
        public readonly int $creditCardExpirationYear,
    ) {
    }

    /**
     * @param PostValidateCreditCardTokenRequest $request
     *
     * @return self
     */
    public static function fromRequest(PostValidateCreditCardTokenRequest $request): self
    {
        return new self(
            gateway: PaymentGatewayEnum::from($request->integer(key: 'gateway_id')),
            officeId: $request->office_id,
            creditCardToken: $request->cc_token,
            creditCardExpirationMonth: $request->integer(key: 'cc_expiration_month'),
            creditCardExpirationYear: $request->integer(key: 'cc_expiration_year'),
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'gateway_id' => $this->gateway->value,
            'office_id' => $this->officeId,
            'cc_token' => $this->creditCardToken,
            'cc_expiration_month' => $this->creditCardExpirationMonth,
            'cc_expiration_year' => $this->creditCardExpirationYear,
        ];
    }
}
