<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\DTO\PaymentProcessorResultDto;
use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Jobs\CheckAchPaymentStatusJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\TransactionTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Traits\WorldPayCredentialsRepositoryMockingTrait;
use Tests\Unit\UnitTestCase;

class CheckAchPaymentStatusJobTest extends UnitTestCase
{
    use WorldPayCredentialsRepositoryMockingTrait;

    /** @var MockObject&PaymentProcessorRepository $paymentProcessorRepository */
    private PaymentProcessorRepository $paymentProcessorRepository;
    /** @var MockObject&PaymentProcessor $paymentProcessor */
    private PaymentProcessor $paymentProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentProcessorRepository = $this->createMock(originalClassName: PaymentProcessorRepository::class);
        $this->paymentProcessor = $this->createMock(originalClassName: PaymentProcessor::class);

        $this->mockWorldPayCredentialsRepository();
    }

    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        Queue::fake();

        CheckAchPaymentStatusJob::dispatch(Payment::factory()->withoutRelationships()->make());

        Queue::assertPushedOn(queue: config(key: 'queue.connections.sqs.queues.process_payments'), job: CheckAchPaymentStatusJob::class);
    }

    #[Test]
    public function it_logs_checking_process_as_success(): void
    {
        // Arrange
        $payment = $this->makePayment();

        $this->paymentProcessorRepository
            ->expects($this->once())
            ->method('status')
            ->willReturn(new PaymentProcessorResultDto(
                isSuccess: true,
                transactionId: $payment->transactions->first()->id,
                message: 'message',
            ));

        // Assert
        Log::shouldReceive('info')
            ->with(__('messages.payment.ach_status_checking.success', [
                'id' => $payment->id,
            ]))
            ->once();

        // Act
        $job = new CheckAchPaymentStatusJob($payment);
        $job->handle(
            paymentProcessorRepository: $this->paymentProcessorRepository,
            paymentProcessor: $this->paymentProcessor,
        );
    }

    #[Test]
    public function it_logs_checking_process_as_failed(): void
    {
        // Arrange
        $payment = $this->makePayment();

        $this->paymentProcessorRepository
            ->expects($this->once())
            ->method('status')
            ->willReturn(new PaymentProcessorResultDto(
                isSuccess: false,
                transactionId: $payment->transactions->first()->id,
                message: 'error message',
            ));

        // Assert
        Log::shouldReceive('error')
            ->with(__('messages.payment.ach_status_checking.failed', [
                'id' => $payment->id,
                'error_message' => 'error message',
            ]))
            ->once();

        // Act
        $job = new CheckAchPaymentStatusJob($payment);
        $job->handle(
            paymentProcessorRepository: $this->paymentProcessorRepository,
            paymentProcessor: $this->paymentProcessor,
        );
    }

    private function makePayment(): Payment
    {
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
                'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                'payment_type_id' => PaymentTypeEnum::ACH->value,
                'amount' => 100,
                'applied_amount' => 50,
                'processed_at' => now()->subDays(1),
            ],
            relationships: [
                'transactions' => Transaction::factory()->withoutRelationships()->make([
                    'transaction_type_id' => TransactionTypeEnum::CAPTURE->value,
                    'amount' => 50,
                    'processed_at' => now()->subDays(1),
                ]),
                'paymentMethod' => PaymentMethod::factory()->ach()->makeWithRelationships(
                    attributes: [
                        'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                    ],
                    relationships: [
                        'account' => Account::factory()->makeWithRelationships(relationships: [
                            'area' => Area::factory()->make()
                        ]),
                    ]
                ),
            ]
        );
        return $payment;
    }

    protected function tearDown(): void
    {
        unset(
            $this->paymentProcessorRepository,
            $this->paymentProcessor,
        );

        parent::tearDown();
    }
}
