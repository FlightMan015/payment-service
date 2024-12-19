<?php

declare(strict_types=1);

namespace App\Api\Repositories\Interface;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InvoiceRepository
{
    public const int DEFAULT_ITEMS_PER_PAGE = 10;

    /**
     * Find an invoice by id
     *
     * @param string $id
     * @param array $columns
     *
     * @throws ResourceNotFoundException
     *
     * @return Invoice
     */
    public function find(string $id, array $columns = ['*']): Invoice;

    /**
     * Find invoices by given filtering params
     *
     * @param array $filter
     *
     * @return LengthAwarePaginator<Invoice>
     */
    public function filter(array $filter): LengthAwarePaginator;

    /**
     * Get invoices by ids
     *
     * @param array $invoiceIds
     *
     * @return Collection<int, Invoice>
     */
    public function getInvoicesByIds(array $invoiceIds): Collection;

    /**
     * Get invoices by external_ids
     *
     * @param array $externalRefIds
     *
     * @return Collection<int, Invoice>
     */
    public function getByExternalIds(array $externalRefIds): Collection;

    /**
     * Get unpaid invoices by account ids
     *
     * @param array $accountIds
     * @param int $page
     * @param int $quantity
     *
     * @return LengthAwarePaginator<Invoice>
     */
    public function getUnpaidInvoicesByAccountIds(array $accountIds, int $page, int $quantity): LengthAwarePaginator;
}
