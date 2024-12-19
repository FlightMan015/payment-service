<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Helpers\RequestDefaultValuesTrait;

/**
 * @property int|null $page
 * @property int|null $per_page
 */
class FilterRequest extends AbstractRequest
{
    use RequestDefaultValuesTrait;

    protected const int DEFAULT_LIMIT_PER_PAGE = 100;
    protected const int DEFAULT_PAGE = 1;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'per_page' => self::DEFAULT_LIMIT_PER_PAGE,
            'page' => self::DEFAULT_PAGE,
        ];
    }
}
