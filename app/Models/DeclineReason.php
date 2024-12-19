<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeclineReasonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\DeclineReason
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property bool $is_reprocessable
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static DeclineReasonFactory factory($count = null, $state = [])
 * @method static Builder|DeclineReason newModelQuery()
 * @method static Builder|DeclineReason newQuery()
 * @method static Builder|DeclineReason onlyTrashed()
 * @method static Builder|DeclineReason query()
 * @method static Builder|DeclineReason whereCreatedAt($value)
 * @method static Builder|DeclineReason whereCreatedBy($value)
 * @method static Builder|DeclineReason whereDeletedAt($value)
 * @method static Builder|DeclineReason whereDeletedBy($value)
 * @method static Builder|DeclineReason whereDescription($value)
 * @method static Builder|DeclineReason whereId($value)
 * @method static Builder|DeclineReason whereIsReprocessable($value)
 * @method static Builder|DeclineReason whereName($value)
 * @method static Builder|DeclineReason whereUpdatedAt($value)
 * @method static Builder|DeclineReason whereUpdatedBy($value)
 * @method static Builder|DeclineReason withTrashed()
 * @method static Builder|DeclineReason withoutTrashed()
 *
 * @mixin \Eloquent
 */
class DeclineReason extends Model
{
    use SoftDeletes;
    /** @use HasFactory<DeclineReasonFactory> */
    use HasFactory;

    protected $table = 'billing.decline_reasons';
    protected $guarded = ['id'];
    protected $casts = ['is_reprocessable' => 'boolean'];
}
