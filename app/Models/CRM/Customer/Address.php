<?php

declare(strict_types=1);

namespace App\Models\CRM\Customer;

use Database\Factories\CRM\Customer\AddressFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Account\Models\Address
 *
 * @property string $id
 * @property string|null $address {"pestroutes_column_name": "address"}
 * @property string|null $city {"pestroutes_column_name": "city"}
 * @property string|null $state {"pestroutes_column_name": "state"}
 * @property string|null $postal_code
 * @property string|null $country
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $pestroutes_customer_id {"pestroutes_column_name": "customerID"}
 * @property string|null $pestroutes_address_type
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Account|null $account
 * @property-read string $full_address
 *
 * @method static CRM\Customer\AddressFactory factory($count = null, $state = [])
 * @method static Builder|Address newModelQuery()
 * @method static Builder|Address newQuery()
 * @method static Builder|Address onlyTrashed()
 * @method static Builder|Address query()
 * @method static Builder|Address whereAddress($value)
 * @method static Builder|Address whereCity($value)
 * @method static Builder|Address whereCountry($value)
 * @method static Builder|Address whereCreatedAt($value)
 * @method static Builder|Address whereCreatedBy($value)
 * @method static Builder|Address whereDeletedAt($value)
 * @method static Builder|Address whereDeletedBy($value)
 * @method static Builder|Address whereId($value)
 * @method static Builder|Address whereLatitude($value)
 * @method static Builder|Address whereLongitude($value)
 * @method static Builder|Address wherePestroutesAddressType($value)
 * @method static Builder|Address wherePestroutesCustomerId($value)
 * @method static Builder|Address wherePostalCode($value)
 * @method static Builder|Address whereState($value)
 * @method static Builder|Address whereUpdatedAt($value)
 * @method static Builder|Address whereUpdatedBy($value)
 * @method static Builder|Address withTrashed()
 * @method static Builder|Address withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Address extends Model
{
    use SoftDeletes;
    /** @use HasFactory<AddressFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'customer.addresses';

    protected $guarded = [
        'id',
    ];

    protected $appends = [
        'full_address'
    ];

    /**
     * @return HasOne<Account>
     */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    /**
     * @return string
     */
    public function getFullAddressAttribute(): string
    {
        return sprintf(
            '%s %s, %s %s',
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code
        );
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }
}
