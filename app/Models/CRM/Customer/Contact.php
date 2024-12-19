<?php

declare(strict_types=1);

namespace App\Models\CRM\Customer;

use Database\Factories\CRM\Customer\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Account\Models\Contact
 *
 * @property string $id
 * @property string|null $company_name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone1
 * @property string|null $phone2
 * @property int|null $pestroutes_customer_id
 * @property string|null $pestroutes_contact_type
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 *
 * @method static CRM\Customer\ContactFactory factory($count = null, $state = [])
 * @method static Builder|Contact newModelQuery()
 * @method static Builder|Contact newQuery()
 * @method static Builder|Contact onlyTrashed()
 * @method static Builder|Contact query()
 * @method static Builder|Contact whereCompanyName($value)
 * @method static Builder|Contact whereCreatedAt($value)
 * @method static Builder|Contact whereCreatedBy($value)
 * @method static Builder|Contact whereDeletedAt($value)
 * @method static Builder|Contact whereDeletedBy($value)
 * @method static Builder|Contact whereEmail($value)
 * @method static Builder|Contact whereFirstName($value)
 * @method static Builder|Contact whereId($value)
 * @method static Builder|Contact whereLastName($value)
 * @method static Builder|Contact wherePestroutesContactType($value)
 * @method static Builder|Contact wherePestroutesCustomerId($value)
 * @method static Builder|Contact wherePhone1($value)
 * @method static Builder|Contact wherePhone2($value)
 * @method static Builder|Contact whereUpdatedAt($value)
 * @method static Builder|Contact whereUpdatedBy($value)
 * @method static Builder|Contact withTrashed()
 * @method static Builder|Contact withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Contact extends Model
{
    use SoftDeletes;
    /** @use HasFactory<ContactFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'customer.contacts';

    protected $guarded = [
        'id',
    ];

    protected $appends = [
        'full_name',
    ];

    /**
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return implode(separator: ' ', array: array_filter([$this->first_name, $this->last_name]));
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }
}
