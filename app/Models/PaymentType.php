<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\PaymentType
 *
 * @property int $id
 * @property int|null $external_ref_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_hidden
 * @property bool $is_enabled
 * @property int $sort_order
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 *
 * @method static PaymentTypeFactory factory($count = null, $state = [])
 * @method static Builder|PaymentType newModelQuery()
 * @method static Builder|PaymentType newQuery()
 * @method static Builder|PaymentType query()
 * @method static Builder|PaymentType whereCreatedAt($value)
 * @method static Builder|PaymentType whereCreatedBy($value)
 * @method static Builder|PaymentType whereDeletedAt($value)
 * @method static Builder|PaymentType whereDeletedBy($value)
 * @method static Builder|PaymentType whereDescription($value)
 * @method static Builder|PaymentType whereExternalRefId($value)
 * @method static Builder|PaymentType whereId($value)
 * @method static Builder|PaymentType whereIsEnabled($value)
 * @method static Builder|PaymentType whereIsHidden($value)
 * @method static Builder|PaymentType whereName($value)
 * @method static Builder|PaymentType whereSortOrder($value)
 * @method static Builder|PaymentType whereUpdatedAt($value)
 * @method static Builder|PaymentType whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class PaymentType extends Model
{
    /** @use HasFactory<PaymentTypeFactory> */
    use HasFactory;

    protected $table = 'billing.payment_types';
    protected $guarded = ['id'];
    protected $casts = [
        'is_hidden' => 'boolean',
        'is_enabled' => 'boolean'
    ];
}
