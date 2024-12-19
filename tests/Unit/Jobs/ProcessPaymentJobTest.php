<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Events\PaymentAttemptedEvent;
use App\Events\PaymentSkippedEvent;
use App\Jobs\ProcessPaymentJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Address;
use App\Models\CRM\Customer\Contact;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\PaymentProcessor;
use App\Services\Payment\DuplicatePaymentChecker;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfileStatus;
use ConfigCat\ClientInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Helpers\Traits\QueueJobWithUuidTrait;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class ProcessPaymentJobTest extends UnitTestCase
{
    use MockeryPHPUnitIntegration;
    use QueueJobWithUuidTrait;
    use WorldPayCredentialsRepositoryMockingTrait;

    private Account $account;
    /** @var Collection<int, Invoice> $invoices */
    private Collection $invoices;
    private string $invoicesIdentifier;
    private ProcessPaymentJob $job;
    /** @var MockObject&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;
    /** @var MockObject&ClientInterface $mockConfigCatClient */
    private ClientInterface $mockConfigCatClient;
    private LoggerInterface|MockInterface $logger;
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;
    /** @var MockObject&AccountRepository $accountRepository */
    private AccountRepository $accountRepository;
    /** @var MockObject&DuplicatePaymentChecker $duplicatePaymentChecker */
    private DuplicatePaymentChecker $duplicatePaymentChecker;
    /** @var MockObject&PaymentMethodRepository $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;

    protected function setUp(): void
    {
        parent::setUp();

        /* @method Account makeWithRelationships() */
        $this->account = Account::factory()->makeWithRelationships(relationships: [
            'area' => Area::factory()->make(),
            'contact' => Contact::factory()->make(),
            'billingContact' => Contact::factory()->make(),
            'billingAddress' => Address::factory()->make(),
            'address' => Address::factory()->make(),
        ]);
        $ledger = Ledger::factory()->makeWithRelationships(
            relationships: ['autopayMethod' => PaymentMethod::factory()->withoutRelationships()->make()]
        );
        $this->account->setRelation(relation: 'ledger', value: $ledger);

        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::factory()->count(5)->makeWithRelationships(
            relationships: [
                'account' => $this->account,
                'items' => InvoiceItem::factory()->count(2)->withoutRelationships()->make(),
            ]
        );
        $this->invoices = $invoices;
        $this->invoicesIdentifier = $this->getInvoicesIdentifier();
        $this->mockConfigCatClient = $this->createMock(originalClassName: ClientInterface::class);

        $this->job = new ProcessPaymentJob(
            account: $this->account,
            invoices: $this->invoices->toArray(),
        );
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);
        $this->logger = Mockery::spy($this->createMock(originalClassName: LoggerInterface::class));
        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);
        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->accountRepository = $this->createMock(originalClassName: AccountRepository::class);
        $this->duplicatePaymentChecker = $this->createMock(originalClassName: DuplicatePaymentChecker::class);

        $this->mockWorldPayCredentialsRepository();

        DB::shouldReceive('transaction')->andReturnUsing(callback: static fn ($callback) => $callback());
        // Create a mock QueryExecuted event
        $queryExecutedMock = Mockery::mock(QueryExecuted::class);
        $queryExecutedMock->sql = 'SELECT * FROM table';
        $queryExecutedMock->bindings = [];
        $queryExecutedMock->time = 100;
        // Mock DB::listen to trigger the callback with the mock QueryExecuted
        DB::shouldReceive('listen')
            ->andReturnUsing(static function ($callback) use ($queryExecutedMock) {
                $callback($queryExecutedMock);
            });

        Event::fake();
    }

    #[Test]
    public function it_adds_warning_and_does_not_dispatch_payment_processed_event_if_account_does_not_have_any_payment_methods(): void
    {
        $this->mockConfigCatClient->method('getValue')->willReturn(false);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(false);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(1);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            relationships: ['account' => $this->account]
        );
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn($paymentMethod);
        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('warning')->once()->withArgs([
            sprintf(
                '%s, skip payment processing',
                __('messages.payment.batch_processing.account_does_not_have_payment_methods'),
            ),
            ['account_id' => $this->account->id],
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
        Event::assertDispatched(PaymentSkippedEvent::class);
    }

    #[Test]
    public function it_adds_warning_and_does_not_dispatch_payment_processed_event_if_primary_payment_method_for_account_not_found(): void
    {
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(true);
        $this->paymentMethodRepository->method('findPrimaryForAccount')->willReturn(null);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(1);
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn(null);

        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('warning')->once()->withArgs([
            __('messages.payment.batch_processing.autopay_method_not_found') . ', skip payment processing',
            ['account_id' => $this->account->id],
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
        Event::assertDispatched(PaymentSkippedEvent::class);
    }

    #[Test]
    #[DataProvider('paymentMethodPestRoutesStatusProvider')]
    public function it_adds_warning_and_does_not_dispatch_payment_processed_event_if_payment_method_status_is_invalid(
        PaymentProfileStatus $status
    ): void {
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(true);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(1);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(
            attributes: ['pestroutes_status_id' => $status->value],
            relationships: ['account' => $this->account]
        );
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn($paymentMethod);

        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('warning')->once()->withArgs([
            sprintf(
                '%s, skip payment processing',
                __('messages.payment.batch_processing.autopay_payment_method_invalid_status')
            ),
            [
                'account_id' => $this->account->id,
                'status' => $status->name,
            ],
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
        Event::assertDispatched(PaymentSkippedEvent::class);
    }

    #[Test]
    public function it_adds_warning_and_does_not_dispatch_payment_processed_event_if_total_payment_attempts_exceeded(): void
    {
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(true);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(1);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $this->account]);
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn($paymentMethod);

        $failedPayments = Payment::factory()->count(3)->makeWithRelationships(
            attributes: ['payment_status_id' => PaymentStatusEnum::DECLINED->value, 'currency_code' => 'USD'],
            relationships: ['account' => $this->account, 'paymentMethod' => $paymentMethod]
        );
        $this->paymentRepository->method('getLatestPaymentsForPaymentMethod')->willReturn($failedPayments);

        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('warning')->once()->withArgs([
            sprintf(
                '%s, skip payment processing',
                __('messages.payment.batch_processing.total_payment_attempts_exceeded')
            ),
            ['max_payment_attempts' => 3]
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
        Event::assertDispatched(PaymentSkippedEvent::class);
    }

    #[Test]
    public function it_adds_warning_and_does_not_dispatch_payment_processed_event_if_daily_payment_attempts_exceeded(): void
    {
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(true);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(1);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $this->account]);
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn($paymentMethod);

        $this->paymentRepository->method('getLatestPaymentsForPaymentMethod')->willReturn(collect());
        $this->paymentRepository->method('getDeclinedForPaymentMethodCount')->willReturn(1);

        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('warning')->once()->withArgs([
            sprintf(
                '%s, skip payment processing',
                __('messages.payment.batch_processing.daily_payment_attempts_exceeded')
            ),
            ['max_payment_attempts' => 1]
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
        Event::assertDispatched(PaymentSkippedEvent::class);
    }

    #[Test]
    public function it_adds_notice_and_does_not_dispatch_payment_processed_event_if_there_is_no_unpaid_balance(): void
    {
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(true);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(0);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $this->account]);
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn($paymentMethod);

        $this->paymentRepository->method('getLatestPaymentsForPaymentMethod')->willReturn(collect());
        $this->paymentRepository->method('getDeclinedForPaymentMethodCount')->willReturn(1);

        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('notice')->once()->withArgs([
            sprintf(
                'Payment for %s skipped because there is no unpaid balance',
                $this->invoicesIdentifier,
            )
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
        Event::assertDispatched(PaymentSkippedEvent::class);
    }

    #[Test]
    public function it_does_not_dispatch_payment_processed_event_if_previous_payment_is_terminated(): void
    {
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(true);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(1);

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $this->account]);
        $terminatedPayment = Payment::factory()->withoutRelationships()->make([
            'payment_status_id' => PaymentStatusEnum::TERMINATED->value
        ]);
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn($paymentMethod);
        $this->paymentRepository->method('getTerminatedPaymentForInvoices')->willReturn($terminatedPayment);

        $this->paymentRepository->method('getLatestPaymentsForPaymentMethod')->willReturn(collect());
        $this->paymentRepository->method('getDeclinedForPaymentMethodCount')->willReturn(0);

        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('warning')->withArgs([
            sprintf(
                'Payment for invoices [%s] skipped because payment %s is terminated',
                $this->getInvoicesIdentifier(),
                $terminatedPayment->id
            ),
            [
                'error_message' => sprintf(
                    'Batch payment processing for payment [%s] skipped because payment is terminated',
                    $terminatedPayment->id
                )
            ]
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
        Event::assertDispatched(PaymentSkippedEvent::class);
    }

    #[Test]
    public function it_does_not_dispatch_payment_processed_event_if_previous_payment_is_duplicated(): void
    {
        $this->mockConfigCatClient->method('getValue')->willReturn(true);
        $this->paymentMethodRepository->method('existsForAccount')->willReturn(true);
        $this->accountRepository->method('getAmountLedgerBalance')->willReturn(collect($this->invoices)->sum('balance'));

        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $this->account]);
        $terminatedPayment = Payment::factory()->withoutRelationships()->make([
            'payment_status_id' => PaymentStatusEnum::TERMINATED->value
        ]);
        $this->paymentMethodRepository->method('findAutopayMethodForAccount')->willReturn($paymentMethod);
        $this->paymentRepository->method('getTerminatedPaymentForInvoices')->willReturn(null);
        $fakePayment = Payment::factory()->withoutRelationships()->make();
        $this->paymentRepository->method('create')->willReturn($fakePayment);

        $this->paymentRepository->method('getLatestPaymentsForPaymentMethod')->willReturn(collect());
        $this->paymentRepository->method('getDeclinedForPaymentMethodCount')->willReturn(0);

        $this->duplicatePaymentChecker->method('isDuplicatePayment')->willReturn(true);
        $latestTerminatedOrSuspendedPayment = Payment::factory()->withoutRelationships()->make();
        $this->duplicatePaymentChecker->method('getOriginalPayment')->willReturn($latestTerminatedOrSuspendedPayment);

        $this->handleJob(job: $this->job);

        $this->logger->shouldHaveReceived('notice')->withArgs([
            sprintf(
                'Payment %s marked suspended',
                $fakePayment->id
            ),
            [
                'message' => sprintf(
                    'Duplicated with payment %s',
                    $latestTerminatedOrSuspendedPayment->id
                )
            ]
        ]);

        Event::assertNotDispatched(PaymentAttemptedEvent::class);
    }

    #[Test]
    public function id_adds_error_log_when_job_fails(): void
    {
        $exception = new \Exception('Test exception');

        Log::expects('error')->once()->withArgs([
            'ProcessPaymentJob failed',
            ['message' => $exception->getMessage(), 'trace' => $exception->getTrace()]
        ]);

        $this->job->failed($exception);
    }

    public static function paymentMethodPestRoutesStatusProvider(): iterable
    {
        yield 'soft deleted' => [PaymentProfileStatus::SoftDeleted];
        yield 'empty' => [PaymentProfileStatus::Empty];
    }

    private function getInvoicesIdentifier(): string
    {
        return collect($this->invoices)->map(static fn ($invoice) => substr($invoice['id'], -6))->join(', ');
    }

    private function handleJob(ProcessPaymentJob $job): void
    {
        $job->handle(
            paymentProcessor: $this->paymentProcessor,
            logger: $this->logger,
            paymentRepository: $this->paymentRepository,
            paymentMethodRepository: $this->paymentMethodRepository,
            accountRepository: $this->accountRepository,
            configCatClient: $this->mockConfigCatClient,
            duplicatePaymentChecker: $this->duplicatePaymentChecker
        );
    }

    protected function tearDown(): void
    {
        unset($this->account, $this->job, $this->paymentProcessor, $this->logger);
        Mockery::close();

        parent::tearDown();
    }
}
