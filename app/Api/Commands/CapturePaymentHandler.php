<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\CapturePaymentResultDto;
use App\Api\Exceptions\InconsistentDataException;
use App\Api\Exceptions\InvalidPaymentStateException;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentTransactionNotFoundException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException as ApiResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Helpers\PaymentOperationValidationHelper;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\InvalidOperationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;

class CapturePaymentHandler
{
    use RetrieveGatewayForPaymentMethodTrait;

    private bool $isSuccess = false;
    private PaymentStatusEnum $status;
    private string|null $transactionId = null;
    private string|null $message = null;

    private Payment $payment;
    private PaymentMethod $paymentMethod;

    /**
     * @param PaymentRepository $repository
     * @param PaymentProcessorRepository $paymentProcessorRepository
     * @param PaymentProcessor $paymentProcessor
     * @param AccountRepository $accountRepository
     */
    public function __construct(
        private readonly PaymentRepository $repository,
        private readonly PaymentProcessorRepository $paymentProcessorRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly AccountRepository $accountRepository
    ) {
    }

    /**
     * @param string $paymentId
     *
     * @throws \Exception
     *
     * @return CapturePaymentResultDto
     */
    public function handle(string $paymentId): CapturePaymentResultDto
    {
        $this->validateAccountExistsForPayment(paymentId: $paymentId);
        $this->validatePaymentStatusForOperation();
        $this->processPayment();

        return new CapturePaymentResultDto(
            isSuccess: $this->isSuccess,
            status: $this->status,
            transactionId: $this->transactionId,
            message: $this->message
        );
    }

    /**
     * @param string $paymentId
     *
     * @throws UnprocessableContentException
     * @throws ApiResourceNotFoundException
     */
    private function validateAccountExistsForPayment(string $paymentId): void
    {
        $this->payment = $this->repository->find(
            paymentId: $paymentId,
            columns: [
                'id',
                'account_id',
                'payment_method_id',
                'payment_status_id',
                'amount',
                'currency_code',
                'processed_at',
            ],
        );

        if (is_null($this->payment->paymentMethod)) {
            throw new InvalidPaymentStateException(
                message: __('messages.operation.capture.original_payment_method_not_found')
            );
        }

        $this->paymentMethod = $this->payment->paymentMethod;

        if (!$this->accountRepository->exists(id: $this->paymentMethod->account_id)) {
            throw new UnprocessableContentException(message: __('messages.account.not_found'));
        }
    }

    /**
     * @throws PaymentValidationException
     */
    private function validatePaymentStatusForOperation(): void
    {
        if (!PaymentOperationValidationHelper::isValidPaymentStatusForOperation(
            payment: $this->payment,
            operation: OperationEnum::CAPTURE
        )) {
            throw new PaymentValidationException(
                message: __('messages.invalid_input'),
                errors: [__('messages.operation.invalid_payment_status')]
            );
        }
    }

    /**
     * @throws UnprocessableContentException
     * @throws \Exception
     */
    private function processPayment(): void
    {
        $this->getGatewayInstanceBasedOnPaymentMethod();

        if (PaymentOperationValidationHelper::isPaymentExpiredForOperation(
            payment: $this->payment,
            operation: OperationEnum::CAPTURE
        )) {
            $this->cancelPayment();
        } else {
            $this->capturePayment();
        }
    }

    /**
     * @throws UnprocessableContentException
     * @throws InconsistentDataException
     */
    private function cancelPayment(): void
    {
        try {
            $paymentResult = $this->paymentProcessorRepository->cancel(
                paymentProcessor: $this->paymentProcessor,
                payment: $this->payment,
                gateway: $this->gateway,
            );
        } catch (PaymentTransactionNotFoundException) {
            throw new InconsistentDataException(message: __('messages.operation.capture.missing_authorize_transaction'));
        }
        $this->isSuccess = $paymentResult->isSuccess;
        $this->status = $paymentResult->isSuccess ? PaymentStatusEnum::CANCELLED : PaymentStatusEnum::DECLINED;
        $this->payment->update(attributes: ['payment_status_id' => $this->status->value]);

        throw new UnprocessableContentException(message: __('messages.operation.capture.payment_expired'));
    }

    /**
     * @throws \Exception
     */
    private function capturePayment(): void
    {
        try {
            $paymentResult = $this->paymentProcessorRepository->capture(
                paymentProcessor: $this->paymentProcessor,
                payment: $this->payment,
                gateway: $this->gateway,
            );
        } catch (CreditCardValidationException | InvalidOperationException $exception) {
            throw new UnprocessableContentException(message: $exception->getMessage());
        } catch (OperationValidationException $exception) {
            throw new PaymentProcessingValidationException(
                message: $exception->getMessage(),
                context: [
                    'payment_id' => $this->payment->id,
                    'method_id' => $this->paymentMethod->id,
                    'amount' => $this->payment->amount,
                    'account_id' => $this->payment->account_id,
                ]
            );
        } catch (PaymentTransactionNotFoundException) {
            throw new InconsistentDataException(message: __('messages.operation.capture.missing_authorize_transaction'));
        }

        $this->isSuccess = $paymentResult->isSuccess;
        $this->status = $paymentResult->isSuccess ? PaymentStatusEnum::CAPTURED : PaymentStatusEnum::DECLINED;

        $this->payment->update(attributes: [
            'payment_status_id' => $this->status->value,
        ]);

        if (!$paymentResult->isSuccess) {
            PaymentAttemptedEvent::dispatch($this->payment, PaymentProcessingInitiator::API_REQUEST, OperationEnum::CAPTURE);

            throw new UnprocessableContentException(message: __(
                key: 'messages.operation.payment_cannot_processed_through_gateway',
                replace: ['message' => $paymentResult->message ? " Error $paymentResult->message" : '']
            ));
        }

        $this->transactionId = $paymentResult->transactionId ?? null;
        $this->message = $paymentResult->message ?? null;

        if ($this->status === PaymentStatusEnum::CAPTURED) {
            PaymentAttemptedEvent::dispatch($this->payment, PaymentProcessingInitiator::API_REQUEST, OperationEnum::CAPTURE);
        }
    }
}
