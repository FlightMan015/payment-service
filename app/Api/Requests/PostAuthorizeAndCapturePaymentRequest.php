<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Models\PaymentMethod;
use App\Rules\AccountExists;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Validation\Rule;

/**
 * @property int $amount
 * @property string $account_id
 * @property string|null $method_id
 */
class PostAuthorizeAndCapturePaymentRequest extends AbstractRequest
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
            'amount' => ['required', 'integer'],
            'account_id' => ['bail', 'required', 'uuid', $accountExists],
            'method_id' => ['bail', 'nullable', 'uuid',  Rule::exists(table: PaymentMethod::class, column: 'id')],
            'notes' => ['nullable', 'string'],
        ];
    }
}
