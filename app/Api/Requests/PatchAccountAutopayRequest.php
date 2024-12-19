<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Models\PaymentMethod;
use App\Rules\AccountExists;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Validation\Rule;

/**
 * @property string $account_id
 * @property string|null $autopay_method_id
 */
class PatchAccountAutopayRequest extends AbstractRequest
{
    /**
     * @throws BindingResolutionException
     *
     * @return array<string, array<Rule|string>>
     */
    public function rules(): array
    {
        $accountExists = app()->make(abstract: AccountExists::class);

        return [
            'account_id' => ['bail', 'required', 'uuid', $accountExists],
            'autopay_method_id' => ['bail', 'nullable', 'uuid', Rule::exists(table: PaymentMethod::class, column: 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(input: ['account_id' => $this->route(param: 'accountId')]);
    }
}
