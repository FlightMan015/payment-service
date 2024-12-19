<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\TransactionType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractModelTest;

class TransactionTest extends AbstractModelTest
{
    protected function getTableName(): string
    {
        return 'billing.transactions';
    }

    protected function getColumnList(): array
    {
        return [
            'id',
            'payment_id',
            'transaction_type_id',
            'raw_request_log',
            'raw_response_log',
            'gateway_transaction_id',
            'gateway_response_code',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    #[Test]
    public function transaction_belongs_to_payment(): void
    {
        $payment = Payment::factory()->create();
        $transaction = Transaction::factory()->for($payment)->create();

        $this->assertInstanceOf(expected: Payment::class, actual: $transaction->payment);
        $this->assertSame($payment->id, $transaction->payment_id);
    }

    #[Test]
    public function transaction_belongs_to_type(): void
    {
        $type = TransactionType::inRandomOrder()->first();
        $payment = Payment::factory()->create();
        $transaction = Transaction::factory()->for($payment)->create(attributes: ['transaction_type_id' => $type->id]);

        $this->assertInstanceOf(expected: TransactionType::class, actual: $transaction->type);
        $this->assertSame($type->id, $transaction->transaction_type_id);
    }
}
