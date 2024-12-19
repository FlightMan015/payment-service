<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GatewayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\Gateway
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property bool $is_hidden
 * @property bool $is_enabled
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 *
 * @method static GatewayFactory factory($count = null, $state = [])
 * @method static Builder|Gateway newModelQuery()
 * @method static Builder|Gateway newQuery()
 * @method static Builder|Gateway query()
 * @method static Builder|Gateway whereCreatedAt($value)
 * @method static Builder|Gateway whereCreatedBy($value)
 * @method static Builder|Gateway whereDeletedAt($value)
 * @method static Builder|Gateway whereDeletedBy($value)
 * @method static Builder|Gateway whereDescription($value)
 * @method static Builder|Gateway whereId($value)
 * @method static Builder|Gateway whereIsEnabled($value)
 * @method static Builder|Gateway whereIsHidden($value)
 * @method static Builder|Gateway whereName($value)
 * @method static Builder|Gateway whereUpdatedAt($value)
 * @method static Builder|Gateway whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class Gateway extends Model
{
    /** @use HasFactory<GatewayFactory> */
    use HasFactory;

    protected $table = 'billing.payment_gateways';
    protected $guarded = ['id'];
    protected $casts = [
        'is_hidden' => 'boolean',
        'is_enabled' => 'boolean'
    ];
}
