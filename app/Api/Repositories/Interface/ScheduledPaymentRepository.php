<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Pagination\LengthAwarePaginator;

interface ScheduledPaymentRepository
{
    /**
     * Retrieve scheduled payment detail by given id
     *
     * @param string $scheduledPaymentId
     * @param array $columns
     *
     * @throws ResourceNotFoundException
     *
     * @return ScheduledPayment
     */
    public function find(string $scheduledPaymentId, array $columns = ['*']): ScheduledPayment;

    /**
     * Retrieve pending scheduled payments for the specific area
     *
     * @param int $areaId
     * @param int $page
     * @param int $quantity
     *
     * @return LengthAwarePaginator<ScheduledPayment>
     */
    public function getPendingScheduledPaymentsForArea(int $areaId, int $page, int $quantity): LengthAwarePaginator;

    /**
     * Create a scheduled payment with given attributes
     *
     * @param array $attributes
     *
     * @return ScheduledPayment
     */
    public function create(array $attributes): ScheduledPayment;

    /**
     * Update the given scheduled payment with given attributes
     *
     * @param ScheduledPayment $payment
     * @param array $attributes
     *
     * @return ScheduledPayment
     */
    public function update(ScheduledPayment $payment, array $attributes): ScheduledPayment;

    /**
     * Search for a duplicate scheduled payment based on the given parameters
     *
     * @param string $accountId
     * @param string $paymentMethodId
     * @param ScheduledPaymentTriggerEnum $trigger
     * @param int $amount
     * @param array $metadata
     *
     * @return ScheduledPayment|null
     */
    public function findDuplicate(
        string $accountId,
        string $paymentMethodId,
        ScheduledPaymentTriggerEnum $trigger,
        int $amount,
        array $metadata
    ): ScheduledPayment|null;
}
