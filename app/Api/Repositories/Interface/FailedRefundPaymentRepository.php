<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Models\FailedRefundPayment;
use Illuminate\Pagination\LengthAwarePaginator;

interface FailedRefundPaymentRepository
{
    /**
     * Retrieve failed refund payments that have not been reported yet
     *
     * @param int $page
     * @param int $quantity
     *
     * @return LengthAwarePaginator<FailedRefundPayment>
     */
    public function getNotReported(int $page, int $quantity): LengthAwarePaginator;

    /**
     * Create a failed refund payment with given attributes
     *
     * @param array $attributes
     *
     * @return FailedRefundPayment
     */
    public function create(array $attributes): FailedRefundPayment;

    /**
     * Update the given failed refund payment with given attributes
     *
     * @param FailedRefundPayment $refund
     * @param array $attributes
     *
     * @return FailedRefundPayment
     */
    public function update(FailedRefundPayment $refund, array $attributes): FailedRefundPayment;
}
