<?php

declare(strict_types=1);

namespace App\Models;

use Aptive\Attribution\Traits\WithAttributes;
use Database\Factories\AccountUpdaterAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\AccountUpdaterAttempt
 *
 * @property string $id
 * @property string|null $requested_by
 * @property string $requested_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 * @property-read Collection<int, \App\Models\PaymentMethod> $methods
 * @property-read int|null $methods_count
 *
 * @method static AccountUpdaterAttemptFactory factory($count = null, $state = [])
 * @method static Builder|AccountUpdaterAttempt newModelQuery()
 * @method static Builder|AccountUpdaterAttempt newQuery()
 * @method static Builder|AccountUpdaterAttempt query()
 * @method static Builder|AccountUpdaterAttempt whereCreatedAt($value)
 * @method static Builder|AccountUpdaterAttempt whereCreatedBy($value)
 * @method static Builder|AccountUpdaterAttempt whereDeletedAt($value)
 * @method static Builder|AccountUpdaterAttempt whereDeletedBy($value)
 * @method static Builder|AccountUpdaterAttempt whereId($value)
 * @method static Builder|AccountUpdaterAttempt whereRequestedAt($value)
 * @method static Builder|AccountUpdaterAttempt whereRequestedBy($value)
 * @method static Builder|AccountUpdaterAttempt whereUpdatedAt($value)
 * @method static Builder|AccountUpdaterAttempt whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class AccountUpdaterAttempt extends Model
{
    /** @use HasFactory<AccountUpdaterAttemptFactory> */
    use HasFactory;
    use HasUuids;
    use WithAttributes;

    protected $table = 'billing.account_updater_attempts';

    protected $guarded = ['id'];

    /**
     * Get all the methods for the Account Updater Attempt
     *
     * @return BelongsToMany<PaymentMethod>
     */
    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(
            related: PaymentMethod::class,
            table: 'billing.account_updater_attempts_methods',
            foreignPivotKey: 'attempt_id',
            relatedPivotKey: 'payment_method_id'
        );
    }
}
