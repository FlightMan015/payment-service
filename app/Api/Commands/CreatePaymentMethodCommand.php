<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PostPaymentMethodRequest;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\CreditCardTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;

final class CreatePaymentMethodCommand
{
    /**
     * @param string $accountId
     * @param PaymentTypeEnum $type
     * @param PaymentGatewayEnum $gateway
     * @param string $firstName
     * @param string $lastName
     * @param string|null $achAccountNumber
     * @param string|null $achRoutingNumber
     * @param string|null $achAccountLastFour
     * @param AchAccountTypeEnum|null $achAccountType
     * @param string|null $achBankName
     * @param string|null $creditCardToken
     * @param CreditCardTypeEnum|null $creditCardType
     * @param int|null $creditCardExpirationMonth
     * @param int|null $creditCardExpirationYear
     * @param string|null $creditCardLastFour
     * @param string $addressLine1
     * @param string|null $addressLine2
     * @param string $email
     * @param string $city
     * @param string $province
     * @param string $postalCode
     * @param string $countryCode
     * @param bool $isPrimary
     * @param bool $shouldSkipGatewayValidation
     */
    public function __construct(
        public readonly string $accountId,
        public readonly PaymentTypeEnum $type,
        public readonly PaymentGatewayEnum $gateway,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string|null $achAccountNumber,
        public readonly string|null $achRoutingNumber,
        public readonly string|null $achAccountLastFour,
        public readonly AchAccountTypeEnum|null $achAccountType,
        public readonly string|null $achBankName,
        public readonly string|null $creditCardToken,
        public readonly CreditCardTypeEnum|null $creditCardType,
        public readonly int|null $creditCardExpirationMonth,
        public readonly int|null $creditCardExpirationYear,
        public readonly string|null $creditCardLastFour,
        public readonly string $addressLine1,
        public readonly string|null $addressLine2,
        public readonly string $email,
        public readonly string $city,
        public readonly string $province,
        public readonly string $postalCode,
        public readonly string $countryCode,
        public readonly bool $isPrimary,
        public readonly bool $shouldSkipGatewayValidation
    ) {
    }

    /**
     * @param PostPaymentMethodRequest $request
     *
     * @return self
     */
    public static function fromRequest(PostPaymentMethodRequest $request): self
    {
        return new self(
            accountId: $request->account_id,
            type: PaymentTypeEnum::fromName(name: $request->type),
            gateway: PaymentGatewayEnum::from($request->integer(key: 'gateway_id')),
            firstName: $request->first_name,
            lastName: $request->last_name,
            achAccountNumber: $request->ach_account_number,
            achRoutingNumber: $request->ach_routing_number,
            achAccountLastFour: (string)$request->ach_account_last_four,
            achAccountType: $request->has(key: 'ach_account_type_id') ? AchAccountTypeEnum::tryFrom(value: $request->ach_account_type_id) : null,
            achBankName: $request->ach_bank_name,
            creditCardToken: $request->cc_token,
            creditCardType: $request->has(key: 'cc_type') ? CreditCardTypeEnum::from($request->cc_type) : null,
            creditCardExpirationMonth: $request->has(key: 'cc_expiration_month') ? $request->integer(key: 'cc_expiration_month') : null,
            creditCardExpirationYear: $request->has(key: 'cc_expiration_year') ? $request->integer(key: 'cc_expiration_year') : null,
            creditCardLastFour: (string)$request->cc_last_four,
            addressLine1: $request->address_line1,
            addressLine2: $request->address_line2,
            email: $request->email,
            city: $request->city,
            province: $request->province,
            postalCode: $request->postal_code,
            countryCode: $request->country_code,
            isPrimary: $request->is_primary,
            shouldSkipGatewayValidation: $request->boolean(key: 'should_skip_gateway_validation')
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'type' => $this->type,
            'gateway_id' => $this->gateway->value,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'ach_account_number' => $this->achAccountNumber,
            'ach_routing_number' => $this->achRoutingNumber,
            'ach_account_last_four' => $this->achAccountLastFour,
            'ach_account_type_id' => $this->achAccountType?->value,
            'ach_bank_name' => $this->achBankName,
            'cc_token' => $this->creditCardToken,
            'cc_type' => $this->creditCardType?->value,
            'cc_expiration_month' => $this->creditCardExpirationMonth,
            'cc_expiration_year' => $this->creditCardExpirationYear,
            'cc_last_four' => $this->creditCardLastFour,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'email' => $this->email,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'is_primary' => $this->isPrimary,
            'should_skip_gateway_validation' => $this->shouldSkipGatewayValidation,
        ];
    }
}
