<?php

declare(strict_types=1);

namespace Database\Factories\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @phpstan-require-extends Factory
 */
trait WithRelationships
{
    use WithoutRelationships;

    /**
     * @param array $attributes
     * @param array<string, array|Model|null> $relationships
     *
     * @return Model|Collection<int, Model>
     */
    public function makeWithRelationships(array $attributes = [], array $relationships = []): Collection|Model
    {
        return $this
            ->withoutRelationships()  // To avoid creating db records for relationships that are not defined in $relationships array
            ->afterMaking(function (Model $model) use ($relationships) {
                foreach ($relationships as $relationship => $factory) {
                    if (!method_exists($model, $relationship)) {
                        continue;
                    }

                    $relationshipInstance = $model->$relationship();
                    if ($relationshipInstance instanceof BelongsTo) {
                        $model->$relationship()->associate($factory);
                        if (!is_null($factory)) {
                            $model->{$relationshipInstance->getForeignKeyName()} = $factory->id;
                        }
                    }

                    if ($relationshipInstance instanceof HasOne) {
                        $model->setRelation(relation: $relationship, value: $factory);
                    }

                    if ($relationshipInstance instanceof HasMany) {
                        $model->setRelation(
                            relation: $relationship,
                            value: $this->convertItemsToCollection(items: $factory)
                        );
                    }
                }

                return $model;
            })
            ->make(attributes: $attributes);
    }

    private function convertItemsToCollection(mixed $items): Collection
    {
        $collection = new Collection();
        if (is_array($items)) {
            foreach ($items as $item) {
                $collection->add($item);
            }
        } elseif ($items instanceof Collection) {
            $collection = $items;
        } else {
            $collection->add($items);
        }

        return $collection;
    }
}
