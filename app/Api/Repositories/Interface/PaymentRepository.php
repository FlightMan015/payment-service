<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PaymentRepository
{
    /**
     * Retrieve list of payments by conditions
     *
     * @param array $filter
     * @param array $columns
     *
     * @return LengthAwarePaginator<Payment>
     */
    public function filter(
        array $filter = [],
        array $columns = ['*'],
    ): LengthAwarePaginator;

    /**
     * Retrieve payment with ledger types by given id
     *
     * @param string $paymentId
     *
     * @throws ResourceNotFoundException
     * @throws UnprocessableContentException
     *
     * @return Payment
     */
    public function findWithLedgerTypes(string $paymentId): Payment;

    /**
     * Retrieve payment detail by given id
     *
     * @param string $paymentId
     * @param array $columns
     * @param array $relations
     *
     * @throws ResourceNotFoundException
     *
     * @return Payment
     */
    public function find(string $paymentId, array $columns = ['*'], array $relations = []): Payment;

    /**
     * Create payment with given attributes
     *
     * @param array $attributes
     *
     * @return Payment
     */
    public function create(array $attributes): Payment;

    /**
     * Update the given payment with given attributes
     *
     * @param Payment $payment
     * @param array $attributes
     *
     * @return Payment
     */
    public function update(Payment $payment, array $attributes): Payment;

    /**
     * Retrieves N ($limit) latest payments for the given payment method ($method)
     *
     * @param PaymentMethod $method
     * @param int $limit
     *
     * @return Collection<int, Payment>
     */
    public function getLatestPaymentsForPaymentMethod(PaymentMethod $method, int $limit): Collection;

    /**
     * Returns count of declined payments for the given payment method (for the given date - optional)
     *
     * @param PaymentMethod $method
     * @param \DateTimeInterface|null $date
     *
     * @return int
     */
    public function getDeclinedForPaymentMethodCount(PaymentMethod $method, \DateTimeInterface|null $date = null): int;

    /**
     * Clone payment, store it in database with overridden values and return new created model
     *
     * @param Payment $payment
     * @param array $overriddenAttributes
     *
     * @return Payment
     */
    public function cloneAndCreateFromExistingPayment(Payment $payment, array $overriddenAttributes = []): Payment;

    /**
     * Retrieve all payments for the given payment with CREDITED status
     *
     * @param Payment $payment
     *
     * @return Collection<int, Payment>
     */
    public function getCreditedChildPayments(Payment $payment): Collection;

    /**
     * Update payment's status
     *
     * @param Payment $payment
     * @param PaymentStatusEnum $paymentStatus
     *
     * @return Payment
     */
    public function updateStatus(Payment $payment, PaymentStatusEnum $paymentStatus): Payment;

    /**
     * @param Payment $payment
     * @param OperationEnum|array $operation
     *
     * @return Transaction|null the latest transaction for the given payment for the specific operation(s)
     */
    public function transactionForOperation(Payment $payment, array|OperationEnum $operation): Transaction|null;

    /**
     * Get latest successful payment for given invoices
     *
     * @param string $accountId
     * @param array $invoiceIds
     *
     * @return Payment|null
     */
    public function getLatestSuccessfulPaymentForInvoices(string $accountId, array $invoiceIds): Payment|null;

    /**
     * Get latest suspended/terminated payment for given original payment
     *
     * @param string $accountId
     * @param string $originalPaymentId
     *
     * @return Payment|null
     */
    public function getLatestSuspendedOrTerminatedPaymentForOriginalPayment(string $accountId, string $originalPaymentId): Payment|null;

    /**
     * Get terminated payment for given invoices
     *
     * @param string $accountId
     * @param array $invoiceIds
     *
     * @return Payment|null
     */
    public function getTerminatedPaymentForInvoices(string $accountId, array $invoiceIds): Payment|null;

    /**
     * Check if payment invoices match a list given invoiceIds
     *
     * @param Payment $payment
     * @param array $invoiceIds
     *
     * @return bool
     */
    public function checkIfPaymentMatchInvoices(Payment $payment, array $invoiceIds): bool;

    /**
     * Retrieve external refunds without transactions for the given area
     *
     * @param int $areaId
     * @param int $page
     * @param int $quantity
     *
     * @return LengthAwarePaginator<Payment>
     */
    public function getExternalRefundsWithoutTransactionsForArea(int $areaId, int $page, int $quantity): LengthAwarePaginator;

    /**
     * Get payments that have not been synchronized to Pest Routes
     *
     * @return Builder<Payment>
     */
    public function getNonSynchronisedPayments(): Builder;

    /**
     * Get ACH payments that were not fully settled within the specified timeframe
     *
     * @param \DateTimeInterface $processedAtFrom
     * @param \DateTimeInterface $processedAtTo
     * @param int $page
     * @param int $quantity
     * @param int $areaId
     *
     * @return LengthAwarePaginator<Payment>
     */
    public function getNotFullySettledAchPayments(
        \DateTimeInterface $processedAtFrom,
        \DateTimeInterface $processedAtTo,
        int $page,
        int $quantity,
        int $areaId,
    ): LengthAwarePaginator;
}
