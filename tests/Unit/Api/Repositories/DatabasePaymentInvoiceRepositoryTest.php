<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Repositories;

use App\Api\Repositories\DatabasePaymentInvoiceRepository;
use App\Models\Payment;
use App\Models\PaymentInvoice;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class DatabasePaymentInvoiceRepositoryTest extends UnitTestCase
{
    #[Test]
    public function create_for_payment_calls_create_method_on_payment_invoices(): void
    {
        $payment = Mockery::mock(Payment::class);
        $paymentInvoice = Mockery::mock(PaymentInvoice::class);

        $attributes = ['applied_at' => now(), 'applied_amount' => 100];

        $payment->allows('invoices->create')->with($attributes)->andReturns($paymentInvoice);

        $result = (new DatabasePaymentInvoiceRepository())->createForPayment(payment: $payment, attributes: $attributes);

        $this->assertSame($paymentInvoice, $result);
    }
}
