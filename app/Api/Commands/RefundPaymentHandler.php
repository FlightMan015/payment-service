<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Services\Payment\Refund\DTO\MakePaymentRefundDto;
use App\Services\Payment\Refund\RefundPaymentServiceInterface;

class RefundPaymentHandler
{
    /**
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(private readonly PaymentRepository $paymentRepository)
    {
    }

    /**
     * @param RefundPaymentCommand $refundPaymentCommand
     * @param RefundPaymentServiceInterface $refundPaymentService
     *
     * @throws \Throwable
     *
     * @return RefundPaymentResultDto
     */
    public function handle(
        RefundPaymentCommand $refundPaymentCommand,
        RefundPaymentServiceInterface $refundPaymentService
    ): RefundPaymentResultDto {
        $payment = $this->paymentRepository->find(paymentId: $refundPaymentCommand->paymentId);

        $dto = new MakePaymentRefundDto(
            originalPayment: $payment,
            refundAmount: $refundPaymentCommand->amount ?? $payment->amount,
            daysRefundAllowed: $refundPaymentCommand->daysRefundAllowed,
            externalRefId: $refundPaymentCommand->externalRefId
        );

        return $refundPaymentService->refund(paymentRefundDto: $dto);
    }
}
