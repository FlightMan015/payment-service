<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Models\PaymentMethod;
use App\Rules\SubscriptionExists;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Validation\Rule;

/**
 * @property string $subscription_id
 * @property string|null $autopay_method_id
 */
class PatchSubscriptionAutopayStatusRequest extends AbstractRequest
{
    /**
     * @throws BindingResolutionException
     *
     * @return array<string, array<Rule|string>>
     */
    public function rules(): array
    {
        return [
            'subscription_id' => ['bail', 'required', 'uuid', app()->make(abstract: SubscriptionExists::class)],
            'autopay_method_id' => ['bail', 'nullable', 'uuid', Rule::exists(table: PaymentMethod::class, column: 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(input: ['subscription_id' => $this->route(param: 'subscriptionId')]);
    }
}
