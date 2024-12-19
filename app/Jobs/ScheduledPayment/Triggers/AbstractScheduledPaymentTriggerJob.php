<?php

declare(strict_types=1);

namespace App\Jobs\ScheduledPayment\Triggers;

use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Events\ScheduledPaymentCancelledEvent;
use App\Events\ScheduledPaymentSubmittedEvent;
use App\Exceptions\ScheduledPaymentTriggerInvalidMetadataException;
use App\Factories\PaymentGatewayFactory;
use App\Factories\ScheduledPaymentTriggerMetadataValidatorFactory;
use App\Models\Payment;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Aptive\Attribution\Enums\DomainEnum;
use Aptive\Attribution\Enums\EntityEnum;
use Aptive\Attribution\Enums\PrefixEnum;
use Aptive\Attribution\Enums\TenantEnum;
use Aptive\Attribution\Urn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Money\Currency;
use Money\Money;
use Psr\Log\LoggerInterface;

abstract class AbstractScheduledPaymentTriggerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 1;

    protected PaymentProcessor $paymentProcessor;
    protected LoggerInterface $logger;
    protected PaymentRepository $paymentRepository;
    protected Urn $scheduledPaymentsProcessingUrn;
    protected object $metadata;
    private Payment $preparedPayment;
    private ScheduledPaymentRepository $scheduledPaymentRepository;

    /**
     * @param ScheduledPayment $payment
     */
    public function __construct(protected ScheduledPayment $payment)
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
        $this->scheduledPaymentsProcessingUrn = new Urn(
            prefix: PrefixEnum::URN,
            tenant: TenantEnum::Aptive,
            domain: DomainEnum::Organization,
            entity: EntityEnum::ApiAccount,
            identity: config(key: 'attribution.scheduled_payments_processing_api_account_id')
        );
    }

    /**
     * @param PaymentProcessor $paymentProcessor
     * @param LoggerInterface $logger
     * @param PaymentRepository $paymentRepository
     * @param ScheduledPaymentRepository $scheduledPaymentRepository
     *
     * @throws \Throwable
     */
    public function handle(
        PaymentProcessor $paymentProcessor,
        LoggerInterface $logger,
        PaymentRepository $paymentRepository,
        ScheduledPaymentRepository $scheduledPaymentRepository
    ): void {
        $this->paymentProcessor = $paymentProcessor;
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
        $this->scheduledPaymentRepository = $scheduledPaymentRepository;

        $this->validatePaymentTrigger();
        $this->checkIfPaymentStatusAllowProcessing();

        try {
            $this->validateAndParseMetadata();
        } catch (ScheduledPaymentTriggerInvalidMetadataException) {
            $this->markPaymentAsCancelled();
            Log::warning(message: __('messages.scheduled_payment.invalid_metadata_payment_cancelled'), context: ['payment_id' => $this->payment->id]);
            return;
        }

        if (!$this->areRelatedEntitiesInValidState()) {
            $this->markPaymentAsCancelled();
            return;
        }

        if ($this->checkIfPaymentShouldBeProcessed()) {
            $this->processPayment();
            $this->markPaymentAsSubmitted();
        }
    }

    abstract protected function validatePaymentTrigger(): void;

    abstract protected function checkIfPaymentShouldBeProcessed(): bool;

    /**
     * Validate the state of related entities (specific to each trigger,
     * for example, subscription is not deleted, payment method is not deleted, etc.)
     *
     * @return bool
     */
    abstract protected function areRelatedEntitiesInValidState(): bool;

    /**
     * @throws \Throwable
     */
    protected function processPayment(): void
    {
        DB::transaction(callback: function () {
            $this->createPaymentRecordInDatabase();
            $this->configurePaymentProcessor();
            $this->processPaymentInGateway();
        });
    }

    protected function createPaymentRecordInDatabase(): void
    {
        $this->preparedPayment = $this->paymentRepository->create(attributes: [
            'account_id' => $this->payment->account_id,
            'payment_type_id' => $this->payment->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::AUTH_CAPTURING,
            'payment_method_id' => $this->payment->paymentMethod->id,
            'payment_gateway_id' => $this->payment->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->payment->amount,
            'applied_amount' => 0,
            'is_scheduled_payment' => true,
            'created_by' => $this->scheduledPaymentsProcessingUrn->toString(),
        ]);

        \Log::shareContext(context: [
            'payment_id' => $this->payment->id,
        ]);
    }

    /**
     * @throws BindingResolutionException
     * @throws UnsupportedValueException
     */
    protected function configurePaymentProcessor(): void
    {
        $this->paymentProcessor->setGateway(PaymentGatewayFactory::makeForPaymentMethod($this->payment->paymentMethod));
        $this->paymentProcessor->setLogger($this->logger);

        $paymentProcessorData = [
            OperationFields::REFERENCE_ID->value => $this->preparedPayment->id,
            OperationFields::NAME_ON_ACCOUNT->value => $this->payment->paymentMethod->name_on_account
                ?: $this->payment->account->billingContact->full_name
                    ?: $this->payment->account->contact->full_name,
            OperationFields::ADDRESS_LINE_1->value => $this->payment->paymentMethod->address_line1
                ?: $this->payment->account->billingAddress->address
                    ?: $this->payment->account->address->address,
            OperationFields::ADDRESS_LINE_2->value => $this->payment->paymentMethod->address_line2,
            OperationFields::CITY->value => $this->payment->paymentMethod->city
                ?: $this->payment->account->billingAddress->city
                    ?: $this->payment->account->address->city,
            OperationFields::PROVINCE->value => $this->payment->paymentMethod->province
                ?: $this->payment->account->billingAddress->state
                    ?: $this->payment->account->address->state,
            OperationFields::POSTAL_CODE->value => $this->payment->paymentMethod->postal_code
                ?: $this->payment->account->billingAddress->postal_code
                    ?: $this->payment->account->address->postal_code,
            OperationFields::COUNTRY_CODE->value => $this->payment->paymentMethod->country_code
                ?: $this->payment->account->billingAddress->country
                    ?: $this->payment->account->address->country,
            OperationFields::EMAIL_ADDRESS->value => $this->payment->paymentMethod->email
                ?: $this->payment->account->billingContact->email
                    ?: $this->payment->account->contact->email,
            OperationFields::CHARGE_DESCRIPTION->value => sprintf('Processing unpaid balance for Account %s', $this->payment->account->id),
            OperationFields::AMOUNT->value => new Money(amount: $this->preparedPayment->amount, currency: new Currency(code: $this->preparedPayment->currency_code)),
            OperationFields::REFERENCE_TRANSACTION_ID->value => $this->preparedPayment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($this->payment->paymentMethod->payment_type_id),
        ];

        if (PaymentTypeEnum::from($this->payment->paymentMethod->payment_type_id) === PaymentTypeEnum::ACH) {
            $paymentProcessorData += !is_null($this->payment->paymentMethod->ach_token)
                ? [
                    OperationFields::ACH_TOKEN->value => $this->payment->paymentMethod->ach_token,
                    OperationFields::ACH_ACCOUNT_TYPE->value => !is_null($this->payment->paymentMethod->ach_account_type)
                        ? AchAccountTypeEnum::from($this->payment->paymentMethod->ach_account_type)
                        : AchAccountTypeEnum::PERSONAL_CHECKING,
                ]
                : [
                    OperationFields::ACH_ACCOUNT_NUMBER->value => $this->payment->paymentMethod->ach_account_number_encrypted,
                    OperationFields::ACH_ROUTING_NUMBER->value => $this->payment->paymentMethod->ach_routing_number,
                    OperationFields::ACH_ACCOUNT_TYPE->value => !is_null($this->payment->paymentMethod->ach_account_type)
                        ? AchAccountTypeEnum::from($this->payment->paymentMethod->ach_account_type)
                        : AchAccountTypeEnum::PERSONAL_CHECKING,
                ];
        } else {
            $paymentProcessorData += [
                OperationFields::TOKEN->value => $this->payment->paymentMethod->cc_token,
                OperationFields::CC_EXP_YEAR->value => $this->payment->paymentMethod->cc_expiration_year,
                OperationFields::CC_EXP_MONTH->value => $this->payment->paymentMethod->cc_expiration_month,
            ];
        }

        $this->paymentProcessor->populate(populatedData: $paymentProcessorData);
    }

    protected function processPaymentInGateway(): void
    {
        $isSuccess = $this->paymentProcessor->sale();

        $this->logger->info(
            message: __('messages.scheduled_payment.processed', ['id' => $this->preparedPayment->id, 'account' => $this->payment->account->id]),
            context: [
                'is_success' => $isSuccess,
                'result' => $this->paymentProcessor->getResponseData(),
                'error' => $this->paymentProcessor->getError(),
            ]
        );

        $this->paymentRepository->update(
            payment: $this->preparedPayment,
            attributes: [
                'processed_at' => now(),
                'payment_status_id' => $isSuccess ? PaymentStatusEnum::CAPTURED : PaymentStatusEnum::DECLINED,
                'updated_by' => $this->scheduledPaymentsProcessingUrn->toString(),
            ]
        );

        PaymentAttemptedEvent::dispatch(
            $this->preparedPayment,
            PaymentProcessingInitiator::BATCH_PROCESSING,
            OperationEnum::AUTH_CAPTURE
        );
    }

    private function checkIfPaymentStatusAllowProcessing(): void
    {
        if ($this->payment->payment_status !== ScheduledPaymentStatusEnum::PENDING) {
            throw new \InvalidArgumentException(__('messages.scheduled_payment.payment_has_invalid_status'));
        }
    }

    private function markPaymentAsSubmitted(): void
    {
        $this->scheduledPaymentRepository->update(
            payment: $this->payment,
            attributes: [
                'status_id' => ScheduledPaymentStatusEnum::SUBMITTED->value,
                'payment_id' => $this->preparedPayment->id,
            ]
        );

        ScheduledPaymentSubmittedEvent::dispatch($this->payment, $this->preparedPayment);
    }

    private function markPaymentAsCancelled(): void
    {
        $this->scheduledPaymentRepository->update(
            payment: $this->payment,
            attributes: [
                'status_id' => ScheduledPaymentStatusEnum::CANCELLED->value,
            ]
        );

        ScheduledPaymentCancelledEvent::dispatch($this->payment);
    }

    /**
     * @throws BindingResolutionException
     * @throws ScheduledPaymentTriggerInvalidMetadataException
     */
    private function validateAndParseMetadata(): void
    {
        $this->metadata = $this->payment->metadata;

        $validator = ScheduledPaymentTriggerMetadataValidatorFactory::make($this->payment->payment_trigger);
        $validator->validate(metadata: (array)$this->metadata);
    }
}
