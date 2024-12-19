<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TransactionTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\TransactionType
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $deleted_at
 *
 * @method static TransactionTypeFactory factory($count = null, $state = [])
 * @method static Builder|TransactionType newModelQuery()
 * @method static Builder|TransactionType newQuery()
 * @method static Builder|TransactionType query()
 * @method static Builder|TransactionType whereCreatedAt($value)
 * @method static Builder|TransactionType whereCreatedBy($value)
 * @method static Builder|TransactionType whereDeletedAt($value)
 * @method static Builder|TransactionType whereDeletedBy($value)
 * @method static Builder|TransactionType whereDescription($value)
 * @method static Builder|TransactionType whereId($value)
 * @method static Builder|TransactionType whereName($value)
 * @method static Builder|TransactionType whereUpdatedAt($value)
 * @method static Builder|TransactionType whereUpdatedBy($value)
 *
 * @mixin \Eloquent
 */
class TransactionType extends Model
{
    /** @use HasFactory<TransactionTypeFactory> */
    use HasFactory;

    protected $table = 'billing.transaction_types';
    protected $guarded = ['id'];
}
