<?php

declare(strict_types=1);

namespace App\Api\Commands;

use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\InvoiceRepository;
use App\Helpers\DateTimeHelper;
use App\Models\Invoice;

readonly class FindInvoiceHandler
{
    /**
     * @param InvoiceRepository $repository
     */
    public function __construct(protected InvoiceRepository $repository)
    {
    }

    /**
     * @param string $invoiceId
     *
     * @throws ResourceNotFoundException
     *
     * @return array
     */
    public function handle(string $invoiceId): array
    {
        $invoice = $this->repository->find(id: $invoiceId, columns: [
            'id',
            'external_ref_id',
            'account_id',
            'subscription_id',
            'service_type_id',
            'is_active',
            'subtotal',
            'tax_rate',
            'total',
            'balance',
            'currency_code',
            'service_charge',
            'invoiced_at',
            'created_at',
            'updated_at',
        ]);

        return self::exposeInvoiceData($invoice);
    }

    public static function exposeInvoiceData(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'account' => [
                'id' => $invoice->account->id,
                'external_ref_id' => $invoice->account->external_ref_id,
                'is_active' => $invoice->account->is_active,
                'source' => $invoice->account->source,
            ],
            'items' => $invoice->items->map->only([
                'id',
                'external_ref_id',
                'quantity',
                'amount',
                'description',
                'is_taxable',
                'total',
            ]),
            'external_ref_id' => $invoice->external_ref_id,
            'subscription_id' => $invoice->subscription_id,
            'service_type_id' => $invoice->service_type_id,
            'is_active' => $invoice->is_active,
            'subtotal' => $invoice->subtotal,
            'total' => $invoice->total,
            'tax_rate' => $invoice->tax_rate,
            'tax_amount' => $invoice->tax_amount,
            'balance' => $invoice->balance,
            'currency_code' => $invoice->currency_code,
            'service_charge' => $invoice->service_charge,
            'invoiced_at' => DateTimeHelper::formatDateTime($invoice->invoiced_at),
            'created_at' => DateTimeHelper::formatDateTime($invoice->created_at->toISOString()),
            'updated_at' => DateTimeHelper::formatDateTime($invoice->updated_at->toISOString()),
        ];
    }
}
