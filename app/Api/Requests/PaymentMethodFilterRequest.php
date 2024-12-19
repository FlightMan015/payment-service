<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Rules\AccountExists;
use App\Rules\GatewayExistsAndActive;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Validation\Rule;

/**
 * @property string|null $account_id
 * @property string[]|null $account_ids
 * @property string $cc_expire_from_date
 * @property string $cc_expire_to_date
 * @property int|null $gateway_id
 * @property bool|null $is_valid
 * @property string|null $type
 */
class PaymentMethodFilterRequest extends FilterRequest
{
    /**
     * @inheritdoc
     *
     * @throws BindingResolutionException
     */
    public function rules(): array
    {
        $accountExists = app()->make(abstract: AccountExists::class);
        $gatewayExistsActive = app()->make(abstract: GatewayExistsAndActive::class);

        return array_merge(parent::rules(), [
            'account_id' => ['bail', 'required_without:account_ids', 'missing_with:account_ids', 'uuid', $accountExists],
            'account_ids' => ['required_without:account_id', 'missing_with:account_id', 'array'],
            'account_ids.*' => ['bail', 'uuid', $accountExists],
            'cc_expire_from_date' => ['nullable', 'filled', 'date_format:Y-m-d'],
            'cc_expire_to_date' => ['nullable', 'filled', 'date_format:Y-m-d'],
            'gateway_id' => ['nullable', 'integer', 'filled', $gatewayExistsActive],
            'is_valid' => ['nullable', 'boolean'],
            'type' => [
                'nullable',
                Rule::in(values: array_map(static fn (PaymentTypeEnum $type) => $type->name, PaymentTypeEnum::cases()))
            ],
        ]);
    }
}
