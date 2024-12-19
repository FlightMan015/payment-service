<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\CRM\Customer\Account;
use Database\Factories\LedgerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Ledger
 *
 * @property string $id
 * @property string $account_id
 * @property int|null $balance
 * @property int|null $balance_age_in_days
 * @property string|null $autopay_payment_method_id
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Account $account
 * @property-read PaymentMethod|null $autopayMethod
 *
 * @method static LedgerFactory factory($count = null, $state = [])
 * @method static Builder|Ledger newModelQuery()
 * @method static Builder|Ledger newQuery()
 * @method static Builder|Ledger onlyTrashed()
 * @method static Builder|Ledger query()
 * @method static Builder|Ledger whereAccountId($value)
 * @method static Builder|Ledger whereAutopayPaymentMethodId($value)
 * @method static Builder|Ledger whereBalance($value)
 * @method static Builder|Ledger whereBalanceAgeInDays($value)
 * @method static Builder|Ledger whereCreatedAt($value)
 * @method static Builder|Ledger whereCreatedBy($value)
 * @method static Builder|Ledger whereDeletedAt($value)
 * @method static Builder|Ledger whereDeletedBy($value)
 * @method static Builder|Ledger whereId($value)
 * @method static Builder|Ledger whereUpdatedAt($value)
 * @method static Builder|Ledger whereUpdatedBy($value)
 * @method static Builder|Ledger withTrashed()
 * @method static Builder|Ledger withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Ledger extends Model
{
    use SoftDeletes;
    use HasUuids;
    /** @use HasFactory<LedgerFactory> */
    use HasFactory;

    protected $table = 'billing.ledger';
    protected $primaryKey = 'account_id';
    protected $guarded = ['account_id'];

    /**
     * @return BelongsTo<Account, Ledger>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class, foreignKey: 'account_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, Ledger>
     */
    public function autopayMethod(): BelongsTo
    {
        return $this->belongsTo(related: PaymentMethod::class, foreignKey: 'autopay_payment_method_id');
    }
}
