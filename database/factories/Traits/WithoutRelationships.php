<?php

declare(strict_types=1);

namespace Database\Factories\Traits;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * This trait helps us to fix the issue when we need to use the factory inside other factories and want to use ::make()
 * method without actually persisting the relationships into database.
 *
 * Some details could be found below
 * Laravel GitHub issue: https://github.com/laravel/framework/issues/41313
 * Proposed solution: https://github.com/laravel/framework/discussions/36169#discussioncomment-4207175
 *
 * @phpstan-require-extends Factory
 */
trait WithoutRelationships
{
    public function withoutRelationships(): static
    {
        return $this->state(function () {
            return collect($this->definition())
                ->filter(static fn ($value) => $value instanceof Factory)
                ->mapWithKeys(static fn ($item, $key) => [$key => null])
                ->toArray();
        });
    }
}
