<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/** @phpstan-require-extends Model */
trait PartiallyReplicableModel
{
    protected static function booted(): void
    {
        static::replicating(static function (self $model) {
            // there are no property or property is empty
            if (empty($model->ignoreWhenReplicating)) {
                return;
            }

            // reset the value of the property to null if it is in the ignoreWhenReplicating array
            foreach ($model->ignoreWhenReplicating as $field) {
                $model->{$field} = null;
            }
        });
    }
}
