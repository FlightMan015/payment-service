<?php

declare(strict_types=1);

namespace App\Api\Requests;

/**
 * @property string $account_id
 * @property string $subscription_id
 * @property string $date_from
 * @property string $date_to
 * @property int $total_from
 * @property int $total_to
 * @property int $balance_from
 * @property int $balance_to
 */
class InvoiceFilterRequest extends FilterRequest
{
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'account_id' => ['bail', 'uuid'],
            'subscription_id' => ['bail', 'uuid'],
            'date_from' => ['date_format:Y-m-d'],
            'date_to' => ['date_format:Y-m-d', 'after_or_equal:date_from'],
            'total_from' => ['integer', 'min:0'],
            'total_to' => ['integer', $this->has('total_from') ? 'gte:total_from' : 'min:1'],
            'balance_from' => ['integer', 'min:0'],
            'balance_to' => ['integer', $this->has('balance_from') ? 'gte:balance_from' : 'min:1'],
        ]);
    }
}
