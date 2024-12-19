<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\PaymentMethodRepository;
use App\Api\Traits\SortableTrait;
use App\Helpers\DateTimeHelper;
use App\Models\AccountUpdaterAttempt;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DatabasePaymentMethodRepository implements PaymentMethodRepository
{
    use SortableTrait;

    private array $allowedSorts = [
        'created_at',
        'updated_at',
        'is_primary',
        'name_on_account',
        'external_ref_id',
        'payment_gateway_id',
        'payment_type_id',
        'cc_expiration_year',
    ];

    /**
     * Retrieve list of payment methods by conditions
     *
     * @param array $filter by fields
     * @param array $columns To be selected columns
     * @param bool $withSoftDeleted should include soft deleted
     * @param array $relationsFilter should have/doesn't have a relation
     * @param array $withRelations
     *
     * @return LengthAwarePaginator<PaymentMethod>
     */
    public function filter(
        array $filter = [],
        array $columns = ['*'],
        bool $withSoftDeleted = false,
        array $relationsFilter = [],
        array $withRelations = ['type']
    ): LengthAwarePaginator {
        $query = PaymentMethod::with($withRelations)->select($columns);

        foreach ($filter as $key => $value) {
            $query = $this->filterBySingleKey(query: $query, key: $key, value: $value);
        }

        foreach ($relationsFilter as $key => $value) {
            $query = $value ? $query->whereHas(relation: $key) : $query->whereDoesntHave(relation: $key);
        }

        if ($withSoftDeleted) {
            $query = $query->withTrashed();
        }

        $this->sort($query, $filter);

        return $query->paginate(perPage: $filter['per_page'] ?? 10, page: $filter['page'] ?? 1);
    }

    /**
     * Retrieve payment method detail by given id
     *
     * @param string $paymentMethodId
     * @param array $columns To be selected columns
     *
     * @throws ResourceNotFoundException
     *
     * @return PaymentMethod
     */
    public function find(string $paymentMethodId, array $columns = ['*']): PaymentMethod
    {
        $paymentMethod = PaymentMethod::select($columns)->with(relations: [
            'gateway:id,name',
            'type:id,name',
            'account:id,area_id' => ['area:id,external_ref_id'],
            'ledger:account_id,autopay_payment_method_id'
        ])->find($paymentMethodId);

        if (empty($paymentMethod)) {
            throw new ResourceNotFoundException(message: __('messages.payment_method.not_found', ['id' => $paymentMethodId]));
        }

        return $paymentMethod;
    }

    /** @inheritDoc */
    public function findByExternalRefId(int $externalRefId, array $columns = ['*']): PaymentMethod
    {
        $paymentMethod = PaymentMethod::select($columns)
            ->with(relations: [
                'gateway:id,name',
                'type:id,name',
                'account:id,area_id' => ['area:id,external_ref_id'],
                'ledger:account_id,autopay_payment_method_id'
            ])
            ->where(column: 'external_ref_id', operator: '=', value: $externalRefId)
            ->first();

        if (empty($paymentMethod)) {
            throw new ResourceNotFoundException(message: __('messages.payment_method.not_found_by_external_ref_id', ['id' => $externalRefId]));
        }

        return $paymentMethod;
    }

    /** @inheritDoc */
    public function findPrimaryForAccount(string $accountId): PaymentMethod|null
    {
        return PaymentMethod::with(relations: ['account' => ['area:id,external_ref_id']])->primaryForAccount(accountId: $accountId)->first();
    }

    /** @inheritDoc */
    public function findAutopayMethodForAccount(string $accountId): PaymentMethod|null
    {
        return PaymentMethod::with(relations: ['account' => ['area:id,external_ref_id'], 'ledger'])->autopayForAccount(accountId: $accountId)->first();
    }

    /** @inheritDoc */
    public function findByAttemptRelation(
        AccountUpdaterAttempt $attempt,
        array $relationAttributes
    ): PaymentMethod|null {
        return $attempt->methods()->where($relationAttributes)->first();
    }

    /** @inheritDoc */
    public function existsForAccount(string $accountId): bool
    {
        return PaymentMethod::whereAccountId($accountId)->exists();
    }

    /** @inheritDoc */
    public function updateForAccount(string $accountId, array $attributes = []): int
    {
        return PaymentMethod::whereAccountId($accountId)->update($attributes);
    }

    /** @inheritDoc */
    public function create(array $attributes): PaymentMethod
    {
        return PaymentMethod::create(attributes: $attributes);
    }

    /** @inheritDoc */
    public function save(PaymentMethod $paymentMethod, array $additionalAttributes = []): PaymentMethod
    {
        $paymentMethod->fill(attributes: $additionalAttributes)->save();

        return $paymentMethod;
    }

    /** @inheritDoc */
    public function update(PaymentMethod $paymentMethod, array $attributes): PaymentMethod
    {
        $paymentMethod->update(attributes: $attributes);

        return $paymentMethod;
    }

    private function filterBySingleKey(Builder|PaymentMethod $query, string $key, mixed $value): Builder|PaymentMethod
    {
        return match ($key) {
            'account_id' => $query->whereAccountId($value),
            'account_ids' => $query->whereIn(column: 'account_id', values: $value),
            'cc_expire_from_date' => $this->whereExpirationFromDate(query: $query, date: $value),
            'cc_expire_to_date' => $this->whereExpirationToDate(query: $query, date: $value),
            'has_cc_token' => $this->whereHasCcToken($query, (bool)$value),
            'has_cc_expiration_data' => $this->whereHasCcExpirationData($query, (bool)$value),
            'gateway_id' => $query->wherePaymentGatewayId($value),
            'gateway_ids' => $query->whereIn(column: 'payment_gateway_id', values: $value),
            'is_valid' => $this->whereIsValid(query: $query, isValid: (bool)$value),
            'type' => $query->where(column: 'payment_type_id', operator: '=', value: PaymentTypeEnum::fromName(name: $value)),
            'type_ids' => $query->whereIn(column: 'payment_type_id', values: $value),
            default => $query,
        };
    }

    private function whereExpirationFromDate(Builder $query, string $date): Builder
    {
        return $query->where(
            $this->greaterThanExpirationDateClosure(
                date: Carbon::createFromFormat(format: DateTimeHelper::GENERAL_DATE_FORMAT, time: $date)
            )
        );
    }

    private function whereExpirationToDate(Builder $query, string $date): Builder
    {
        return $query->where(
            $this->lessThanExpirationDateClosure(
                date: Carbon::createFromFormat(format: DateTimeHelper::GENERAL_DATE_FORMAT, time: $date)
            )
        );
    }

    private function whereIsValid(Builder $query, bool $isValid): Builder
    {
        if ($isValid) {
            return $query->where(
                fn (Builder $builder) => $builder
                    ->where(column: 'payment_type_id', operator: '=', value: PaymentTypeEnum::ACH) // include all ACHs
                    ->orWhere($this->greaterThanExpirationDateClosure(date: Carbon::now()))  // include all not expired CCs
            );
        }

        return $query->where(
            fn (Builder $builder) => $builder
                ->where($this->lessThanExpirationDateClosure(date: Carbon::now()))  // include all expired CCs
                ->addSelect(DB::raw(value: "CONCAT('Credit Card expires on ', cc_expiration_month, '/', cc_expiration_year) AS invalid_reason"))
        );
    }

    private function whereHasCcToken(Builder $query, bool $hasCcToken): Builder
    {
        if ($hasCcToken) {
            return $query->whereNotNull(columns: 'cc_token');
        }

        return $query->whereNull(columns: 'cc_token');
    }

    private function whereHasCcExpirationData(Builder $query, bool $hasCcExpirationData): Builder
    {
        if ($hasCcExpirationData) {
            return $query->whereNotNull(columns: ['cc_expiration_month', 'cc_expiration_year']);
        }

        return $query->where(
            static fn (Builder $builder) => $builder->whereNull('cc_expiration_month')->orWhereNull('cc_expiration_year')
        );
    }

    /**
     * @inheritdoc
     */
    public function softDelete(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->delete();
    }

    /**
     * @inheritdoc
     */
    public function makePrimary(PaymentMethod $paymentMethod): void
    {
        $paymentMethod->makePrimary();
    }

    /**
     * @inheritdoc
     */
    public function setPaymentHoldDate(PaymentMethod $paymentMethod, \DateTimeInterface|null $paymentHoldDate): bool
    {
        return $paymentMethod->update(attributes: ['payment_hold_date' => $paymentHoldDate]);
    }

    private function greaterThanExpirationDateClosure(CarbonInterface $date): \Closure
    {
        return static function (Builder $query) use ($date) {
            $query->where(
                static fn (Builder $builder) => $builder
                    // this year not expired payment methods
                    ->where(
                        static fn (Builder $builder) => $builder
                            ->where(column: 'cc_expiration_year', operator: '=', value: $date->year)
                            ->where(column: 'cc_expiration_month', operator: '>=', value: $date->month)
                    )
                    // not expired payment methods for the future years
                    ->orWhere(column: 'cc_expiration_year', operator: '>', value: $date->year)
            );
        };
    }

    private function lessThanExpirationDateClosure(CarbonInterface $date): \Closure
    {
        return static function (Builder $query) use ($date) {
            $query->where(
                static fn (Builder $builder) => $builder
                    // this year expired payment methods
                    ->where(
                        static fn (Builder $builder) => $builder
                            ->where(column: 'cc_expiration_year', operator: '=', value: $date->year)
                            ->where(column: 'cc_expiration_month', operator: '<', value: $date->month)
                    )
                    // expired payment methods for the previous years
                    ->orWhere(column: 'cc_expiration_year', operator: '<', value: $date->year)
            );
        };
    }
}
