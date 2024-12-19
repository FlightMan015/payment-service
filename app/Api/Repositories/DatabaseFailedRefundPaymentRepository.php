<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Repositories\Interface\FailedRefundPaymentRepository;
use App\Models\FailedRefundPayment;
use Illuminate\Pagination\LengthAwarePaginator;

class DatabaseFailedRefundPaymentRepository implements FailedRefundPaymentRepository
{
    /** @inheritDoc */
    public function getNotReported(int $page, int $quantity): LengthAwarePaginator
    {
        return FailedRefundPayment::whereNull('report_sent_at')
            ->with([
                'account:id,external_ref_id,billing_contact_id,contact_id' => [
                    'billingContact:id,first_name,last_name',
                    'contact:id,first_name,last_name',
                ],
                'originalPayment:id,amount,processed_at',
            ])
            ->paginate($quantity, ['*'], 'page', $page);
    }

    /** @inheritDoc */
    public function create(array $attributes): FailedRefundPayment
    {
        return FailedRefundPayment::create(attributes: $attributes);
    }

    /** @inheritDoc */
    public function update(FailedRefundPayment $refund, array $attributes): FailedRefundPayment
    {
        $refund->update($attributes);

        return $refund;
    }
}
