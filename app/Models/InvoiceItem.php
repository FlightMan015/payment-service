<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Invoice\Models\InvoiceItem
 *
 * @property string $id
 * @property int|null $external_ref_id
 * @property string $invoice_id
 * @property float $quantity
 * @property float $amount
 * @property string $description
 * @property bool $is_taxable
 * @property int|null $pestroutes_invoice_id
 * @property int|null $pestroutes_product_id
 * @property int|null $pestroutes_service_type_id
 * @property string|null $pestroutes_created_at
 * @property string|null $pestroutes_updated_at
 * @property string|null $pestroutes_json
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $service_type_id
 * @property-read float $total
 * @property-read Invoice $invoice
 *
 * @method static InvoiceItemFactory factory($count = null, $state = [])
 * @method static Builder|InvoiceItem newModelQuery()
 * @method static Builder|InvoiceItem newQuery()
 * @method static Builder|InvoiceItem onlyTrashed()
 * @method static Builder|InvoiceItem query()
 * @method static Builder|InvoiceItem whereAmount($value)
 * @method static Builder|InvoiceItem whereCreatedAt($value)
 * @method static Builder|InvoiceItem whereCreatedBy($value)
 * @method static Builder|InvoiceItem whereDeletedAt($value)
 * @method static Builder|InvoiceItem whereDeletedBy($value)
 * @method static Builder|InvoiceItem whereDescription($value)
 * @method static Builder|InvoiceItem whereExternalRefId($value)
 * @method static Builder|InvoiceItem whereId($value)
 * @method static Builder|InvoiceItem whereInvoiceId($value)
 * @method static Builder|InvoiceItem whereIsTaxable($value)
 * @method static Builder|InvoiceItem wherePestroutesCreatedAt($value)
 * @method static Builder|InvoiceItem wherePestroutesInvoiceId($value)
 * @method static Builder|InvoiceItem wherePestroutesJson($value)
 * @method static Builder|InvoiceItem wherePestroutesProductId($value)
 * @method static Builder|InvoiceItem wherePestroutesServiceTypeId($value)
 * @method static Builder|InvoiceItem wherePestroutesUpdatedAt($value)
 * @method static Builder|InvoiceItem whereQuantity($value)
 * @method static Builder|InvoiceItem whereServiceTypeId($value)
 * @method static Builder|InvoiceItem whereUpdatedAt($value)
 * @method static Builder|InvoiceItem whereUpdatedBy($value)
 * @method static Builder|InvoiceItem withTrashed()
 * @method static Builder|InvoiceItem withoutTrashed()
 *
 * @mixin \Eloquent
 */
class InvoiceItem extends Model
{
    use SoftDeletes;
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'billing.invoice_items';
    protected $keyType = 'uuid';

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'quantity' => 'float',
        'amount' => 'float',
        'is_taxable' => 'boolean'
    ];

    protected $appends = [
        'total'
    ];

    /**
     * @return BelongsTo<Invoice, InvoiceItem>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return float
     */
    public function getTotalAttribute(): float
    {
        return $this->quantity * $this->amount;
    }
}
