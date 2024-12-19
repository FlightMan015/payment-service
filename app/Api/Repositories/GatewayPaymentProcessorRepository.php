<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Exceptions\MissingGatewayException;
use App\Api\Exceptions\PaymentTransactionNotFoundException;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\PaymentTransactionRepository;
use App\Events\PaymentReturnedEvent;
use App\Events\PaymentSettledEvent;
use App\Factories\PaymentGatewayFactory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\Log;
use Money\Currency;
use Money\Money;

class GatewayPaymentProcessorRepository implements PaymentProcessorRepository
{
    /**
     * @param PaymentRepository $paymentRepository
     * @param PaymentTransactionRepository $transactionRepository
     */
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentTransactionRepository $transactionRepository,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws MissingGatewayException
     */
    public function authorize(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto {
        $paymentProcessor = $this->populateProcessorData(paymentProcessor: $paymentProcessor, payment: $payment);
        $paymentProcessor->setGateway(gateway: $gateway);

        $result = new PaymentProcessorResultDto(isSuccess: false);
        $result->isSuccess = $paymentProcessor->authorize();
        $result->transactionId = $paymentProcessor->getTransactionLog()->id ?? null;
        $result->message = $paymentProcessor->getError();

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @throws PaymentTransactionNotFoundException
     * @throws MissingGatewayException
     */
    public function capture(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto {
        $transaction = $payment->transactionForOperation(operation: OperationEnum::AUTHORIZE);

        if (is_null($transaction)) {
            throw new PaymentTransactionNotFoundException(message: 'Transaction does not exist');
        }

        $paymentProcessor->populate(populatedData: [
            OperationFields::REFERENCE_TRANSACTION_ID->value => $transaction->gateway_transaction_id,
            OperationFields::AMOUNT->value => new Money(
                amount: $payment->amount,
                currency: new Currency($payment->currency_code)
            ),
            OperationFields::REFERENCE_ID->value => $payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($payment->paymentMethod->type->id),
        ]);
        $paymentProcessor->setGateway(gateway: $gateway);

        $result = new PaymentProcessorResultDto(isSuccess: false);
        $result->isSuccess = $paymentProcessor->capture();
        $result->transactionId = $paymentProcessor->getTransactionLog()->id ?? null;
        $result->message = $paymentProcessor->getError();

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @throws PaymentTransactionNotFoundException
     * @throws MissingGatewayException
     */
    public function cancel(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto {
        $transaction = $payment->transactionForOperation(operation: OperationEnum::AUTHORIZE);

        if ($transaction === null) {
            throw new PaymentTransactionNotFoundException(message: 'Transaction does not exist');
        }
        $paymentMethod = $payment->paymentMethod;

        $paymentProcessor->populate(populatedData: [
            OperationFields::REFERENCE_TRANSACTION_ID->value => $transaction->gateway_transaction_id,
            OperationFields::AMOUNT->value => new Money(
                amount: $payment->amount,
                currency: new Currency($payment->currency_code)
            ),
            OperationFields::REFERENCE_ID->value => $payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($paymentMethod->type->id),
        ]);
        $paymentProcessor->setGateway(
            gateway: $gateway
        );

        $result = new PaymentProcessorResultDto(isSuccess: false);
        $result->isSuccess = $paymentProcessor->cancel();
        $result->transactionId = $paymentProcessor->getTransactionLog()->id ?? null;
        $result->message = $paymentProcessor->getError();

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function authorizeAndCapture(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
        GatewayInterface $gateway
    ): PaymentProcessorResultDto {
        $paymentProcessor = $this->populateProcessorData(paymentProcessor: $paymentProcessor, payment: $payment);
        $paymentProcessor->setGateway(gateway: $gateway);

        $result = new PaymentProcessorResultDto(isSuccess: false);
        $result->isSuccess = $paymentProcessor->sale();
        $result->transactionId = $paymentProcessor->getTransactionLog()->id ?? null;
        $result->message = $paymentProcessor->getError() ?? $paymentProcessor->getException()?->getMessage();

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @throws PaymentTransactionNotFoundException
     * @throws MissingGatewayException
     */
    public function status(
        PaymentProcessor $paymentProcessor,
        Payment $payment,
    ): PaymentProcessorResultDto {
        $transaction = $payment->transactionForOperation(operation: OperationEnum::forPaymentStatus(
            PaymentStatusEnum::from($payment->payment_status_id)
        ));

        if ($transaction === null) {
            throw new PaymentTransactionNotFoundException(message: 'Transaction does not exist');
        }

        $gateway = PaymentGatewayFactory::makeForPaymentMethod(paymentMethod: $payment->paymentMethod);
        $paymentProcessor->setGateway(gateway: $gateway);

        $paymentProcessor->populate(populatedData: [
            OperationFields::REFERENCE_TRANSACTION_ID->value => $transaction->gateway_transaction_id,
            OperationFields::REFERENCE_ID->value => $payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($payment->type->id),
        ]);

        $result = new PaymentProcessorResultDto(isSuccess: false);
        $result->isSuccess = $paymentProcessor->status();
        $result->transactionId = $paymentProcessor->getTransactionLog()->id;
        $result->message = $paymentProcessor->getError();

        if ($result->isSuccess && $payment->payment_type === PaymentTypeEnum::ACH) {
            $this->achStatusCheck(
                payment: $payment,
                checkStatusTransaction: $transaction,
                paymentProcessor: $paymentProcessor,
            );
        }

        return $result;
    }

    private function populateProcessorData(
        PaymentProcessor $paymentProcessor,
        Payment $payment
    ): PaymentProcessor {
        $paymentMethod = $payment->paymentMethod;
        $paymentProcessor->populate(populatedData: [
            OperationFields::REFERENCE_ID->value => $payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($paymentMethod->type->id),
            OperationFields::NAME_ON_ACCOUNT->value => $paymentMethod->name_on_account,
            OperationFields::ADDRESS_LINE_1->value => $paymentMethod->address_line1,
            OperationFields::ADDRESS_LINE_2->value => $paymentMethod->address_line2,
            OperationFields::CITY->value => $paymentMethod->city,
            OperationFields::PROVINCE->value => $paymentMethod->province,
            OperationFields::POSTAL_CODE->value => $paymentMethod->postal_code,
            OperationFields::COUNTRY_CODE->value => $paymentMethod->country_code,
            OperationFields::EMAIL_ADDRESS->value => $paymentMethod->email,
            OperationFields::AMOUNT->value => new Money(
                amount: $payment->amount,
                currency: new Currency($payment->currency_code)
            ),
            OperationFields::REFERENCE_TRANSACTION_ID->value => $payment->id,
            OperationFields::CHARGE_DESCRIPTION->value => 'Authorize for payment #' . $payment->id,
        ]);

        if ($this->isUsingCreditCard(paymentMethod: $paymentMethod)) {
            $paymentProcessor->populate(populatedData: [
                OperationFields::TOKEN->value => $paymentMethod->cc_token,
                OperationFields::CC_EXP_MONTH->value => $paymentMethod->cc_expiration_month,
                OperationFields::CC_EXP_YEAR->value => $paymentMethod->cc_expiration_year,
            ]);
        } else {
            $paymentProcessor->populate(populatedData: !is_null($paymentMethod->ach_token)
                ? [
                    OperationFields::ACH_TOKEN->value => $paymentMethod->ach_token,
                    OperationFields::ACH_ACCOUNT_TYPE->value => !is_null($paymentMethod->ach_account_type)
                        ? AchAccountTypeEnum::from($paymentMethod->ach_account_type)
                        : AchAccountTypeEnum::PERSONAL_CHECKING,
                ]
                : [
                    OperationFields::ACH_ACCOUNT_NUMBER->value => $paymentMethod->ach_account_number_encrypted,
                    OperationFields::ACH_ROUTING_NUMBER->value => $paymentMethod->ach_routing_number,
                    OperationFields::ACH_ACCOUNT_TYPE->value => !is_null($paymentMethod->ach_account_type)
                        ? AchAccountTypeEnum::from($paymentMethod->ach_account_type)
                        : AchAccountTypeEnum::PERSONAL_CHECKING,
                ]);
        }

        return $paymentProcessor;
    }

    private function isUsingCreditCard(PaymentMethod $paymentMethod): bool
    {
        return PaymentTypeEnum::from($paymentMethod->payment_type_id) !== PaymentTypeEnum::ACH;
    }

    private function achStatusCheck(
        Payment $payment,
        Transaction $checkStatusTransaction,
        PaymentProcessor $paymentProcessor,
    ): void {
        switch ($paymentProcessor->getGatewayPaymentStatus()) {
            case PaymentStatusEnum::RETURNED:
                $returnedPayment = $this->paymentRepository->cloneAndCreateFromExistingPayment(
                    payment: $payment,
                    overriddenAttributes: [
                        'payment_status_id' => PaymentStatusEnum::RETURNED,
                    ],
                );

                $this->transactionRepository->update(
                    transaction: $checkStatusTransaction,
                    attributes: ['payment_id' => $returnedPayment->id],
                );

                PaymentReturnedEvent::dispatch($returnedPayment);

                Log::info(__('messages.payment.ach_status_checking.payment_returned', [
                    'id' => $returnedPayment->id,
                    'transaction_id' => $checkStatusTransaction->id,
                ]));
                break;
            case PaymentStatusEnum::SETTLED:
                $this->paymentRepository->updateStatus(
                    payment: $payment,
                    paymentStatus: PaymentStatusEnum::SETTLED,
                );

                PaymentSettledEvent::dispatch($payment);

                Log::info(__('messages.payment.ach_status_checking.payment_settled', [
                    'id' => $payment->id,
                ]));
                break;
            default:
                Log::info(__('messages.payment.ach_status_checking.payment_status_up_to_date', [
                    'id' => $payment->id,
                ]));
        }
    }
}
