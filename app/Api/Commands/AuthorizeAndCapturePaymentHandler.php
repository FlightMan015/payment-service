<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\AuthorizePaymentResultDto;
use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\PaymentProcessorAuthorizationAndCaptureTrait;
use App\Api\Traits\PaymentValidationTrait;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\DB;

class AuthorizeAndCapturePaymentHandler
{
    use PaymentValidationTrait;
    use RetrieveGatewayForPaymentMethodTrait;
    use PaymentProcessorAuthorizationAndCaptureTrait;

    private AuthorizeAndCapturePaymentCommand $command;
    private PaymentMethod|null $paymentMethod;
    private Payment $payment;
    private PaymentProcessorResultDto $operationResult;

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
     * @throws \Throwable
     *
     * @return AuthorizePaymentResultDto
     */
    public function handle(AuthorizeAndCapturePaymentCommand $command): AuthorizePaymentResultDto
    {
        $this->command = $command;

        $this->retrieveAndValidatePaymentMethodExistsAndBelongsToAccount();
        $this->getGatewayInstanceBasedOnPaymentMethod();

        DB::transaction(callback: function () {
            $this->createDatabasePaymentRecord();
            $this->callPaymentProcessorAuthorizationAndCapture();
            $this->handleOperationResult();
        });

        return new AuthorizePaymentResultDto(
            status: PaymentStatusEnum::from($this->payment->payment_status_id),
            paymentId: $this->payment->id,
            transactionId: $this->operationResult->transactionId,
            message: $this->operationResult->message,
        );
    }

    private function createDatabasePaymentRecord(): void
    {
        $this->payment = $this->paymentRepository->create(
            attributes: array_merge($this->command->toArray(), [
                'account_id' => $this->paymentMethod->account->id,
                'payment_type_id' => $this->paymentMethod->payment_type_id,
                'payment_status_id' => PaymentStatusEnum::AUTH_CAPTURING->value,
                'payment_method_id' => $this->paymentMethod->id,
                'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
                'currency_code' => 'USD',
                'amount' => $this->command->amount,
                'applied_amount' => 0,
                'processed_at' => now(),
            ])
        );
    }

    /**
     * @throws \Exception
     */
    private function handleOperationResult(): void
    {
        $paymentStatus = $this->operationResult->isSuccess ? PaymentStatusEnum::CAPTURED : PaymentStatusEnum::DECLINED;
        $this->paymentRepository->update(payment: $this->payment, attributes: ['payment_status_id' => $paymentStatus->value]);

        PaymentAttemptedEvent::dispatch(
            $this->payment,
            PaymentProcessingInitiator::API_REQUEST,
            OperationEnum::AUTH_CAPTURE
        );
    }
}
