<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Requests\PatchPaymentMethodRequest;

final class UpdatePaymentMethodCommand
{
    /**
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $addressLine1
     * @param string|null $addressLine2
     * @param string|null $email
     * @param string|null $city
     * @param string|null $province
     * @param string|null $postalCode
     * @param string|null $countryCode
     * @param int|null $creditCardExpirationMonth
     * @param int|null $creditCardExpirationYear
     * @param bool|null $isPrimary
     */
    public function __construct(
        public readonly string|null $firstName,
        public readonly string|null $lastName,
        public readonly string|null $addressLine1,
        public readonly string|null $addressLine2,
        public readonly string|null $email,
        public readonly string|null $city,
        public readonly string|null $province,
        public readonly string|null $postalCode,
        public readonly string|null $countryCode,
        public readonly int|null $creditCardExpirationMonth,
        public readonly int|null $creditCardExpirationYear,
        public readonly bool|null $isPrimary
    ) {
    }

    /**
     * @param PatchPaymentMethodRequest $request
     *
     * @return self
     */
    public static function fromRequest(PatchPaymentMethodRequest $request): self
    {
        return new self(
            firstName: $request->first_name,
            lastName: $request->last_name,
            addressLine1: $request->address_line1,
            addressLine2: $request->address_line2,
            email: $request->email,
            city: $request->city,
            province: $request->province,
            postalCode: $request->postal_code,
            countryCode: $request->country_code,
            creditCardExpirationMonth: $request->has(key: 'cc_expiration_month') ? $request->integer(key: 'cc_expiration_month') : null,
            creditCardExpirationYear: $request->has(key: 'cc_expiration_year') ? $request->integer(key: 'cc_expiration_year') : null,
            isPrimary: !is_null($request->get(key: 'is_primary')) ? $request->boolean(key: 'is_primary') : null,
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = [
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'email' => $this->email,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'cc_expiration_month' => $this->creditCardExpirationMonth,
            'cc_expiration_year' => $this->creditCardExpirationYear,
            'is_primary' => $this->isPrimary,
        ];

        if ($this->firstName !== null || $this->lastName !== null) {
            $data['name_on_account'] = trim(implode(separator: ' ', array: array_filter([$this->firstName, $this->lastName])));
        }

        return $data;
    }
}
