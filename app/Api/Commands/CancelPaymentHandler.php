<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\CancelPaymentResultDto;
use App\Api\Exceptions\InvalidPaymentStateException;
use App\Api\Exceptions\MissingGatewayException;
use App\Api\Exceptions\PaymentCancellationFailedException;
use App\Api\Exceptions\PaymentTransactionNotFoundException;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\ChecksThatPaymentWasProcessedInGatewayTrait;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Helpers\PaymentOperationValidationHelper;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Money\Currency;
use Money\Money;

class CancelPaymentHandler
{
    use RetrieveGatewayForPaymentMethodTrait;
    use ChecksThatPaymentWasProcessedInGatewayTrait;

    private PaymentMethod|null $paymentMethod;
    private Transaction|null $originalTransaction;
    private bool $isSuccess;

    /**
     * @param PaymentRepository $paymentRepository
     * @param PaymentProcessor $paymentProcessor
     */
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentProcessor $paymentProcessor
    ) {
    }

    /**
     * @param string $paymentId
     *
     * @throws InvalidPaymentStateException
     * @throws PaymentCancellationFailedException
     * @throws ResourceNotFoundException
     * @throws \Throwable
     *
     * @return CancelPaymentResultDto
     */
    public function handle(string $paymentId): CancelPaymentResultDto
    {
        $this->retrieveOriginalPaymentRecord(paymentId: $paymentId);
        $this->validatePaymentState();
        $this->validatePaymentMethod();
        $this->processPaymentCancellation();

        return new CancelPaymentResultDto(
            isSuccess: $this->isSuccess,
            status: PaymentStatusEnum::from($this->payment->payment_status_id),
            transactionId: $this->paymentProcessor->getTransactionLog()?->id,
        );
    }

    /**
     * @param string $paymentId
     *
     * @throws ResourceNotFoundException
     */
    private function retrieveOriginalPaymentRecord(string $paymentId): void
    {
        $this->payment = $this->paymentRepository->find(paymentId: $paymentId);
    }

    /**
     * @throws PaymentCancellationFailedException
     */
    private function validatePaymentState(): void
    {
        $hasValidStatus = PaymentOperationValidationHelper::isValidPaymentStatusForOperation(
            payment: $this->payment,
            operation: OperationEnum::CANCEL
        );

        if (!$hasValidStatus) {
            throw new PaymentCancellationFailedException(message: __('messages.operation.cancel.payment_invalid_status'));
        }

        if (!$this->paymentGatewayAllowsCancellation()) {
            throw new PaymentCancellationFailedException(message: __('messages.operation.cancel.cancellation_cannot_be_processed_for_gateway'));
        }

        if ($this->paymentWasAlreadyProcessedInGateway()) {
            throw new PaymentCancellationFailedException(message: __('messages.operation.cancel.already_fully_processed_in_gateway'));
        }
    }

    /**
     * @throws InvalidPaymentStateException
     */
    private function validatePaymentMethod(): void
    {
        if (is_null($this->paymentMethod = $this->payment->paymentMethod)) {
            throw new InvalidPaymentStateException(
                message: __('messages.operation.cancel.original_payment_method_not_found')
            );
        }
    }

    private function paymentGatewayAllowsCancellation(): bool
    {
        return PaymentGatewayEnum::from(value: $this->payment->payment_gateway_id)->isRealGateway();
    }

    /**
     * @throws PaymentCancellationFailedException
     * @throws \Throwable
     */
    private function processPaymentCancellation(): void
    {
        try {
            DB::transaction(callback: function () {
                $this->processPaymentCancellationInGateway();

                if (!$this->isSuccess) {
                    Log::error('Payment cancellation failed, error: ' . $this->paymentProcessor->getError());
                    throw new \RuntimeException(message: $this->paymentProcessor->getError());
                }

                $this->paymentRepository->updateStatus(payment: $this->payment, paymentStatus: PaymentStatusEnum::CANCELLED);
            });
        } catch (\Throwable $ex) {
            throw new PaymentCancellationFailedException(
                message: __('messages.operation.cancel.gateway_error', ['message' => $ex->getMessage()])
            );
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws PaymentTransactionNotFoundException
     * @throws UnsupportedValueException
     * @throws MissingGatewayException
     */
    private function processPaymentCancellationInGateway(): void
    {
        $this->getPaymentTransaction();

        $this->paymentRepository->updateStatus(payment: $this->payment, paymentStatus: PaymentStatusEnum::CANCELLING);
        $this->getGatewayInstanceBasedOnPaymentMethod();
        $this->paymentProcessor->setGateway(gateway: $this->gateway);

        $this->paymentProcessor->populate(populatedData: [
            OperationFields::REFERENCE_TRANSACTION_ID->value => $this->originalTransaction->gateway_transaction_id,
            OperationFields::REFERENCE_ID->value => $this->payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($this->payment->type->id),
            OperationFields::AMOUNT->value => new Money(
                amount: $this->payment->amount,
                currency: new Currency($this->payment->currency_code)
            ),
        ]);

        $this->isSuccess = $this->paymentProcessor->cancel();
    }

    /**
     * @throws PaymentTransactionNotFoundException
     */
    private function getPaymentTransaction(): void
    {
        $this->originalTransaction = $this->paymentRepository->transactionForOperation(
            payment: $this->payment,
            operation: OperationEnum::forPaymentStatus(PaymentStatusEnum::from($this->payment->payment_status_id))
        );

        if (is_null($this->originalTransaction)) {
            throw new PaymentTransactionNotFoundException(message: __('messages.operation.cancel.missing_original_transaction'));
        }
    }
}
