<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Repositories\Interface\PaymentInvoiceRepository;
use App\Models\Payment;
use App\Models\PaymentInvoice;

class DatabasePaymentInvoiceRepository implements PaymentInvoiceRepository
{
    /** @inheritDoc */
    public function createForPayment(Payment $payment, array $attributes): PaymentInvoice
    {
        return $payment->invoices()->create(attributes: $attributes);
    }
}
