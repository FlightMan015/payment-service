<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetInvoiceTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/invoices/%s';

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoice = $this->createInvoiceInDatabase();
    }

    #[Test]
    public function it_returns_200_response_for_retrieving_invoice_successfully(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, $this->invoice['id']),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => [
                'id',
                'status',
                'account' => ['id', 'external_ref_id', 'is_active', 'source'],
                'items',
                'external_ref_id',
                'subscription_id',
                'service_type_id',
                'is_active',
                'subtotal',
                'total',
                'balance',
                'currency_code',
                'service_charge',
                'invoiced_at',
                'created_at',
                'updated_at',
                'tax_amount',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $this->assertCount(10, $response->json('result.items'));
    }

    #[Test]
    public function it_returns_400_bad_request_for_invalid_id(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, 'test-id'),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('validation.parameter_invalid_uuid', ['parameter' => 'invoice id']));
    }

    #[Test]
    public function it_return_401_response_for_non_api_key_api(): void
    {
        $response = $this->get(uri: sprintf(self::ENDPOINT_URI, $this->invoice['id']));
        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('auth.api_key_not_found'));
    }

    #[Test]
    public function it_returns_404_not_found_response_for_request_with_non_existing_uuid(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, $invoiceId = Str::uuid()->toString()),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.invoice.not_found', ['id' => $invoiceId]));
    }

    private function createInvoiceInDatabase(): Invoice
    {
        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->count(10)->for($invoice)->create();

        return $invoice;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->invoice);
    }
}
