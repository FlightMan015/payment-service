<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\DatabasePaymentTransactionRepository;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabasePaymentTransactionRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private DatabasePaymentTransactionRepository $repository;
    private Transaction|null $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app()->make(DatabasePaymentTransactionRepository::class);
        $this->transaction = $this->createTransactionInDatabase();
    }

    private function createTransactionInDatabase(): Transaction
    {
        return Transaction::factory()->create();
    }

    #[Test]
    public function find_throws_not_found_exception_for_not_existing_entity(): void
    {
        $transactionId = Str::uuid()->toString();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.payment_transaction.not_found', ['id' => $transactionId]));
        $this->repository->find(paymentId: Str::uuid()->toString(), transactionId: $transactionId);
    }

    #[Test]
    public function find_return_correct_transaction(): void
    {
        $columns = [
            'id',
            'payment_id',
            'transaction_type_id',
            'created_at',
        ];
        $actual = $this->repository->find(
            paymentId: $this->transaction->payment_id,
            transactionId: $this->transaction->id,
            columns: $columns
        );

        $this->assertInstanceOf(Transaction::class, $actual);
        $this->assertNotTrue(array_diff($columns, array_keys($actual->toArray())));
    }

    #[Test]
    public function filter_method_returns_expected_pagination_result(): void
    {
        Transaction::query()->delete(); // delete all previously created records from DB
        $totalRecords = random_int(min: 10, max: 100);
        $perPage = random_int(min: 5, max: min($totalRecords, 20));
        $pagesExpected = (int)ceil($totalRecords / $perPage);

        /** @var Collection<int, Transaction> $entities */
        $entities = Transaction::factory()->count(count: $totalRecords)->create([
            'payment_id' => $this->transaction->payment_id,
        ]);

        $results = $this->repository->filter(filter: [
            'id' => $entities->pluck('id')->toArray(),
            'per_page' => $perPage,
            'page' => 1,
        ]);

        $this->assertCount(expectedCount: $perPage, haystack: $results);
        $this->assertSame(expected: $pagesExpected, actual: $results->lastPage());
        $this->assertSame(expected: $totalRecords, actual: $results->total());
    }

    #[Test]
    public function it_updates_corresponding_fields_if_correct_data_provided_for_transaction(): void
    {
        $data = [
            'gateway_transaction_id' => 9999,
        ];
        $actual = $this->repository->update(transaction: $this->transaction, attributes: $data);

        $this->assertInstanceOf(Transaction::class, $actual);
        $this->assertEquals($actual->gateway_transaction_id, 9999);
    }

    protected function getEntity(): Model
    {
        return $this->transaction;
    }

    protected function getRepository(): mixed
    {
        return $this->repository;
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->transaction);
        parent::tearDown();
    }
}
