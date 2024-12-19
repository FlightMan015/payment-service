<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\CancelScheduledPaymentResultDto;
use App\Api\Exceptions\PaymentCancellationFailedException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;

class CancelScheduledPaymentHandler
{
    private ScheduledPayment $scheduledPayment;

    /**
     * @param ScheduledPaymentRepository $scheduledPaymentRepository
     */
    public function __construct(
        private readonly ScheduledPaymentRepository $scheduledPaymentRepository,
    ) {
    }

    /**
     * @param string $scheduledPaymentId
     *
     * @throws ResourceNotFoundException
     * @throws \Throwable
     *
     * @return CancelScheduledPaymentResultDto
     */
    public function handle(string $scheduledPaymentId): CancelScheduledPaymentResultDto
    {
        $this->retrieveOriginalRecord(scheduledPaymentId: $scheduledPaymentId);
        $this->validatePaymentState();

        $this->scheduledPaymentRepository->update(
            payment: $this->scheduledPayment,
            attributes: [
                'status_id' => ScheduledPaymentStatusEnum::CANCELLED->value,
            ],
        );

        return new CancelScheduledPaymentResultDto(
            isSuccess: true,
            status: ScheduledPaymentStatusEnum::from($this->scheduledPayment->status_id),
        );
    }

    /**
     * @param string $scheduledPaymentId
     *
     * @throws ResourceNotFoundException
     */
    private function retrieveOriginalRecord(string $scheduledPaymentId): void
    {
        $this->scheduledPayment = $this->scheduledPaymentRepository->find(scheduledPaymentId: $scheduledPaymentId);
    }

    /**
     * @throws PaymentCancellationFailedException
     */
    private function validatePaymentState(): void
    {
        $hasValidStatus = $this->scheduledPayment->status_id === ScheduledPaymentStatusEnum::PENDING->value;

        if (!$hasValidStatus) {
            throw new PaymentCancellationFailedException(message: __('messages.scheduled_payment.invalid_status_for_cancellation'));
        }
    }
}
