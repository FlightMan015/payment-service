<?php

declare(strict_types=1);

namespace App\Api\Repositories;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\InvoiceRepository;
use App\Api\Traits\SortableTrait;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DatabaseInvoiceRepository implements InvoiceRepository
{
    use SortableTrait;

    private array $allowedSorts = [
        'invoiced_at',
        'created_at',
        'updated_at',
        'total',
        'balance',
        'subtotal',
        'tax_rate',
        'service_charge',
        'is_active',
        'service_type_id',
        'external_ref_id',
    ];

    private string $defaultSort = 'invoiced_at';

    /**
     * @inheritDoc
     */
    public function find(string $id, array $columns = ['*']): Invoice
    {
        $invoice = Invoice::select($columns)
            ->with(['account', 'items'])
            ->find(id: $id);

        if (empty($invoice)) {
            throw new ResourceNotFoundException(message: __('messages.invoice.not_found', ['id' => $id]));
        }

        return $invoice;
    }

    /**
     * @inheritDoc
     */
    public function filter(array $filter): LengthAwarePaginator
    {
        $query = Invoice::filtered($filter)->with(['account', 'items']);

        $this->sort($query, $filter);

        return $query->paginate(perPage: $filter['per_page'], page: $filter['page']);
    }

    /**
     * @inheritDoc
     */
    public function getInvoicesByIds(array $invoiceIds): Collection
    {
        return Invoice::whereIn('id', $invoiceIds)->select(['id', 'balance'])->get();
    }

    /**
     * @inheritDoc
     */
    public function getByExternalIds(array $externalRefIds): Collection
    {
        return Invoice::whereIn('external_ref_id', $externalRefIds)->get();
    }

    /**
     * @inheritDoc
     */
    public function getUnpaidInvoicesByAccountIds(array $accountIds, int $page, int $quantity): LengthAwarePaginator
    {
        return Invoice::whereIn('account_id', $accountIds)
            ->whereIsActive(true)
            ->paginate(perPage: $quantity, page: $page);
    }
}
