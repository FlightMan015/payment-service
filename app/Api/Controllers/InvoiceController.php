<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Commands\FindInvoiceHandler;
use App\Api\Commands\GetInvoicesHandler;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Requests\InvoiceFilterRequest;
use App\Api\Responses\GetMultipleSuccessResponse;
use App\Api\Responses\GetSingleSuccessResponse;
use Illuminate\Http\JsonResponse;

class InvoiceController
{
    /**
     * Retrieve invoice detail by given id
     *
     * @param string $invoiceId
     * @param FindInvoiceHandler $handler
     *
     * @throws ResourceNotFoundException
     *
     * @return GetSingleSuccessResponse
     */
    public function find(string $invoiceId, FindInvoiceHandler $handler): GetSingleSuccessResponse
    {
        return GetSingleSuccessResponse::create(
            entity: $handler->handle(invoiceId: $invoiceId),
            selfLink: route(
                name: 'invoices.find',
                parameters: [
                    'invoiceId' => $invoiceId
                ]
            ),
        );
    }

    /**
     * Retrieve invoices collection by given filtering params
     *
     * @param InvoiceFilterRequest $request
     * @param GetInvoicesHandler $handler
     *
     * @return JsonResponse
     */
    public function get(InvoiceFilterRequest $request, GetInvoicesHandler $handler): JsonResponse
    {
        return GetMultipleSuccessResponse::create(
            paginator: $handler->handle($request),
            request: $request,
        );
    }
}
