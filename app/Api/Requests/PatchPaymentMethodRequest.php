<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Helpers\RequestDefaultValuesTrait;
use App\PaymentProcessor\Enums\OperationFields;
use Axlon\PostalCodeValidation\Rules\PostalCode;
use Illuminate\Validation\Rule;
use LVR\State\Abbr as StateTwoCharactersCodeRule;

/**
 * @property string|null $first_name The first name associated with the payment method.
 * @property string|null $last_name The last name associated with the payment method.
 * @property string|null $address_line1 The first line of the address associated with the payment method.
 * @property string|null $address_line2 The second line of the address associated with the payment method.
 * @property string|null $email The email address associated with the payment method.
 * @property string|null $city The city associated with the payment method address.
 * @property string|null $province The province associated with the payment method address (2-letter code).
 * @property string|null $postal_code The postal code associated with the payment method address.
 * @property string|null $country_code The country code associated with the payment method address (2-letter code).
 * @property int|null $cc_expiration_month The cc_expiration_month for CC payment method
 * @property int|null $cc_expiration_year The cc_expiration_year for CC payment method
 */
class PatchPaymentMethodRequest extends AbstractRequest
{
    use RequestDefaultValuesTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @throws \Exception
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', $this->regex(expression: OperationFields::NAME_ON_ACCOUNT_REGEX, modifier: 'u')],
            'last_name' => ['nullable', 'string', $this->regex(expression: OperationFields::NAME_ON_ACCOUNT_REGEX, modifier: 'u')],
            'address_line1' => ['nullable', 'string', $this->regex(expression: OperationFields::ADDRESS_LINE_1_REGEX)],
            'address_line2' => ['nullable', 'string', $this->regex(expression: OperationFields::ADDRESS_LINE_2_REGEX)],
            'email' => ['nullable', 'email', 'max:256', $this->regex(expression: OperationFields::EMAIL_ADDRESS_REGEX)],
            'city' => ['nullable', 'string', 'max:64', $this->regex(expression: OperationFields::CITY_REGEX)],
            'province' => [
                'nullable',
                'string',
                'size:2',
                Rule::when(
                    condition: in_array(
                        needle: $this->input(key: 'country_code'),
                        haystack: ['US', 'CA', 'BR'],
                        strict: true
                    ),
                    rules: [new StateTwoCharactersCodeRule($this->input(key: 'country_code'))]
                ),
                $this->regex(expression: OperationFields::PROVINCE_REGEX)
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:10',
                PostalCode::with(field: 'country_code'),
                $this->regex(expression: OperationFields::POSTAL_CODE_REGEX)
            ],
            // TODO Use laravel-validation-rules/country-codes as soon as they support Laravel 11
            // see CLEO-870
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(['US'])],
            'cc_expiration_month' => [
                'nullable',
                'required_with:cc_expiration_year',
                $this->regex(expression: OperationFields::CC_EXP_MONTH_REGEX),
            ],
            'cc_expiration_year' => [
                'nullable',
                'required_with:cc_expiration_month',
                'date_format:Y',
                'size:4'
            ],
            'cc_expiration' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->request->has('cc_expiration_month') && $this->request->has('cc_expiration_year')) {
            $month  = sprintf('%02d', $this->request->get('cc_expiration_month'));
            $year  = $this->request->get('cc_expiration_year');
            $this->merge(['cc_expiration' => date("$year-$month-d")]);
        }

        parent::prepareForValidation();
    }
}
