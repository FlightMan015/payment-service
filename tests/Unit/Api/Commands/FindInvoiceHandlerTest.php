<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\FindInvoiceHandler;
use App\Api\Exceptions\ResourceNotFoundException;
use App\Api\Repositories\Interface\InvoiceRepository;
use App\Models\CRM\Customer\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class FindInvoiceHandlerTest extends UnitTestCase
{
    /** @var Model&Invoice $invoice */
    private Invoice $invoice;

    #[Test]
    public function it_returns_invoice_with_correct_data_when_found(): void
    {
        $invoiceData = $this->handler()->handle(invoiceId: Str::uuid()->toString());

        $this->assertCount(expectedCount: count(data_get($invoiceData, 'items')), haystack: $this->invoice->items);
        $this->assertSame(array_keys($invoiceData), [
            'id',
            'status',
            'account',
            'items',
            'external_ref_id',
            'subscription_id',
            'service_type_id',
            'is_active',
            'subtotal',
            'total',
            'tax_rate',
            'tax_amount',
            'balance',
            'currency_code',
            'service_charge',
            'invoiced_at',
            'created_at',
            'updated_at',
        ]);
    }

    #[Test]
    public function it_throws_resource_not_found_exception_when_invoice_id_not_exist(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->handler(repository: $this->mockInvoiceRepositoryFind(foundInvoice: false))
            ->handle(invoiceId: Str::uuid()->toString());
    }

    private function mockInvoiceRepositoryFind(bool $foundInvoice): InvoiceRepository|MockObject
    {
        $repository = $this->createMock(originalClassName: InvoiceRepository::class);
        if ($foundInvoice === true) {
            $this->invoice = $this->mockInvoiceObject();

            $repository->method('find')->willReturn($this->invoice);
        } else {
            $repository->method('find')->willThrowException(new ResourceNotFoundException('some error'));
        }

        return $repository;
    }

    private function mockInvoiceObject(): Invoice
    {
        /** @var Model&Invoice $invoice */
        $invoice = Invoice::factory()->makeWithRelationships(relationships: [
            'account' => Account::factory()->withoutRelationships()->make(),
            'items' =>  InvoiceItem::factory()->count(10)->withoutRelationships()->make(),
        ]);

        return $invoice;
    }

    private function handler(InvoiceRepository|null $repository = null): FindInvoiceHandler
    {
        return new FindInvoiceHandler(repository: $repository ?? $this->mockInvoiceRepositoryFind(foundInvoice: true));
    }
}
