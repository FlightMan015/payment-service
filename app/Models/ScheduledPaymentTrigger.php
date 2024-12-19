<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScheduledPaymentTriggerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static ScheduledPaymentTriggerFactory factory($count = null, $state = [])
 * @method static Builder|ScheduledPaymentTrigger newModelQuery()
 * @method static Builder|ScheduledPaymentTrigger newQuery()
 * @method static Builder|ScheduledPaymentTrigger onlyTrashed()
 * @method static Builder|ScheduledPaymentTrigger query()
 * @method static Builder|ScheduledPaymentTrigger whereCreatedAt($value)
 * @method static Builder|ScheduledPaymentTrigger whereCreatedBy($value)
 * @method static Builder|ScheduledPaymentTrigger whereDeletedAt($value)
 * @method static Builder|ScheduledPaymentTrigger whereDeletedBy($value)
 * @method static Builder|ScheduledPaymentTrigger whereDescription($value)
 * @method static Builder|ScheduledPaymentTrigger whereId($value)
 * @method static Builder|ScheduledPaymentTrigger whereName($value)
 * @method static Builder|ScheduledPaymentTrigger whereUpdatedAt($value)
 * @method static Builder|ScheduledPaymentTrigger whereUpdatedBy($value)
 * @method static Builder|ScheduledPaymentTrigger withTrashed()
 * @method static Builder|ScheduledPaymentTrigger withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ScheduledPaymentTrigger extends Model
{
    /** @use HasFactory<ScheduledPaymentTriggerFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'billing.scheduled_payment_triggers';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];
}
