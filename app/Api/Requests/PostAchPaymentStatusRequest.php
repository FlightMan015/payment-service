<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Models\CRM\FieldOperations\Area;
use Illuminate\Validation\Rule;

/**
 * @property string $processed_at_from
 * @property string $processed_at_to
 * @property int[]|null $area_ids
 */
class PostAchPaymentStatusRequest extends FilterRequest
{
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'processed_at_from' => ['required', 'date_format:Y-m-d H:i:s', 'before:processed_at_to'],
            'processed_at_to' => ['required', 'date_format:Y-m-d H:i:s', 'after:processed_at_from'],
            'area_ids' => ['nullable', 'array', 'min:1'],
            'area_ids.*' => [
                'integer',
                Rule::exists(table: Area::class, column: 'id')->where('is_active', true)
            ],
        ]);
    }
}
