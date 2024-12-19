<?php

declare(strict_types=1);

namespace App\Models;

use Aptive\Attribution\Traits\WithAttributes;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * App\Models\Transaction
 *
 * @property string $id
 * @property string $payment_id
 * @property int $transaction_type_id
 * @property string|null $raw_request_log
 * @property string|null $raw_response_log
 * @property string $gateway_transaction_id
 * @property string $gateway_response_code
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 * @property int|null $decline_reason_id
 * @property-read DeclineReason|null $declineReason
 * @property-read Payment $payment
 * @property-read TransactionType $type
 *
 * @method static TransactionFactory factory($count = null, $state = [])
 * @method static Builder|Transaction newModelQuery()
 * @method static Builder|Transaction newQuery()
 * @method static Builder|Transaction query()
 * @method static Builder|Transaction whereCreatedAt($value)
 * @method static Builder|Transaction whereCreatedBy($value)
 * @method static Builder|Transaction whereDeclineReasonId($value)
 * @method static Builder|Transaction whereDeletedAt($value)
 * @method static Builder|Transaction whereDeletedBy($value)
 * @method static Builder|Transaction whereGatewayResponseCode($value)
 * @method static Builder|Transaction whereGatewayTransactionId($value)
 * @method static Builder|Transaction whereId($value)
 * @method static Builder|Transaction wherePaymentId($value)
 * @method static Builder|Transaction whereRawRequestLog($value)
 * @method static Builder|Transaction whereRawResponseLog($value)
 * @method static Builder|Transaction whereTransactionTypeId($value)
 * @method static Builder|Transaction whereUpdatedAt($value)
 * @method static Builder|Transaction whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;
    use HasUuids;
    use WithAttributes;

    protected $table = 'billing.transactions';
    protected $guarded = ['id'];
    protected $casts = ['is_anonymized' => 'boolean'];

    /**
     * Get the payment type the payment method belongs to
     *
     * @return BelongsTo<Payment, Transaction>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(related: Payment::class, foreignKey: 'payment_id');
    }

    /**
     * Get the payment type the payment method belongs to
     *
     * @return BelongsTo<TransactionType, Transaction>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(related: TransactionType::class, foreignKey: 'transaction_type_id', ownerKey: 'id');
    }

    /**
     * @return HasOne<DeclineReason>
     */
    public function declineReason(): HasOne
    {
        return $this->hasOne(related: DeclineReason::class, foreignKey: 'id', localKey: 'decline_reason_id');
    }
}
