<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Helpers\RequestDefaultValuesTrait;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\Rules\GatewayExistsAndActive;

/**
 * @property int $gateway_id Gateway ID
 * @property int|null $office_id Office ID
 * @property string $cc_token The credit card token
 * @property string $cc_expiration_month The credit card expire month
 * @property string $cc_expiration_year The credit card expire year
 * @property string|null $cc_expiration The credit card expiration
 */
class PostValidateCreditCardTokenRequest extends AbstractRequest
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
            'gateway_id' => ['bail', 'required', 'integer', $gatewayExistsActive],
            'office_id' => [
                'required_if:gateway_id,' . PaymentGatewayEnum::WORLDPAY->value . ',' . PaymentGatewayEnum::WORLDPAY_TOKENEX_TRANSPARENT->value,
                'integer',
            ],
            'cc_token' => [
                'required',
                'string',
                $this->regex(expression: OperationFields::TOKEN_REGEX),
            ],
            'cc_expiration_month' => [
                'required',
                $this->regex(expression: OperationFields::CC_EXP_MONTH_REGEX),
            ],
            'cc_expiration_year' => [
                'required',
                'date_format:Y',
                'size:4',
            ],
            'cc_expiration' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Add cc_expiration to validate that the expiration date is in the future
        if ($this->request->has('cc_expiration_month') && $this->request->has('cc_expiration_year')) {
            $month  = sprintf('%02d', $this->request->get('cc_expiration_month'));
            $year  = $this->request->get('cc_expiration_year');
            $this->merge(['cc_expiration' => date("$year-$month-d")]);
        }

        parent::prepareForValidation();
    }
}
