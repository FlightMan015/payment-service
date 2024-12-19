<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScheduledPaymentStatusFactory;
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
 * @method static ScheduledPaymentStatusFactory factory($count = null, $state = [])
 * @method static Builder|ScheduledPaymentStatus newModelQuery()
 * @method static Builder|ScheduledPaymentStatus newQuery()
 * @method static Builder|ScheduledPaymentStatus onlyTrashed()
 * @method static Builder|ScheduledPaymentStatus query()
 * @method static Builder|ScheduledPaymentStatus whereCreatedAt($value)
 * @method static Builder|ScheduledPaymentStatus whereCreatedBy($value)
 * @method static Builder|ScheduledPaymentStatus whereDeletedAt($value)
 * @method static Builder|ScheduledPaymentStatus whereDeletedBy($value)
 * @method static Builder|ScheduledPaymentStatus whereDescription($value)
 * @method static Builder|ScheduledPaymentStatus whereId($value)
 * @method static Builder|ScheduledPaymentStatus whereName($value)
 * @method static Builder|ScheduledPaymentStatus whereUpdatedAt($value)
 * @method static Builder|ScheduledPaymentStatus whereUpdatedBy($value)
 * @method static Builder|ScheduledPaymentStatus withTrashed()
 * @method static Builder|ScheduledPaymentStatus withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ScheduledPaymentStatus extends Model
{
    /** @use HasFactory<ScheduledPaymentStatusFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'billing.scheduled_payment_statuses';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

}
