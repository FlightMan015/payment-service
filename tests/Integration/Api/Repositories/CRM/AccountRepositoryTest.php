<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Api\Repositories\CRM;

use App\Api\Repositories\CRM\AccountRepository;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_accounts_with_unpaid_balance_as_expected(): void
    {
        $expectedTotalAccountsCount = 7;

        $area = Area::factory()->create();

        $accountsWithUnpaidBalance = Account::factory()->count(count: $expectedTotalAccountsCount)->create(attributes: [
            'area_id' => $area->id,
            'is_active' => true
        ]);

        foreach ($accountsWithUnpaidBalance as $account) {
            $account->ledger()->create(attributes: [
                'balance' => random_int(1, 20000),
                'autopay_payment_method_id' => PaymentMethod::factory()->create(attributes: ['account_id' => $account->id])->id
            ]);
        }

        $accountWithPaidBalanceNoLedger = Account::factory()->count(count: 6)->create(attributes: [
            'area_id' => $area->id,
            'is_active' => true
        ]);

        $accountsWithPaidBalanceZeroLedger = Account::factory()->count(count: 4)->create(attributes: [
            'area_id' => $area->id,
            'is_active' => true
        ]);

        foreach ($accountsWithPaidBalanceZeroLedger as $account) {
            $account->ledger()->create(attributes: [
                'balance' => 0,
                'autopay_payment_method_id' => PaymentMethod::factory()->create(attributes: ['account_id' => $account->id])->id
            ]);
        }

        $accountsWithoutAutoPayPaymentMethod = Account::factory()->count(count: 8)->create(attributes: [
            'area_id' => $area->id,
            'is_active' => true
        ]);

        foreach ($accountsWithoutAutoPayPaymentMethod as $account) {
            $account->ledger()->create(attributes: [
                'balance' => 100,
                'autopay_payment_method_id' => null
            ]);
        }

        $paginator = (new AccountRepository())->getAccountsWithUnpaidBalance(areaId: $area->id, page: 1, quantity: 5);

        $paginator->getCollection()->each(callback: function (Account $account) {
            $this->assertGreaterThan(expected: 0, actual: $account->ledger->balance);
            $this->assertNotNull(actual: $account->ledger->autopayMethod);
        });

        $this->assertSame($expectedTotalAccountsCount, $paginator->total());
    }

    #[Test]
    public function get_accounts_with_unpaid_balance_method_does_not_return_accounts_when_they_have_no_ledger(): void
    {
        $area = Area::factory()->create();

        $accountWithPaidBalanceNoLedger = Account::factory()->count(count: 6)->create(attributes: [
            'area_id' => $area->id,
            'is_active' => true
        ]);

        $accountWithPaidBalanceNoLedger->collect()->each(function (Account $account) {
            $this->assertNull($account->ledger);
        });

        $this->assertEmpty((new AccountRepository())->getAccountsWithUnpaidBalance(areaId: $area->id, page: 1, quantity: 5)->getCollection());
    }

    #[Test]
    public function get_accounts_with_unpaid_balance_method_returns_only_accounts_with_existing_valid_ledger_relation(): void
    {
        $area = Area::factory()->create();

        $accountWithPaidBalanceNoLedger = Account::factory()->count(count: 6)->create(attributes: [
            'area_id' => $area->id,
            'is_active' => true
        ]);

        $accountWithPaidBalanceNoLedger->collect()->each(function (Account $account) {
            $this->assertNull($account->ledger);
        });

        $accountsWithLedgerQuantity = 4;
        $accountsWithLedger = Account::factory()->count(count: $accountsWithLedgerQuantity)->create(attributes: [
            'area_id' => $area->id,
            'is_active' => true
        ]);

        foreach ($accountsWithLedger as $account) {
            $account->ledger()->create(attributes: [
                'balance' => 100,
                'autopay_payment_method_id' => PaymentMethod::factory()->for($account)->create()->id
            ]);
        }

        $paginator = (new AccountRepository())->getAccountsWithUnpaidBalance(areaId: $area->id, page: 1, quantity: 5);

        $paginator->getCollection()->each(callback: function (Account $account) {
            $this->assertGreaterThan(expected: 0, actual: $account->ledger->balance);
            $this->assertNotNull(actual: $account->ledger->autopayMethod);
        });

        $this->assertSame($accountsWithLedgerQuantity, $paginator->total());
    }

    #[Test]
    public function it_returns_accounts_by_external_ref_ids_as_expected(): void
    {
        $externalRefIds = [123, 456, 789];

        foreach ($externalRefIds as $externalRefId) {
            Account::factory()->create(attributes: ['is_active' => true, 'external_ref_id' => $externalRefId]);
        }

        // create some accounts with different external ref ids
        Account::factory()->count(count: 15)->create(attributes: ['is_active' => true]);

        $accounts = (new AccountRepository())->getByExternalIds(externalRefIds: $externalRefIds);
        foreach ($accounts as $account) {
            $this->assertContains($account->external_ref_id, $externalRefIds);
        }

        $this->assertCount(expectedCount: count($externalRefIds), haystack: $accounts);
    }

    #[Test]
    public function it_returns_account_ledger_balance_as_expected(): void
    {
        $balance = 10;
        $account = Account::factory()->hasLedger(['balance' => $balance])->create();

        $this->assertEquals(expected: $balance, actual: (new AccountRepository())->getAmountLedgerBalance($account));
    }
}
