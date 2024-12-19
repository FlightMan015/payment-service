<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Exceptions\PaymentRefundFailedException;
use App\Api\Exceptions\PaymentTransactionNotFoundException;
use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\FailedRefundPaymentRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Events\RefundPaymentFailedEvent;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Money\Currency;
use Money\Money;

class RefundElectronicPaymentService extends AbstractRefundPaymentService
{
    use RetrieveGatewayForPaymentMethodTrait;

    public const int ELECTRONIC_REFUND_DAYS_TECHNICAL_LIMIT = 45;

    private PaymentMethod|null $paymentMethod;

    /**
     * @param PaymentRepository $paymentRepository
     * @param PaymentProcessor $paymentProcessor
     * @param FailedRefundPaymentRepository $failedRefundPaymentRepository
     */
    public function __construct(
        protected PaymentRepository $paymentRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly FailedRefundPaymentRepository $failedRefundPaymentRepository
    ) {
        parent::__construct(paymentRepository: $paymentRepository);
    }

    /**
     * @throws PaymentRefundFailedException
     */
    protected function checkIfPaymentCanBeRefunded(): void
    {
        $this->validateOriginalPaymentMethod();
        $this->validateOriginalPaymentState();
        $this->validateOriginalPaymentWasNotPreviouslyRefunded();
        $this->validateOriginalPaymentDateAllowsRefund();
        $this->validateRefundAmountDoesNotExceedOriginalPaymentAmount();
    }

    /**
     * @throws PaymentRefundFailedException
     */
    private function validateOriginalPaymentMethod(): void
    {
        $this->paymentMethod = $this->paymentRefundDto->originalPayment->paymentMethod;

        if (is_null($this->paymentMethod)) {
            throw new PaymentRefundFailedException(
                message: __('messages.operation.refund.original_payment_method_not_found')
            );
        }

        if (
            !in_array(
                $this->paymentRefundDto->originalPayment->payment_type,
                [PaymentTypeEnum::ACH, PaymentTypeEnum::CC]
            )
        ) {
            throw new PaymentRefundFailedException(
                message: __('messages.operation.refund.automatic_refund_cannot_be_processed_for_gateway')
            );
        }
    }

    /**
     * @throws PaymentRefundFailedException
     */
    private function validateOriginalPaymentState(): void
    {
        $this->validateOriginalPaymentStatus();

        if (!$this->paymentRefundDto->originalPayment->wasAlreadyProcessedInGateway()) {
            throw new PaymentRefundFailedException(
                message: __(
                    'messages.operation.refund.not_fully_processed_in_gateway_yet'
                )
            );
        }
    }

    /**
     * @throws PaymentRefundFailedException
     */
    private function validateOriginalPaymentDateAllowsRefund(): void
    {
        $paymentProcessedAt = Carbon::parse(time: $this->paymentRefundDto->originalPayment->processed_at);
        $daysRefundAllowed = is_null($this->paymentRefundDto->daysRefundAllowed)
            ? self::ELECTRONIC_REFUND_DAYS_TECHNICAL_LIMIT
            : min($this->paymentRefundDto->daysRefundAllowed, self::ELECTRONIC_REFUND_DAYS_TECHNICAL_LIMIT);
        $maxAllowedRefundDate = Carbon::now()->subDays(value: $daysRefundAllowed);

        if ($paymentProcessedAt->isBefore(date: $maxAllowedRefundDate)) {
            throw new PaymentRefundFailedException(
                message: __('messages.operation.refund.automatic_refund_cannot_be_processed', ['days' => $daysRefundAllowed])
            );
        }
    }

    /**
     * @throws \Throwable
     */
    protected function processPaymentRefund(): RefundPaymentResultDto
    {
        DB::transaction(callback: function () use (&$refundResult) {
            $this->createDatabaseRefundPaymentRecord();
            $this->populatePaymentProcessor($this->getOriginalPaymentCaptureTransaction());
            $refundResult = $this->paymentProcessor->credit();
            $this->paymentRepository->updateStatus(
                payment: $this->refundPayment,
                paymentStatus: $refundResult ? PaymentStatusEnum::CREDITED : PaymentStatusEnum::DECLINED
            );
            $this->processRefundResult(isSuccess: $refundResult);
        });

        return new RefundPaymentResultDto(
            isSuccess: $refundResult,
            status: PaymentStatusEnum::from($this->refundPayment->payment_status_id),
            refundPaymentId: $this->refundPayment->id,
            transactionId: $this->paymentProcessor->getTransactionLog()?->id,
            errorMessage: $this->paymentProcessor->getError(),
        );
    }

    private function createDatabaseRefundPaymentRecord(): void
    {
        if ($this->paymentRefundDto->existingRefundPayment) {
            // skip creating a new refund payment if it already exists and we just re-process it
            $this->refundPayment = $this->paymentRefundDto->existingRefundPayment;
            return;
        }

        $overrideAttributes = [
            'amount' => $this->paymentRefundDto->refundAmount,
            'payment_status_id' => PaymentStatusEnum::CREDITING->value,
            'processed_at' => now(),
        ];

        if ($this->paymentRefundDto->externalRefId) {
            $overrideAttributes['external_ref_id'] = $this->paymentRefundDto->externalRefId;
        }

        $this->refundPayment = $this->paymentRepository->cloneAndCreateFromExistingPayment(
            payment: $this->paymentRefundDto->originalPayment,
            overriddenAttributes: $overrideAttributes,
        );
    }

    /**
     * @throws PaymentTransactionNotFoundException
     */
    private function getOriginalPaymentCaptureTransaction(): Transaction
    {
        $captureTransaction = $this->paymentRepository->transactionForOperation(
            payment: $this->paymentRefundDto->originalPayment,
            operation: [OperationEnum::CAPTURE, OperationEnum::AUTH_CAPTURE]
        );

        if (is_null($captureTransaction)) {
            throw new PaymentTransactionNotFoundException(
                message: __('messages.operation.refund.missing_capture_transaction')
            );
        }

        return $captureTransaction;
    }

    /**
     * @param Transaction $captureTransaction
     *
     * @throws BindingResolutionException
     * @throws UnsupportedValueException
     *
     * @return void
     */
    private function populatePaymentProcessor(Transaction $captureTransaction): void
    {
        $this->getGatewayInstanceBasedOnPaymentMethod();
        $this->paymentProcessor->setGateway(gateway: $this->gateway);
        $this->paymentProcessor->populate(populatedData: [
            OperationFields::REFERENCE_TRANSACTION_ID->value => $captureTransaction->gateway_transaction_id,
            OperationFields::AMOUNT->value => new Money(
                amount: $this->refundPayment->amount,
                currency: new Currency($this->refundPayment->currency_code)
            ),
            OperationFields::REFERENCE_ID->value => $this->refundPayment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($this->refundPayment->type->id),
        ]);
    }

    private function processRefundResult(bool $isSuccess): void
    {
        if (!$isSuccess) {
            $this->failedRefundPaymentRepository->create(attributes: [
                'original_payment_id' => $this->paymentRefundDto->originalPayment->id,
                'refund_payment_id' => $this->refundPayment->id,
                'account_id' => $this->paymentRefundDto->originalPayment->account_id,
                'amount' => $this->refundPayment->amount,
                'failed_at' => now(),
                'failure_reason' => $this->paymentProcessor->getError(),
            ]);

            RefundPaymentFailedEvent::dispatch($this->refundPayment);
        }
    }
}
