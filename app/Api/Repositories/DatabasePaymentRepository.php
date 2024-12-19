<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Exceptions\UnprocessableContentException;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Api\Traits\SortableTrait;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\OperationEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabasePaymentRepository implements PaymentRepository
{
    use SortableTrait;

    private array $allowedSorts = [
        'processed_at',
        'created_at',
        'updated_at',
        'amount',
        'applied_amount',
        'external_ref_id',
        'payment_status_id',
        'payment_type_id',
        'payment_gateway_id',
        'notes'
    ];

    private string $defaultSort = 'processed_at';

    /**
     * Retrieve list of payments by conditions
     *
     * @param array $filter
     * @param array $columns
     *
     * @return LengthAwarePaginator<Payment>
     */
    public function filter(
        array $filter = [],
        array $columns = ['*'],
    ): LengthAwarePaginator {
        $query = Payment::filtered($filter)
            ->select($columns)
            ->with(['status', 'account', 'account.billingContact', 'paymentMethod']);

        if (!empty($filter['first_name']) || !empty($filter['last_name'])) {
            $query->join('customer.accounts as accounts', 'payments.account_id', '=', 'accounts.id')
                ->join('customer.contacts as contacts', 'accounts.billing_contact_id', '=', 'contacts.id');
        }

        if (!empty($filter['first_name'])) {
            $query->orderBy(DB::raw(sprintf('POSITION(\'%s\' IN first_name)', $filter['first_name'])));
        }

        if (!empty($filter['last_name'])) {
            $query->orderBy(DB::raw(sprintf('POSITION(\'%s\' IN last_name)', $filter['last_name'])));
        }

        $this->sort($query, $filter);

        return $query->paginate(perPage: $filter['per_page'], page: $filter['page']);
    }

    /**
     * @inheritDoc
     */
    public function findWithLedgerTypes(string $paymentId): Payment
    {
        $payment = $this->find($paymentId);
        if (!in_array($payment->payment_type_id, PaymentTypeEnum::ledgerOnlyValues())) {
            throw new UnprocessableContentException(
                message: __(
                    key: 'messages.payment.not_found_for_ledger_type',
                    replace: ['type' => implode(separator: ', ', array: PaymentTypeEnum::ledgerOnlyNames())]
                )
            );
        }

        return $payment;
    }

    /**
     * Retrieve payment detail by given id
     *
     * @param string $paymentId
     * @param array $columns
     * @param array $relations
     *
     * @throws ResourceNotFoundException
     *
     * @return Payment
     */
    public function find(string $paymentId, array $columns = ['*'], array $relations = ['status']): Payment
    {
        $payment = Payment::select($columns)->with($relations)->find($paymentId);

        if (empty($payment)) {
            throw new ResourceNotFoundException(message: __('messages.payment.not_found', ['id' => $paymentId]));
        }

        return $payment;
    }

    /** @inheritDoc */
    public function create(array $attributes): Payment
    {
        return Payment::create(attributes: $attributes);
    }

    /** @inheritDoc */
    public function update(Payment $payment, array $attributes): Payment
    {
        $attributes['processed_at'] = $attributes['check_date'] ?? $attributes['processed_at'] ?? $payment->processed_at;
        $payment->update(attributes: $attributes);

        return $payment;
    }

    /**
     * @param PaymentMethod $method
     * @param int $limit
     *
     * @return Collection<int, Payment>
     */
    public function getLatestPaymentsForPaymentMethod(PaymentMethod $method, int $limit): Collection
    {
        return Payment::wherePaymentMethodId($method->id)
            ->limit(value: $limit)
            ->latest(column: 'processed_at')
            ->get();
    }

    /** @inheritDoc */
    public function getDeclinedForPaymentMethodCount(PaymentMethod $method, \DateTimeInterface|null $date = null): int
    {
        $query = Payment::declinedForPaymentMethod(methodId: $method->id);

        if (!is_null($date)) {
            $query = $query->whereDate('processed_at', $date);
        }

        return $query->count();
    }

    /** @inheritDoc */
    public function cloneAndCreateFromExistingPayment(
        Payment $payment,
        array $overriddenAttributes = [],
    ): Payment {
        $clonedPayment = $payment->replicate();
        $clonedPayment->fill(array_merge($overriddenAttributes, [
            'original_payment_id' => $payment->id,
        ]));
        $clonedPayment->save();

        return $clonedPayment;
    }

    /** @inheritDoc */
    public function getCreditedChildPayments(Payment $payment): Collection
    {
        return $payment->childrenPayments()
            ->wherePaymentStatusId(PaymentStatusEnum::CREDITED->value)
            ->get();
    }

    /** @inheritDoc */
    public function updateStatus(Payment $payment, PaymentStatusEnum $paymentStatus): Payment
    {
        $payment->payment_status_id = $paymentStatus->value;
        $payment->save();

        return $payment;
    }

    /** @inheritDoc */
    public function transactionForOperation(Payment $payment, array|OperationEnum $operation): Transaction|null
    {
        return $payment->transactionForOperation(operation: $operation);
    }

    /**
     * @inheritdoc
     */
    public function getLatestSuccessfulPaymentForInvoices(string $accountId, array $invoiceIds): Payment|null
    {
        return Payment::getPaymentForInvoiceByStatus(
            accountId: $accountId,
            invoiceIds: $invoiceIds,
            status: PaymentStatusEnum::CAPTURED
        )->latest('processed_at')->first();
    }

    /**
     * @inheritdoc
     */
    public function getLatestSuspendedOrTerminatedPaymentForOriginalPayment(string $accountId, string $originalPaymentId): Payment|null
    {
        return Payment::getSuspendedOrTerminatedPaymentForOriginalPayment(
            accountId: $accountId,
            originalPaymentId: $originalPaymentId,
        )->latest('processed_at')->first();
    }

    /**
     * @inheritdoc
     */
    public function getTerminatedPaymentForInvoices(string $accountId, array $invoiceIds): Payment|null
    {
        return Payment::getPaymentForInvoiceByStatus(
            accountId: $accountId,
            invoiceIds: $invoiceIds,
            status: PaymentStatusEnum::TERMINATED
        )->latest('processed_at')->first();
    }

    /**
     * @inheritdoc
     */
    public function checkIfPaymentMatchInvoices(Payment $payment, array $invoiceIds): bool
    {
        return collect($payment->invoices()->pluck('invoice_id'))
            ->diff($invoiceIds)
            ->isEmpty();
    }

    /**
     * @inheritDoc
     */
    public function getExternalRefundsWithoutTransactionsForArea(int $areaId, int $page, int $quantity): LengthAwarePaginator
    {
        return Payment::whereDoesntHave('transactions')
            ->forAreaId($areaId)
            ->withOriginalPaymentFromPaymentService()
            ->whereNull('pestroutes_refund_processed_at') // was not re-processed yet
            ->whereNotNull('external_ref_id') // has relation with external service
            ->where('pestroutes_created_by_crm', false) // was created in external service
            ->where('payment_status_id', PaymentStatusEnum::CREDITED->value)
            ->whereIn('payment_type_id', array_map(static fn (PaymentTypeEnum $type) => $type->value, PaymentTypeEnum::electronicTypes()))
            ->paginate($quantity, ['*'], 'page', $page);
    }

    /**
     * @return Builder<Payment>
     */
    public function getNonSynchronisedPayments(): Builder
    {
        return Payment::notSynchronized();
    }

    /**
     * @inheritDoc
     */
    public function getNotFullySettledAchPayments(
        \DateTimeInterface $processedAtFrom,
        \DateTimeInterface $processedAtTo,
        int $page,
        int $quantity,
        int $areaId,
    ): LengthAwarePaginator {
        return Payment::wherePaymentTypeId(PaymentTypeEnum::ACH->value)
            ->wherePaymentGatewayId(PaymentGatewayEnum::WORLDPAY->value)
            ->wherePaymentStatusId(PaymentStatusEnum::CAPTURED->value)
            ->whereBetween('processed_at', [$processedAtFrom, $processedAtTo])
            ->whereDoesntHave('returnedPayment')
            ->forAreaId($areaId)
            ->where('pestroutes_created_by_crm', true)
            ->paginate($quantity, ['*'], 'page', $page);
    }
}
