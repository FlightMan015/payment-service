<?php

declare(strict_types=1);

namespace App\Models\CRM\FieldOperations;

use Database\Factories\CRM\FieldOperations\AreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Area\Models\Area
 *
 * @property int $id
 * @property int|null $external_ref_id
 * @property int|null $market_id
 * @property string $name
 * @property bool $is_active
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $timezone
 * @property string|null $license_number
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $zip
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $caution_statements
 * @property-read string $full_address
 *
 * @method static Builder|Area active()
 * @method static CRM\FieldOperations\AreaFactory factory($count = null, $state = [])
 * @method static Builder|Area newModelQuery()
 * @method static Builder|Area newQuery()
 * @method static Builder|Area onlyTrashed()
 * @method static Builder|Area query()
 * @method static Builder|Area whereAddress($value)
 * @method static Builder|Area whereCautionStatements($value)
 * @method static Builder|Area whereCity($value)
 * @method static Builder|Area whereCreatedAt($value)
 * @method static Builder|Area whereCreatedBy($value)
 * @method static Builder|Area whereDeletedAt($value)
 * @method static Builder|Area whereDeletedBy($value)
 * @method static Builder|Area whereEmail($value)
 * @method static Builder|Area whereExternalRefId($value)
 * @method static Builder|Area whereId($value)
 * @method static Builder|Area whereIsActive($value)
 * @method static Builder|Area whereLicenseNumber($value)
 * @method static Builder|Area whereMarketId($value)
 * @method static Builder|Area whereName($value)
 * @method static Builder|Area wherePhone($value)
 * @method static Builder|Area whereState($value)
 * @method static Builder|Area whereTimezone($value)
 * @method static Builder|Area whereUpdatedAt($value)
 * @method static Builder|Area whereUpdatedBy($value)
 * @method static Builder|Area whereWebsite($value)
 * @method static Builder|Area whereZip($value)
 * @method static Builder|Area withTrashed()
 * @method static Builder|Area withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Area extends Model
{
    use SoftDeletes;
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $table = 'field_operations.areas';

    protected $fillable = [
        'market_id',
        'name',
        'is_active',
        'timezone',
        'license_number',
        'address',
        'city',
        'state',
        'zip',
        'phone',
        'email',
        'website',
        'caution_statements',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'full_address'
    ];

    /**
     * @return string
     */
    public function getFullAddressAttribute(): string
    {
        $address = [
            $this->city,
            trim($this->state . ' ' . $this->zip)
        ];

        return implode(', ', array_filter($address));
    }

    /**
     * Scope a query to only include active areas.
     *
     * @param Builder<Area> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return AreaFactory
     */
    protected static function newFactory(): AreaFactory
    {
        return AreaFactory::new();
    }
}
