<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\CRM\Customer\Account;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\Traits\SodiumEncrypt;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\PaymentMethod
 *
 * @property string $id
 * @property int|null $external_ref_id
 * @property string $account_id
 * @property int $payment_gateway_id
 * @property int $payment_type_id
 * @property string|null $ach_account_number_encrypted
 * @property string|null $ach_routing_number
 * @property string|null $ach_account_type
 * @property string|null $ach_token
 * @property string|null $cc_token
 * @property int|null $cc_expiration_month
 * @property int|null $cc_expiration_year
 * @property string|null $name_on_account
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $email
 * @property string|null $city
 * @property string|null $province
 * @property string|null $postal_code
 * @property string|null $country_code
 * @property bool $is_primary
 * @property string|null $last_four
 * @property int|null $pestroutes_customer_id
 * @property int|null $pestroutes_created_by
 * @property int|null $pestroutes_payment_method_id
 * @property int|null $pestroutes_status_id
 * @property int|null $pestroutes_ach_account_type_id
 * @property int|null $pestroutes_ach_check_type_id
 * @property string|null $pestroutes_payment_hold_date
 * @property string|null $pestroutes_created_at
 * @property string|null $pestroutes_updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $ach_bank_name
 * @property string|null $cc_type
 * @property string|null $pestroutes_json
 * @property Carbon|null $payment_hold_date
 * @property string|null $pestroutes_metadata
 * @property string|null $pestroutes_data_link_alias
 * @property-read Account|null $account
 * @property-read Collection<int, \App\Models\AccountUpdaterAttempt> $accountUpdaterAttempts
 * @property-read int|null $account_updater_attempts_count
 * @property-read Gateway $gateway
 * @property-read bool $is_autopay
 * @property-read PaymentGatewayEnum $payment_gateway
 * @property-read Ledger|null $ledger
 * @property-read PaymentType $type
 *
 * @method static Builder|PaymentMethod autopayForAccount(string $accountId)
 * @method static PaymentMethodFactory factory($count = null, $state = [])
 * @method static Builder|PaymentMethod newModelQuery()
 * @method static Builder|PaymentMethod newQuery()
 * @method static Builder|PaymentMethod onlyTrashed()
 * @method static Builder|PaymentMethod primaryForAccount(string $accountId)
 * @method static Builder|PaymentMethod query()
 * @method static Builder|PaymentMethod whereAccountId($value)
 * @method static Builder|PaymentMethod whereAchAccountNumberEncrypted($value)
 * @method static Builder|PaymentMethod whereAchAccountType($value)
 * @method static Builder|PaymentMethod whereAchBankName($value)
 * @method static Builder|PaymentMethod whereAchRoutingNumber($value)
 * @method static Builder|PaymentMethod whereAchToken($value)
 * @method static Builder|PaymentMethod whereAddressLine1($value)
 * @method static Builder|PaymentMethod whereAddressLine2($value)
 * @method static Builder|PaymentMethod whereCcExpirationMonth($value)
 * @method static Builder|PaymentMethod whereCcExpirationYear($value)
 * @method static Builder|PaymentMethod whereCcToken($value)
 * @method static Builder|PaymentMethod whereCcType($value)
 * @method static Builder|PaymentMethod whereCity($value)
 * @method static Builder|PaymentMethod whereCountryCode($value)
 * @method static Builder|PaymentMethod whereCreatedAt($value)
 * @method static Builder|PaymentMethod whereCreatedBy($value)
 * @method static Builder|PaymentMethod whereDeletedAt($value)
 * @method static Builder|PaymentMethod whereDeletedBy($value)
 * @method static Builder|PaymentMethod whereEmail($value)
 * @method static Builder|PaymentMethod whereExternalRefId($value)
 * @method static Builder|PaymentMethod whereId($value)
 * @method static Builder|PaymentMethod whereIsPrimary($value)
 * @method static Builder|PaymentMethod whereLastFour($value)
 * @method static Builder|PaymentMethod whereNameOnAccount($value)
 * @method static Builder|PaymentMethod wherePaymentGatewayId($value)
 * @method static Builder|PaymentMethod wherePaymentHoldDate($value)
 * @method static Builder|PaymentMethod wherePaymentTypeId($value)
 * @method static Builder|PaymentMethod wherePestroutesAchAccountTypeId($value)
 * @method static Builder|PaymentMethod wherePestroutesAchCheckTypeId($value)
 * @method static Builder|PaymentMethod wherePestroutesCreatedAt($value)
 * @method static Builder|PaymentMethod wherePestroutesCreatedBy($value)
 * @method static Builder|PaymentMethod wherePestroutesCustomerId($value)
 * @method static Builder|PaymentMethod wherePestroutesDataLinkAlias($value)
 * @method static Builder|PaymentMethod wherePestroutesJson($value)
 * @method static Builder|PaymentMethod wherePestroutesMetadata($value)
 * @method static Builder|PaymentMethod wherePestroutesPaymentHoldDate($value)
 * @method static Builder|PaymentMethod wherePestroutesPaymentMethodId($value)
 * @method static Builder|PaymentMethod wherePestroutesStatusId($value)
 * @method static Builder|PaymentMethod wherePestroutesUpdatedAt($value)
 * @method static Builder|PaymentMethod wherePostalCode($value)
 * @method static Builder|PaymentMethod whereProvince($value)
 * @method static Builder|PaymentMethod whereUpdatedAt($value)
 * @method static Builder|PaymentMethod whereUpdatedBy($value)
 * @method static Builder|PaymentMethod withTrashed()
 * @method static Builder|PaymentMethod withoutTrashed()
 *
 * @mixin \Eloquent
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;
    use SodiumEncrypt;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'billing.payment_methods';
    protected $guarded = ['id'];
    protected array $encryptable = ['ach_account_number_encrypted'];
    protected $casts = [
        'is_anonymized' => 'boolean',
        'payment_hold_date' => 'datetime:Y-m-d',
        'is_pestroutes_auto_pay_enabled' => 'boolean',
    ];

    /**
     * Get the gateway the payment method belongs to
     *
     * @return BelongsTo<Gateway, PaymentMethod>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(related: Gateway::class, foreignKey: 'payment_gateway_id', ownerKey: 'id');
    }

    /**
     * Get the payment type the payment method belongs to
     *
     * @return BelongsTo<PaymentType, PaymentMethod>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(related: PaymentType::class, foreignKey: 'payment_type_id');
    }

    /**
     * @return BelongsTo<Account, PaymentMethod>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class);
    }

    /**
     * Build a query for getting primary payment method for given account id. Returns latest created valid payment
     * method.
     *
     * @param Builder<PaymentMethod> $builder
     * @param string $accountId
     *
     * @return void
     */
    public function scopePrimaryForAccount(Builder $builder, string $accountId): void
    {
        $builder->whereAccountId($accountId)->whereIsPrimary(true);
    }

    /**
     * Build a query for getting autopay payment method for given account id.
     *
     * @param Builder<PaymentMethod> $builder
     * @param string $accountId
     *
     * @return void
     */
    public function scopeAutopayForAccount(Builder $builder, string $accountId): void
    {
        $builder->whereHas(relation: 'ledger', callback: static function (Builder|Ledger $ledgerBuilder) use ($accountId) {
            $ledgerBuilder->whereAccountId($accountId);
        });
    }

    /**
     * @param \DateTimeInterface $processingDateTime
     *
     * @return bool
     */
    public function hasValidHoldDate(\DateTimeInterface $processingDateTime = new \DateTime()): bool
    {
        if (is_null($this->payment_hold_date)) {
            return true;
        }

        $processingDate = Carbon::instance(date: $processingDateTime)->startOfDay();
        $paymentHoldDate = Carbon::parse(time: $this->payment_hold_date)->startOfDay();

        return $paymentHoldDate->lt(date: $processingDate);
    }

    /**
     * @return void
     */
    public function makePrimary(): void
    {
        // mark all methods except for the current as non primary
        self::whereAccountId($this->account_id)->whereNot(column: 'id', operator: '=', value: $this->id)->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    /**
     * @return void
     */
    public function unsetPrimary(): void
    {
        $this->is_primary = false;
        $this->save();
    }

    /**
     * @return string[] attribute => name
     */
    public static function requiredAttributes(): array
    {
        return [
            'name_on_account_first' => 'First name',
            'name_on_account_last' => 'Last name',
            'address_line1' => 'Address line 1',
            'email' => 'Email',
            'city' => 'City',
            'province' => 'Province',
            'postal_code' => 'Postal code',
            'country_code' => 'Country code',
        ];
    }

    /**
     * Get all the account updater attempts for the Payment Method
     *
     * @return BelongsToMany<AccountUpdaterAttempt>
     */
    public function accountUpdaterAttempts(): BelongsToMany
    {
        return $this->belongsToMany(
            related: AccountUpdaterAttempt::class,
            table: 'billing.account_updater_attempts_methods',
            foreignPivotKey: 'payment_method_id',
            relatedPivotKey: 'attempt_id'
        );
    }

    /**
     * @return HasOne<Ledger>
     */
    public function ledger(): HasOne
    {
        return $this->hasOne(related: Ledger::class, foreignKey: 'autopay_payment_method_id');
    }

    /**
     * @return bool
     */
    public function getIsAutopayAttribute(): bool
    {
        return $this->ledger()->where(column: 'account_id', operator: '=', value: $this->account_id)->exists();
    }

    /**
     * @return PaymentGatewayEnum
     */
    public function getPaymentGatewayAttribute(): PaymentGatewayEnum
    {
        return PaymentGatewayEnum::from($this->payment_gateway_id);
    }

    /**
     * @return bool
     */
    public function isRealGatewayPaymentMethod(): bool
    {
        return $this->payment_gateway->isRealGateway();
    }
}
