<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Exceptions\UnsupportedValueException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Events\PaymentSkippedEvent;
use App\Events\PaymentSuspendedEvent;
use App\Exceptions\AbstractPaymentProcessingException;
use App\Exceptions\AccountDoesNotHaveAnyPaymentMethodsException;
use App\Exceptions\AutopayPaymentMethodNotFound;
use App\Exceptions\DailyPaymentAttemptsExceededException;
use App\Exceptions\InvalidPaymentHoldDateException;
use App\Exceptions\InvalidPreferredBillingDateException;
use App\Exceptions\InvalidUnpaidInvoicesBalanceException;
use App\Exceptions\PaymentMethodInvalidStatus;
use App\Exceptions\PaymentSuspendedException;
use App\Exceptions\PaymentTerminatedException;
use App\Exceptions\ThereIsNoUnpaidBalanceException;
use App\Exceptions\TotalPaymentAttemptsExceededException;
use App\Exceptions\UnpaidInvoicesBalanceMismatchException;
use App\Factories\PaymentGatewayFactory;
use App\Models\CRM\Customer\Account;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\OperationFields;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Enums\SuspendReasonEnum;
use App\PaymentProcessor\Exceptions\OperationValidationException;
use App\PaymentProcessor\PaymentProcessor;
use App\Services\Payment\DuplicatePaymentChecker;
use Aptive\Attribution\Enums\DomainEnum;
use Aptive\Attribution\Enums\EntityEnum;
use Aptive\Attribution\Enums\PrefixEnum;
use Aptive\Attribution\Enums\TenantEnum;
use Aptive\Attribution\Urn;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfileStatus;
use Carbon\Carbon;
use ConfigCat\ClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Money\Currency;
use Money\Money;
use Psr\Log\LoggerInterface;

class ProcessPaymentJob implements ShouldQueue
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

    private const int MAX_TOTAL_PAYMENT_ATTEMPTS_FOR_PAYMENT_METHOD = 3;
    private const int MAX_DAILY_PAYMENT_ATTEMPTS_FOR_PAYMENT_METHOD = 1;

    private Urn $batchPaymentProcessingUrn;
    private PaymentProcessor $paymentProcessor;
    private LoggerInterface $logger;
    private PaymentMethod|null $paymentMethod = null;
    private Payment $payment;
    private PaymentRepository $paymentRepository;
    private PaymentMethodRepository $paymentMethodRepository;
    private AccountRepository $accountRepository;
    private bool $paymentIsSuspended = false;
    private Payment|null $terminatedPayment = null;
    private string|null $paymentIsSuspendedRelatedId = null;
    private int|null $paymentAmount = null;

    /**
     * Create a new job instance.
     *
     * @param Account $account
     * @param array<Invoice> $invoices
     */
    public function __construct(
        private readonly Account $account,
        private readonly array $invoices,
    ) {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
        $this->batchPaymentProcessingUrn = new Urn(
            prefix: PrefixEnum::URN,
            tenant: TenantEnum::Aptive,
            domain: DomainEnum::Organization,
            entity: EntityEnum::ApiAccount,
            identity: config(key: 'attribution.batch_payment_processing_api_account_id')
        );
    }

    /**
     * Execute the job.
     *
     * @param PaymentProcessor $paymentProcessor
     * @param LoggerInterface $logger
     * @param PaymentRepository $paymentRepository
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param AccountRepository $accountRepository
     * @param ClientInterface $configCatClient
     * @param DuplicatePaymentChecker $duplicatePaymentChecker
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     *
     * @return void
     */
    public function handle(
        PaymentProcessor $paymentProcessor,
        LoggerInterface $logger,
        PaymentRepository $paymentRepository,
        PaymentMethodRepository $paymentMethodRepository,
        AccountRepository $accountRepository,
        ClientInterface $configCatClient,
        DuplicatePaymentChecker $duplicatePaymentChecker
    ): void {
        $areDatabaseQueriesLogsEnabled = $configCatClient->getValue(
            key: 'areDatabaseQueriesLogsEnabled',
            defaultValue: false
        );
        if ($areDatabaseQueriesLogsEnabled) {
            DB::listen(static function (QueryExecuted $query) use ($logger) {
                $logger->debug(message: __('messages.payment.batch_processing.debug.sql_query_executed'), context: [
                    'query_info' => [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time,
                    ]
                ]);
            });
        }

        \Log::shareContext(context: [
            'account' => [
                'id' => $this->account->id,
                'external_ref_id' => $this->account->external_ref_id,
                'balance' => $this->account->ledger?->balance,
                'payment_hold_date' => $this->account->payment_hold_date,
                'billing_info' => [
                    'name_on_account' => $this->account->billingContact->full_name,
                    'address_line1' => $this->account->billingAddress->address,
                    'city' => $this->account->billingAddress->city,
                    'province' => $this->account->billingAddress->state,
                    'postal_code' => $this->account->billingAddress->postal_code,
                    'country_code' => $this->account->billingAddress->country,
                    'email' => $this->account->billingContact->email,
                ],
                'service_info' => [
                    'name_on_account' => $this->account->contact->full_name,
                    'address_line1' => $this->account->address->address,
                    'city' => $this->account->address->city,
                    'province' => $this->account->address->state,
                    'postal_code' => $this->account->address->postal_code,
                    'country_code' => $this->account->address->country,
                    'email' => $this->account->contact->email,
                ]
            ],
            'area_id' => $this->account->area_id,
        ]);

        $logger->info(message: __('messages.payment.batch_processing.job_started'), context: ['job_id' => $this->job?->uuid()]);

        $this->setLogger($logger);
        $this->setPaymentRepository($paymentRepository);
        $this->setAccountRepository($accountRepository);
        $this->setPaymentMethodRepository($paymentMethodRepository);
        $this->setPaymentAmount();

        try {
            $this->checkIfPaymentShouldBeAttempted();
            $this->paymentIsSuspended = $duplicatePaymentChecker->isDuplicatePayment(
                invoices: $this->invoices,
                paymentAmount: $this->paymentAmount,
                account: $this->account,
                paymentMethod: $this->paymentMethod,
            );

            $this->paymentIsSuspendedRelatedId = $duplicatePaymentChecker->getOriginalPayment()?->id;
            $this->checkPaymentTermination();
            $this->checkTotalInvoicesBalance();
        } catch (PaymentSuspendedException $exception) {
            $this->logger->warning(
                message: __(
                    key: 'messages.payment.batch_processing.previous_payment_already_suspended',
                    replace: ['id' => $exception->context['suspended_payment_id']]
                ),
                context: $exception->context
            );

            PaymentSkippedEvent::dispatch(
                $this->account,
                $exception->getMessage(),
                $this->paymentMethod ?? null,
                $this->payment ?? null
            );

            return;
        } catch (PaymentTerminatedException $exception) {
            $this->logger->warning(
                message: __(
                    key: 'messages.payment.batch_processing.previous_payment_already_terminated',
                    replace: ['id' => $exception->context['terminated_payment_id']]
                ),
                context: $exception->context
            );

            PaymentSkippedEvent::dispatch(
                $this->account,
                $exception->getMessage(),
                $this->paymentMethod ?? null,
                $this->payment ?? null
            );

            return;
        } catch (AbstractPaymentProcessingException $exception) {
            $this->logger->warning(
                message: __(key: 'messages.payment.batch_processing.skip_processing', replace: ['message' => $exception->getMessage()]),
                context: $exception->context
            );

            PaymentSkippedEvent::dispatch(
                $this->account,
                $exception->getMessage(),
                $this->paymentMethod ?? null,
                $this->payment ?? null
            );

            return;
        }

        $this->setPaymentProcessor($paymentProcessor);

        try {
            DB::transaction(callback: function () {
                $this->createPaymentRecordInDatabase();
                $this->setupPaymentProcessor();
                $this->processPayment();
            });
        } catch (OperationValidationException $exception) {
            $this->logger->warning(
                message: __(
                    key: 'messages.payment.batch_processing.skip_processing',
                    replace: ['message' => __('messages.payment.batch_processing.validation_failed')]
                ),
                context: ['validation_error_message' => $exception->getMessage()]
            );

            PaymentSkippedEvent::dispatch(
                $this->account,
                __('messages.payment.batch_processing.validation_failed'),
                $this->paymentMethod,
                null
            );

            return;
        }
    }

    /**
     * @param PaymentProcessor $paymentProcessor
     *
     * @throws BindingResolutionException
     * @throws UnsupportedValueException
     */
    private function setPaymentProcessor(PaymentProcessor $paymentProcessor): void
    {
        $this->paymentProcessor = $paymentProcessor;
        $this->paymentProcessor->setGateway(PaymentGatewayFactory::makeForPaymentMethod($this->paymentMethod));
        $this->paymentProcessor->setLogger($this->logger);
    }

    private function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function setPaymentRepository(PaymentRepository $paymentRepository): void
    {
        $this->paymentRepository = $paymentRepository;
    }

    private function setAccountRepository(AccountRepository $accountRepository): void
    {
        $this->accountRepository = $accountRepository;
    }

    private function setPaymentMethodRepository(PaymentMethodRepository $paymentMethodRepository): void
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    private function checkIfPaymentShouldBeAttempted(): void
    {
        $this->checkIfThereIsNoUnpaidBalance();
        $this->checkIfAccountHasAnyPaymentMethods();
        $this->getAccountAutopayPaymentMethod();
        $this->checkPaymentMethodStatus();
        $this->checkTotalPaymentAttempts();
        $this->checkDailyPaymentAttempts();
        $this->checkPaymentHoldDate();
        $this->checkPreferredBillingDate();
    }

    private function checkIfThereIsNoUnpaidBalance(): void
    {
        // Omit processing if paymentAmount is less than or equals to 0
        if ($this->paymentAmount <= 0) {
            $this->logger->notice(
                message: sprintf(
                    'Payment for %s skipped because there is no unpaid balance',
                    $this->getInvoicesIdentifier(),
                ),
            );

            throw new ThereIsNoUnpaidBalanceException($this->account->id);
        }
    }

    private function checkIfAccountHasAnyPaymentMethods(): void
    {
        if (!$this->paymentMethodRepository->existsForAccount(accountId: $this->account->id)) {
            throw new AccountDoesNotHaveAnyPaymentMethodsException(accountId: $this->account->id);
        }
    }

    private function getAccountAutopayPaymentMethod(): void
    {
        $this->paymentMethod = $this->paymentMethodRepository->findAutopayMethodForAccount(accountId: $this->account->id);

        if (is_null($this->paymentMethod)) {
            throw new AutopayPaymentMethodNotFound(accountId: $this->account->id);
        }

        \Log::shareContext(context: [
            'payment_method' => [
                'id' => $this->paymentMethod->id,
                'external_ref_id' => $this->paymentMethod->external_ref_id,
                'type' => PaymentTypeEnum::from($this->paymentMethod->payment_type_id),
                'cc_token' => \Str::mask(string: $this->paymentMethod->cc_token, character: '*', index: 6, length: 10),
                'cc_expiration_month' => $this->paymentMethod->cc_expiration_month,
                'cc_expiration_year' => $this->paymentMethod->cc_expiration_year,
                'ach_token' => \Str::mask(string: $this->paymentMethod->ach_token, character: '*', index: 6, length: 10),
                'ach_account_number_encrypted' => $this->paymentMethod->ach_account_number_encrypted,
                'ach_routing_number' => $this->paymentMethod->ach_routing_number,
                'last_four' => $this->paymentMethod->last_four,
                'name_on_account' => $this->paymentMethod->name_on_account,
                'address_line1' => $this->paymentMethod->address_line1,
                'city' => $this->paymentMethod->city,
                'province' => $this->paymentMethod->province,
                'postal_code' => $this->paymentMethod->postal_code,
                'country_code' => $this->paymentMethod->country_code,
                'email' => $this->paymentMethod->email,
            ]
        ]);
    }

    private function checkPaymentMethodStatus(): void
    {
        $paymentMethodStatus = !is_null($this->paymentMethod->pestroutes_status_id)
            ? PaymentProfileStatus::tryFrom($this->paymentMethod->pestroutes_status_id)
            : null;
        $disallowedStatuses = [PaymentProfileStatus::SoftDeleted, PaymentProfileStatus::Empty];

        if (in_array(needle: $paymentMethodStatus, haystack: $disallowedStatuses, strict: true)) {
            throw new PaymentMethodInvalidStatus(accountId: $this->account->id, status: $paymentMethodStatus);
        }
    }

    /**
     * here we are retrieving three latest payments for the given primary method,
     * and if all of them were failed, then we will throw an exception
     */
    private function checkTotalPaymentAttempts(): void
    {
        $latestPayments = $this->paymentRepository->getLatestPaymentsForPaymentMethod(
            method: $this->paymentMethod,
            limit: self::MAX_TOTAL_PAYMENT_ATTEMPTS_FOR_PAYMENT_METHOD
        );

        $failedPaymentsQuantity = $latestPayments
            ->where(key: 'payment_status_id', operator: '=', value: PaymentStatusEnum::DECLINED->value)
            ->count();

        if ($failedPaymentsQuantity === self::MAX_TOTAL_PAYMENT_ATTEMPTS_FOR_PAYMENT_METHOD) {
            throw new TotalPaymentAttemptsExceededException(maxPaymentAttempts: self::MAX_TOTAL_PAYMENT_ATTEMPTS_FOR_PAYMENT_METHOD);
        }
    }

    private function checkDailyPaymentAttempts(): void
    {
        $dailyPaymentAttempts = $this->paymentRepository->getDeclinedForPaymentMethodCount(
            method: $this->paymentMethod,
            date: Carbon::today()
        );

        if ($dailyPaymentAttempts >= self::MAX_DAILY_PAYMENT_ATTEMPTS_FOR_PAYMENT_METHOD) {
            throw new DailyPaymentAttemptsExceededException(
                maxPaymentAttempts: self::MAX_DAILY_PAYMENT_ATTEMPTS_FOR_PAYMENT_METHOD
            );
        }
    }

    private function checkPaymentHoldDate(): void
    {
        if (!$this->paymentMethod->hasValidHoldDate(processingDateTime: now())) {
            throw new InvalidPaymentHoldDateException(paymentHoldDate: $this->paymentMethod->payment_hold_date);
        }
    }

    private function checkPreferredBillingDate(): void
    {
        if (is_null($this->account->preferred_billing_day_of_month)) {
            return;
        }

        if ($this->isTodayTheLastDayOfMonth() && $this->isPreferredBillingDayGreaterOrEqualThanNumberOfDaysInCurrentMonth()) {
            return;
        }

        if ($this->account->preferred_billing_day_of_month > now()->day) {
            throw new InvalidPreferredBillingDateException(preferredDay: $this->account->preferred_billing_day_of_month);
        }
    }

    private function isTodayTheLastDayOfMonth(): bool
    {
        return now()->isLastOfMonth();
    }

    private function isPreferredBillingDayGreaterOrEqualThanNumberOfDaysInCurrentMonth(): bool
    {
        return $this->account->preferred_billing_day_of_month >= now()->endOfMonth()->day;
    }

    private function checkTotalInvoicesBalance(): void
    {
        $totalUnpaidInvoicesAmount = collect($this->invoices)->sum('balance');

        if ($totalUnpaidInvoicesAmount <= 0) {
            throw new InvalidUnpaidInvoicesBalanceException(context: [
                'account_id' => $this->account->id,
                'account_balance' => $this->paymentAmount,
                'total_unpaid_invoices_amount' => $totalUnpaidInvoicesAmount,
            ]);
        }

        if ($this->paymentAmount !== $totalUnpaidInvoicesAmount) {
            throw new UnpaidInvoicesBalanceMismatchException(
                context: [
                    'account_id' => $this->account->id,
                    'account_balance' => $this->paymentAmount,
                    'total_unpaid_invoices_amount' => $totalUnpaidInvoicesAmount,
                ]
            );
        }
    }

    private function createPaymentRecordInDatabase(): void
    {
        $this->payment = $this->paymentRepository->create(attributes: [
            'account_id' => $this->account->id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::AUTH_CAPTURING,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->paymentAmount, // it should be in cents
            'applied_amount' => 0,
            'is_batch_payment' => true,
            'created_by' => $this->batchPaymentProcessingUrn->toString(),
        ]);

        \Log::shareContext(context: [
            'payment_id' => $this->payment->id,
        ]);
    }

    private function setupPaymentProcessor(): void
    {
        $this->paymentProcessor->setLogger(logger: $this->logger);

        $paymentProcessorData = [
            OperationFields::REFERENCE_ID->value => $this->payment->id,
            OperationFields::NAME_ON_ACCOUNT->value => $this->paymentMethod->name_on_account
                ?: $this->account->billingContact->full_name
                ?: $this->account->contact->full_name,
            OperationFields::ADDRESS_LINE_1->value => $this->paymentMethod->address_line1
                ?: $this->account->billingAddress->address
                ?: $this->account->address->address,
            OperationFields::ADDRESS_LINE_2->value => $this->paymentMethod->address_line2,
            OperationFields::CITY->value => $this->paymentMethod->city
                ?: $this->account->billingAddress->city
                ?: $this->account->address->city,
            OperationFields::PROVINCE->value => $this->paymentMethod->province
                ?: $this->account->billingAddress->state
                ?: $this->account->address->state,
            OperationFields::POSTAL_CODE->value => $this->paymentMethod->postal_code
                ?: $this->account->billingAddress->postal_code
                ?: $this->account->address->postal_code,
            OperationFields::COUNTRY_CODE->value => $this->paymentMethod->country_code
                ?: $this->account->billingAddress->country
                ?: $this->account->address->country,
            OperationFields::EMAIL_ADDRESS->value => $this->paymentMethod->email
                ?: $this->account->billingContact->email
                ?: $this->account->contact->email,
            OperationFields::CHARGE_DESCRIPTION->value => sprintf('Processing unpaid balance for Account %s', $this->account->id),
            OperationFields::AMOUNT->value => new Money(amount: $this->payment->amount, currency: new Currency(code: $this->payment->currency_code)),
            OperationFields::REFERENCE_TRANSACTION_ID->value => $this->payment->id,
            OperationFields::PAYMENT_TYPE->value => PaymentTypeEnum::from($this->paymentMethod->payment_type_id),
        ];

        if (PaymentTypeEnum::from($this->paymentMethod->payment_type_id) === PaymentTypeEnum::ACH) {
            $paymentProcessorData += !is_null($this->paymentMethod->ach_token)
                ? [
                    OperationFields::ACH_TOKEN->value => $this->paymentMethod->ach_token,
                    OperationFields::ACH_ACCOUNT_TYPE->value => !is_null($this->paymentMethod->ach_account_type)
                        ? AchAccountTypeEnum::from($this->paymentMethod->ach_account_type)
                        : AchAccountTypeEnum::PERSONAL_CHECKING,
                ]
                : [
                    OperationFields::ACH_ACCOUNT_NUMBER->value => $this->paymentMethod->ach_account_number_encrypted,
                    OperationFields::ACH_ROUTING_NUMBER->value => $this->paymentMethod->ach_routing_number,
                    OperationFields::ACH_ACCOUNT_TYPE->value => !is_null($this->paymentMethod->ach_account_type)
                        ? AchAccountTypeEnum::from($this->paymentMethod->ach_account_type)
                        : AchAccountTypeEnum::PERSONAL_CHECKING,
                ];
        } else {
            $paymentProcessorData += [
                OperationFields::TOKEN->value => $this->paymentMethod->cc_token,
                OperationFields::CC_EXP_YEAR->value => $this->paymentMethod->cc_expiration_year,
                OperationFields::CC_EXP_MONTH->value => $this->paymentMethod->cc_expiration_month,
            ];
        }

        $this->paymentProcessor->populate(populatedData: $paymentProcessorData);
    }

    /**
     * @throws \Throwable
     */
    private function processPayment(): void
    {
        if ($this->paymentIsSuspended) {
            // Update payment as suspended
            $this->logger->notice(
                message: sprintf('Payment %s marked suspended', $this->payment->id),
                context: [
                    'message' => sprintf('Duplicated with payment %s', $this->paymentIsSuspendedRelatedId),
                ]
            );

            $this->paymentRepository->update(
                payment: $this->payment,
                attributes: [
                    'processed_at' => now(),
                    'original_payment_id' => $this->paymentIsSuspendedRelatedId,
                    'payment_status_id' => PaymentStatusEnum::SUSPENDED->value,
                    'updated_by' => $this->batchPaymentProcessingUrn->toString(),
                    'suspend_reason_id' => SuspendReasonEnum::DUPLICATED->value,
                    'suspended_at' => now(),
                ]
            );

            $invoiceIds = collect($this->invoices)->pluck('id')->toArray();

            PaymentSuspendedEvent::dispatch(
                $this->account,
                $invoiceIds,
                $this->paymentAmount,
                SuspendReasonEnum::DUPLICATED,
                $this->paymentMethod,
                $this->payment
            );
        } else {
            $isSuccess = $this->paymentProcessor->sale();

            $this->logger->info(
                message: sprintf('Unpaid balance for account %s was processed', $this->account->id),
                context: [
                    'is_success' => $isSuccess,
                    'result' => $this->paymentProcessor->getResponseData(),
                    'error' => $this->paymentProcessor->getError(),
                ]
            );

            $this->paymentRepository->update(
                payment: $this->payment,
                attributes: [
                    'processed_at' => now(),
                    'payment_status_id' => $isSuccess ? PaymentStatusEnum::CAPTURED : PaymentStatusEnum::DECLINED,
                    'updated_by' => $this->batchPaymentProcessingUrn->toString(),
                ]
            );

            PaymentAttemptedEvent::dispatch(
                $this->payment,
                PaymentProcessingInitiator::BATCH_PROCESSING,
                OperationEnum::AUTH_CAPTURE
            );
        }
    }

    /**
     * Get a list of invoice id as comma separated values
     *
     * @return string
     */
    private function getInvoicesIdentifier(): string
    {
        return collect($this->invoices)
            ->map(static fn ($invoice) => substr($invoice['id'], -6))
            ->join(', ');
    }

    /**
     * Check for payment termination
     *
     * @return void
     */
    private function checkPaymentTermination(): void
    {
        if ($this->checkPaymentIsAlreadyTerminated()) {
            // Check if previous payment is marked as terminated
            $this->logger->warning(
                message: sprintf(
                    'Payment for invoices [%s] skipped because payment %s is terminated',
                    $this->getInvoicesIdentifier(),
                    $this->terminatedPayment->id
                ),
                context: [
                    'error_message' => sprintf(
                        'Batch payment processing for payment [%s] skipped because payment is terminated',
                        $this->terminatedPayment->id
                    )
                ]
            );

            throw new PaymentTerminatedException(
                context: [
                    'account_id' => $this->account->id,
                    'account_balance' => $this->paymentAmount,
                    'terminated_payment_id' => $this->terminatedPayment->id,
                ]
            );
        }
    }

    private function checkPaymentIsAlreadyTerminated(): bool
    {
        $invoiceIds = collect($this->invoices)->pluck('id')->toArray();
        $terminatedPayment = $this->paymentRepository->getTerminatedPaymentForInvoices($this->account->id, $invoiceIds);
        if ($terminatedPayment) {
            $this->terminatedPayment = $terminatedPayment;

            return true;
        }

        return false;
    }

    private function setPaymentAmount(): void
    {
        $this->paymentAmount = $this->accountRepository->getAmountLedgerBalance($this->account);
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error(message: 'ProcessPaymentJob failed', context: [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTrace()
        ]);
    }
}
