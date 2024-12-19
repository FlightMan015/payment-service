<?php

declare(strict_types=1);

namespace App\Models\CRM\Organization;

use Database\Factories\CRM\Dealer\DealerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Dealer\Models\Dealer
 *
 * @property int $id
 * @property string $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static CRM\Dealer\DealerFactory factory($count = null, $state = [])
 * @method static Builder|Dealer newModelQuery()
 * @method static Builder|Dealer newQuery()
 * @method static Builder|Dealer onlyTrashed()
 * @method static Builder|Dealer query()
 * @method static Builder|Dealer whereCreatedAt($value)
 * @method static Builder|Dealer whereCreatedBy($value)
 * @method static Builder|Dealer whereDeletedAt($value)
 * @method static Builder|Dealer whereDeletedBy($value)
 * @method static Builder|Dealer whereId($value)
 * @method static Builder|Dealer whereName($value)
 * @method static Builder|Dealer whereUpdatedAt($value)
 * @method static Builder|Dealer whereUpdatedBy($value)
 * @method static Builder|Dealer withTrashed()
 * @method static Builder|Dealer withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Dealer extends Model
{
    use SoftDeletes;
    /** @use HasFactory<DealerFactory> */
    use HasFactory;

    protected $table = 'organization.dealers';

    protected $guarded = ['id'];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): DealerFactory
    {
        return DealerFactory::new();
    }
}
