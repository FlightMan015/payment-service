<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Api\DTO\RefundPaymentResultDto;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;

interface RefundPaymentServiceInterface
{
    /**
     * @param MakePaymentRefundDto $paymentRefundDto
     *
     * @return RefundPaymentResultDto
     */
    public function refund(MakePaymentRefundDto $paymentRefundDto): RefundPaymentResultDto;
}
