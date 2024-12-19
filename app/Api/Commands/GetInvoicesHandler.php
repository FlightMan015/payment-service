<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Repositories\Interface\InvoiceRepository;
use App\Api\Requests\InvoiceFilterRequest;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetInvoicesHandler
{
    /**
     * @param InvoiceRepository $repository
     */
    public function __construct(protected InvoiceRepository $repository)
    {
    }

    /**
     * @param InvoiceFilterRequest $request
     *
     * @return LengthAwarePaginator<Invoice>
     */
    public function handle(InvoiceFilterRequest $request): LengthAwarePaginator
    {
        $paginator = $this->repository->filter($request->validated());
        $paginator->through(static fn (Invoice $invoice) => FindInvoiceHandler::exposeInvoiceData($invoice));

        return $paginator;
    }
}
