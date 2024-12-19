<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Commands\PaymentSyncReportHandler;
use App\Api\DTO\PaymentSyncReportDto;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\AbstractContractTest;

class GetPaymentsSyncReportTest extends AbstractContractTest
{
    protected const string ENDPOINT = 'api/v1/data-sync/payments-report';

    protected MockObject&PaymentSyncReportHandler $paymentSyncReportHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentSyncReportHandler = $this->createMock(PaymentSyncReportHandler::class);

        $this->instance(
            abstract: PaymentSyncReportHandler::class,
            instance: $this->paymentSyncReportHandler
        );
    }

    #[Test]
    public function it_returns_a_200_but_only_notifies_of_no_unsynced_payments_when_unsynced_payments_dont_exist(): void
    {
        $this
            ->paymentSyncReportHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new PaymentSyncReportDto(
                numberUnprocessed: 0,
                message: __('messages.payment.batch_processing.payment_sync_payments_already_synced')
            ));

        $this->sendRequest(headers: ['Api-Key' => config('auth.api_keys.payment_processing')])
            ->assertStatus(HttpStatus::OK)
            ->assertJson([
                '_metadata' => [
                    'success' => true,
                ],
                'result' => [
                    'message' => __('messages.payment.batch_processing.payment_sync_payments_already_synced'),
                ],
            ]);
    }

    #[Test]
    public function it_returns_a_200_and_sends_payment_sync_report_when_unsynced_payments_exist(): void
    {
        $this
            ->paymentSyncReportHandler
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new PaymentSyncReportDto(
                numberUnprocessed: 1,
                message: __('messages.payment.batch_processing.payment_sync_report_processed')
            ));

        $this->sendRequest(headers: ['Api-Key' => config('auth.api_keys.payment_processing')])
            ->assertStatus(HttpStatus::OK)
            ->assertJson([
                '_metadata' => [
                    'success' => true,
                ],
                'result' => [
                    'message' => __('messages.payment.batch_processing.payment_sync_report_processed'),
                ],
            ]);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_incorrect(): void
    {
        $response = $this->sendRequest(headers: ['Api-Key' => 'SomeIncorrectApiKey1234'])
            ->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $this->assertErrorResponseStructure(response: $response);
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
    }

    private function sendRequest(array $headers = []): TestResponse
    {
        return $this
            ->withHeaders(array_merge(['Accept' => 'application/json'], $headers))
            ->json('GET', self::ENDPOINT);
    }
}
