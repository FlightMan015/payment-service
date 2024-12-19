<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentInvoice;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractModelTest;

class PaymentInvoiceTest extends AbstractModelTest
{
    #[Test]
    public function payment_invoice_belongs_to_payment(): void
    {
        $payment = Payment::factory()->create();
        $invoice = Invoice::factory()->create();
        $paymentInvoice = PaymentInvoice::factory()->for($payment)->for($invoice)->create();

        $this->assertInstanceOf(expected: Payment::class, actual: $paymentInvoice->payment);
        $this->assertSame($payment->id, $paymentInvoice->payment_id);
        $this->assertSame($invoice->id, $paymentInvoice->invoice_id);
    }

    protected function getTableName(): string
    {
        return 'billing.payment_invoice_allocations';
    }

    protected function getColumnList(): array
    {
        return [
            'payment_id',
            'invoice_id',
            'amount',
            'pestroutes_payment_id',
            'pestroutes_invoice_id',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
            'tax_amount',
        ];
    }
}
