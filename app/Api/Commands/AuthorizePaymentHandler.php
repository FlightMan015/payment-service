<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\DTO\AuthorizePaymentResultDto;
use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Exceptions\PaymentProcessingValidationException;
use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ResourceNotFoundException as ApiResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\RetrieveGatewayForPaymentMethodTrait;
use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Exceptions\CreditCardValidationException;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\DB;

class AuthorizePaymentHandler
{
    use RetrieveGatewayForPaymentMethodTrait;

    private AuthorizePaymentCommand $command;
    private PaymentMethod|null $paymentMethod;
    private Account|null $account;
    private Payment $payment;
    private PaymentProcessorResultDto $operationResult;

    /**
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param PaymentRepository $paymentRepository
     * @param PaymentProcessorRepository $paymentProcessorRepository
     * @param PaymentProcessor $paymentProcessor
     * @param AccountRepository $accountRepository
     */
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentProcessorRepository $paymentProcessorRepository,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly AccountRepository $accountRepository
    ) {
    }

    /**
     * @param AuthorizePaymentCommand $command
     *
     * @throws \Throwable
     *
     * @return AuthorizePaymentResultDto
     */
    public function handle(AuthorizePaymentCommand $command): AuthorizePaymentResultDto
    {
        $this->command = $command;

        $this->retrieveAccount();
        $this->retrieveAndValidatePaymentMethodExistsAndBelongsToAccount();
        $this->getGatewayInstanceBasedOnPaymentMethod();

        DB::transaction(callback: function () {
            $this->createDatabasePaymentRecord();
            $this->callPaymentProcessorAuthorization();
            $this->handleOperationResult();
        });

        return new AuthorizePaymentResultDto(
            status: PaymentStatusEnum::from($this->payment->payment_status_id),
            paymentId: $this->payment->id,
            transactionId: $this->operationResult->transactionId,
            message: $this->operationResult->message,
        );
    }

    /**
     * @throws UnprocessableContentException
     */
    private function retrieveAccount(): void
    {
        $this->account = $this->accountRepository->find(id: $this->command->accountId);

        if (is_null($this->account)) {
            throw new UnprocessableContentException(message: __('messages.account.not_found'));
        }
    }

    /**
     * @throws PaymentValidationException
     * @throws \Throwable
     */
    private function retrieveAndValidatePaymentMethodExistsAndBelongsToAccount(): void
    {
        if (is_null($this->command->paymentMethodId)) {
            $this->paymentMethod = $this->paymentMethodRepository->findPrimaryForAccount(accountId: $this->command->accountId);

            throw_if(
                condition: is_null($this->paymentMethod),
                exception: new PaymentValidationException(
                    message: __('messages.invalid_input'),
                    errors: [__('messages.operation.primary_payment_method_not_found')]
                )
            );

            return;
        }

        try {
            $this->paymentMethod = $this->paymentMethodRepository->find(
                paymentMethodId: $this->command->paymentMethodId,
                columns: [
                    'id',
                    'account_id',
                    'payment_type_id',
                    'payment_gateway_id',
                    'cc_token',
                    'cc_expiration_month',
                    'cc_expiration_year',
                ],
            );
        } catch (ApiResourceNotFoundException) {
            throw new PaymentValidationException(
                message: __('messages.invalid_input'),
                errors: [__('messages.operation.given_payment_method_not_found')]
            );
        }

        throw_if(
            condition: $this->paymentMethod->account->id !== $this->command->accountId,
            exception: new PaymentValidationException(
                message: __('messages.invalid_input'),
                errors: [__('messages.operation.given_payment_method_not_belong_to_account')]
            )
        );
    }

    private function createDatabasePaymentRecord(): void
    {
        $this->payment = $this->paymentRepository->create(
            attributes: [
                'account_id' => $this->account->id,
                'payment_type_id' => $this->paymentMethod->payment_type_id,
                'payment_status_id' => PaymentStatusEnum::AUTHORIZING->value,
                'payment_method_id' => $this->paymentMethod->id,
                'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
                'currency_code' => 'USD',
                'amount' => $this->command->amount,
                'applied_amount' => 0,
                'processed_at' => now(),
                'notes' => $this->command->notes,
            ]
        );
    }

    /**
     * @throws PaymentProcessingValidationException
     */
    private function callPaymentProcessorAuthorization(): void
    {
        try {
            $this->operationResult = $this->paymentProcessorRepository->authorize(
                paymentProcessor: $this->paymentProcessor,
                payment: $this->payment,
                gateway: $this->gateway
            );
        } catch (CreditCardValidationException|OperationValidationException $exception) {
            throw new PaymentProcessingValidationException(message: $exception->getMessage(), context: $this->command->toArray());
        }
    }

    /**
     * @throws \Exception
     */
    private function handleOperationResult(): void
    {
        $paymentStatus = $this->operationResult->isSuccess ? PaymentStatusEnum::AUTHORIZED : PaymentStatusEnum::DECLINED;
        $this->paymentRepository->update(payment: $this->payment, attributes: ['payment_status_id' => $paymentStatus->value]);

        PaymentAttemptedEvent::dispatch(
            $this->payment,
            PaymentProcessingInitiator::API_REQUEST,
            OperationEnum::AUTHORIZE
        );
    }
}
