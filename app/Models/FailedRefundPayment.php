<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\CRM\Customer\Account;
use Aptive\Attribution\Traits\WithAttributes;
use Database\Factories\FailedRefundPaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $original_payment_id The identifier of the original payment that failed to be refunded
 * @property string $refund_payment_id The identifier of the refund payment record that failed
 * @property string $account_id
 * @property int $amount The requested amount to be refunded
 * @property string $failed_at The date and time when the refund failed
 * @property string $failure_reason The reason why the refund failed (from Gateway)
 * @property string|null $report_sent_at If it was included in a report, the date and time when it was sent
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Account $account
 * @property-read Payment $originalPayment
 * @property-read Payment $refundPayment
 *
 * @method static FailedRefundPaymentFactory factory($count = null, $state = [])
 * @method static Builder|FailedRefundPayment newModelQuery()
 * @method static Builder|FailedRefundPayment newQuery()
 * @method static Builder|FailedRefundPayment onlyTrashed()
 * @method static Builder|FailedRefundPayment query()
 * @method static Builder|FailedRefundPayment whereAccountId($value)
 * @method static Builder|FailedRefundPayment whereAmount($value)
 * @method static Builder|FailedRefundPayment whereCreatedAt($value)
 * @method static Builder|FailedRefundPayment whereCreatedBy($value)
 * @method static Builder|FailedRefundPayment whereDeletedAt($value)
 * @method static Builder|FailedRefundPayment whereDeletedBy($value)
 * @method static Builder|FailedRefundPayment whereFailedAt($value)
 * @method static Builder|FailedRefundPayment whereFailureReason($value)
 * @method static Builder|FailedRefundPayment whereId($value)
 * @method static Builder|FailedRefundPayment whereOriginalPaymentId($value)
 * @method static Builder|FailedRefundPayment whereRefundPaymentId($value)
 * @method static Builder|FailedRefundPayment whereReportSentAt($value)
 * @method static Builder|FailedRefundPayment whereUpdatedAt($value)
 * @method static Builder|FailedRefundPayment whereUpdatedBy($value)
 * @method static Builder|FailedRefundPayment withTrashed()
 * @method static Builder|FailedRefundPayment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class FailedRefundPayment extends Model
{
    /** @use HasFactory<FailedRefundPaymentFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use WithAttributes;

    protected $table = 'billing.failed_refund_payments';
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Account, FailedRefundPayment>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class);
    }

    /**
     * @return BelongsTo<Payment, FailedRefundPayment>
     */
    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(related: Payment::class);
    }

    /**
     * @return BelongsTo<Payment, FailedRefundPayment>
     */
    public function refundPayment(): BelongsTo
    {
        return $this->belongsTo(related: Payment::class);
    }
}
