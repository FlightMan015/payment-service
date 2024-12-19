<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Repositories\Interface\InvoiceRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Helpers\MoneyHelper;
use App\Infrastructure\PestRoutes\PestRoutesDataRetrieverService;
use App\Jobs\ProcessPaymentJob;
use App\Jobs\RetrieveAreaUnpaidInvoicesJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\PaymentMethod;
use Aptive\PestRoutesSDK\Collection as PestRoutesSdkCollection;
use Aptive\PestRoutesSDK\Converters\DateTimeConverter;
use Aptive\PestRoutesSDK\Resources\Customers\Customer;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfile;
use Aptive\PestRoutesSDK\Resources\Tickets\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Stubs\CustomerResponses;
use Tests\Stubs\PaymentProfileResponses;
use Tests\Stubs\TicketResponses;
use Tests\Unit\UnitTestCase;

class RetrieveAreaUnpaidInvoicesJobTest extends UnitTestCase
{
    /** @var AccountRepository&MockObject $accountRepository */
    private AccountRepository $accountRepository;
    /** @var AreaRepository&MockObject $areaRepository */
    private AreaRepository $areaRepository;
    /** @var PaymentMethodRepository&MockObject $paymentMethodRepository */
    private PaymentMethodRepository $paymentMethodRepository;
    /** @var InvoiceRepository&MockObject $invoiceRepository */
    private InvoiceRepository $invoiceRepository;
    /** @var PestRoutesDataRetrieverService&MockObject $pestRoutesDataRetriever */
    private PestRoutesDataRetrieverService $pestRoutesDataRetriever;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountRepository = $this->createMock(originalClassName: AccountRepository::class);
        $this->areaRepository = $this->createMock(originalClassName: AreaRepository::class);
        $this->paymentMethodRepository = $this->createMock(originalClassName: PaymentMethodRepository::class);
        $this->invoiceRepository = $this->createMock(originalClassName: InvoiceRepository::class);
        $this->pestRoutesDataRetriever = $this->createMock(originalClassName: PestRoutesDataRetrieverService::class);

        Queue::fake();
    }

    #[Test]
    #[DataProvider('invalidConfigProvider')]
    public function it_throws_exception_if_incorrect_config_provided(array $config): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing config value');

        new RetrieveAreaUnpaidInvoicesJob(areaId: 123, config: $config);
    }

    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        $areaId = 123;
        $config = [
            'isPestRoutesBalanceCheckEnabled' => false,
            'isPestRoutesAutoPayCheckEnabled' => false,
            'isPestRoutesPaymentHoldDateCheckEnabled' => false,
            'isPestRoutesInvoiceCheckEnabled' => false,
        ];

        RetrieveAreaUnpaidInvoicesJob::dispatch($areaId, $config);

        Queue::assertPushedOn(queue: config(key: 'queue.connections.sqs.queues.process_payments'), job: RetrieveAreaUnpaidInvoicesJob::class);
    }

    #[Test]
    public function it_retrieves_accounts_and_dispatches_process_payment_jobs_when_pestroutes_check_disabled(): void
    {
        /** @var Collection<int, Account> $accounts */
        $accounts = Account::factory()->count(4)->withoutRelationships()->make();
        $this->accountRepository->expects($this->once())
            ->method('getAccountsWithUnpaidBalance')
            ->with(areaId: 49, page: 1, quantity: 500)
            ->willReturn(
                new LengthAwarePaginator(items: $accounts, total: 4, perPage: 10)
            );

        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->handleJob(areaId: 49, isPestRoutesBalanceCheckEnabled: false, isPestRoutesAutoPayCheckEnabled: false);

        Queue::assertPushed(job: ProcessPaymentJob::class, callback: 4);
    }

    #[Test]
    public function it_retrieves_accounts_with_pagination_and_dispatches_process_payment_jobs_when_pestroutes_check_disabled(): void
    {
        /** @var Collection<int, Account> $lastAccount */
        $lastAccount = Account::factory()->count(1)->withoutRelationships()->make();
        $totalAccountsCount = RetrieveAreaUnpaidInvoicesJob::ACCOUNTS_BATCH_SIZE_PER_REQUEST + 1;
        $this->accountRepository
            ->expects($this->exactly(2))
            ->method('getAccountsWithUnpaidBalance')
            ->willReturnCallback(static fn ($officeId, $page, $perPage) => match ($page) {
                1 => new LengthAwarePaginator(
                    items: Account::factory()->count(500)->withoutRelationships()->make(),
                    total: $totalAccountsCount,
                    perPage: RetrieveAreaUnpaidInvoicesJob::ACCOUNTS_BATCH_SIZE_PER_REQUEST,
                    currentPage: 1
                ),
                2 => new LengthAwarePaginator(
                    items: $lastAccount,
                    total: $totalAccountsCount,
                    perPage: RetrieveAreaUnpaidInvoicesJob::ACCOUNTS_BATCH_SIZE_PER_REQUEST,
                    currentPage: 2
                ),
                default => throw new \RuntimeException('Unexpected page number')
            });

        $invoices = $this->mockDatabaseInvoicesForAccounts($lastAccount);

        $this->handleJob(areaId: 49, isPestRoutesBalanceCheckEnabled: false, isPestRoutesAutoPayCheckEnabled: false);

        // Ensure no jobs are dispatched for accounts without invoices
        Queue::assertPushed(job: ProcessPaymentJob::class, callback: 1);
    }

    #[Test]
    public function it_retrieves_accounts_and_dispatches_process_payment_jobs_when_pestroutes_check_enabled(): void
    {
        $area = Area::factory()->make();

        $customers = CustomerResponses::getCollection(quantity: 5, totalQuantity: 5);
        $this->areaRepository->method('find')->willReturn($area);
        $this->pestRoutesDataRetriever->method('getCustomersWithUnpaidBalance')->willReturn($customers);

        /** @var Collection<int, Account> $accounts */
        $accounts = new Collection();

        foreach ($customers as $customer) {
            $account = Account::factory()->makeWithRelationships(
                attributes: ['external_ref_id' => $customer->id],
                relationships: ['ledger' => null, 'area' => $area]
            );
            $accounts->push($account);
        }

        $this->accountRepository->method('getByExternalIds')->willReturn($accounts);

        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->handleJob(areaId: 49, isPestRoutesBalanceCheckEnabled: true, isPestRoutesAutoPayCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class, callback: count($customers));
    }

    #[Test]
    public function it_throws_exception_when_pestroutes_check_enabled_and_area_is_not_found(): void
    {
        $this->areaRepository->method('find')->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(__('messages.area.not_found', ['id' => 49]));

        $this->handleJob(areaId: 49, isPestRoutesBalanceCheckEnabled: true, isPestRoutesAutoPayCheckEnabled: true);

        Queue::assertNotPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_retrieves_account_and_add_info_logs_when_account_has_up_to_date_information_when_all_pestroutes_checks_enabled(): void
    {
        $tickets = TicketResponses::getTicketCollection(quantity: 5, totalQuantity: 5);
        $this->pestRoutesDataRetriever
            ->method('getTicketsByCustomerIds')
            ->willReturnOnConsecutiveCalls(
                $tickets
            );
        $balance = collect($tickets->items)->sum('balance');
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area,
            paymentProfile: $paymentProfile
        );
        $invoices = $this->mockInvoices($tickets, $account);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();
        $this->assertAccountBalanceMatchesCustomerBalanceInfoLog(account: $account, customer: $customer);
        $this->assertAccountAutopayMethodMatchesCustomerAutopayMethodInfoLog(account: $account, customer: $customer, autoPayPaymentMethod: $autoPayMethod);
        $this->assertPaymentProfilePaymentHoldDateMatchesPaymentMethodPaymentHoldDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountPreferredBillingDateMatchesCustomerPreferredBillingDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->assertInvoicesRetrievedFromPestRoutesInfoLog(count: count($tickets));
        $this->assertInvoicesComparedAndFilteredInfoLog(pestRoutesInvoicesCount: count($tickets), databaseInvoicesCount: count($invoices));

        $this->handleJob(
            areaId: $area->id,
            isPestRoutesBalanceCheckEnabled: true,
            isPestRoutesAutoPayCheckEnabled: true,
            isPestRoutesPaymentHoldDateCheckEnabled: true,
            isPestRoutesInvoiceCheckEnabled: true
        );

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_retrieves_account_and_add_warning_log_when_pestroutes_invoice_not_existed_in_db(): void
    {
        $tickets = TicketResponses::getTicketCollection(quantity: 5, totalQuantity: 5);
        $this->pestRoutesDataRetriever
            ->method('getTicketsByCustomerIds')
            ->willReturnOnConsecutiveCalls(
                $tickets
            );
        $balance = collect($tickets->items)->sum('balance');
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area,
            paymentProfile: $paymentProfile
        );
        $newTickets = TicketResponses::getTicketCollection(quantity: 5, totalQuantity: 5);
        $invoices = $this->mockInvoices($newTickets, $account);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();
        $this->assertAccountBalanceMatchesCustomerBalanceInfoLog(account: $account, customer: $customer);
        $this->assertAccountAutopayMethodMatchesCustomerAutopayMethodInfoLog(account: $account, customer: $customer, autoPayPaymentMethod: $autoPayMethod);
        $this->assertPaymentProfilePaymentHoldDateMatchesPaymentMethodPaymentHoldDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountPreferredBillingDateMatchesCustomerPreferredBillingDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->assertInvoicesRetrievedFromPestRoutesInfoLog(count: 5);
        $this->assertInvoiceBalanceDiscrepancyWarningLog(invoice: $invoices->first(), ticket: $tickets->items[0]);
        foreach ($tickets->items as $ticket) {
            $this->assertInvoiceNotFoundByExternalReferenceIdWarningLog(ticket: $ticket);
        }
        $this->assertInvoicesComparedAndFilteredInfoLog(pestRoutesInvoicesCount: 5, databaseInvoicesCount: 5);

        $this->handleJob(
            areaId: $area->id,
            isPestRoutesBalanceCheckEnabled: true,
            isPestRoutesAutoPayCheckEnabled: true,
            isPestRoutesPaymentHoldDateCheckEnabled: true,
            isPestRoutesInvoiceCheckEnabled: true
        );

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_retrieves_account_and_add_warning_log_when_pestroutes_invoice_balance_discrepancy_detected(): void
    {
        $tickets = TicketResponses::getTicketCollection(quantity: 1, totalQuantity: 1);
        $this->pestRoutesDataRetriever
            ->method('getTicketsByCustomerIds')
            ->willReturnOnConsecutiveCalls(
                $tickets
            );
        $balance = collect($tickets->items)->sum('balance');
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area,
            paymentProfile: $paymentProfile
        );
        $invoices = $this->mockInvoices($tickets, $account);
        $invoices->first()->update(['balance' => $invoices->first()->balance + 1]);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();
        $this->assertAccountBalanceMatchesCustomerBalanceInfoLog(account: $account, customer: $customer);
        $this->assertAccountAutopayMethodMatchesCustomerAutopayMethodInfoLog(account: $account, customer: $customer, autoPayPaymentMethod: $autoPayMethod);
        $this->assertPaymentProfilePaymentHoldDateMatchesPaymentMethodPaymentHoldDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountPreferredBillingDateMatchesCustomerPreferredBillingDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->assertInvoicesRetrievedFromPestRoutesInfoLog(count: 1);
        $this->assertInvoiceBalanceDiscrepancyWarningLog(invoice: $invoices->first(), ticket: $tickets->items[0]);
        foreach ($tickets->items as $ticket) {
            $this->assertInvoiceNotFoundByExternalReferenceIdWarningLog(ticket: $ticket);
        }
        $this->assertInvoicesComparedAndFilteredInfoLog(pestRoutesInvoicesCount: 1, databaseInvoicesCount: 1);

        $this->handleJob(
            areaId: $area->id,
            isPestRoutesBalanceCheckEnabled: true,
            isPestRoutesAutoPayCheckEnabled: true,
            isPestRoutesPaymentHoldDateCheckEnabled: true,
            isPestRoutesInvoiceCheckEnabled: true
        );

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_retrieves_account_and_add_info_logs_when_account_has_up_to_date_information_when_balance_check_enabled(): void
    {
        $balance = 6.53;
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area
        );
        /** @var Collection<int, Account> $accounts */
        $accounts = (new Collection())->push($account);
        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();
        $this->assertAccountBalanceMatchesCustomerBalanceInfoLog(account: $account, customer: $customer);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->handleJob(areaId: $area->id, isPestRoutesBalanceCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_retrieves_account_and_add_info_logs_when_account_has_up_to_date_information_when_autopay_check_enabled(): void
    {
        $balance = 6.53;
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area
        );
        /** @var Collection<int, Account> $accounts */
        $accounts = (new Collection())->push($account);
        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();
        $this->assertAccountAutopayMethodMatchesCustomerAutopayMethodInfoLog(account: $account, customer: $customer, autoPayPaymentMethod: $autoPayMethod);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->handleJob(areaId: $area->id, isPestRoutesAutoPayCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_log_when_account_is_not_found_in_database_when_all_checks_enabled(): void
    {
        $tickets = TicketResponses::getEmptyTicketCollection();
        $this->pestRoutesDataRetriever
            ->method('getTicketsByCustomerIds')
            ->willReturnOnConsecutiveCalls(
                $tickets
            );
        $balance = 6.53;
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId);
        $this->accountRepository->method('getByExternalIds')->willReturn(new Collection());
        $this->mockInvoices(tickets: $tickets, account: null);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog(count: 0);

        $this->assertAccountNotFoundInDatabaseWarningLog(customerId: $customer->id);

        $this->assertAccountsWereComparedInfoLog(count: 0);
        $this->assertPaymentProcessedJobsDispatchedInfoLog(count: 0);

        $this->assertInvoicesRetrievedFromPestRoutesInfoLog(count: 0);
        $this->assertInvoicesComparedAndFilteredInfoLog(pestRoutesInvoicesCount: 0, databaseInvoicesCount: 0);

        $this->handleJob(
            areaId: $area->id,
            isPestRoutesBalanceCheckEnabled: true,
            isPestRoutesAutoPayCheckEnabled: true,
            isPestRoutesPaymentHoldDateCheckEnabled: true,
            isPestRoutesInvoiceCheckEnabled: true
        );

        Queue::assertNotPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_log_and_updating_balance_when_account_balance_does_not_match_and_check_enabled(): void
    {
        $customerBalance = 6.53;
        $accountBalance = 12.54;
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $customerBalance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            balance: $accountBalance,
            customer: $customer,
            area: $area
        );
        /** @var Collection<int, Account> $accounts */
        $accounts = (new Collection())->push($account);
        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertAccountBalanceDiscrepancyWarningLog(account: $account, customer: $customer);
        $this->assertAccountLedgerUpdatedWithCustomerBalance($account, $customerBalance);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->handleJob(areaId: $area->id, isPestRoutesBalanceCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_log_when_customer_does_not_have_autopay_method_in_pestroutes_when_check_is_enabled(): void
    {
        $balance = 6.53;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: null, balance: $balance);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: null,
            balance: $balance,
            customer: $customer,
            area: $area
        );

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(count: 0, paymentProfiles: []);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertCustomerDoesNotHaveAutoPayPaymentMethodInPestRoutesWarningLogForAutoPayCheck(account: $account, customer: $customer);

        $this->assertAccountsWereComparedInfoLog(count: 0);
        $this->assertPaymentProcessedJobsDispatchedInfoLog(count: 0);

        $this->handleJob(areaId: $area->id, isPestRoutesAutoPayCheckEnabled: true);

        Queue::assertNotPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_log_and_updating_autopay_method_when_account_autopay_does_not_match_and_check_enabled(): void
    {
        $balance = 6.53;
        $customerPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $customerPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $customerPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: null,
            balance: $balance,
            customer: $customer,
            area: $area
        );
        $newPaymentMethod = PaymentMethod::factory()->withoutRelationships()->make(['external_ref_id' => $customerPaymentProfileId]);
        $this->paymentMethodRepository->method('findByExternalRefId')->willReturn($newPaymentMethod);
        /** @var Collection<int, Account> $accounts */
        $accounts = (new Collection())->push($account);
        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertAccountAutoPayDiscrepancyWarningLog(account: $account, customer: $customer);
        $this->assertAccountAutoPayLedgerUpdatingInfoLog(account: $account, customer: $customer, paymentMethod: $newPaymentMethod);
        $this->assertAccountLedgerUpdatedWithCustomerAutoPayPaymentMethod(account: $account, paymentMethod: $newPaymentMethod);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->handleJob(areaId: $area->id, isPestRoutesAutoPayCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_logs_when_account_autopay_does_not_match_and_does_not_exist_in_database_and_check_enabled(): void
    {
        $balance = 6.53;
        $customerPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $customerPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $customerPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: null,
            balance: $balance,
            customer: $customer,
            area: $area
        );
        $this->paymentMethodRepository->method('findByExternalRefId')->willThrowException(new ResourceNotFoundException());

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertAccountAutoPayDiscrepancyWarningLog(account: $account, customer: $customer);
        $this->assertCustomerAutopayPaymentMethodNotFoundInDatabaseWarningLogForAutoPayCheck(account: $account, customer: $customer);

        $this->assertAccountsWereComparedInfoLog(count: 0);
        $this->assertPaymentProcessedJobsDispatchedInfoLog(count: 0);

        $this->handleJob(areaId: $area->id, isPestRoutesAutoPayCheckEnabled: true);

        Queue::assertNotPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_retrieves_account_and_add_info_logs_when_account_has_up_to_date_information_when_payment_hold_date_check_enabled(): void
    {
        $balance = 6.53;
        $autoPayPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $autoPayPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $autoPayPaymentProfileId, paymentHoldDate: Carbon::instance($customer->paymentHoldDate));
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area,
            paymentHoldDate: Carbon::instance($customer->paymentHoldDate),
        );
        /** @var Collection<int, Account> $accounts */
        $accounts = (new Collection())->push($account);
        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();
        $this->assertPaymentProfilePaymentHoldDateMatchesPaymentMethodPaymentHoldDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountPreferredBillingDateMatchesCustomerPreferredBillingDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->handleJob(areaId: $area->id, isPestRoutesPaymentHoldDateCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    #[DataProvider('differentPaymentHoldDateDataProvider')]
    public function it_adds_warning_log_and_updating_payment_hold_date_when_account_payment_hold_date_does_not_match_and_check_enabled(
        \DateTimeInterface|null $paymentProfilePaymentHoldDate,
        \DateTimeInterface|null $paymentMethodPaymentHoldDate
    ): void {
        $balance = 6.53;
        $customerPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $customerPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(
            autoPayPaymentProfileId: $customerPaymentProfileId,
            paymentHoldDate: $paymentProfilePaymentHoldDate
        );
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $customerPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area,
            paymentHoldDate: $paymentMethodPaymentHoldDate
        );
        $this->paymentMethodRepository->method('findByExternalRefId')->willReturn($autoPayMethod);
        /** @var Collection<int, Account> $accounts */
        $accounts = (new Collection())->push($account);
        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertPaymentMethodPaymentHoldDateDiscrepancyWarningLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertPaymentMethodUpdatedWithPaymentProfilePaymentHoldDateMethod(paymentMethod: $autoPayMethod, paymentHoldDate: $paymentProfilePaymentHoldDate);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->handleJob(areaId: $area->id, isPestRoutesPaymentHoldDateCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_log_and_updating_preferred_billing_date_when_account_preferred_billing_date_does_not_match_and_check_enabled(): void
    {
        $balance = 6.53;
        $customerPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $customerPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $customerPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: $customerPaymentProfileId,
            balance: $balance,
            customer: $customer,
            area: $area,
            preferredBillingDate: $customer->preferredDayForBilling + 1
        );
        $this->paymentMethodRepository->method('findByExternalRefId')->willReturn($autoPayMethod);
        /** @var Collection<int, Account> $accounts */
        $accounts = (new Collection())->push($account);
        $this->mockDatabaseInvoicesForAccounts($accounts);

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertPaymentProfilePaymentHoldDateMatchesPaymentMethodPaymentHoldDateInfoLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountPreferredBillingDateDiscrepancyWarningLog(account: $account, customer: $customer, paymentProfile: $paymentProfile);
        $this->assertAccountUpdatedWithPreferredBillingDate(account: $account, preferredBillingDate: $customer->preferredDayForBilling);

        $this->assertAccountsWereComparedInfoLog(accounts: [$account]);
        $this->assertPaymentProcessedJobsDispatchedInfoLog();

        $this->handleJob(areaId: $area->id, isPestRoutesPaymentHoldDateCheckEnabled: true);

        Queue::assertPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_log_when_customer_does_not_have_autopay_method_selected_in_pestroutes_and_payment_hold_date_check_enabled(): void
    {
        $balance = 6.53;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: null, balance: $balance);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: null,
            balance: $balance,
            customer: $customer,
            area: $area
        );

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(count: 0, paymentProfiles: []);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertCustomerDoesNotHaveAutoPayPaymentMethodInPestRoutesWarningLogForHoldDateCheck(account: $account, customer: $customer);

        $this->assertAccountsWereComparedInfoLog(count: 0);
        $this->assertPaymentProcessedJobsDispatchedInfoLog(count: 0);

        $this->handleJob(areaId: $area->id, isPestRoutesPaymentHoldDateCheckEnabled: true);

        Queue::assertNotPushed(job: ProcessPaymentJob::class);
    }

    #[Test]
    public function it_adds_warning_log_when_customer_autopay_method_does_not_exist_in_database_and_payment_hold_date_check_enabled(): void
    {
        $balance = 6.53;
        $customerPaymentProfileId = 12345;

        $area = $this->createArea();
        $customer = $this->createCustomer(autoPayPaymentProfileId: $customerPaymentProfileId, balance: $balance);
        $paymentProfile = $this->createPaymentProfile(autoPayPaymentProfileId: $customerPaymentProfileId);
        [$account, $autoPayMethod] = $this->createAccount(
            autoPayPaymentProfileId: null,
            balance: $balance,
            customer: $customer,
            area: $area
        );

        $this->assertCustomersRetrievedFromPestRoutesInfoLog(customers: [$customer]);
        $this->assertPaymentProfilesRetrievedFromPestRoutesInfoLog(paymentProfiles: [$paymentProfile]);
        $this->assertAccountsRetrievedFromDatabaseInfoLog();

        $this->assertCustomerAutopayPaymentMethodNotFoundInDatabaseWarningLogForHoldDateCheck(account: $account, customer: $customer);

        $this->assertAccountsWereComparedInfoLog(count: 0);
        $this->assertPaymentProcessedJobsDispatchedInfoLog(count: 0);

        $this->handleJob(areaId: $area->id, isPestRoutesPaymentHoldDateCheckEnabled: true);

        Queue::assertNotPushed(job: ProcessPaymentJob::class);
    }

    public static function invalidConfigProvider(): iterable
    {
        yield 'empty config' => [[]];
        yield 'config without isPestRoutesBalanceCheckEnabled' => [
            [
                'isPestRoutesAutoPayCheckEnabled' => true,
                'isPestRoutesPaymentHoldDateCheckEnabled' => true,
            ]
        ];
        yield 'config without isPestRoutesAutoPayCheckEnabled' => [
            [
                'isPestRoutesBalanceCheckEnabled' => true,
                'isPestRoutesPaymentHoldDateCheckEnabled' => true,
            ]
        ];
        yield 'config without isPestRoutesPaymentHoldDateCheckEnabled' => [
            [
                'isPestRoutesAutoPayCheckEnabled' => true,
                'isPestRoutesBalanceCheckEnabled' => true,
            ]
        ];
    }

    public static function differentPaymentHoldDateDataProvider(): iterable
    {
        yield 'payment profile date - today, payment method date - tomorrow' => [Carbon::today(), Carbon::tomorrow()];
        yield 'payment profile date - null, payment method date - today' => [null, Carbon::today()];
        yield 'payment profile date - today, payment method date- null' => [Carbon::today(), null];
    }

    private function createArea(int $id = 49): Area
    {
        $area = Area::factory()->make(['id' => $id]);
        $this->areaRepository->method('find')->willReturn($area);

        return $area;
    }

    private function createCustomer(int|null $autoPayPaymentProfileId, float $balance): Customer
    {
        $customer = CustomerResponses::getCustomer(
            autoPayPaymentProfileId: $autoPayPaymentProfileId,
            responsibleBalance: $balance
        );

        $this->pestRoutesDataRetriever
            ->method('getCustomersWithUnpaidBalance')
            ->willReturn(new PestRoutesSdkCollection(items: [$customer], total: 1));

        return $customer;
    }

    private function createPaymentProfile(
        int|null $autoPayPaymentProfileId,
        \DateTimeInterface|null $paymentHoldDate = null
    ): PaymentProfile {
        $paymentProfile = PaymentProfileResponses::getProfile(
            paymentHoldDate: $paymentHoldDate
                ? Carbon::instance($paymentHoldDate)->tz(DateTimeConverter::PEST_ROUTES_TIMEZONE)->format('Y-m-d H:i:s')
                : '0000-00-00 00:00:00',
            id: $autoPayPaymentProfileId
        )->items[0];

        $this->pestRoutesDataRetriever
            ->method('getPaymentProfiles')
            ->willReturn(new PestRoutesSdkCollection(items: [$paymentProfile], total: 1));

        return $paymentProfile;
    }

    private function createAccount(
        int|null $autoPayPaymentProfileId,
        float $balance,
        Customer $customer,
        Area $area,
        PaymentProfile|null $paymentProfile = null,
        bool|\DateTimeInterface|null $paymentHoldDate = false,
        bool|int|null $preferredBillingDate = false
    ): array {
        $autoPayMethod = $autoPayPaymentProfileId
            ? PaymentMethod::factory()->withoutRelationships()->make([
                'external_ref_id' => $autoPayPaymentProfileId,
                'payment_hold_date' => $paymentHoldDate === false ? $paymentProfile?->paymentHoldDate : $paymentHoldDate
            ])
            : null;

        $ledger = Ledger::factory()->makeWithRelationships(
            attributes: ['balance' => MoneyHelper::convertToCents(amount: $balance), 'autopay_payment_method_id' => $autoPayMethod?->id],
            relationships: ['autopayMethod' => $autoPayMethod]
        );

        $account = Account::factory()->makeWithRelationships(
            attributes: [
                'external_ref_id' => $customer->id,
                'preferred_billing_day_of_month' => $preferredBillingDate === false
                    ? $customer->preferredDayForBilling
                    : $preferredBillingDate
            ],
            relationships: ['ledger' => $ledger, 'area' => $area]
        );

        $this->accountRepository->expects($this->once())->method('getByExternalIds')->with([$customer->id])->willReturn(new Collection(items: [$account]));

        return [$account, $autoPayMethod];
    }

    /**
     * @param PestRoutesSdkCollection $tickets
     * @param Account|null|null $account
     *
     * @return Collection<int, Invoice>
     */
    private function mockInvoices(PestRoutesSdkCollection $tickets, Account|null $account = null): SupportCollection
    {
        $invoices = collect();
        if ($account && $tickets->count()) {
            foreach ($tickets as $ticket) {
                $invoice = Invoice::factory()->makeWithRelationships(
                    [
                        'external_ref_id' => $ticket->id,
                        'balance' => MoneyHelper::convertToCents($ticket->balance),
                        'account_id' => $account->id,
                    ],
                    [
                        'account' => $account
                    ]
                );
                $invoices->add($invoice);

                Log::shouldReceive('info')->with(__('messages.payment.batch_processing.balance_matches'), [
                    'invoice_id' => $invoice->id,
                    'invoice_balance' => $invoice->balance,
                    'customer_id' => $ticket->customerId,
                    'office_id' => $ticket->officeId,
                    'pest_routes_ticket_balance' => MoneyHelper::convertToCents(amount: $ticket->balance),
                    'original_invoice_balance' => $invoice->balance,
                ]);
            }
        }

        $this->invoiceRepository->expects($this->once())
            ->method('getByExternalIds')
            ->willReturn(
                $invoices
            );
        return $invoices;
    }

    /**
     * @param Collection<int, Account> $accounts
     *
     * @return SupportCollection<int, Invoice>
     */
    private function mockDatabaseInvoicesForAccounts(Collection $accounts): SupportCollection
    {
        $invoices = collect();
        foreach ($accounts as $account) {
            $invoices->add(Invoice::factory()->makeWithRelationships(
                attributes: [
                    'balance' => 1000,
                    'account_id' => $account->id,
                ],
                relationships: [
                    'account' => $account
                ]
            ));
        }

        $this->invoiceRepository
            ->method('getUnpaidInvoicesByAccountIds')
            ->willReturn(new LengthAwarePaginator(items: $invoices, total: $invoices->count(), perPage: 10));

        return $invoices;
    }

    private function assertCustomersRetrievedFromPestRoutesInfoLog(int $count = 1, array $customers = []): void
    {
        Log::shouldReceive('info')->once()->with('Retrieved customers from PestRoutes', [
            'count' => $count,
            'expected_customer_balance_to_be_processed' => array_map(
                callback: static fn (Customer $customer) => [
                    'customer_id' => $customer->id,
                    'balance' => $customer->responsibleBalance
                ],
                array: $customers
            )
        ]);
    }

    private function assertPaymentProfilesRetrievedFromPestRoutesInfoLog(int $count = 1, array $paymentProfiles = []): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.autopay_payment_profiles_retrieved'), ['count' => $count]);
    }

    private function assertAccountsRetrievedFromDatabaseInfoLog(int $count = 1): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.accounts_retrieved'), ['count' => $count]);
    }

    private function assertAccountBalanceMatchesCustomerBalanceInfoLog(Account $account, Customer $customer): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.balance_matches'), [
            'account_id' => $account->id,
            'account_balance' => $account->ledger?->balance,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'customer_balance' => MoneyHelper::convertToCents(amount: $customer->responsibleBalance),
            'original_customer_balance' => $customer->responsibleBalance,
        ]);
    }

    private function assertAccountAutopayMethodMatchesCustomerAutopayMethodInfoLog(
        Account $account,
        Customer $customer,
        PaymentMethod $autoPayPaymentMethod
    ): void {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.autopay_matches'), [
            'account_id' => $account->id,
            'autopay_payment_method_id' => $autoPayPaymentMethod->id,
            'autopay_payment_method_external_ref_id' => $autoPayPaymentMethod->external_ref_id,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'customer_autopay_payment_profile_id' => $customer->autoPayPaymentProfileId,
        ]);
    }

    private function assertPaymentProfilePaymentHoldDateMatchesPaymentMethodPaymentHoldDateInfoLog(Account $account, Customer $customer, PaymentProfile $paymentProfile): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.hold_date_matches'), [
            'account_id' => $account->id,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'payment_profile_id' => $paymentProfile->id,
            'payment_profile_payment_hold_date' => $paymentProfile->paymentHoldDate,
            'payment_method_id' => $account->ledger->autopayMethod->id,
            'payment_method_payment_hold_date' => $account->ledger->autopayMethod->payment_hold_date,
        ]);
    }

    private function assertAccountPreferredBillingDateMatchesCustomerPreferredBillingDateInfoLog(Account $account, Customer $customer, PaymentProfile $paymentProfile): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.preferred_billing_date_matches'), [
            'account_id' => $account->id,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'payment_profile_id' => $paymentProfile->id,
            'payment_profile_payment_hold_date' => $paymentProfile->paymentHoldDate,
            'payment_method_id' => $account->ledger->autopayMethod->id,
            'payment_method_payment_hold_date' => $account->ledger->autopayMethod->payment_hold_date,
            'account_preferred_billing_day_of_month' => $account->preferred_billing_day_of_month,
            'customer_preferred_billing_day_of_month' => $customer->preferredDayForBilling,
        ]);
    }

    private function assertAccountsWereComparedInfoLog(int $count = 1, array $accounts = []): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.accounts_compared_and_filtered'), [
            'count' => $count,
            'expected_account_balance_to_be_processed' => array_values(
                array_map(
                    callback: static fn (Account $account) => [
                        'account_id' => $account->id,
                        'account_external_ref_id' => $account->external_ref_id,
                        'balance' => $account->ledger?->balance
                    ],
                    array: $accounts
                )
            )
        ]);
    }

    private function assertPaymentProcessedJobsDispatchedInfoLog(int $count = 1): void
    {
        Log::shouldReceive('info')->once()->with('DISPATCHED - Process Payment Jobs', ['number_of_jobs' => $count]);
    }

    private function assertAccountNotFoundInDatabaseWarningLog(int $customerId): void
    {
        Log::shouldReceive('warning')->once()
            ->with(__('messages.account.not_found_by_ext_reference_id', ['id' => $customerId]));
    }

    private function assertInvoicesRetrievedFromPestRoutesInfoLog(int $count): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.invoices_retrieved'), ['count' => $count]);
    }

    private function assertInvoicesComparedAndFilteredInfoLog(int $pestRoutesInvoicesCount, int $databaseInvoicesCount): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.invoices_compared_and_filtered'), [
            'count_pest_routes_invoices' => $pestRoutesInvoicesCount,
            'count_database_invoices' => $databaseInvoicesCount,
        ]);
    }

    private function assertInvoiceNotFoundByExternalReferenceIdWarningLog(Ticket $ticket): void
    {
        Log::shouldReceive('warning')
            ->with(__('messages.invoice.not_found_by_ext_reference_id', ['ext_reference_id' => $ticket->id]));
    }

    private function assertInvoiceBalanceDiscrepancyWarningLog(Invoice $invoice, Ticket $ticket): void
    {
        Log::shouldReceive('warning')
            ->with(__('messages.payment.batch_processing.invoice_balance_discrepancy'), [
                'invoice_id' => $invoice->id,
                'invoice_balance' => $invoice->balance,
                'customer_id' => $ticket->customerId,
                'office_id' => $ticket->officeId,
                'pest_routes_ticket_balance' => MoneyHelper::convertToCents(amount: $ticket->balance),
                'original_invoice_balance' => $invoice->balance,
            ]);
    }

    private function assertAccountBalanceDiscrepancyWarningLog(Account $account, Customer $customer): void
    {
        Log::shouldReceive('warning')->once()->with(__('messages.payment.batch_processing.balance_discrepancy'), [
            'account_id' => $account->id,
            'account_balance' => $account->ledger?->balance,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'customer_balance' => MoneyHelper::convertToCents(amount: $customer->responsibleBalance),
            'original_customer_balance' => $customer->responsibleBalance,
        ]);
    }

    private function assertAccountLedgerUpdatedWithCustomerBalance(Account $account, float $customerBalance): void
    {
        $this->accountRepository->expects($this->once())->method('setLedgerBalance')->with(
            account: $account,
            balance: MoneyHelper::convertToCents(amount: $customerBalance)
        );
    }

    private function assertCustomerDoesNotHaveAutoPayPaymentMethodInPestRoutesWarningLog(array $context): void
    {
        Log::shouldReceive('warning')->once()->with(__('messages.payment.batch_processing.customer_not_on_autopay'), $context);
    }

    private function assertCustomerDoesNotHaveAutoPayPaymentMethodInPestRoutesWarningLogForAutoPayCheck(Account $account, Customer $customer): void
    {
        $this->assertCustomerDoesNotHaveAutoPayPaymentMethodInPestRoutesWarningLog(
            context: [
                'account_id' => $account->id,
                'autopay_payment_method_id' => null,
                'autopay_payment_method_external_ref_id' => null,
                'area_id' => $account->area->id,
                'customer_id' => $customer->id,
                'office_id' => $customer->officeId,
                'customer_autopay_payment_profile_id' => $customer->autoPayPaymentProfileId,
            ]
        );
    }

    private function assertCustomerDoesNotHaveAutoPayPaymentMethodInPestRoutesWarningLogForHoldDateCheck(Account $account, Customer $customer): void
    {
        $this->assertCustomerDoesNotHaveAutoPayPaymentMethodInPestRoutesWarningLog(
            context: [
                'account_id' => $account->id,
                'area_id' => $account->area->id,
                'customer_id' => $customer->id,
                'office_id' => $customer->officeId,
            ]
        );
    }

    private function assertAccountAutoPayDiscrepancyWarningLog(Account $account, Customer $customer): void
    {
        Log::shouldReceive('warning')->once()->with(__('messages.payment.batch_processing.autopay_discrepancy'), [
            'account_id' => $account->id,
            'autopay_payment_method_id' => null,
            'autopay_payment_method_external_ref_id' => null,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'customer_autopay_payment_profile_id' => $customer->autoPayPaymentProfileId
        ]);
    }

    private function assertAccountAutoPayLedgerUpdatingInfoLog(Account $account, Customer $customer, PaymentMethod $paymentMethod): void
    {
        Log::shouldReceive('info')->once()->with(__('messages.payment.batch_processing.autopay_updated'), [
            'account_id' => $account->id,
            'autopay_payment_method_id' => null,
            'autopay_payment_method_external_ref_id' => null,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'customer_autopay_payment_profile_id' => $customer->autoPayPaymentProfileId,
            'new_autopay_payment_method_id' => $paymentMethod->id,
            'new_autopay_payment_method_external_ref_id' => $paymentMethod->external_ref_id
        ]);
    }

    private function assertAccountLedgerUpdatedWithCustomerAutoPayPaymentMethod(Account $account, PaymentMethod $paymentMethod): void
    {
        $this->accountRepository->expects($this->once())->method('setAutoPayPaymentMethod')->with(
            account: $account,
            autopayPaymentMethod: $paymentMethod
        );
    }

    private function assertCustomerAutopayPaymentMethodNotFoundInDatabaseWarningLog(Account $account, Customer $customer, array $context): void
    {
        Log::shouldReceive('warning')->once()->with(__('messages.payment.batch_processing.autopay_method_not_found'), $context);
    }

    private function assertCustomerAutopayPaymentMethodNotFoundInDatabaseWarningLogForAutoPayCheck(Account $account, Customer $customer): void
    {
        $this->assertCustomerAutopayPaymentMethodNotFoundInDatabaseWarningLog(
            account: $account,
            customer: $customer,
            context: [
                'account_id' => $account->id,
                'autopay_payment_method_id' => null,
                'autopay_payment_method_external_ref_id' => null,
                'area_id' => $account->area->id,
                'customer_id' => $customer->id,
                'office_id' => $customer->officeId,
                'customer_autopay_payment_profile_id' => $customer->autoPayPaymentProfileId
            ]
        );
    }

    private function assertCustomerAutopayPaymentMethodNotFoundInDatabaseWarningLogForHoldDateCheck(Account $account, Customer $customer): void
    {
        $this->assertCustomerAutopayPaymentMethodNotFoundInDatabaseWarningLog(
            account: $account,
            customer: $customer,
            context: [
                'account_id' => $account->id,
                'area_id' => $account->area->id,
                'customer_id' => $customer->id,
                'office_id' => $customer->officeId,
            ]
        );
    }

    private function assertPaymentMethodPaymentHoldDateDiscrepancyWarningLog(
        Account $account,
        Customer $customer,
        PaymentProfile $paymentProfile
    ): void {
        Log::shouldReceive('warning')->once()->with(__('messages.payment.batch_processing.hold_date_discrepancy'), [
            'account_id' => $account->id,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'payment_profile_id' => $paymentProfile->id,
            'payment_profile_payment_hold_date' => $paymentProfile->paymentHoldDate,
            'payment_method_id' => $account->ledger->autopayMethod->id,
            'payment_method_payment_hold_date' => $account->ledger->autopayMethod->payment_hold_date,
        ]);
    }

    private function assertAccountPreferredBillingDateDiscrepancyWarningLog(
        Account $account,
        Customer $customer,
        PaymentProfile $paymentProfile
    ): void {
        Log::shouldReceive('warning')->once()->with(__('messages.payment.batch_processing.preferred_billing_date_discrepancy'), [
            'account_id' => $account->id,
            'area_id' => $account->area->id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'payment_profile_id' => $paymentProfile->id,
            'payment_profile_payment_hold_date' => $paymentProfile->paymentHoldDate,
            'payment_method_id' => $account->ledger->autopayMethod->id,
            'payment_method_payment_hold_date' => $account->ledger->autopayMethod->payment_hold_date,
            'account_preferred_billing_day_of_month' => $account->preferred_billing_day_of_month,
            'customer_preferred_billing_day_of_month' => $customer->preferredDayForBilling,
        ]);
    }

    private function assertPaymentMethodUpdatedWithPaymentProfilePaymentHoldDateMethod(PaymentMethod $paymentMethod, \DateTimeInterface|null $paymentHoldDate): void
    {
        $this->paymentMethodRepository->expects($this->once())->method('setPaymentHoldDate')->with(
            paymentMehtod: $paymentMethod,
            paymentHoldDate: $paymentHoldDate
        );
    }

    private function assertAccountUpdatedWithPreferredBillingDate(Account $account, int|null $preferredBillingDate): void
    {
        $this->accountRepository->expects($this->once())->method('setPreferredBillingDayOfMonth')->with(
            account: $account,
            preferredBillingDate: $preferredBillingDate
        );
    }

    private function handleJob(
        int $areaId = 49,
        bool $isPestRoutesBalanceCheckEnabled = false,
        bool $isPestRoutesAutoPayCheckEnabled = false,
        bool $isPestRoutesPaymentHoldDateCheckEnabled = false,
        bool $isPestRoutesInvoiceCheckEnabled = false,
    ): void {
        $job = new RetrieveAreaUnpaidInvoicesJob(
            areaId: $areaId,
            config: [
                'isPestRoutesBalanceCheckEnabled' => $isPestRoutesBalanceCheckEnabled,
                'isPestRoutesAutoPayCheckEnabled' => $isPestRoutesAutoPayCheckEnabled,
                'isPestRoutesPaymentHoldDateCheckEnabled' => $isPestRoutesPaymentHoldDateCheckEnabled,
                'isPestRoutesInvoiceCheckEnabled' => $isPestRoutesInvoiceCheckEnabled,
            ]
        );

        $job->handle(
            accountRepository: $this->accountRepository,
            areaRepository: $this->areaRepository,
            paymentMethodRepository: $this->paymentMethodRepository,
            invoiceRepository: $this->invoiceRepository,
            pestRoutesDataRetriever: $this->pestRoutesDataRetriever,
        );
    }
}
