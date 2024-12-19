<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Models\FailedJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FailedJobRepository
{
    /**
     * Retrieve list of failed jobs by conditions
     *
     * @param array $filter
     * @param array $columns
     *
     * @return LengthAwarePaginator<FailedJob>
     */
    public function filter(
        array $filter = [],
        array $columns = ['*'],
    ): LengthAwarePaginator;

    /**
     * Retrieve list of failed jobs (uuid) by conditions
     *
     * @param array $filter
     *
     * @return Collection<int, FailedJob>
     */
    public function getRetryFailedJobs(array $filter): Collection;
}
