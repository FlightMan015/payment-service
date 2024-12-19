<?php

declare(strict_types=1);

namespace App\Api\Repositories\CRM;

use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountException;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountRepository
{
    /**
     * @param string $id
     *
     * @return Account|null
     */
    public function find(string $id): Account|null
    {
        return Account::whereIsActive(true)->find($id);
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function exists(string $id): bool
    {
        return Account::whereIsActive(true)->whereId($id)->exists();
    }

    /**
     * @param int $areaId
     * @param int $page
     * @param int $quantity
     *
     * @return LengthAwarePaginator<Account>
     */
    public function getAccountsWithUnpaidBalance(int $areaId, int $page, int $quantity): LengthAwarePaginator
    {
        return Account::whereAreaId($areaId)
            ->whereIsActive(true) // only active
            ->whereHas(relation: 'ledger', callback: static function (Builder $builder) {
                $builder
                    ->where(column: 'balance', operator: '>', value: 0) // has unpaid balance
                    ->whereNotNull(columns: 'autopay_payment_method_id'); // has autopay turned on
            })
            ->with([
                'ledger:account_id,balance,autopay_payment_method_id' => ['autopayMethod:id,external_ref_id'],
                'billingContact:id,first_name,last_name,email',
                'contact:id,first_name,last_name,email',
                'billingAddress:id,address,city,state,postal_code,country',
                'address:id,address,city,state,postal_code,country',
            ])
            ->select(columns: [
                'id',
                'area_id',
                'is_active',
                'payment_hold_date',
                'preferred_billing_day_of_month',
                'billing_contact_id',
                'billing_address_id',
                'contact_id',
                'service_address_id',
            ])
            ->paginate(perPage: $quantity, page: $page);
    }

    /**
     * @param Account $account
     * @param PaymentMethod|null $autopayPaymentMethod
     *
     * @throws PaymentMethodDoesNotBelongToAccountException
     *
     * @return bool
     */
    public function setAutoPayPaymentMethod(Account $account, PaymentMethod|null $autopayPaymentMethod): bool
    {
        return $account->setAutoPayPaymentMethod(autopayPaymentMethod: $autopayPaymentMethod);
    }

    /**
     * @param Account $account
     * @param int $balance
     *
     * @return bool
     */
    public function setLedgerBalance(Account $account, int $balance): bool
    {
        return $account->setLedgerBalance(balance: $balance);
    }

    /**
     * @param array $externalRefIds
     *
     * @return Collection<int, Account>
     */
    public function getByExternalIds(array $externalRefIds): Collection
    {
        return Account::whereIn(column: 'external_ref_id', values: $externalRefIds)
            ->whereIsActive(true) // only active
            ->with([
                'ledger:account_id,balance,autopay_payment_method_id' => [
                    'autopayMethod:id,external_ref_id,payment_hold_date'
                ],
                'billingContact:id,first_name,last_name,email',
                'contact:id,first_name,last_name,email',
                'billingAddress:id,address,city,state,postal_code,country',
                'address:id,address,city,state,postal_code,country',
            ])
            ->select(columns: [
                'id',
                'area_id',
                'is_active',
                'payment_hold_date',
                'external_ref_id',
                'preferred_billing_day_of_month',
                'billing_contact_id',
                'billing_address_id',
                'contact_id',
                'service_address_id',
            ])
            ->get();
    }

    /**
     * @param Account $account
     * @param \DateTimeInterface|null $paymentHoldDate
     *
     * @return bool
     */
    public function setPaymentHoldDate(Account $account, \DateTimeInterface|null $paymentHoldDate): bool
    {
        return $account->update(attributes: ['payment_hold_date' => $paymentHoldDate]);
    }

    /**
     * @param Account $account
     * @param int|null $preferredBillingDayOfMonth
     *
     * @return bool
     */
    public function setPreferredBillingDayOfMonth(Account $account, int|null $preferredBillingDayOfMonth): bool
    {
        return $account->update(attributes: ['preferred_billing_day_of_month' => $preferredBillingDayOfMonth]);
    }

    /**
     * @param Account $account
     *
     * @return int
     */
    public function getAmountLedgerBalance(Account $account): int
    {
        return $account->ledger->balance;
    }
}
