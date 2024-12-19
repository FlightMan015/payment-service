<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\CRM\Customer\Account;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Aptive\Attribution\Traits\WithAttributes;
use Aptive\Component\Money\MoneyHandler;
use Database\Factories\ScheduledPaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Money\Currency;
use Money\Money;

/**
 * @property string $id
 * @property string $account_id
 * @property string $payment_method_id
 * @property int $trigger_id
 * @property int $status_id
 * @property object $metadata Metadata for processing the scheduled payment by the trigger (e.g. subscription_id, appointment_id, etc)
 * @property int $amount
 * @property string|null $payment_id
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Account $account
 * @property-read ScheduledPaymentStatusEnum $payment_status
 * @property-read ScheduledPaymentTriggerEnum $payment_trigger
 * @property-read Payment|null $payment
 * @property-read PaymentMethod $paymentMethod
 * @property-read ScheduledPaymentStatus $status
 * @property-read ScheduledPaymentTrigger $trigger
 *
 * @method static ScheduledPaymentFactory factory($count = null, $state = [])
 * @method static Builder|ScheduledPayment newModelQuery()
 * @method static Builder|ScheduledPayment newQuery()
 * @method static Builder|ScheduledPayment onlyTrashed()
 * @method static Builder|ScheduledPayment query()
 * @method static Builder|ScheduledPayment whereAccountId($value)
 * @method static Builder|ScheduledPayment whereAmount($value)
 * @method static Builder|ScheduledPayment whereCreatedAt($value)
 * @method static Builder|ScheduledPayment whereCreatedBy($value)
 * @method static Builder|ScheduledPayment whereDeletedAt($value)
 * @method static Builder|ScheduledPayment whereDeletedBy($value)
 * @method static Builder|ScheduledPayment whereId($value)
 * @method static Builder|ScheduledPayment whereMetadata($value)
 * @method static Builder|ScheduledPayment wherePaymentId($value)
 * @method static Builder|ScheduledPayment wherePaymentMethodId($value)
 * @method static Builder|ScheduledPayment whereStatusId($value)
 * @method static Builder|ScheduledPayment whereTriggerId($value)
 * @method static Builder|ScheduledPayment whereUpdatedAt($value)
 * @method static Builder|ScheduledPayment whereUpdatedBy($value)
 * @method static Builder|ScheduledPayment withTrashed()
 * @method static Builder|ScheduledPayment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ScheduledPayment extends Model
{
    /** @use HasFactory<ScheduledPaymentFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use WithAttributes;

    protected $table = 'billing.scheduled_payments';
    protected $guarded = ['id'];
    protected $casts = [
        'metadata' => 'object',
    ];

    /**
     * Get the status associated with the ScheduledPayment
     *
     * @return BelongsTo<ScheduledPaymentStatus, ScheduledPayment>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(related: ScheduledPaymentStatus::class, foreignKey: 'status_id', ownerKey: 'id');
    }

    /**
     * Get the trigger associated with the ScheduledPayment
     *
     * @return BelongsTo<ScheduledPaymentTrigger, ScheduledPayment>
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(related: ScheduledPaymentTrigger::class, foreignKey: 'trigger_id', ownerKey: 'id');
    }

    /**
     * Get the method the scheduled payment belongs to
     *
     * @return BelongsTo<PaymentMethod, ScheduledPayment>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(related: PaymentMethod::class, foreignKey: 'payment_method_id')->withTrashed();
    }

    /**
     * Get the account the scheduled payment belongs to
     *
     * @return BelongsTo<Account, ScheduledPayment>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class, foreignKey: 'account_id')->withTrashed();
    }

    /**
     * @return HasOne<Payment>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(related: Payment::class, foreignKey: 'payment_id');
    }

    /**
     * @return ScheduledPaymentTriggerEnum
     */
    public function getPaymentTriggerAttribute(): ScheduledPaymentTriggerEnum
    {
        return ScheduledPaymentTriggerEnum::from($this->trigger_id);
    }

    /**
     * @return ScheduledPaymentStatusEnum
     */
    public function getPaymentStatusAttribute(): ScheduledPaymentStatusEnum
    {
        return ScheduledPaymentStatusEnum::from($this->status_id);
    }

    /**
     * @throws \Exception
     *
     * @return float
     */
    public function getDecimalAmount(): float
    {
        return (float) (new MoneyHandler())->formatFloat(
            money: new Money(amount: $this->amount, currency: new Currency($this->currency_code ?? 'USD'))
        );
    }
}
