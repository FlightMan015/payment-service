<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Models\Payment;
use App\Models\PaymentInvoice;

interface PaymentInvoiceRepository
{
    /**
     * Creating invoice record for the given payment
     *
     * @param Payment $payment
     * @param array $attributes
     *
     * @return PaymentInvoice
     */
    public function createForPayment(Payment $payment, array $attributes): PaymentInvoice;
}
