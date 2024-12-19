<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentInvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\PaymentInvoice
 *
 * @property string $payment_id
 * @property string $invoice_id
 * @property int $amount
 * @property int|null $pestroutes_payment_id
 * @property int|null $pestroutes_invoice_id
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 * @property int $tax_amount
 * @property-read Invoice $invoice
 * @property-read Payment $payment
 *
 * @method static PaymentInvoiceFactory factory($count = null, $state = [])
 * @method static Builder|PaymentInvoice newModelQuery()
 * @method static Builder|PaymentInvoice newQuery()
 * @method static Builder|PaymentInvoice query()
 * @method static Builder|PaymentInvoice whereAmount($value)
 * @method static Builder|PaymentInvoice whereCreatedAt($value)
 * @method static Builder|PaymentInvoice whereCreatedBy($value)
 * @method static Builder|PaymentInvoice whereDeletedAt($value)
 * @method static Builder|PaymentInvoice whereDeletedBy($value)
 * @method static Builder|PaymentInvoice whereInvoiceId($value)
 * @method static Builder|PaymentInvoice wherePaymentId($value)
 * @method static Builder|PaymentInvoice wherePestroutesInvoiceId($value)
 * @method static Builder|PaymentInvoice wherePestroutesPaymentId($value)
 * @method static Builder|PaymentInvoice whereTaxAmount($value)
 * @method static Builder|PaymentInvoice whereUpdatedAt($value)
 * @method static Builder|PaymentInvoice whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class PaymentInvoice extends Model
{
    /** @use HasFactory<PaymentInvoiceFactory> */
    use HasFactory;

    protected $table = 'billing.payment_invoice_allocations';

    protected $guarded = ['payment_id'];
    public $incrementing = false;

    /**
     * Get the payment the record belongs to
     *
     * @return BelongsTo<Payment, PaymentInvoice>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(related: Payment::class, foreignKey: 'payment_id');
    }

    /**
     * Get the invoice the record belongs to
     *
     * @return BelongsTo<Invoice, PaymentInvoice>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(related: Invoice::class, foreignKey: 'invoice_id');
    }
}
