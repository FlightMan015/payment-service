<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use App\Rules\AccountExists;
use Illuminate\Validation\Rule;

/**
 * @property-read string $account_id CRM Account ID for whom the payment is being added.
 * @property-read int $amount Amount value for payment.
 * @property-read string $method_id Payment method ID.
 * @property-read int $trigger_id Scheduled payment trigger ID.
 * @property-read array|null $metadata Additional metadata for the payment (subscription_id...).
 */
class PostScheduledPaymentRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['bail', 'required', 'uuid', app()->make(AccountExists::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'method_id' => ['required', 'uuid', Rule::exists(table: PaymentMethod::class, column: 'id')],
            'trigger_id' => ['bail', 'required', 'integer', Rule::in(ScheduledPaymentTriggerEnum::cases())],
            'metadata' => ['array'],
        ];
    }
}
