<?php

declare(strict_types=1);

namespace App\Models\CRM\ApiAccount;

use Database\Factories\CRM\ApiAccount\ApiAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\CRM\ApiAccount\ApiAccount
 *
 * @property string $id Same UUID as the Entity created in Fusion Auth
 * @property string $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static CRM\ApiAccount\ApiAccountFactory factory($count = null, $state = [])
 * @method static Builder|ApiAccount newModelQuery()
 * @method static Builder|ApiAccount newQuery()
 * @method static Builder|ApiAccount onlyTrashed()
 * @method static Builder|ApiAccount query()
 * @method static Builder|ApiAccount whereCreatedAt($value)
 * @method static Builder|ApiAccount whereCreatedBy($value)
 * @method static Builder|ApiAccount whereDeletedAt($value)
 * @method static Builder|ApiAccount whereDeletedBy($value)
 * @method static Builder|ApiAccount whereId($value)
 * @method static Builder|ApiAccount whereName($value)
 * @method static Builder|ApiAccount whereUpdatedAt($value)
 * @method static Builder|ApiAccount whereUpdatedBy($value)
 * @method static Builder|ApiAccount withTrashed()
 * @method static Builder|ApiAccount withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ApiAccount extends Model
{
    use HasUuids;
    use SoftDeletes;
    /** @use HasFactory<ApiAccountFactory> */
    use HasFactory;

    protected $table = 'organization.api_accounts';

    protected $fillable = [
        'id',
        'name',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}
