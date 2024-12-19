<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Rules\AccountExists;
use Illuminate\Validation\Rule;

/**
 * @property-read string $account_id CRM Account ID for whom the payment is being added.
 * @property-read int $amount Amount value for payment.
 * @property-read string $type The type of payment.
 * @property-read string $check_date Date when the check was deposited into the bank account (required for payment of type CHECK).
 */
class PostPaymentRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['bail', 'required', 'uuid', app()->make(AccountExists::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::in(PaymentTypeEnum::CHECK->name)],
            'check_date' => [
                Rule::requiredIf(fn () => $this->input('type') === PaymentTypeEnum::CHECK->name),
                'date_format:Y-m-d'
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
