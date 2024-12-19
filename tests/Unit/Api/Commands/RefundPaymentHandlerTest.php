<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\RefundPaymentCommand;
use App\Api\Commands\RefundPaymentHandler;
use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Exceptions\PaymentRefundFailedException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\Payment;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\Services\Payment\Refund\RefundPaymentServiceInterface;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class RefundPaymentHandlerTest extends UnitTestCase
{
    #[Test]
    public function it_returns_dto_from_refund_service(): void
    {
        $payment = Payment::factory()->withoutRelationships()->make();
        $command = new RefundPaymentCommand(paymentId: $payment->id);
        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('find')->willReturn($payment);

        $handler = new RefundPaymentHandler(paymentRepository: $paymentRepository);

        $refundService = $this->createMock(RefundPaymentServiceInterface::class);
        $refundService->method('refund')->willReturn(
            new RefundPaymentResultDto(
                isSuccess: true,
                status: PaymentStatusEnum::CREDITED,
                refundPaymentId: Str::uuid()->toString()
            )
        );

        $result = $handler->handle(refundPaymentCommand: $command, refundPaymentService: $refundService);

        $this->assertTrue($result->isSuccess);
    }

    #[Test]
    public function it_throws_exception_if_refund_service_throws_exception(): void
    {
        $payment = Payment::factory()->withoutRelationships()->make();
        $command = new RefundPaymentCommand(paymentId: $payment->id);
        $paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentRepository->method('find')->willReturn($payment);

        $handler = new RefundPaymentHandler(paymentRepository: $paymentRepository);

        $refundService = $this->createMock(RefundPaymentServiceInterface::class);
        $exception = new PaymentRefundFailedException('something went wrong with refund');
        $refundService->method('refund')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $handler->handle(refundPaymentCommand: $command, refundPaymentService: $refundService);
    }
}
