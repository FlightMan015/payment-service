<?php

declare(strict_types=1);

namespace App\Api\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SortableTrait
{
    private const string DEFAULT_SORT_COLUMN = 'created_at';
    private const string SORT_DIRECTION_ASC = 'asc';
    private const string SORT_DIRECTION_DESC = 'desc';
    private const string DEFAULT_SORT_DIRECTION = self::SORT_DIRECTION_ASC;

    /**
     * @param array $filter
     * @param Builder<covariant Model>|Model $query
     *
     * @return Builder<Model>|Model
     */
    private function sort(Builder|Model $query, array $filter = []): Builder|Model
    {
        $query->orderByRaw(sprintf('(%s is null) asc', DB::getQueryGrammar()->wrap($this->getSortColumn($filter))));
        return $query->orderBy(column: $this->getSortColumn($filter), direction: $this->getSortDirection($filter));
    }

    private function getSortColumn(array $filter): string
    {
        if(isset($filter['sort'], $this->allowedSorts)) {
            $sortColumn = Str::after($filter['sort'], '.');
            if(in_array($sortColumn, $this->allowedSorts, true)) {
                return $filter['sort'];
            }
        }

        return $this->defaultSort ?? self::DEFAULT_SORT_COLUMN;
    }

    private function getSortDirection(array $filter): string
    {
        $sortOrder = self::DEFAULT_SORT_DIRECTION;

        if(isset($filter['direction'])) {
            $sortOrder = $filter['direction'];
        }

        if(isset($this->defaultSortDirection)) {
            $sortOrder = $this->defaultSortDirection;
        }

        $sortOrder = strtolower($sortOrder);

        if(!in_array($sortOrder, [
            self::SORT_DIRECTION_ASC,
            self::SORT_DIRECTION_DESC
        ], true)) {
            throw new \InvalidArgumentException(__('messages.sorting.invalid_order'));
        }

        return $sortOrder;
    }
}
