<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentAttemptedEvent;
use App\Events\PaymentSkippedEvent;
use App\Jobs\ProcessPaymentJob;
use App\Models\CRM\Customer\Account;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
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
use ConfigCat\ClientInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\TestCase;

class ProcessPaymentJobTest extends TestCase
{
    use DatabaseTransactions;
    use MockeryPHPUnitIntegration;
    use WorldPayCredentialsRepositoryMockingTrait;

    private Account $account;
    /** @var Collection<int, Invoice> $invoices */
    private Collection $invoices;
    private ProcessPaymentJob $job;
    /** @var MockInterface&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    /** @var MockObject&ClientInterface $mockConfigCatClient */
    private ClientInterface $mockConfigCatClient;
    private LoggerInterface|MockInterface $logger;
    private PaymentMethod $paymentMethod;
    private string $differentPaymentMethodId;
    private Urn $expectedUrn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createAccount();
        $this->invoices = Collection::wrap($this->createInvoiceForAccount());
        $this->job = $this->createProcessPaymentJob();
        $this->mockConfigCatClient = $this->createMock(originalClassName: ClientInterface::class);
        $this->mockPaymentProcessor();
        $this->paymentProcessor->allows('populate')->byDefault();
        $this->logger = \Mockery::spy($this->createMock(originalClassName: LoggerInterface::class));
        $this->expectedUrn = new Urn(
            prefix: PrefixEnum::URN,
            tenant: TenantEnum::Aptive,
            domain: DomainEnum::Organization,
            entity: EntityEnum::ApiAccount,
            identity: config(key: 'attribution.batch_payment_processing_api_account_id')
        );

        $this->mockWorldPayCredentialsRepository();

        Event::fake();
        Queue::fake(jobsToFake: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_creates_payment_record_in_database(): void
    {
        $this->createAccountPaymentMethod();

        $this->handleJob();

        $this->assertDatabaseHas(table: Payment::class, data: [
            'account_id' => $this->account->id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->account->ledger->balance,
            'applied_amount' => 0,
            'is_batch_payment' => true,
            'created_by' => $this->expectedUrn->toString(),
        ]);
    }

    #[Test]
    public function it_calls_payment_processor_sale_method_if_total_payment_attempts_not_exceeded(): void
    {
        $this->createAccountPaymentMethod();

        // Create 2 previous failed payment attempts
        Payment::factory()->count(count: 2)->create([
            'account_id' => $this->account->id,
            'payment_status_id' => PaymentStatusEnum::DECLINED,
        ]);

        $this->handleJob();

        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_does_not_call_payment_processor_sale_method_if_total_payment_attempts_exceeded(): void
    {
        $this->createAccountPaymentMethod();

        // Create 3 previous failed payment attempts for the primary payment method
        Payment::factory()->count(count: 3)->create([
            'account_id' => $this->account->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_status_id' => PaymentStatusEnum::DECLINED->value,
        ]);

        $this->handleJob();
        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_calls_payment_processor_sale_method_if_daily_payment_attempts_not_exceeded(): void
    {
        $this->createAccountPaymentMethod();

        // we are not creating any failed previous payment attempts

        $this->handleJob();
        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_does_not_call_payment_processor_sale_method_if_daily_payment_attempts_exceeded(): void
    {
        $this->createAccountPaymentMethod();

        // Create a previous failed payment attempt for today for the primary payment method
        Payment::factory()->create([
            'account_id' => $this->account->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_status_id' => PaymentStatusEnum::DECLINED,
            'processed_at' => Carbon::today(),
        ]);

        $this->handleJob();
        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_uses_correct_autopay_payment_method(): void
    {
        // the oldest
        PaymentMethod::factory()->create(['account_id' => $this->account->id, 'is_primary' => false]);

        // marking as autopay
        $expectedPaymentMethod = PaymentMethod::factory()->create(['account_id' => $this->account->id]);
        $this->account->setAutoPayPaymentMethod(autopayPaymentMethod: $expectedPaymentMethod);

        // recent but soft deleted
        PaymentMethod::factory()->create(['account_id' => $this->account->id])->delete();

        $this->handleJob();

        $this->assertDatabaseHas(table: Payment::class, data: [
            'account_id' => $this->account->id,
            'payment_type_id' => $expectedPaymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_method_id' => $expectedPaymentMethod->id,
            'payment_gateway_id' => $expectedPaymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->account->ledger->balance,
            'applied_amount' => 0,
            'is_batch_payment' => true,
            'created_by' => $this->expectedUrn->toString(),
        ]);
    }

    #[Test]
    public function it_does_not_call_payment_processor_sale_method_if_primary_payment_method_not_found(): void
    {
        $this->handleJob();
        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_does_not_call_payment_processor_sale_method_if_payment_hold_date_is_invalid(): void
    {
        $this->createAccountPaymentMethod(paymentHoldDate: now()->addWeek());

        $this->handleJob();
        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_calls_payment_processor_sale_method_if_payment_hold_date_is_valid(): void
    {
        $this->createAccountPaymentMethod(paymentHoldDate: null);

        $this->handleJob();
        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_does_not_call_payment_processor_sale_method_if_preferred_billing_date_is_invalid(): void
    {
        Carbon::setTestNow('first day of this month');

        $this->createAccountPaymentMethod();
        $this->account->update(['preferred_billing_day_of_month' => now()->day + 1]);

        $this->handleJob();
        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
    }

    #[Test]
    #[DataProvider('validPreferredBillingDateProvider')]
    public function it_calls_payment_processor_sale_method_if_preferred_billing_date_is_valid(
        int|null $preferredBillingDate,
        string|null $testCurrentDate = null,
    ): void {
        if (!is_null($testCurrentDate)) {
            Carbon::setTestNow($testCurrentDate);
        }

        $this->createAccountPaymentMethod();
        $this->account->update(['preferred_billing_day_of_month' => $preferredBillingDate]);

        $this->handleJob();
        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_dispatches_event_after_payment_processing_if_sale_successful(): void
    {
        $this->createAccountPaymentMethod();
        $this->paymentProcessor->allows('sale')->andReturns(true); // Successful

        $this->handleJob();

        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    public function it_dispatches_event_after_payment_processing_if_sale_unsuccessful(): void
    {
        $this->createAccountPaymentMethod();
        $this->paymentProcessor->allows('sale')->andReturns(false); // Unsuccessful

        $this->handleJob();

        Event::assertDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    public function payment_record_has_captured_status_after_processing_payment(): void
    {
        $this->createAccountPaymentMethod(paymentType: PaymentTypeEnum::CC);
        $this->paymentProcessor->allows('sale')->andReturns(true);

        $this->handleJob();

        $this->assertDatabaseHas(table: Payment::class, data: [
            'account_id' => $this->account->id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->account->ledger->balance,
            'applied_amount' => 0,
            'is_batch_payment' => true,
            'created_by' => $this->expectedUrn->toString(),
        ]);
    }

    #[Test]
    public function payment_record_marked_as_suspended_if_previous_one_is_for_same_invoices(): void
    {
        $this->createAccountPaymentMethod(paymentType: PaymentTypeEnum::CC);
        $this->createDuplicatedPayment();

        $this->handleJob();

        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
        $this->assertDatabaseHas(table: Payment::class, data: [
            'account_id' => $this->account->id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::SUSPENDED->value,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->account->ledger->balance,
            'applied_amount' => 0,
            'is_batch_payment' => true,
            'suspend_reason_id' => SuspendReasonEnum::DUPLICATED->value,
            'created_by' => $this->expectedUrn->toString(),
        ]);
        // This makes sure there's a value for suspended_at
        $this->assertDatabaseMissing(table: Payment::class, data: [
            'account_id' => $this->account->id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::SUSPENDED->value,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->account->ledger->balance,
            'applied_amount' => 0,
            'suspended_at' => null,
            'is_batch_payment' => true,
            'suspend_reason_id' => SuspendReasonEnum::DUPLICATED->value,
            'created_by' => $this->expectedUrn->toString(),
        ]);
    }

    #[Test, DataProvider('suspendedPaymentDataProvider')]
    public function it_calls_payment_processor_sale_method_when_payment_is_not_suspended(array $attributes, bool $usesDifferentPaymentMethod = false): void
    {
        $this->createAccountPaymentMethod(paymentType: PaymentTypeEnum::CC);
        $this->createDuplicatedPayment($attributes, $usesDifferentPaymentMethod ? $this->differentPaymentMethodId : null);

        $this->handleJob();

        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
        $this->assertDatabaseHas(table: Payment::class, data: [
            'account_id' => $this->account->id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'currency_code' => 'USD',
            'amount' => $this->account->ledger->balance,
            'applied_amount' => 0,
            'is_batch_payment' => true,
            'suspended_at' => null,
            'suspend_reason_id' => null,
            'created_by' => $this->expectedUrn->toString(),
        ]);
    }

    #[Test]
    public function it_skips_the_payment_processing_and_is_not_creating_payment_when_operation_validation_failed(): void
    {
        $this->createAccountPaymentMethod();
        $this->paymentProcessor->allows('sale')->andThrow(new OperationValidationException(errors: ['name_on_account empty']));

        $this->handleJob();

        Event::assertDispatched(PaymentSkippedEvent::class);
        $this->assertDatabaseMissing(table: Payment::class, data: [
            'account_id' => $this->account->id,
            'payment_type_id' => $this->paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_gateway_id' => $this->paymentMethod->payment_gateway_id,
            'amount' => $this->account->ledger->balance,
        ]);
    }

    #[Test]
    public function it_uses_account_billing_info_as_backup_if_payment_method_info_is_empty(): void
    {
        $this->createAccountPaymentMethod();

        // Remove billing info from payment method
        $this->paymentMethod->update([
            'name_on_account' => null,
            'address_line1' => null,
            'city' => null,
            'province' => null,
            'postal_code' => null,
            'country_code' => null,
            'email' => null,
        ]);

        $expectedBillingInfo = [
            OperationFields::NAME_ON_ACCOUNT->value => $this->account->billingContact->full_name,
            OperationFields::ADDRESS_LINE_1->value => $this->account->billingAddress->address,
            OperationFields::CITY->value => $this->account->billingAddress->city,
            OperationFields::PROVINCE->value => $this->account->billingAddress->state,
            OperationFields::POSTAL_CODE->value => $this->account->billingAddress->postal_code,
            OperationFields::COUNTRY_CODE->value => $this->account->billingAddress->country,
            OperationFields::EMAIL_ADDRESS->value => $this->account->billingContact->email,
        ];

        $this->mockPaymentProcessor();
        $this->assertPaymentProcessorPopulateCalledWithExpectedBillingInfo(expectedBillingInfo: $expectedBillingInfo);

        $this->handleJob();

        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_uses_account_service_info_as_backup_if_payment_method_info_and_account_billing_is_empty(): void
    {
        $this->createAccountPaymentMethod();

        // Remove billing info from payment method
        $this->paymentMethod->update([
            'name_on_account' => null,
            'address_line1' => null,
            'city' => null,
            'province' => null,
            'postal_code' => null,
            'country_code' => null,
            'email' => null,
        ]);

        // Remove billing info from account
        $this->account->billingAddress->update([
            'address' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
        ]);
        $this->account->billingContact->update([
            'first_name' => null,
            'last_name' => null,
            'email' => null,
        ]);

        $expectedBillingInfo = [
            OperationFields::NAME_ON_ACCOUNT->value => $this->account->contact->full_name,
            OperationFields::ADDRESS_LINE_1->value => $this->account->address->address,
            OperationFields::CITY->value => $this->account->address->city,
            OperationFields::PROVINCE->value => $this->account->address->state,
            OperationFields::POSTAL_CODE->value => $this->account->address->postal_code,
            OperationFields::COUNTRY_CODE->value => $this->account->address->country,
            OperationFields::EMAIL_ADDRESS->value => $this->account->contact->email,
        ];

        $this->mockPaymentProcessor();
        $this->assertPaymentProcessorPopulateCalledWithExpectedBillingInfo(expectedBillingInfo: $expectedBillingInfo);

        $this->handleJob();

        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_calls_payment_processor_sale_method_if_invoices_total_balance_match_account_balance(): void
    {
        $this->createAccountPaymentMethod();

        $this->handleJob();
        $this->paymentProcessor->shouldHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_does_not_call_payment_processor_sale_method_if_invoices_total_balance_unmatch_account_balance(): void
    {
        $this->createAccountPaymentMethod();
        $this->invoices->add($this->createInvoiceForAccount()); // Create 1 more invoice so that the total invoice balance is greater than the account balance
        $this->job = $this->createProcessPaymentJob();

        $this->handleJob();
        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
    }

    #[Test]
    public function it_does_not_call_payment_processor_sale_method_if_invoices_total_balance_less_than_0(): void
    {
        $this->createAccountPaymentMethod();
        $this->invoices->each(static fn (Invoice $invoice) => $invoice->update(['balance' => -1]));
        $this->job = $this->createProcessPaymentJob();

        $this->handleJob();

        $this->paymentProcessor->shouldNotHaveReceived(method: 'sale');
    }

    public static function suspendedPaymentDataProvider(): iterable
    {
        yield 'processed more than one week' => [
            'attributes' => [
                'processed_at' => now()->subDays(8)
            ]
        ];
        yield 'not the same amount' => [
            'attributes' => [
                'amount' => 0
            ]
        ];
        yield 'uses different payment method' => [
            'attributes' => [],
            'usesDifferentPaymentMethod' => true
        ];
    }

    public static function validPreferredBillingDateProvider(): iterable
    {
        yield 'Preferred billing date is null (not set)' => [null];
        yield 'Preferred billing date is -1 (not set)' => [-1];
        yield 'Preferred billing date is 0 (not set)' => [0];
        yield 'Preferred billing date is yesterday' => [now()->day - 1];
        // Both check last day of month
        yield 'Preferred billing date is greater than number of days in the month' => [31, '2024-02-29'];
        yield 'Preferred billing date equals to number of days in the month' => [29, '2024-02-29'];
    }

    private function createAccount(): Account
    {
        $balance = random_int(100, 1000);
        $account = Account::factory()
            ->has(Ledger::factory(['balance' => $balance]))
            ->create(attributes: ['responsible_balance' => $balance]);

        return $account;
    }

    private function createInvoiceForAccount(Account|null $account = null): Invoice
    {
        // Mocking when invoice has negative balance, but should not affect sum balance
        Invoice::factory()->create([
            'account_id' => $account->id ?? $this->account->id,
            'balance' => -9999,
        ]);
        Invoice::factory()->create([
            'account_id' => $account->id ?? $this->account->id,
            'balance' => 9999,
        ]);

        return Invoice::factory()->create([
            'account_id' => $account->id ?? $this->account->id,
            'balance' => $account ? $account->responsible_balance : $this->account->responsible_balance,
        ]);
    }

    private function createAccountPaymentMethod(
        PaymentTypeEnum $paymentType = PaymentTypeEnum::ACH,
        \DateTime|null $paymentHoldDate = null
    ): void {
        $this->paymentMethod = match ($paymentType) {
            PaymentTypeEnum::CC => PaymentMethod::factory()->cc()->create([
                'account_id' => $this->account->id,
                'payment_type_id' => $paymentType,
                'payment_hold_date' => $paymentHoldDate,
            ]),
            PaymentTypeEnum::ACH => PaymentMethod::factory()->ach()->create([
                'account_id' => $this->account->id,
                'payment_type_id' => $paymentType,
                'payment_hold_date' => $paymentHoldDate,
            ]),
            default => PaymentMethod::factory()->create([
                'account_id' => $this->account->id,
                'payment_type_id' => $paymentType,
                'payment_hold_date' => $paymentHoldDate,
            ])
        };
        $this->differentPaymentMethodId = PaymentMethod::factory()->create([
            'account_id' => $this->account->id,
            'payment_type_id' => $paymentType,
            'payment_hold_date' => $paymentHoldDate,
        ])->id;

        $this->account->setAutoPayPaymentMethod(autopayPaymentMethod: $this->paymentMethod);
    }

    private function createDuplicatedPayment(array $attributes = [], string|null $paymentMethodId = null): void
    {
        $params = collect([
            'account_id' => $this->account->id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED,
            'is_batch_payment' => true,
            'payment_method_id' => $paymentMethodId ?? $this->paymentMethod->id,
            'processed_at' => now()->subDays(3)->format('Y-m-d'),
            'amount' => $this->account->ledger->balance,
        ])->merge($attributes)->all();
        $duplicatedPayment = Payment::factory()->create($params);

        // Set same invoices
        foreach ($this->invoices as $invoice) {
            $duplicatedPayment->invoices()->create([
                'invoice_id' => $invoice->id,
                'amount' => (int) $invoice->balance,
            ]);
        }
    }

    private function createProcessPaymentJob(): ProcessPaymentJob
    {
        $job = new ProcessPaymentJob(
            account: $this->account,
            invoices: $this->invoices->toArray(),
        );

        $mockJob = $this->createMock(Job::class);
        $mockJob->method('uuid')->willReturn(value: (string)Str::uuid());
        $job->setJob(job: $mockJob);

        return $job;
    }

    private function mockPaymentProcessor(): void
    {
        $this->paymentProcessor = \Mockery::mock(PaymentProcessor::class);
        $this->paymentProcessor->allows('setLogger')->byDefault();
        $this->paymentProcessor->allows('setGateway')->andReturnNull()->byDefault();
        $this->paymentProcessor->allows('getResponseData')->andReturns('someResponse')->byDefault();
        $this->paymentProcessor->allows('getError')->andReturns('someError')->byDefault();
        $this->paymentProcessor->allows('sale')->andReturns(true)->byDefault();
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
    }

    private function handleJob(): void
    {
        $this->job->handle(
            paymentProcessor: $this->paymentProcessor,
            logger: $this->logger,
            paymentRepository: $this->app->make(abstract: PaymentRepository::class),
            paymentMethodRepository: $this->app->make(abstract: PaymentMethodRepository::class),
            accountRepository: $this->app->make(abstract: AccountRepository::class),
            configCatClient: $this->mockConfigCatClient,
            duplicatePaymentChecker: $this->app->make(abstract: DuplicatePaymentChecker::class)
        );
    }

    private function assertPaymentProcessorPopulateCalledWithExpectedBillingInfo(array $expectedBillingInfo): void
    {
        $this->paymentProcessor->allows('populate')
            ->withArgs(
                static fn ($array) => \Arr::only($array, [
                    OperationFields::NAME_ON_ACCOUNT->value,
                    OperationFields::ADDRESS_LINE_1->value,
                    OperationFields::CITY->value,
                    OperationFields::PROVINCE->value,
                    OperationFields::POSTAL_CODE->value,
                    OperationFields::COUNTRY_CODE->value,
                    OperationFields::EMAIL_ADDRESS->value
                ]) === $expectedBillingInfo
            )
            ->byDefault();
    }

    protected function tearDown(): void
    {
        unset(
            $this->account,
            $this->invoices,
            $this->job,
            $this->paymentProcessor,
            $this->logger,
            $this->paymentMethod,
            $this->expectedUrn
        );

        parent::tearDown();
    }
}
