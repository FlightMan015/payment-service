<?php

declare(strict_types=1);

namespace App\Api\Requests;

/**
 * @property string|null $amount Payment amount.
 * @property string|null $check_date Payment check date.
 */
class PatchPaymentRequest extends AbstractRequest
{
    /**
     * @throws \Exception
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['integer', 'min:1'],
            'check_date' => ['date_format:Y-m-d'],
        ];
    }
}
