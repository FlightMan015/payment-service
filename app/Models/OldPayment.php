<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OldPaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\OldPayment
 *
 * @property int $id
 * @property int|null $appointment_id
 * @property int|null $ticket_id
 * @property float $amount
 * @property int $success
 * @property string|null $request_origin
 * @property string|null $service_response
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static OldPaymentFactory factory($count = null, $state = [])
 * @method static Builder|OldPayment newModelQuery()
 * @method static Builder|OldPayment newQuery()
 * @method static Builder|OldPayment query()
 * @method static Builder|OldPayment whereAmount($value)
 * @method static Builder|OldPayment whereAppointmentId($value)
 * @method static Builder|OldPayment whereCreatedAt($value)
 * @method static Builder|OldPayment whereId($value)
 * @method static Builder|OldPayment whereRequestOrigin($value)
 * @method static Builder|OldPayment whereServiceResponse($value)
 * @method static Builder|OldPayment whereSuccess($value)
 * @method static Builder|OldPayment whereTicketId($value)
 * @method static Builder|OldPayment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class OldPayment extends Model
{
    /** @use HasFactory<OldPaymentFactory> */
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'payments';

    protected $fillable = [
        'appointment_id',
        'ticket_id',
        'amount',
        'success',
        'request_origin',
        'service_response',
    ];
}
