<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Helpers\JsonDecoder;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DatabaseScheduledPaymentRepository implements ScheduledPaymentRepository
{
    /** @inheritDoc */
    public function find(string $scheduledPaymentId, array $columns = ['*']): ScheduledPayment
    {
        $scheduledPayment = ScheduledPayment::select($columns)->with(['status'])->find($scheduledPaymentId);

        if (empty($scheduledPayment)) {
            throw new ResourceNotFoundException(message: __('messages.scheduled_payment.not_found', ['id' => $scheduledPaymentId]));
        }

        return $scheduledPayment;
    }

    /** @inheritDoc */
    public function getPendingScheduledPaymentsForArea(int $areaId, int $page, int $quantity): LengthAwarePaginator
    {
        return ScheduledPayment::whereStatusId(ScheduledPaymentStatusEnum::PENDING->value)
            ->whereHas(relation: 'account', callback: static function (Builder $builder) use ($areaId) {
                $builder->where(column: 'area_id', operator: '=', value: $areaId); // for specific area
            })
            ->with([
                'paymentMethod:id,external_ref_id',
                'account' => [
                    'billingContact:id,first_name,last_name,email',
                    'contact:id,first_name,last_name,email',
                    'billingAddress:id,address,city,state,postal_code,country',
                    'address:id,address,city,state,postal_code,country',
                ]
            ])
            ->select(columns: [
                'id',
                'account_id',
                'payment_method_id',
                'trigger_id',
                'status_id',
                'amount',
                'payment_id'
            ])
            ->paginate(perPage: $quantity, page: $page);
    }

    /** @inheritDoc */
    public function create(array $attributes): ScheduledPayment
    {
        return ScheduledPayment::create(attributes: $attributes);
    }

    /** @inheritDoc */
    public function update(ScheduledPayment $payment, array $attributes): ScheduledPayment
    {
        $payment->update($attributes);

        return $payment;
    }

    /**
     * @inheritDoc
     *
     * @throws \JsonException
     */
    public function findDuplicate(
        string $accountId,
        string $paymentMethodId,
        ScheduledPaymentTriggerEnum $trigger,
        int $amount,
        array $metadata
    ): ScheduledPayment|null {
        return ScheduledPayment::where('trigger_id', $trigger->value)
            ->where('account_id', $accountId)
            ->where('payment_method_id', $paymentMethodId)
            ->where('amount', $amount)
            ->where('metadata', JsonDecoder::encode($metadata))
            ->first();
    }
}
