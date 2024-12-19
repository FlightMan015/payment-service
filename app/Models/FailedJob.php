<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FailedJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\FailedJob
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property string $failed_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 *
 * @method static FailedJobFactory factory($count = null, $state = [])
 * @method static Builder|FailedJob newModelQuery()
 * @method static Builder|FailedJob newQuery()
 * @method static Builder|FailedJob query()
 * @method static Builder|FailedJob whereConnection($value)
 * @method static Builder|FailedJob whereCreatedAt($value)
 * @method static Builder|FailedJob whereCreatedBy($value)
 * @method static Builder|FailedJob whereDeletedAt($value)
 * @method static Builder|FailedJob whereDeletedBy($value)
 * @method static Builder|FailedJob whereException($value)
 * @method static Builder|FailedJob whereFailedAt($value)
 * @method static Builder|FailedJob whereId($value)
 * @method static Builder|FailedJob wherePayload($value)
 * @method static Builder|FailedJob whereQueue($value)
 * @method static Builder|FailedJob whereUpdatedAt($value)
 * @method static Builder|FailedJob whereUpdatedBy($value)
 * @method static Builder|FailedJob whereUuid($value)
 *
 * @mixin \Eloquent
 */
class FailedJob extends Model
{
    /** @use HasFactory<FailedJobFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];
    protected $table = 'billing.failed_jobs';
}
