<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentTerminatedEvent;
use App\Models\Payment;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Aptive\Attribution\Traits\BuildsURN;

class TerminatePaymentHandler
{
    use BuildsURN;

    /**
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        protected readonly PaymentRepository $paymentRepository
    ) {
    }

    /**
     * @param string $paymentId
     *
     * @throws ResourceNotFoundException
     * @throws UnprocessableContentException
     *
     * @return Payment
     */
    public function handle(string $paymentId): Payment
    {
        $payment = $this->paymentRepository->find(paymentId: $paymentId);

        if ($payment->payment_status_id === PaymentStatusEnum::TERMINATED->value) {
            throw new UnprocessableContentException(
                message: __('messages.payment.already_terminated', ['id' => $payment->id]),
            );
        }

        if ($payment->payment_status_id !== PaymentStatusEnum::SUSPENDED->value) {
            throw new UnprocessableContentException(
                message: __('messages.payment.suspended_payments_only'),
            );
        }

        $result = $this->paymentRepository->update(payment: $payment, attributes: [
            'payment_status_id' => PaymentStatusEnum::TERMINATED->value,
            'terminated_at' => now(),
            'terminated_by' => $this->buildUserUrn(),
        ]);

        PaymentTerminatedEvent::dispatch(
            $result->account,
            $result->paymentMethod,
            $result,
            $result->originalPayment,
        );

        return $result;
    }

    protected function buildUserUrn(): string|null
    {
        return self::buildUrn();
    }

}
