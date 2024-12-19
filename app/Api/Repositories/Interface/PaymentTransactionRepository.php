<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentTransactionRepository
{
    /**
     * Retrieve a list of transactions by conditions
     *
     * @param array $filter
     * @param array $columns
     *
     * @return LengthAwarePaginator<Transaction>
     */
    public function filter(array $filter = [], array $columns = ['*']): LengthAwarePaginator;

    /**
     * Retrieve transaction's detail that belongs to the give payment id by given id
     *
     * @param string $paymentId
     * @param string $transactionId
     * @param array $columns
     *
     * @return Transaction
     */
    public function find(string $paymentId, string $transactionId, array $columns = ['*']): Transaction;

    /**
     * Retrieve transaction's detail by given id
     *
     * @param string $transactionId
     * @param array $columns
     *
     * @return Transaction
     */
    public function findById(string $transactionId, array $columns = ['*']): Transaction;

    /**
     * Create payment transaction with given attributes
     *
     * @param array $attributes
     *
     * @return Transaction
     */
    public function create(array $attributes): Transaction;

    /**
     * Update the given transaction with given attributes
     *
     * @param Transaction $transaction
     * @param array $attributes
     *
     * @return Transaction
     */
    public function update(Transaction $transaction, array $attributes): Transaction;
}
