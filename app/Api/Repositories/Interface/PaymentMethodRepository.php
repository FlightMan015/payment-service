<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Models\AccountUpdaterAttempt;
use App\Models\PaymentMethod;
use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentMethodRepository
{
    /**
     * Retrieve list of payments by conditions
     *
     * @param array $filter
     * @param array $columns
     * @param bool $withSoftDeleted
     * @param array $relationsFilter
     * @param array $withRelations
     *
     * @return LengthAwarePaginator<PaymentMethod>
     */
    public function filter(
        array $filter = [],
        array $columns = ['*'],
        bool $withSoftDeleted = false,
        array $relationsFilter = [],
        array $withRelations = []
    ): LengthAwarePaginator;

    /**
     * Retrieve payment method detail by given id
     *
     * @param string $paymentMethodId
     * @param array $columns
     *
     * @throws ResourceNotFoundException
     *
     * @return PaymentMethod
     */
    public function find(string $paymentMethodId, array $columns = ['*']): PaymentMethod;

    /**
     * Retrieve payment method detail by given external ref id
     *
     * @param int $externalRefId
     * @param array $columns
     *
     * @throws ResourceNotFoundException
     *
     * @return PaymentMethod
     */
    public function findByExternalRefId(int $externalRefId, array $columns = ['*']): PaymentMethod;

    /**
     * Update all payment methods attribute for the given account id
     *
     * @param string $accountId
     * @param array $attributes
     *
     * @return int Number of affected records
     */
    public function updateForAccount(string $accountId, array $attributes = []): int;

    /**
     * Retrieve primary payment method for the given account id
     *
     * @param string $accountId
     *
     * @return PaymentMethod|null
     */
    public function findPrimaryForAccount(string $accountId): PaymentMethod|null;

    /**
     * Retrieve autopay payment method for the given account id
     *
     * @param string $accountId
     *
     * @return PaymentMethod|null
     */
    public function findAutopayMethodForAccount(string $accountId): PaymentMethod|null;

    /**
     * @param AccountUpdaterAttempt $attempt
     * @param array $relationAttributes
     *
     * @return PaymentMethod|null
     */
    public function findByAttemptRelation(AccountUpdaterAttempt $attempt, array $relationAttributes): PaymentMethod|null;

    /**
     * @param string $accountId
     *
     * @return bool whether the account has any payment method or not
     */
    public function existsForAccount(string $accountId): bool;

    /**
     * @param array $attributes
     *
     * @return PaymentMethod
     */
    public function create(array $attributes): PaymentMethod;

    /**
     * Method used to save the current instance
     *
     * @param PaymentMethod $paymentMethod
     * @param array $additionalAttributes
     *
     * @return PaymentMethod
     */
    public function save(PaymentMethod $paymentMethod, array $additionalAttributes = []): PaymentMethod;

    /**
     * @param PaymentMethod $paymentMethod
     * @param array $attributes
     *
     * @return PaymentMethod
     */
    public function update(PaymentMethod $paymentMethod, array $attributes): PaymentMethod;

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return bool
     */
    public function softDelete(PaymentMethod $paymentMethod): bool;

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return void
     */
    public function makePrimary(PaymentMethod $paymentMethod): void;

    /**
     * @param PaymentMethod $paymentMethod
     * @param \DateTimeInterface|null $paymentHoldDate
     *
     * @return bool
     */
    public function setPaymentHoldDate(PaymentMethod $paymentMethod, \DateTimeInterface|null $paymentHoldDate): bool;
}
