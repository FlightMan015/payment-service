<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Models\AccountUpdaterAttempt;

interface AccountUpdaterAttemptRepository
{
    /**
     * Create account updater attempt with given attributes
     *
     * @param array $attributes
     *
     * @return AccountUpdaterAttempt
     */
    public function create(array $attributes): AccountUpdaterAttempt;

    /**
     * @param string $uuid
     *
     * @return AccountUpdaterAttempt|null
     */
    public function find(string $uuid): AccountUpdaterAttempt|null;

    /**
     * @param string $relation
     * @param callable $callback
     *
     * @return AccountUpdaterAttempt|null
     */
    public function firstWhereHasRelation(string $relation, callable $callback): AccountUpdaterAttempt|null;

    /**
     * @param mixed $relation
     * @param mixed $id
     * @param array $attributes
     *
     * @return int
     */
    public function updateExistingPivot(mixed $relation, mixed $id, array $attributes): int;
}
