<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\SuspendReason
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder|SuspendReason newModelQuery()
 * @method static Builder|SuspendReason newQuery()
 * @method static Builder|SuspendReason onlyTrashed()
 * @method static Builder|SuspendReason query()
 * @method static Builder|SuspendReason whereCreatedAt($value)
 * @method static Builder|SuspendReason whereCreatedBy($value)
 * @method static Builder|SuspendReason whereDeletedAt($value)
 * @method static Builder|SuspendReason whereDeletedBy($value)
 * @method static Builder|SuspendReason whereDescription($value)
 * @method static Builder|SuspendReason whereId($value)
 * @method static Builder|SuspendReason whereName($value)
 * @method static Builder|SuspendReason whereUpdatedAt($value)
 * @method static Builder|SuspendReason whereUpdatedBy($value)
 * @method static Builder|SuspendReason withTrashed()
 * @method static Builder|SuspendReason withoutTrashed()
 *
 * @mixin \Eloquent
 */
class SuspendReason extends Model
{
    use SoftDeletes;

    protected $table = 'billing.suspend_reasons';
    protected $guarded = ['id'];
}
