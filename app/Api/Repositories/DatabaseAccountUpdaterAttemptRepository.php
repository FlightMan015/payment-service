<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Repositories\Interface\AccountUpdaterAttemptRepository;
use App\Models\AccountUpdaterAttempt;

class DatabaseAccountUpdaterAttemptRepository implements AccountUpdaterAttemptRepository
{
    /** @inheritDoc */
    public function create(array $attributes): AccountUpdaterAttempt
    {
        return AccountUpdaterAttempt::create(attributes: $attributes);
    }

    /** @inheritDoc */
    public function find(string $uuid): AccountUpdaterAttempt|null
    {
        return AccountUpdaterAttempt::find($uuid);
    }

    /** @inheritDoc */
    public function firstWhereHasRelation(string $relation, callable $callback): AccountUpdaterAttempt|null
    {
        return AccountUpdaterAttempt::whereHas(relation: $relation, callback: $callback)->oldest()->first();
    }

    /** @inheritDoc */
    public function updateExistingPivot(mixed $relation, mixed $id, array $attributes): int
    {
        return $relation->updateExistingPivot(id: $id, attributes: array_merge($attributes, ['updated_at' => now()]));
    }
}
