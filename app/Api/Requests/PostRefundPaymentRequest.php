<?php

declare(strict_types=1);

namespace App\Api\Requests;

/**
 * @property int|null $amount Amount in cents that user wants to refund. Null for refund all
 */
class PostRefundPaymentRequest extends AbstractRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @throws \Exception
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'gt:0'],
        ];
    }
}
