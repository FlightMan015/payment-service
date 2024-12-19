<?php

declare(strict_types=1);

namespace App\Models\CRM\Customer;

use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountException;
use App\Models\CRM\FieldOperations\Area;
use App\Models\CRM\Organization\Dealer;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Database\Factories\CRM\Customer\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\CRM\Customer\Account
 *
 * @property string $id
 * @property int|null $external_ref_id
 * @property int|null $area_id
 * @property int $dealer_id
 * @property string $contact_id
 * @property string $billing_contact_id
 * @property string $service_address_id
 * @property string $billing_address_id
 * @property bool $is_active
 * @property string|null $source
 * @property string|null $autopay_type
 * @property bool|null $paid_in_full
 * @property int|null $balance
 * @property int|null $balance_age
 * @property int|null $responsible_balance
 * @property int|null $responsible_balance_age
 * @property int|null $preferred_billing_day_of_month
 * @property Carbon|null $payment_hold_date
 * @property string|null $most_recent_credit_card_last_four
 * @property string|null $most_recent_credit_card_exp_date
 * @property bool|null $sms_reminders
 * @property bool|null $phone_reminders
 * @property bool|null $email_reminders
 * @property float|null $tax_rate {"pestroutes_column_name": "taxRate"}
 * @property int|null $pestroutes_created_by
 * @property int|null $pestroutes_source_id
 * @property int|null $pestroutes_master_account
 * @property int|null $pestroutes_preferred_tech_id
 * @property string|null $pestroutes_customer_link
 * @property string|null $pestroutes_created_at
 * @property string|null $pestroutes_cancelled_at
 * @property string|null $pestroutes_updated_at
 * @property string|null $pestroutes_json
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $status
 * @property string|null $pestroutes_metadata
 * @property string|null $pestroutes_data_link_alias
 * @property bool|null $receive_invoices_by_mail
 * @property-read Address $address
 * @property-read Area|null $area
 * @property-read Address $billingAddress
 * @property-read Contact $billingContact
 * @property-read Contact $contact
 * @property-read Dealer $dealer
 * @property-read Collection<int, Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read Ledger|null $ledger
 * @property-read Collection<int, PaymentMethod> $paymentMethods
 * @property-read int|null $payment_methods_count
 * @property-read Collection<int, Payment> $payments
 * @property-read int|null $payments_count
 * @property-read Address $serviceAddress
 *
 * @method static CRM\Customer\AccountFactory factory($count = null, $state = [])
 * @method static Builder|Account newModelQuery()
 * @method static Builder|Account newQuery()
 * @method static Builder|Account onlyTrashed()
 * @method static Builder|Account query()
 * @method static Builder|Account whereAreaId($value)
 * @method static Builder|Account whereAutopayType($value)
 * @method static Builder|Account whereBalance($value)
 * @method static Builder|Account whereBalanceAge($value)
 * @method static Builder|Account whereBillingAddressId($value)
 * @method static Builder|Account whereBillingContactId($value)
 * @method static Builder|Account whereContactId($value)
 * @method static Builder|Account whereCreatedAt($value)
 * @method static Builder|Account whereCreatedBy($value)
 * @method static Builder|Account whereDealerId($value)
 * @method static Builder|Account whereDeletedAt($value)
 * @method static Builder|Account whereDeletedBy($value)
 * @method static Builder|Account whereEmailReminders($value)
 * @method static Builder|Account whereExternalRefId($value)
 * @method static Builder|Account whereId($value)
 * @method static Builder|Account whereIsActive($value)
 * @method static Builder|Account whereMostRecentCreditCardExpDate($value)
 * @method static Builder|Account whereMostRecentCreditCardLastFour($value)
 * @method static Builder|Account wherePaidInFull($value)
 * @method static Builder|Account wherePaymentHoldDate($value)
 * @method static Builder|Account wherePestroutesCancelledAt($value)
 * @method static Builder|Account wherePestroutesCreatedAt($value)
 * @method static Builder|Account wherePestroutesCreatedBy($value)
 * @method static Builder|Account wherePestroutesCustomerLink($value)
 * @method static Builder|Account wherePestroutesDataLinkAlias($value)
 * @method static Builder|Account wherePestroutesJson($value)
 * @method static Builder|Account wherePestroutesMasterAccount($value)
 * @method static Builder|Account wherePestroutesMetadata($value)
 * @method static Builder|Account wherePestroutesPreferredTechId($value)
 * @method static Builder|Account wherePestroutesSourceId($value)
 * @method static Builder|Account wherePestroutesUpdatedAt($value)
 * @method static Builder|Account wherePhoneReminders($value)
 * @method static Builder|Account wherePreferredBillingDayOfMonth($value)
 * @method static Builder|Account whereReceiveInvoicesByMail($value)
 * @method static Builder|Account whereResponsibleBalance($value)
 * @method static Builder|Account whereResponsibleBalanceAge($value)
 * @method static Builder|Account whereServiceAddressId($value)
 * @method static Builder|Account whereSmsReminders($value)
 * @method static Builder|Account whereSource($value)
 * @method static Builder|Account whereStatus($value)
 * @method static Builder|Account whereTaxRate($value)
 * @method static Builder|Account whereUpdatedAt($value)
 * @method static Builder|Account whereUpdatedBy($value)
 * @method static Builder|Account withTrashed()
 * @method static Builder|Account withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Account extends Model
{
    use SoftDeletes;
    use HasUuids;
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $table = 'customer.accounts';

    protected $keyType = 'uuid';

    protected $guarded = [
        'id',
        'balance',
        'balance_age',
        'responsible_balance',
        'responsible_balance_age',
        'most_recent_credit_card_last_four',
        'most_recent_credit_card_exp_date'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'paid_in_full' => 'boolean',
        'sms_reminders' => 'boolean',
        'phone_reminders' => 'boolean',
        'email_reminders' => 'boolean',

        'payment_hold_date' => 'date',
    ];

    /**
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(related: Payment::class, foreignKey: 'account_id', localKey: 'id');
    }

    /**
     * @return HasMany<PaymentMethod>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(related: PaymentMethod::class);
    }

    /**
     * @return HasMany<Invoice>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return BelongsTo<Area, Account>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(related: Area::class);
    }

    /**
     * @return BelongsTo<Dealer, Account>
     */
    public function dealer(): BelongsTo
    {
        return $this->belongsTo(related: Dealer::class);
    }

    /**
     * @return BelongsTo<Contact, Account>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(related: Contact::class);
    }

    /**
     * @return BelongsTo<Contact, Account>
     */
    public function billingContact(): BelongsTo
    {
        return $this->belongsTo(related: Contact::class, foreignKey: 'billing_contact_id');
    }

    /**
     * @return BelongsTo<Address, Account>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(related: Address::class, foreignKey: 'service_address_id');
    }

    /**
     * @return BelongsTo<Address, Account>
     */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(related: Address::class, foreignKey: 'billing_address_id');
    }

    /**
     * @return HasOne<Ledger>
     */
    public function ledger(): HasOne
    {
        return $this->hasOne(related: Ledger::class, foreignKey: 'account_id');
    }

    /**
     * @return BelongsTo<Address, Account>
     */
    public function serviceAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @param PaymentMethod|null $autopayPaymentMethod
     *
     * @return bool
     */
    public function setAutoPayPaymentMethod(PaymentMethod|null $autopayPaymentMethod): bool
    {
        if (!is_null($autopayPaymentMethod) && $this->id !== $autopayPaymentMethod->account_id) {
            throw new PaymentMethodDoesNotBelongToAccountException(
                paymentMethodId: $autopayPaymentMethod->id,
                accountId: $this->id
            );
        }

        $this->ledger()->updateOrCreate(
            attributes: ['account_id' => $this->id],
            values: ['autopay_payment_method_id' => $autopayPaymentMethod?->id]
        );

        return true;
    }

    /**
     * @param int $balance
     *
     * @return bool
     */
    public function setLedgerBalance(int $balance): bool
    {
        $this->ledger()->updateOrCreate(
            attributes: ['account_id' => $this->id],
            values: ['balance' => $balance]
        );

        return true;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AccountFactory
    {
        return AccountFactory::new();
    }
}
