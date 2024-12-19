<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\CRM\Customer\Account;
use App\Models\CRM\Customer\Subscription;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractModelTest;

class InvoiceTest extends AbstractModelTest
{
    use DatabaseTransactions;

    protected function getTableName(): string
    {
        return 'billing.invoices';
    }

    protected function getColumnList(): array
    {
        return [
            'id',
            'external_ref_id',
            'account_id',
            'is_active',
            'subtotal',
            'tax_rate',
            'total',
            'balance',
            'currency_code',
            'service_charge',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    #[Test]
    public function it_filters_invoice_by_account_id_correctly(): void
    {
        $account = Account::factory()->create();

        $searchableInvoices = Invoice::factory()->for($account)->count(6)->create();
        $notReturnedInvoices = Invoice::factory()->count(2)->create();

        $filteredData = Invoice::filtered(['account_id' => $account->id])->get();

        $this->assertCount($searchableInvoices->count(), $filteredData);
        $this->assertSame($filteredData->first()->account_id, $searchableInvoices->first()->account_id);
    }

    #[Test]
    public function it_filters_invoice_by_subscription_id_correctly(): void
    {
        $subscription = Subscription::factory()->create();
        $searchableInvoices = Invoice::factory()->for($subscription)->count(6)->create();
        $notReturnedInvoices = Invoice::factory()->count(2)->create();

        $filteredData = Invoice::filtered(['subscription_id' => $subscription->id])->get();

        $this->assertCount($searchableInvoices->count(), $filteredData);
        $this->assertSame($filteredData->first()->account_id, $searchableInvoices->first()->account_id);
    }

    #[Test]
    public function it_filters_invoice_by_invoiced_at_range_correctly(): void
    {
        $searchablePayments = Invoice::factory()->count(6)->create([
            'invoiced_at' => Carbon::now()->addDays(rand(1, 10)),
        ]);
        $notReturnedPayments = Invoice::factory()->count(2)->create([
            'invoiced_at' => Carbon::now()->subDays(rand(1, 10)),
        ]);

        $filteredData = Invoice::filtered([
            'date_from' => Carbon::now(),
            'date_to' => Carbon::now()->addDays(11),
        ])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
    }

    #[Test]
    public function it_filters_invoice_by_total_range_correctly(): void
    {
        $searchablePayments = Invoice::factory()->count(6)->create([
            'total' => rand(1000, 5742),
        ]);
        $notReturnedPayments = Invoice::factory()->count(2)->create([
            'total' => 998,
        ]);

        $filteredData = Invoice::filtered([
            'total_from' => 999,
            'total_to' => 100000,
        ])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
    }

    #[Test]
    public function it_filters_invoice_by_balance_range_correctly(): void
    {
        $searchablePayments = Invoice::factory()->count(6)->create([
            'balance' => rand(1000, 5742),
        ]);
        $notReturnedPayments = Invoice::factory()->count(2)->create([
            'balance' => 998,
        ]);

        $filteredData = Invoice::filtered([
            'balance_from' => 999,
            'balance_to' => 100000,
        ])->get();

        $this->assertCount($searchablePayments->count(), $filteredData);
    }
}
