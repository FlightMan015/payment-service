<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Api\Traits\SortableTrait;
use App\Models\Transaction;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DatabasePaymentTransactionRepository implements PaymentTransactionRepository
{
    use SortableTrait;

    private array $allowedSorts = [
        'created_at',
        'updated_at',
        'gateway_response_code',
        'gateway_transaction_id',
        'transaction_type_id',
    ];

    /** @inheritDoc */
    public function filter(array $filter = [], array $columns = ['*']): LengthAwarePaginator
    {
        $query = Transaction::select($columns)->with(relations: ['type', 'payment'])->select();

        foreach ($filter as $key => $value) {
            $query = $this->filterBySingleKey(query: $query, key: $key, value: $value);
        }

        $this->sort(query: $query, filter: $filter);

        return $query->paginate(perPage: $filter['per_page'], page: $filter['page']);
    }

    private function filterBySingleKey(Builder|Transaction $query, string $key, mixed $value): Builder|Transaction
    {
        return match ($key) {
            'payment_id' => $query->wherePaymentId($value),
            default => $query,
        };
    }

    /**
     * @inheritDoc
     */
    public function find(string $paymentId, string $transactionId, array $columns = ['*']): Transaction
    {
        return Transaction::select($columns)->wherePaymentId($paymentId)->with(relations: 'type')->findOr(
            id: $transactionId,
            callback: static fn () => throw new ResourceNotFoundException(
                message: __('messages.payment_transaction.not_found', ['id' => $transactionId])
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function findById(string $transactionId, array $columns = ['*']): Transaction
    {
        return Transaction::select($columns)->with(relations: 'type')->findOr(
            id: $transactionId,
            callback: static fn () => throw new ResourceNotFoundException(
                message: __('messages.payment_transaction.not_found', ['id' => $transactionId])
            )
        );
    }

    /** @inheritDoc */
    public function create(array $attributes): Transaction
    {
        return Transaction::create(attributes: $attributes);
    }

    /** @inheritDoc */
    public function update(Transaction $transaction, array $attributes): Transaction
    {
        $transaction->update(attributes: $attributes);

        return $transaction;
    }
}
