<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Helpers\RequestDefaultValuesTrait;

/**
 * @property int[]|null $area_ids
 */
class ProcessPaymentsRequest extends AbstractRequest
{
    use RequestDefaultValuesTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'area_ids' => null,
        ];
    }
}
