<?php

declare(strict_types=1);

namespace Tests\Integration\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use Aptive\Worldpay\CredentialsRepository\CredentialsRepository;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\PaymentProcessor\WorldpayCredentialsStub;
use Tests\Stubs\PaymentProcessor\WorldpayResponseStub;
use Tests\TestCase;

class RefundPaymentsCommandTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_refunds_payment_if_gateway_returns_success_and_creating_a_refund_payment_record(): void
    {
        $payment = $this->createPayment(processedAt: Carbon::yesterday());

        $this->mockPaymentGateway(isRefundSuccess: true);

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$payment->id]])
            ->expectsOutput(output: "Attempting Refund of Payment ID: $payment->id")
            ->expectsOutput(output: "Refunded Payment ID: $payment->id successfully")
            ->assertExitCode(exitCode: 0);

        $this->assertDatabaseHas(table: Payment::class, data: [
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'original_payment_id' => $payment->id,
        ]);
    }

    #[Test]
    public function it_does_not_refund_payment_if_it_was_more_than_45_days_ago_and_adds_error_log(): void
    {
        $payment = $this->createPayment(processedAt: Carbon::now()->subDays(46));

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$payment->id]])
            ->expectsOutput(output: "Attempting Refund of Payment ID: $payment->id")
            ->expectsOutput(output: "Error refunding Payment ID: $payment->id, reason: The original payment payment is more than 45 days old, automatic refund cannot be processed")
            ->assertExitCode(exitCode: 0);

        $this->assertDatabaseMissing(table: Payment::class, data: [
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'original_payment_id' => $payment->id,
        ]);
    }

    #[Test]
    public function it_sets_the_correct_external_ref_id_when_running_with_option(): void
    {
        // remove all payments and transactions just in case
        Transaction::query()->forceDelete();
        Payment::query()->forceDelete();

        $payment = $this->createPayment(processedAt: Carbon::yesterday());

        $this->mockPaymentGateway(isRefundSuccess: true);

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$payment->id], '--populate-fake-external-ref-id' => true])
            ->expectsOutput(output: "Attempting Refund of Payment ID: $payment->id")
            ->expectsOutput(output: "Refunded Payment ID: $payment->id successfully")
            ->assertExitCode(exitCode: 0);

        $this->assertDatabaseHas(table: Payment::class, data: [
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'original_payment_id' => $payment->id,
            'external_ref_id' => 1000000000
        ]);
    }

    #[Test]
    public function it_sets_the_correct_external_ref_id_if_there_is_already_existing_fake_external_ref_id_with_option_passed(): void
    {
        // remove all payments just in case
        Payment::query()->delete();
        // create a fake payment with fake external ref id
        Payment::factory()->create(['external_ref_id' => 1000000054]);

        $payment = $this->createPayment(processedAt: Carbon::yesterday());

        $this->mockPaymentGateway(isRefundSuccess: true);

        $this->artisan(command: 'refund:full', parameters: ['ids' => [$payment->id], '--populate-fake-external-ref-id' => true])
            ->expectsOutput(output: "Attempting Refund of Payment ID: $payment->id")
            ->expectsOutput(output: "Refunded Payment ID: $payment->id successfully")
            ->assertExitCode(exitCode: 0);

        $this->assertDatabaseHas(table: Payment::class, data: [
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'original_payment_id' => $payment->id,
            'external_ref_id' => 1000000055
        ]);
    }

    private function mockPaymentGateway(bool $isRefundSuccess): void
    {
        $this->mockDynamoDbForGettingWorldPayCredentials();

        $guzzle = $this->createMock(originalClassName: GuzzleClient::class);
        $guzzle->method('post')->willReturn(
            new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: $isRefundSuccess ? WorldpayResponseStub::creditSuccess() : null,
            )
        );

        $this->app->instance(abstract: GuzzleClient::class, instance: $guzzle);
    }

    private function mockDynamoDbForGettingWorldPayCredentials(): void
    {
        $mockCredential = $this->getMockBuilder(CredentialsRepository::class)->getMock();
        $mockCredential->method('get')->willReturn(WorldpayCredentialsStub::make());
        $this->app->instance(abstract: CredentialsRepository::class, instance: $mockCredential);
    }

    private function createPayment(Carbon $processedAt): Payment
    {
        $paymentMethod = PaymentMethod::factory()->cc()->create(attributes: [
            'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
        ]);
        $payment = Payment::factory()->create(attributes: [
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => $paymentMethod->payment_gateway_id,
            'payment_type_id' => $paymentMethod->payment_type_id,
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'processed_at' => $processedAt,
        ]);
        Transaction::factory()->for($payment)->create(attributes: [
            'transaction_type_id' => TransactionTypeEnum::AUTH_CAPTURE->value,
        ]);

        return $payment;
    }
}
