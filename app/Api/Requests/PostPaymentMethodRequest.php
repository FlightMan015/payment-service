<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Helpers\RequestDefaultValuesTrait;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\CreditCardTypeEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Rules\GatewayExistsAndActive;
use Axlon\PostalCodeValidation\Rules\PostalCode;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use LVR\State\Abbr as StateTwoCharactersCodeRule;

/**
 * @property string $account_id The ID of the account for whom the payment method is being added.
 * @property string $type The type of payment method.
 * @property int $gateway_id Gateway ID
 * @property string $first_name The first name associated with the payment method.
 * @property string $last_name The last name associated with the payment method.
 * @property string|null $ach_account_number The ACH account number for ACH payment methods.
 * @property string|null $ach_routing_number The ACH routing number for ACH payment methods.
 * @property string|null $ach_account_last_four The last four of ACH account number for ACH payment methods.
 * @property string|null $ach_account_type_id The ACH account type id.
 * @property string|null $ach_bank_name The ACH account bank name.
 * @property string|null $cc_token The credit card token for credit card payment methods.
 * @property string|null $cc_expiration_month The credit card expiration month for credit card payment methods.
 * @property string|null $cc_expiration_year The credit card expiration year for credit card payment methods.
 * @property string|null $cc_last_four The last four of credit card number for credit card payment methods.
 * @property string $address_line1 The first line of the address associated with the payment method.
 * @property string|null $address_line2 The second line of the address associated with the payment method.
 * @property string $email The email address associated with the payment method.
 * @property string $city The city associated with the payment method address.
 * @property string $province The province associated with the payment method address (2-letter code).
 * @property string $postal_code The postal code associated with the payment method address.
 * @property string $country_code The country code associated with the payment method address (2-letter code).
 * @property bool $is_primary Indicates whether the payment method is the primary method for the account.
 * @property bool $should_skip_gateway_validation Indicates whether to make the Gateway $0 auth payment.
 */
class PostPaymentMethodRequest extends AbstractRequest
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
        $gatewayExistsActive = app()->make(abstract: GatewayExistsAndActive::class);

        return [
            'account_id' => ['bail', 'required', 'uuid'],
            'gateway_id' => ['bail', 'required', 'integer', $gatewayExistsActive],
            'type' => [
                'required',
                Rule::in(values: array_map(static fn (PaymentTypeEnum $type) => $type->name, PaymentTypeEnum::cases()))
            ],
            'first_name' => ['required', 'string', $this->regex(expression: OperationFields::NAME_ON_ACCOUNT_REGEX, modifier: 'u')],
            'last_name' => ['required', 'string', $this->regex(expression: OperationFields::NAME_ON_ACCOUNT_REGEX, modifier: 'u')],
            'ach_account_number' => [
                'nullable',
                'string',
                $this->requiredForACH(),
                $this->regex(expression: OperationFields::ACH_ACCOUNT_NUMBER_REGEX)
            ],
            'ach_routing_number' => [
                'nullable',
                'string',
                $this->requiredForACH(),
                $this->regex(expression: OperationFields::ACH_ROUTING_NUMBER_REGEX)
            ],
            'ach_account_last_four' => [
                'nullable',
                'alpha_num',
                $this->requiredForACH(),
                'digits:4',
            ],
            'ach_account_type_id' => ['nullable', 'string', $this->requiredForACH(), Rule::enum(type: AchAccountTypeEnum::class)],
            'ach_bank_name' => ['nullable', 'string', 'max:128'],
            'cc_token' => [
                'nullable',
                'string',
                $this->requiredForCreditCard(),
                $this->regex(expression: OperationFields::TOKEN_REGEX),
            ],
            'cc_type' => [
                'nullable',
                Rule::enum(type: CreditCardTypeEnum::class)
            ],
            'cc_expiration_month' => [
                'nullable',
                'required_with:cc_expiration_year',
                $this->regex(expression: OperationFields::CC_EXP_MONTH_REGEX),
                $this->requiredForCreditCard(),
            ],
            'cc_expiration_year' => [
                'nullable',
                'required_with:cc_expiration_month',
                'date_format:Y',
                'size:4',
                $this->requiredForCreditCard(),
            ],
            'cc_expiration' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'cc_last_four' => [
                'nullable',
                'alpha_num',
                $this->requiredForCreditCard(),
                'digits:4',
            ],
            'address_line1' => ['required', 'string', $this->regex(expression: OperationFields::ADDRESS_LINE_1_REGEX)],
            'address_line2' => ['nullable', 'string', $this->regex(expression: OperationFields::ADDRESS_LINE_2_REGEX)],
            'email' => ['required', 'email', 'max:256', $this->regex(expression: OperationFields::EMAIL_ADDRESS_REGEX)],
            'city' => ['required', 'string', 'max:64', $this->regex(expression: OperationFields::CITY_REGEX)],
            'province' => [
                'required',
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
                'required',
                'string',
                'max:10',
                PostalCode::with(field: 'country_code'),
                $this->regex(expression: OperationFields::POSTAL_CODE_REGEX)
            ],
            // TODO Use laravel-validation-rules/country-codes as soon as they support Laravel 11
            // see CLEO-870
            'country_code' => ['required', 'string', 'size:2', Rule::in(['US'])],
            'is_primary' => ['required', 'boolean'],
            'should_skip_gateway_validation' => ['required', 'boolean'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'country_code' => 'US',
            'is_primary' => false,
            'should_skip_gateway_validation' => false,
            'ach_account_type_id' => AchAccountTypeEnum::PERSONAL_CHECKING->value,
        ];
    }

    private function requiredForACH(): RequiredIf
    {
        return Rule::requiredIf(callback: fn () => $this->input(key: 'type') === PaymentTypeEnum::ACH->name);
    }

    private function requiredForCreditCard(): RequiredIf
    {
        return Rule::requiredIf(callback: fn () => in_array(
            needle: $this->input(key: 'type'),
            haystack: PaymentTypeEnum::creditCardNames(),
            strict: true
        ));
    }

    protected function prepareForValidation(): void
    {
        // add default values
        foreach ($this->defaults() as $key => $defaultValue) {
            if (!$this->has($key)) {
                $this->merge([$key => $defaultValue]);
            }
        }

        // add cc_expiration to validate that the expiration date is in the future
        if ($this->request->has('cc_expiration_month') && $this->request->has('cc_expiration_year')) {
            $month  = sprintf('%02d', $this->request->get('cc_expiration_month'));
            $year  = $this->request->get('cc_expiration_year');
            $this->merge(['cc_expiration' => date("$year-$month-d")]);
        }

        parent::prepareForValidation();
    }
}
