<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Subscription;
use App\PaymentProcessor\Enums\CurrencyCodeEnum;
use App\PaymentProcessor\Enums\InvoiceStatusEnum;
use Aptive\Component\Money\MoneyHandler;
use Aptive\Illuminate\Filter\Traits\HasFilters;
use Database\Factories\InvoiceFactory;
use Dflydev\DotAccessData\Exception\DataException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Money\Currency;
use Money\Money;

/**
 * App\Models\Invoice
 *
 * @property string $id
 * @property int|null $external_ref_id {"pestroutes_column_name": "ticketID"}
 * @property string $account_id
 * @property string|null $subscription_id
 * @property int|null $service_type_id
 * @property bool|null $is_active {"pestroutes_column_name": "active"}
 * @property int $subtotal {"pestroutes_column_name": "subTotal"}
 * @property float $tax_rate {"pestroutes_column_name": "taxRate"}
 * @property int $total {"pestroutes_column_name": "total"}
 * @property int $balance {"pestroutes_column_name": "balance"}
 * @property CurrencyCodeEnum $currency_code
 * @property int $service_charge {"pestroutes_column_name": "serviceCharge"}
 * @property string|null $invoiced_at
 * @property int|null $pestroutes_customer_id {"pestroutes_column_name": "customerID"}
 * @property int|null $pestroutes_subscription_id {"pestroutes_column_name": "subscriptionID"}
 * @property int|null $pestroutes_service_type_id {"pestroutes_column_name": "serviceID"}
 * @property int|null $pestroutes_created_by {"pestroutes_column_name": "createdBy"}
 * @property string|null $pestroutes_created_at {"pestroutes_column_name": "dateCreated"}
 * @property string|null $pestroutes_invoiced_at {"pestroutes_column_name": "invoiceDate"}
 * @property string|null $pestroutes_updated_at {"pestroutes_column_name": "dateUpdated"}
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $pestroutes_json
 * @property bool $in_collections
 * @property bool $is_written_off To flag an invoice as bad debt expense (write them off)
 * @property string|null $reason
 * @property-read Account|null $account
 * @property-read float $items_total
 * @property-read string $status
 * @property-read float $tax_amount
 * @property-read Collection<int, \App\Models\InvoiceItem> $items
 * @property-read int|null $items_count
 * @property-read Subscription|null $subscription
 *
 * @method static InvoiceFactory factory($count = null, $state = [])
 * @method static Builder|Invoice filtered(array $filters, ?\Aptive\Illuminate\Filter\Builder\FilterBuilderInterface $filterBuilder = null)
 * @method static Builder|Invoice newModelQuery()
 * @method static Builder|Invoice newQuery()
 * @method static Builder|Invoice onlyTrashed()
 * @method static Builder|Invoice query()
 * @method static Builder|Invoice whereAccountId($value)
 * @method static Builder|Invoice whereBalance($value)
 * @method static Builder|Invoice whereCreatedAt($value)
 * @method static Builder|Invoice whereCreatedBy($value)
 * @method static Builder|Invoice whereCurrencyCode($value)
 * @method static Builder|Invoice whereDeletedAt($value)
 * @method static Builder|Invoice whereDeletedBy($value)
 * @method static Builder|Invoice whereExternalRefId($value)
 * @method static Builder|Invoice whereId($value)
 * @method static Builder|Invoice whereInCollections($value)
 * @method static Builder|Invoice whereInvoicedAt($value)
 * @method static Builder|Invoice whereIsActive($value)
 * @method static Builder|Invoice whereIsWrittenOff($value)
 * @method static Builder|Invoice wherePestroutesCreatedAt($value)
 * @method static Builder|Invoice wherePestroutesCreatedBy($value)
 * @method static Builder|Invoice wherePestroutesCustomerId($value)
 * @method static Builder|Invoice wherePestroutesInvoicedAt($value)
 * @method static Builder|Invoice wherePestroutesJson($value)
 * @method static Builder|Invoice wherePestroutesServiceTypeId($value)
 * @method static Builder|Invoice wherePestroutesSubscriptionId($value)
 * @method static Builder|Invoice wherePestroutesUpdatedAt($value)
 * @method static Builder|Invoice whereReason($value)
 * @method static Builder|Invoice whereServiceCharge($value)
 * @method static Builder|Invoice whereServiceTypeId($value)
 * @method static Builder|Invoice whereSubscriptionId($value)
 * @method static Builder|Invoice whereSubtotal($value)
 * @method static Builder|Invoice whereTaxRate($value)
 * @method static Builder|Invoice whereTotal($value)
 * @method static Builder|Invoice whereUpdatedAt($value)
 * @method static Builder|Invoice whereUpdatedBy($value)
 * @method static Builder|Invoice withTrashed()
 * @method static Builder|Invoice withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Invoice extends Model
{
    use SoftDeletes;
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;
    use HasUuids;
    use HasFilters;

    protected $table = 'billing.invoices';
    protected $keyType = 'uuid';

    protected $fillable = [
        'account_id',
        'is_active',
        'subtotal',
        'tax_rate',
        'total',
        'balance',
        'currency_code',
        'service_charge',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'currency_code' => CurrencyCodeEnum::class,
    ];

    protected $appends = [
        'tax_amount',
        'items_total',
    ];

    public array $filters = [
        'compare' => [
            'account_id',
            'subscription_id',
        ],
        'between' => [
            'invoiced_at' => [
                'param_name' => 'date',
                'value_type' => 'date',
            ],
            'total',
            'balance',
        ],
    ];

    /**
     * @return BelongsTo<Account, Invoice>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Subscription, Invoice>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<InvoiceItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return string
     */
    public function getStatusAttribute(): string
    {
        if ($this->balance === 0) {
            return InvoiceStatusEnum::PAID->value;
        }
        if ($this->balance > 0) {
            return InvoiceStatusEnum::UNPAID->value;
        }

        throw new DataException('Invoice balance is invalid.');
    }

    /**
     * @return float
     */
    public function getItemsTotalAttribute(): float
    {
        return $this->items->sum(static fn (InvoiceItem $item): float => $item->total);
    }

    /**
     * @return float
     */
    public function getTaxAmountAttribute(): float
    {
        return $this->tax_rate * $this->subtotal;
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
            money: new Money(amount: (int) $this->balance, currency: new Currency('USD'))
        );
    }
}
