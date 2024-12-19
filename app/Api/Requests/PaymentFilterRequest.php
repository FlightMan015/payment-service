<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\Rule;

/**
 * @property string $account_id
 * @property string $invoice_id
 * @property array $payment_ids
 * @property string $payment_method_id
 * @property string $date_from
 * @property string $date_to
 * @property int $area_id
 * @property string $payment_status
 * @property int $amount_from
 * @property int $amount_to
 * @property string $first_name
 * @property string $last_name
 */
class PaymentFilterRequest extends FilterRequest
{
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'account_id' => ['bail', 'uuid'],
            'invoice_id' => ['bail', 'uuid'],
            'payment_ids' => ['array'],
            'payment_ids.*' => ['bail', 'uuid'],
            'payment_method_id' => ['bail', 'uuid'],
            'date_from' => ['date_format:Y-m-d'],
            'date_to' => ['date_format:Y-m-d', 'after_or_equal:date_from'],
            'area_id' => ['integer'],
            'payment_status' => ['string', Rule::in(array_column(PaymentStatusEnum::cases(), 'name'))],
            'amount_from' => ['integer', 'gte:0'],
            'amount_to' => ['integer', $this->has('amount_from') ? 'gte:amount_from' : ''],
            'first_name' => ['string', 'max:255'],
            'last_name' => ['string', 'max:255'],
        ]);
    }

    /**
     * Return validated input with additional casts
     *
     * @return ValidatedInput
     */
    public function validatedWithCasts(): ValidatedInput
    {
        $validated = $this->safe();

        if ($this->has('sort')) {
            $validated = $validated->merge([
                'sort' => 'payments.' . $this->sort
            ]);
        }

        return $validated;
    }
}
