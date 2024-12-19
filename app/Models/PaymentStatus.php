<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\PaymentStatus
 *
 * @property int $id
 * @property int|null $external_ref_id
 * @property string $name
 * @property string|null $description
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 *
 * @method static Builder|PaymentStatus newModelQuery()
 * @method static Builder|PaymentStatus newQuery()
 * @method static Builder|PaymentStatus query()
 * @method static Builder|PaymentStatus whereCreatedAt($value)
 * @method static Builder|PaymentStatus whereCreatedBy($value)
 * @method static Builder|PaymentStatus whereDeletedAt($value)
 * @method static Builder|PaymentStatus whereDeletedBy($value)
 * @method static Builder|PaymentStatus whereDescription($value)
 * @method static Builder|PaymentStatus whereExternalRefId($value)
 * @method static Builder|PaymentStatus whereId($value)
 * @method static Builder|PaymentStatus whereName($value)
 * @method static Builder|PaymentStatus whereUpdatedAt($value)
 * @method static Builder|PaymentStatus whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class PaymentStatus extends Model
{
    protected $table = 'billing.payment_statuses';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];
}
