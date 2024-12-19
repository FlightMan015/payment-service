<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\CRM\AccountRepository;
use App\Api\Repositories\CRM\AreaRepository;
use App\Api\Repositories\Interface\InvoiceRepository;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Traits\ValidatesPaymentProcessingConfig;
use App\Helpers\DateTimeHelper;
use App\Helpers\MoneyHelper;
use App\Infrastructure\PestRoutes\PestRoutesDataRetrieverService;
use App\Models\CRM\Customer\Account;
use App\Models\Invoice;
use Aptive\Component\Http\Exceptions\InternalServerErrorHttpException;
use Aptive\PestRoutesSDK\Entity;
use Aptive\PestRoutesSDK\Resources\Customers\Customer;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfile;
use Aptive\PestRoutesSDK\Resources\Tickets\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetrieveAreaUnpaidInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use ValidatesPaymentProcessingConfig;

    public const int ACCOUNTS_BATCH_SIZE_PER_REQUEST = 500;

    public int $timeout = 2 * CarbonInterface::SECONDS_PER_MINUTE;

    private array $accounts;
    private array $invoicesByAccount;

    private AccountRepository $accountRepository;
    private AreaRepository $areaRepository;
    private PaymentMethodRepository $paymentMethodRepository;
    private InvoiceRepository $invoiceRepository;
    private PestRoutesDataRetrieverService $pestRoutesDataRetriever;

    /**
     * Create a new job instance.
     *
     * @param int $areaId
     * @param array $config
     */
    public function __construct(private readonly int $areaId, private readonly array $config)
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
        $this->validateConfig();
    }

    /**
     * Execute the job.
     *
     * @param AccountRepository $accountRepository
     * @param AreaRepository $areaRepository
     * @param PaymentMethodRepository $paymentMethodRepository
     * @param InvoiceRepository $invoiceRepository
     * @param PestRoutesDataRetrieverService $pestRoutesDataRetriever
     *
     * @throws InternalServerErrorHttpException
     * @throws ResourceNotFoundException
     * @throws \JsonException
     *
     * @return void
     */
    public function handle(
        AccountRepository $accountRepository,
        AreaRepository $areaRepository,
        PaymentMethodRepository $paymentMethodRepository,
        InvoiceRepository $invoiceRepository,
        PestRoutesDataRetrieverService $pestRoutesDataRetriever
    ): void {
        $this->accountRepository = $accountRepository;
        $this->areaRepository = $areaRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->pestRoutesDataRetriever = $pestRoutesDataRetriever;

        $this->retrieveAccounts();
        $this->retrieveInvoicesGroupByAccount();

        /** @var Account $account */
        foreach ($this->accounts as $account) {
            if ($invoices = data_get($this->invoicesByAccount, $account->id)) {
                ProcessPaymentJob::dispatch(
                    $account,
                    $invoices,
                );
            }
        }

        Log::info(message: 'DISPATCHED - Process Payment Jobs', context: ['number_of_jobs' => count($this->accounts)]);
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     */
    private function retrieveAccounts(): void
    {
        if ($this->arePestRoutesAccountChecksEnabled()) {
            $this->accounts = $this->retrieveAccountsFromPestRoutes();
            return;
        }

        $this->accounts = $this->retrieveAccountsFromDatabase();
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     */
    private function retrieveInvoicesGroupByAccount(): void
    {
        if ($this->config['isPestRoutesInvoiceCheckEnabled']) {
            $invoices = $this->retrieveInvoicesFromPestRoutes();
        } else {
            $invoices = $this->retrieveInvoicesFromDatabase();
        }

        $this->invoicesByAccount = array_reduce($invoices, static function ($result, $invoice) {
            $result[$invoice->account_id][] = $invoice;
            return $result;
        }, []);
    }

    private function arePestRoutesAccountChecksEnabled(): bool
    {
        return $this->config['isPestRoutesBalanceCheckEnabled']
            || $this->config['isPestRoutesAutoPayCheckEnabled']
            || $this->config['isPestRoutesPaymentHoldDateCheckEnabled'];
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     */
    private function retrieveAccountsFromPestRoutes(): array
    {
        // retrieve all customers from pestroutes
        $customers = $this->retrieveCustomersFromPestRoutes();
        Log::info(message: __('messages.payment.batch_processing.customers_retrieved'), context: [
            'count' => count($customers),
            'expected_customer_balance_to_be_processed' => array_map(
                callback: static fn (Customer $customer) => [
                    'customer_id' => $customer->id,
                    'balance' => $customer->responsibleBalance
                ],
                array: $customers
            )
        ]);

        // retrieve all autopay payment methods from pestroutes
        $paymentProfiles = $this->retrievePaymentProfilesFromPestRoutes(customers: $customers);
        Log::info(message: __('messages.payment.batch_processing.autopay_payment_profiles_retrieved'), context: ['count' => count($paymentProfiles)]);

        // load accounts by external identifiers
        $accounts = $this->accountRepository
            ->getByExternalIds(
                externalRefIds: array_map(callback: static fn (Customer $customer) => $customer->id, array: $customers)
            )
            ->all();
        Log::info(message: __('messages.payment.batch_processing.accounts_retrieved'), context: ['count' => count($accounts)]);

        // set accounts array keys ar their external_ref_id
        $accounts = array_combine(
            keys: array_map(callback: static fn (Account $account) => $account->external_ref_id, array: $accounts),
            values: $accounts
        );

        // compare account with customer
        foreach ($customers as $customer) {
            $account = $accounts[$customer->id] ?? null;
            if (is_null($account)) {
                Log::warning(message: __('messages.account.not_found_by_ext_reference_id', ['id' => $customer->id]));
                continue;
            }

            $this->compareCustomerBalanceWithAccountBalance(customer: $customer, account: $account);
            $this->compareCustomerAutoPayWithAccountAutoPay(customer: $customer, account: $account, accounts: $accounts);
            $this->comparePaymentProfilePaymentHoldDateWithPaymentMethodPaymentHoldDate(
                paymentProfile: $paymentProfiles[$customer->autoPayPaymentProfileId] ?? null,
                customer: $customer,
                account: $account,
                accounts: $accounts
            );
        }

        Log::info(message: __('messages.payment.batch_processing.accounts_compared_and_filtered'), context: [
            'count' => count($accounts),
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

        // return accounts
        return $accounts;
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     */
    private function retrieveInvoicesFromPestRoutes(): array
    {
        // Retrieve all invoices from pestroutes
        $pestRoutesTickets = $this->retrieveTicketsFromPestRoutes();
        Log::info(message: __('messages.payment.batch_processing.invoices_retrieved'), context: ['count' => count($pestRoutesTickets)]);

        // Load invoices by external identifiers
        $databaseInvoices = $this->invoiceRepository
            ->getByExternalIds(
                externalRefIds: array_map(callback: static fn (Ticket $ticket) => $ticket->id, array: $pestRoutesTickets)
            );

        // Compare invoices
        foreach ($pestRoutesTickets as $prTicket) {
            $dbInvoice = $databaseInvoices->where('external_ref_id', $prTicket->id)->first();
            if (is_null($dbInvoice)) {
                Log::warning(message: __('messages.invoice.not_found_by_ext_reference_id', ['ext_reference_id' => $prTicket->id]));

                // Skip the account if the invoice and ticket do not match
                $this->removeAccountFromProcessing(externalRefId: $prTicket->customerId);

                continue;
            }

            if (
                !$this->comparePestRoutesInvoiceWithDatabaseInvoice(
                    pestRoutesInvoice: $prTicket,
                    databaseInvoice: $dbInvoice
                )
            ) {
                $this->removeAccountFromProcessing(externalRefId: $prTicket->customerId);

                continue;
            }
        }

        Log::info(message: __('messages.payment.batch_processing.invoices_compared_and_filtered'), context: [
            'count_pest_routes_invoices' => count($pestRoutesTickets),
            'count_database_invoices' => count($databaseInvoices),
        ]);

        return $databaseInvoices->all();
    }

    private function removeAccountFromProcessing(int $externalRefId): void
    {
        if (data_get($this->accounts, $externalRefId)) {
            unset($this->accounts[$externalRefId]);
        }
    }

    private function retrieveAccountsFromDatabase(): array
    {
        $accounts = [];
        $page = 1;

        do {
            $paginatedAccounts = $this->accountRepository->getAccountsWithUnpaidBalance(
                areaId: $this->areaId,
                page: $page,
                quantity: self::ACCOUNTS_BATCH_SIZE_PER_REQUEST
            );

            array_push($accounts, ...$paginatedAccounts->items());

            $page++;
        } while (count($accounts) < $paginatedAccounts->total());

        // set accounts array keys ar their external_ref_id
        $accounts = array_combine(
            keys: array_map(callback: static fn (Account $account) => $account->external_ref_id, array: $accounts),
            values: $accounts
        );

        return $accounts;
    }

    private function retrieveInvoicesFromDatabase(): array
    {
        $invoices = [];
        $page = 1;

        $accountIds = [];
        foreach ($this->accounts as $account) {
            $accountIds[] = $account->id;
        }

        do {
            $paginatedInvoices = $this->invoiceRepository->getUnpaidInvoicesByAccountIds(
                accountIds: $accountIds,
                page: $page,
                quantity: self::ACCOUNTS_BATCH_SIZE_PER_REQUEST
            );

            if ($paginatedInvoices->count()) {
                array_push($invoices, ...$paginatedInvoices->items());
            }

            $page++;
        } while (count($invoices) < $paginatedInvoices->total());

        return $invoices;
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     *
     * @return array<Customer>
     */
    private function retrieveCustomersFromPestRoutes(): array
    {
        $area = $this->areaRepository->find(id: $this->areaId);

        if (is_null($area)) {
            throw new ResourceNotFoundException(message: __('messages.area.not_found', ['id' => $this->areaId]));
        }

        $customers = [];
        $page = 1;

        do {
            $paginatedCustomers = $this->pestRoutesDataRetriever->getCustomersWithUnpaidBalance(
                officeId: $area->external_ref_id,
                page: $page,
                quantity: self::ACCOUNTS_BATCH_SIZE_PER_REQUEST
            );

            array_push($customers, ...$paginatedCustomers->items);

            $page++;
        } while (count($customers) < $paginatedCustomers->total);

        return $customers;
    }

    /**
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     *
     * @return array<Ticket>
     */
    private function retrieveTicketsFromPestRoutes(): array
    {
        $tickets = [];
        $page = 1;

        $customerIds = [];
        foreach ($this->accounts as $account) {
            $customerIds[] = $account->external_ref_id;
        }

        do {
            $paginatedTickets = $this->pestRoutesDataRetriever->getTicketsByCustomerIds(
                customerIds: $customerIds,
                page: $page,
                quantity: self::ACCOUNTS_BATCH_SIZE_PER_REQUEST
            );

            if ($paginatedTickets->count()) {
                array_push($tickets, ...$paginatedTickets->items);
            }

            $page++;
        } while (count($tickets) < $paginatedTickets->total);

        return $tickets;
    }

    private function comparePestRoutesInvoiceWithDatabaseInvoice(Ticket $pestRoutesInvoice, Invoice $databaseInvoice): bool
    {
        $ticketCentsBalance = MoneyHelper::convertToCents(amount: $pestRoutesInvoice->balance);

        $context = [
            'invoice_id' => $databaseInvoice->id,
            'invoice_balance' => $databaseInvoice->balance,
            'customer_id' => $pestRoutesInvoice->customerId,
            'office_id' => $pestRoutesInvoice->officeId,
            'pest_routes_ticket_balance' => $ticketCentsBalance,
            'original_invoice_balance' => $databaseInvoice->balance,
        ];

        if ($ticketCentsBalance !== $databaseInvoice->balance) {
            Log::warning(message: __('messages.payment.batch_processing.invoice_balance_discrepancy'), context: $context);
            return false;
        }

        Log::info(message: __('messages.payment.batch_processing.balance_matches'), context: $context);
        return true;
    }

    private function compareCustomerBalanceWithAccountBalance(Customer $customer, Account $account): void
    {
        if (!$this->config['isPestRoutesBalanceCheckEnabled']) {
            return;
        }

        $customerCentsBalance = MoneyHelper::convertToCents(amount: $customer->responsibleBalance);

        $context = [
            'account_id' => $account->id,
            'account_balance' => $account->ledger?->balance,
            'area_id' => $account->area_id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'customer_balance' => $customerCentsBalance,
            'original_customer_balance' => $customer->responsibleBalance,
        ];

        if ($customerCentsBalance !== $account->ledger?->balance) {
            Log::warning(message: __('messages.payment.batch_processing.balance_discrepancy'), context: $context);
            $this->accountRepository->setLedgerBalance(account: $account, balance: $customerCentsBalance);

            return;
        }

        Log::info(message: __('messages.payment.batch_processing.balance_matches'), context: $context);
    }

    private function compareCustomerAutoPayWithAccountAutoPay(
        Customer $customer,
        Account $account,
        array &$accounts
    ): void {
        if (!$this->config['isPestRoutesAutoPayCheckEnabled']) {
            return;
        }

        $context = [
            'account_id' => $account->id,
            'autopay_payment_method_id' => $account->ledger?->autopay_payment_method_id,
            'autopay_payment_method_external_ref_id' => $account->ledger?->autopayMethod?->external_ref_id,
            'area_id' => $account->area_id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
            'customer_autopay_payment_profile_id' => $customer->autoPayPaymentProfileId,
        ];

        if (is_null($customer->autoPayPaymentProfileId)) {
            Log::warning(message: __('messages.payment.batch_processing.customer_not_on_autopay'), context: $context);
            unset($accounts[$customer->id]);
            return;
        }

        if ($customer->autoPayPaymentProfileId !== $account->ledger?->autopayMethod?->external_ref_id) {
            Log::warning(message: __('messages.payment.batch_processing.autopay_discrepancy'), context: $context);

            try {
                $paymentMethod = $this->paymentMethodRepository->findByExternalRefId(externalRefId: $customer->autoPayPaymentProfileId);

                Log::info(
                    message: __('messages.payment.batch_processing.autopay_updated'),
                    context: $context + [
                        'new_autopay_payment_method_id' => $paymentMethod->id,
                        'new_autopay_payment_method_external_ref_id' => $paymentMethod->external_ref_id
                    ]
                );

                $this->accountRepository->setAutoPayPaymentMethod(
                    account: $account,
                    autopayPaymentMethod: $paymentMethod
                );
            } catch (ResourceNotFoundException) {
                Log::warning(message: __('messages.payment.batch_processing.autopay_method_not_found'), context: $context);
                unset($accounts[$customer->id]);
            }

            return;
        }

        Log::info(message: __('messages.payment.batch_processing.autopay_matches'), context: $context);
    }

    private function comparePaymentProfilePaymentHoldDateWithPaymentMethodPaymentHoldDate(
        PaymentProfile|null $paymentProfile,
        Customer $customer,
        Account $account,
        array &$accounts
    ): void {
        if (!$this->config['isPestRoutesPaymentHoldDateCheckEnabled']) {
            return;
        }

        $context = [
            'account_id' => $account->id,
            'area_id' => $account->area_id,
            'customer_id' => $customer->id,
            'office_id' => $customer->officeId,
        ];

        if (is_null($paymentProfile)) {
            Log::warning(message: __('messages.payment.batch_processing.customer_not_on_autopay'), context: $context);
            unset($accounts[$customer->id]);
            return;
        }

        if (is_null($account->ledger?->autopayMethod)) {
            // refresh account to get the autopay method in case it was fixed during the comparing process
            $account->refresh();
        }

        if (is_null($account->ledger?->autopayMethod)) {
            Log::warning(message: __('messages.payment.batch_processing.autopay_method_not_found'), context: $context);
            unset($accounts[$customer->id]);
            return;
        }

        $context += [
            'payment_profile_id' => $paymentProfile->id,
            'payment_profile_payment_hold_date' => $paymentProfile->paymentHoldDate,
            'payment_method_id' => $account->ledger->autopayMethod->id,
            'payment_method_payment_hold_date' => $account->ledger->autopayMethod->payment_hold_date,
        ];

        if (!DateTimeHelper::isTwoDatesAreSameDay($account->ledger->autopayMethod->payment_hold_date, $paymentProfile->paymentHoldDate)) {
            Log::warning(message: __('messages.payment.batch_processing.hold_date_discrepancy'), context: $context);
            $this->paymentMethodRepository->setPaymentHoldDate(paymentMethod: $account->ledger->autopayMethod, paymentHoldDate: $paymentProfile->paymentHoldDate);

            return;
        }

        Log::info(message: __('messages.payment.batch_processing.hold_date_matches'), context: $context);

        $context += [
            'account_preferred_billing_day_of_month' => $account->preferred_billing_day_of_month,
            'customer_preferred_billing_day_of_month' => $customer->preferredDayForBilling,
        ];

        if ($account->preferred_billing_day_of_month !== $customer->preferredDayForBilling) {
            Log::warning(message: __('messages.payment.batch_processing.preferred_billing_date_discrepancy'), context: $context);
            $this->accountRepository->setPreferredBillingDayOfMonth(account: $account, preferredBillingDayOfMonth: $customer->preferredDayForBilling);

            return;
        }

        Log::info(message: __('messages.payment.batch_processing.preferred_billing_date_matches'), context: $context);
    }

    private function retrievePaymentProfilesFromPestRoutes(array $customers): array
    {
        $paymentProfilesCollection = $this->pestRoutesDataRetriever->getPaymentProfiles(
            ids: array_filter(array_map(callback: static fn (Customer $customer) => $customer->autoPayPaymentProfileId, array: $customers))
        );

        // set payment profiles array keys ar their id
        return array_combine(
            keys: array_map(
                callback: static fn (Entity $paymentProfile): int => $paymentProfile->id,
                array: $paymentProfilesCollection->getItems()
            ),
            values: $paymentProfilesCollection->getItems()
        );
    }
}
