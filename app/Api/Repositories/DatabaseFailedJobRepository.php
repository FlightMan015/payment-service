<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Repositories\Interface\FailedJobRepository;
use App\Api\Traits\SortableTrait;
use App\Models\FailedJob;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DatabaseFailedJobRepository implements FailedJobRepository
{
    use SortableTrait;

    private array $allowedSorts = [
        'failed_at',
        'created_at',
        'updated_at',
        'id',
        'connection',
        'queue',
    ];

    private string $defaultSort = 'failed_at';

    /**
     * @inheritDoc
     */
    public function filter(
        array $filter = [],
        array $columns = ['*'],
    ): LengthAwarePaginator {
        $query = FailedJob::select($columns);

        foreach ($filter as $key => $value) {
            $query = $this->filterBySingleKey(query: $query, key: $key, value: $value);
        }

        $this->sort(query: $query, filter: $filter);

        return $query->paginate(perPage: $filter['per_page'], page: $filter['page']);
    }

    /**
     * @param array $filter
     *
     * @return Collection<int, FailedJob>
     */
    public function getRetryFailedJobs(array $filter): Collection
    {
        $query = FailedJob::select(['uuid']);

        foreach ($filter as $key => $value) {
            $query = $this->filterBySingleKey(query: $query, key: $key, value: $value);
        }

        return $query->get();
    }

    private function filterBySingleKey(
        Builder|FailedJob $query,
        string $key,
        mixed $value
    ): Builder|FailedJob {
        return match ($key) {
            'job_ids' => $query->whereIn('uuid', is_array($value) ? $value : []),
            'queue' => $query->where('queue', 'like', "%$value%"),
            default => $query,
        };
    }
}
