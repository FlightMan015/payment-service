<?php

declare(strict_types=1);

namespace App\Models;

use App\Api\Filter\AccountBillingFirstNameFilter;
use App\Api\Filter\AccountBillingLastNameFilter;
use App\Models\CRM\Customer\Account;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\Traits\PartiallyReplicableModel;
use Aptive\Attribution\Traits\WithAttributes;
use Aptive\Component\Money\MoneyHandler;
use Aptive\Illuminate\Filter\Traits\HasFilters;
use Carbon\Carbon;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Money\Currency;
use Money\Money;

/**
 * App\Models\Payment
 *
 * @property string $id
 * @property int|null $external_ref_id {"pestroutes_column_name": "paymentID"}
 * @property string $account_id
 * @property int $payment_type_id
 * @property int $payment_status_id
 * @property string|null $payment_method_id
 * @property int $payment_gateway_id
 * @property string $currency_code
 * @property int $amount {"pestroutes_column_name": "amount"}
 * @property int|null $applied_amount {"pestroutes_column_name": "appliedAmount"}
 * @property string|null $notes {"pestroutes_column_name": "notes"}
 * @property string|null $processed_at
 * @property int|null $notification_id
 * @property string|null $notification_sent_at
 * @property bool|null $is_office_payment {"pestroutes_column_name": "officePayment"}
 * @property bool|null $is_collection_payment {"pestroutes_column_name": "collectionPayment"}
 * @property bool|null $is_write_off {"pestroutes_column_name": "writeOff"}
 * @property int|null $pestroutes_customer_id {"pestroutes_column_name": "customerID"}
 * @property int|null $pestroutes_created_by {"pestroutes_column_name": "employeeID"}
 * @property string|null $pestroutes_created_at {"pestroutes_column_name": "date"}
 * @property string|null $pestroutes_updated_at {"pestroutes_column_name": "dateUpdated"}
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $pestroutes_json
 * @property string|null $original_payment_id
 * @property int|null $pestroutes_original_payment_id
 * @property bool $pestroutes_created_by_crm
 * @property bool $is_batch_payment To flag a payment was processed via batch or not
 * @property string|null $suspended_at
 * @property int|null $suspend_reason_id
 * @property bool $is_scheduled_payment Flag to determine if the payment was a scheduled payment
 * @property string|null $pestroutes_refund_processed_at Timestamp when the eligible refund that was created in PestRoutes was processed by Payment Service
 * @property string|null $pestroutes_metadata
 * @property string|null $terminated_by Stores the user who terminated the payment
 * @property string|null $terminated_at Stores the time when the payment was terminated
 * @property string|null $pestroutes_data_link_alias
 * @property-read Account $account
 * @property-read Collection<int, Payment> $childrenPayments
 * @property-read int|null $children_payments_count
 * @property-read Gateway $gateway
 * @property-read PaymentTypeEnum $payment_type
 * @property-read Collection<int, \App\Models\PaymentInvoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read Payment|null $originalPayment
 * @property-read PaymentMethod|null $paymentMethod
 * @property-read Payment|null $refundPayment
 * @property-read Payment|null $returnedPayment
 * @property-read PaymentStatus $status
 * @property-read SuspendReason|null $suspendReason
 * @property-read Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read PaymentType $type
 *
 * @method static Builder|Payment declinedForPaymentMethod(string $methodId)
 * @method static PaymentFactory factory($count = null, $state = [])
 * @method static Builder|Payment filtered(array $filters, ?\Aptive\Illuminate\Filter\Builder\FilterBuilderInterface $filterBuilder = null)
 * @method static Builder|Payment forAreaId(int $areaId)
 * @method static Builder|Payment getPaymentForInvoiceByStatus(string $accountId, array $invoiceIds, \App\PaymentProcessor\Enums\Database\PaymentStatusEnum $status)
 * @method static Builder|Payment getSuspendedOrTerminatedPaymentForOriginalPayment(string $accountId, string $originalPaymentId)
 * @method static Builder|Payment newModelQuery()
 * @method static Builder|Payment newQuery()
 * @method static Builder|Payment notSynchronized()
 * @method static Builder|Payment onlyTrashed()
 * @method static Builder|Payment query()
 * @method static Builder|Payment whereAccountId($value)
 * @method static Builder|Payment whereAmount($value)
 * @method static Builder|Payment whereAppliedAmount($value)
 * @method static Builder|Payment whereCreatedAt($value)
 * @method static Builder|Payment whereCreatedBy($value)
 * @method static Builder|Payment whereCurrencyCode($value)
 * @method static Builder|Payment whereDeletedAt($value)
 * @method static Builder|Payment whereDeletedBy($value)
 * @method static Builder|Payment whereExternalRefId($value)
 * @method static Builder|Payment whereId($value)
 * @method static Builder|Payment whereIsBatchPayment($value)
 * @method static Builder|Payment whereIsCollectionPayment($value)
 * @method static Builder|Payment whereIsOfficePayment($value)
 * @method static Builder|Payment whereIsScheduledPayment($value)
 * @method static Builder|Payment whereIsWriteOff($value)
 * @method static Builder|Payment whereNotes($value)
 * @method static Builder|Payment whereNotificationId($value)
 * @method static Builder|Payment whereNotificationSentAt($value)
 * @method static Builder|Payment whereOriginalPaymentId($value)
 * @method static Builder|Payment wherePaymentGatewayId($value)
 * @method static Builder|Payment wherePaymentMethodId($value)
 * @method static Builder|Payment wherePaymentStatusId($value)
 * @method static Builder|Payment wherePaymentTypeId($value)
 * @method static Builder|Payment wherePestroutesCreatedAt($value)
 * @method static Builder|Payment wherePestroutesCreatedBy($value)
 * @method static Builder|Payment wherePestroutesCreatedByCrm($value)
 * @method static Builder|Payment wherePestroutesCustomerId($value)
 * @method static Builder|Payment wherePestroutesDataLinkAlias($value)
 * @method static Builder|Payment wherePestroutesJson($value)
 * @method static Builder|Payment wherePestroutesMetadata($value)
 * @method static Builder|Payment wherePestroutesOriginalPaymentId($value)
 * @method static Builder|Payment wherePestroutesRefundProcessedAt($value)
 * @method static Builder|Payment wherePestroutesUpdatedAt($value)
 * @method static Builder|Payment whereProcessedAt($value)
 * @method static Builder|Payment whereSuspendReasonId($value)
 * @method static Builder|Payment whereSuspendedAt($value)
 * @method static Builder|Payment whereTerminatedAt($value)
 * @method static Builder|Payment whereTerminatedBy($value)
 * @method static Builder|Payment whereUpdatedAt($value)
 * @method static Builder|Payment whereUpdatedBy($value)
 * @method static Builder|Payment withOriginalPaymentFromPaymentService()
 * @method static Builder|Payment withTrashed()
 * @method static Builder|Payment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use PartiallyReplicableModel;
    use WithAttributes;
    use HasFilters;

    final public const string GATEWAY_PROCESSING_TIME = '20:00:00';
    final public const string GATEWAY_PROCESSING_TIMEZONE = 'MST';

    protected $table = 'billing.payments';
    protected $guarded = ['id'];
    protected array $ignoreWhenReplicating = [
        'external_ref_id',
        'pestroutes_customer_id',
        'pestroutes_created_by',
        'pestroutes_created_at',
        'pestroutes_updated_at',
        'pestroutes_original_payment_id',
        'pestroutes_json',
        'notification_id',
        'notification_sent_at'
    ];

    public array $filters = [
        'compare' => [
            'account_id',
            'payment_method_id',
            'payment_status_id' => [
                'param_name' => 'payment_status',
                'convert_value' => [PaymentStatusEnum::class, 'fromName'],
            ],
            'account.area_id' => [
                'param_name' => 'area_id',
            ],
            'invoices.invoice_id' => [
                'param_name' => 'invoice_id',
            ],
        ],
        'between' => [
            'amount',
            'processed_at' => [
                'param_name' => 'date',
                'value_type' => 'date',
            ],
        ],
        AccountBillingFirstNameFilter::FILTER_KEY => [
            'param_name' => 'first_name',
        ],
        AccountBillingLastNameFilter::FILTER_KEY => [
            'param_name' => 'last_name',
        ],
    ];

    /**
     * Get the gateway the payment belongs to
     *
     * @return BelongsTo<Gateway, Payment>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(related: Gateway::class, foreignKey: 'payment_gateway_id');
    }

    /**
     * Get the method the payment belongs to
     *
     * @return BelongsTo<PaymentMethod, Payment>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(related: PaymentMethod::class, foreignKey: 'payment_method_id');
    }

    /**
     * Get the payment type the payment method belongs to
     *
     * @return BelongsTo<PaymentType, Payment>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(related: PaymentType::class, foreignKey: 'payment_type_id');
    }

    /**
     * Get the status associated with the CustomerPayment
     *
     * @return BelongsTo<PaymentStatus, Payment>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(related: PaymentStatus::class, foreignKey: 'payment_status_id', ownerKey: 'id');
    }

    /**
     * Get all the transactions for the CustomerPayment
     *
     * @return HasMany<Transaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(related: Transaction::class, foreignKey: 'payment_id');
    }

    /**
     * Get all the invoices for the CustomerPayment
     *
     * @return HasMany<PaymentInvoice>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(related: PaymentInvoice::class, foreignKey: 'payment_id');
    }

    /**
     * @return BelongsTo<Account, Payment>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class);
    }

    /**
     * @return BelongsTo<SuspendReason, Payment>
     */
    public function suspendReason(): BelongsTo
    {
        return $this->belongsTo(related: SuspendReason::class);
    }

    /**
     * @return HasOne<Payment>
     */
    public function returnedPayment(): HasOne
    {
        return $this->hasOne(self::class, 'original_payment_id')
            ->where('payment_status_id', PaymentStatusEnum::RETURNED->value);
    }

    /**
     * Returns the amount formatted as a decimal
     * This should only be used for display formatting
     *
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

    /**
     * Build a query for getting declined payment for given payment method id.
     *
     * @param Builder<Payment> $builder
     * @param string $methodId
     *
     * @return void
     */
    public function scopeDeclinedForPaymentMethod(Builder $builder, string $methodId): void
    {
        $builder->wherePaymentMethodId($methodId)->wherePaymentStatusId(PaymentStatusEnum::DECLINED);
    }

    /**
     * @param Builder<Payment> $builder
     * @param int $areaId
     *
     * @return void
     */
    public function scopeForAreaId(Builder $builder, int $areaId): void
    {
        $builder->whereHas(relation: 'account', callback: static function (Builder $builder) use ($areaId) {
            $builder->where(column: 'area_id', operator: '=', value: $areaId);
        });
    }

    /**
     * @param Builder<Payment> $builder
     *
     * @return void
     */
    public function scopeWithOriginalPaymentFromPaymentService(Builder $builder): void
    {
        $builder->whereHas(relation: 'originalPayment', callback: static function (Builder $builder) {
            $builder->where(column: 'pestroutes_created_by_crm', operator: '=', value: true);
        });
    }

    /**
     * @param array<OperationEnum>|OperationEnum $operation
     *
     * @return Transaction|null the latest transaction for the given payment for the specific operation(s)
     */
    public function transactionForOperation(array|OperationEnum $operation): Transaction|null
    {
        if (is_array($operation)) {
            return $this->transactions()->whereIn(
                'transaction_type_id',
                array_map(static fn (OperationEnum $op) => $op->value, $operation)
            )->latest()->first();
        }

        return $this->transactions()->where('transaction_type_id', $operation->value)->latest()->first();
    }

    /**
     * @param TransactionTypeEnum $transactionType
     *
     * @return Transaction|null The latest transaction for the given payment for the specific transaction type
     */
    public function transactionByTransactionType(TransactionTypeEnum $transactionType): Transaction|null
    {
        return $this->transactions()->where('transaction_type_id', $transactionType->value)->latest()->first();
    }

    /**
     * @param PaymentStatusEnum|PaymentStatusEnum[] $status
     *
     * @return bool
     */
    public function isStatus(array|PaymentStatusEnum $status): bool
    {
        if (is_array($status)) {
            return in_array(
                needle: $this->payment_status_id,
                haystack: array_map(static fn (PaymentStatusEnum $paymentStatus) => $paymentStatus->value, $status),
                strict: true
            );
        }

        return $this->payment_status_id === $status->value;
    }

    /**
     * Get all children payments by original payment id
     *
     * @return HasMany<Payment>
     */
    public function childrenPayments(): HasMany
    {
        return $this->hasMany(related: self::class, foreignKey: 'original_payment_id');
    }

    /**
     * @return bool
     */
    public function isRealGatewayPayment(): bool
    {
        return PaymentGatewayEnum::from($this->payment_gateway_id)->isRealGateway();
    }

    public function wasAlreadyProcessedInGateway(): bool
    {
        $paymentProcessedAt = Carbon::parse(time: $this->processed_at)
            ->setTimezone(self::GATEWAY_PROCESSING_TIMEZONE);
        $paymentProcessedAtGatewayAt = $this->getNextGatewayPaymentProcessingTime(paymentProcessedAt: $paymentProcessedAt);

        return $paymentProcessedAtGatewayAt->isPast();
    }

    private function getNextGatewayPaymentProcessingTime(Carbon $paymentProcessedAt): Carbon
    {
        $nextProcessingTime = $paymentProcessedAt->clone()
            ->setTimeFromTimeString(time: self::GATEWAY_PROCESSING_TIME);

        if ($paymentProcessedAt->gt($nextProcessingTime)) {
            $nextProcessingTime->addDay();
        }

        return $nextProcessingTime;
    }

    /**
     * @return PaymentTypeEnum
     */
    public function getPaymentTypeAttribute(): PaymentTypeEnum
    {
        return PaymentTypeEnum::from($this->payment_type_id);
    }

    /**
     * @param Builder<Payment> $query
     * @param string $accountId
     * @param array<int> $invoiceIds
     * @param PaymentStatusEnum $status
     *
     * @return Builder<Payment>
     */
    public function scopeGetPaymentForInvoiceByStatus(Builder $query, string $accountId, array $invoiceIds, PaymentStatusEnum $status): Builder
    {
        return $query->whereAccountId($accountId)
            ->where('is_batch_payment', true)
            ->where('payment_status_id', $status->value)
            ->whereHas('invoices', static function ($query) use ($invoiceIds) {
                $query->whereIn('invoice_id', $invoiceIds);
            });
    }

    /**
     * @return HasOne<Payment>
     */
    public function refundPayment(): HasOne
    {
        return $this->hasOne(self::class, 'original_payment_id')
            ->where('payment_status_id', PaymentStatusEnum::CREDITED->value);
    }

    /**
     * @return BelongsTo<Payment, Payment>
     */
    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_payment_id');
    }

    /**
     * @param Builder<Payment> $query
     *
     * @return Builder<Payment>
     */
    public function scopeNotSynchronized(Builder $query): Builder
    {
        return $query->whereNull('external_ref_id')
            ->whereIn(
                'payment_status_id',
                [
                    PaymentStatusEnum::CAPTURED,
                    PaymentStatusEnum::CREDITED,
                    PaymentStatusEnum::DECLINED,
                    PaymentStatusEnum::CANCELLED
                ]
            )
            ->where('pestroutes_created_by_crm', true);
    }

    /**
     * @param Builder<Payment> $query
     * @param string $accountId
     * @param string $originalPaymentId
     *
     * @return Builder<Payment>
     */
    public function scopeGetSuspendedOrTerminatedPaymentForOriginalPayment(Builder $query, string $accountId, string $originalPaymentId): Builder
    {
        return $query->whereAccountId($accountId)
            ->where('is_batch_payment', true)
            ->whereIn('payment_status_id', [
                PaymentStatusEnum::SUSPENDED->value,
                PaymentStatusEnum::TERMINATED->value,
            ])
            ->whereOriginalPaymentId($originalPaymentId);
    }
}
