<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\AuthorizePaymentResultDto;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\PaymentProcessorAuthorizationAndCaptureTrait;
use App\Api\Traits\PaymentValidationTrait;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Events\SuspendedPaymentProcessedEvent;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\PaymentProcessor;
use DB;
use Illuminate\Contracts\Container\BindingResolutionException;
use Log;
use Throwable;

class AuthorizeAndCaptureSuspendedPaymentHandler
{
    use RetrieveGatewayForPaymentMethodTrait;
    use PaymentValidationTrait;
    use PaymentProcessorAuthorizationAndCaptureTrait;

    private Payment $payment;

    private AuthorizeAndCapturePaymentCommand $command;

    private PaymentMethod|null $paymentMethod;

    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param PaymentRepository $paymentRepository
     * @param PaymentProcessorRepository $paymentProcessorRepository
     * @param PaymentProcessor $paymentProcessor
     */
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentProcessorRepository $paymentProcessorRepository,
        private readonly PaymentProcessor $paymentProcessor
    ) {
    }

    /**
     * @param AuthorizeAndCapturePaymentCommand $command
     *
     * @throws BindingResolutionException
     * @throws PaymentValidationException
     * @throws UnsupportedValueException
     * @throws ResourceNotFoundException
     * @throws Throwable
     *
     * @return AuthorizePaymentResultDto
     */
    public function handle(AuthorizeAndCapturePaymentCommand $command): AuthorizePaymentResultDto
    {
        $this->payment = $this->paymentRepository->find(paymentId: $command->paymentId, relations: ['paymentMethod', 'status']);
        $this->paymentMethod = $this->payment->paymentMethod;

        if ($this->payment->payment_status_id !== PaymentStatusEnum::SUSPENDED->value) {
            throw new PaymentValidationException(
                message: __('messages.payment.suspended_payments_processing.invalid_status'),
                errors: [__('messages.payment.suspended_payments_processing.not_suspended', ['id' => $command->paymentId])]
            );
        }

        $this->command = $command;

        $this->retrieveAndValidatePaymentMethodExistsAndBelongsToAccount();
        $this->getGatewayInstanceBasedOnPaymentMethod();

        Log::info(__('messages.payment.suspended_payments_processing.capturing'), ['paymentId' => $command->paymentId]);
        DB::transaction(callback: function () {
            $this->callPaymentProcessorAuthorizationAndCapture();
            $this->handleOperationResult();
        });
        Log::info(__('messages.payment.suspended_payments_processing.captured'), ['paymentId' => $command->paymentId]);

        return new AuthorizePaymentResultDto(
            status: PaymentStatusEnum::from($this->payment->payment_status_id),
            paymentId: $this->payment->id,
            transactionId: $this->operationResult->transactionId,
            message: $this->operationResult->message,
        );
    }

    /**
     * @throws \Exception
     */
    private function handleOperationResult(): void
    {
        $paymentStatus = $this->operationResult->isSuccess ? PaymentStatusEnum::CAPTURED : PaymentStatusEnum::DECLINED;
        $this->paymentRepository->update(payment: $this->payment, attributes: ['payment_status_id' => $paymentStatus->value]);
        Log::info(__('messages.payment.suspended_payments_processing.updated'), [
            'paymentId' => $this->payment->id,
            'status' => $paymentStatus->value
        ]);

        SuspendedPaymentProcessedEvent::dispatch(
            $this->payment->account,
            $this->paymentMethod,
            $this->payment,
            $this->payment->originalPayment
        );
    }
}
