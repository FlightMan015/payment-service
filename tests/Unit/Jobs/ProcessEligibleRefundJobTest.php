<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\DTO\RefundPaymentResultDto;
use App\Api\Repositories\Interface\PaymentRepository;
use App\Jobs\ProcessEligibleRefundJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\Services\Payment\Refund\RefundElectronicPaymentService;
use Aptive\Attribution\Enums\DomainEnum;
use Aptive\Attribution\Enums\EntityEnum;
use Aptive\Attribution\Enums\PrefixEnum;
use Aptive\Attribution\Enums\TenantEnum;
use Aptive\Attribution\Urn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class ProcessEligibleRefundJobTest extends UnitTestCase
{
    /** @var RefundElectronicPaymentService&MockObject $refundService */
    private RefundElectronicPaymentService $refundService;
    /** @var PaymentRepository&MockObject $paymentRepository */
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundService = $this->createMock(RefundElectronicPaymentService::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
    }

    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        Queue::fake(jobsToFake: ProcessEligibleRefundJob::class);

        $refund = Payment::factory()->withoutRelationships()->make(['payment_status_id' => PaymentStatusEnum::CREDITED->value]);

        ProcessEligibleRefundJob::dispatch($refund);

        Queue::assertPushedOn(queue: config(key: 'queue.connections.sqs.queues.process_payments'), job: ProcessEligibleRefundJob::class);
    }

    #[Test]
    public function it_does_not_process_refund_if_payment_does_not_have_payment_method_and_exclude_it_from_future_processing(): void
    {
        $payment = Payment::factory()->makeWithRelationships(relationships: ['paymentMethod' => null]);
        $job = new ProcessEligibleRefundJob($payment);

        Log::shouldReceive('shareContext')->once()->with([
            'payment_id' => $payment->id,
            'external_ref_id' => $payment->external_ref_id,
            'account_id' => $payment->account_id,
            'amount' => $payment->amount,
        ])->andReturnSelf();
        Log::shouldReceive('warning')->once()->with(
            __('messages.payment.eligible_refunds_processing.payment_method_not_found_cannot_process'),
            ['payment_id' => $payment->id]
        );

        $now = now();
        Carbon::setTestNow($now);

        $this->paymentRepository->expects($this->once())->method('update')->with(
            $this->equalTo($payment),
            [
                'pestroutes_refund_processed_at' => $now,
                'updated_by' => $this->getUrn()
            ]
        );

        // TODO: verify notification is sent [CLEO-1097]

        $job->handle($this->refundService, $this->paymentRepository);
    }

    #[Test]
    public function it_does_not_process_refund_if_the_original_payment_was_not_processed_in_gateway_yet_and_does_not_exclude_it_from_future_processing(): void
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);

        $originalPayment = Payment::factory()->makeWithRelationships(attributes: [
            'processed_at' => now(),
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
        ], relationships: ['paymentMethod' => $paymentMethod]);

        $payment = Payment::factory()->makeWithRelationships(relationships: [
            'originalPayment' => $originalPayment,
            'paymentMethod' => $paymentMethod
        ]);

        $job = new ProcessEligibleRefundJob($payment);

        Log::shouldReceive('shareContext')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once()->with(
            __('messages.payment.eligible_refunds_processing.original_payment_was_not_processed'),
            ['payment_id' => $payment->id]
        );

        $this->paymentRepository->expects($this->never())->method('update');
        // TODO: verify notification is NOT sent in such case [CLEO-1097]

        $job->handle($this->refundService, $this->paymentRepository);
    }

    #[Test]
    public function it_adds_warning_if_refund_failed_in_gateway_and_exclude_it_from_future_processing(): void
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships();
        $originalPayment = Payment::factory()->makeWithRelationships(attributes: [
            'processed_at' => now()->subDay(),
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'pestroutes_created_by_crm' => true,
        ], relationships: ['paymentMethod' => $paymentMethod]);
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CREDITED->value,
                'pestroutes_created_by_crm' => false,
            ],
            relationships: [
                'originalPayment' => $originalPayment,
                'paymentMethod' => $paymentMethod,
                'account' => $account
            ]
        );

        $job = new ProcessEligibleRefundJob($payment);

        $this->refundService->method('refund')->willReturn(
            new RefundPaymentResultDto(
                isSuccess: false,
                status: PaymentStatusEnum::CREDITED,
                refundPaymentId: $payment->id,
                errorMessage: 'Some error message'
            )
        );

        Log::shouldReceive('shareContext')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->with(__('messages.payment.eligible_refunds_processing.start_refund'));
        Log::shouldReceive('warning')->once()->with(
            __('messages.payment.eligible_refunds_processing.refund_failed'),
            ['error' => 'Some error message']
        );

        $now = now();
        Carbon::setTestNow($now);
        $this->paymentRepository->expects($this->once())->method('update')->with(
            $payment,
            [
                'pestroutes_refund_processed_at' => now(),
                'updated_by' => $this->getUrn()
            ]
        );
        // TODO: verify notification is sent [CLEO-1097]

        $job->handle($this->refundService, $this->paymentRepository);
    }

    #[Test]
    public function it_successfully_processes_refund_and_add_info_log_at_the_end_of_the_process_and_exclude_it_from_future_processing(): void
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships();
        $originalPayment = Payment::factory()->makeWithRelationships(attributes: [
            'processed_at' => now()->subDay(),
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'pestroutes_created_by_crm' => true,
        ], relationships: ['paymentMethod' => $paymentMethod]);
        $payment = Payment::factory()->makeWithRelationships(
            attributes: [
                'payment_status_id' => PaymentStatusEnum::CREDITED->value,
                'pestroutes_created_by_crm' => false,
            ],
            relationships: [
                'originalPayment' => $originalPayment,
                'paymentMethod' => $paymentMethod,
                'account' => $account
            ]
        );
        $expectedTransactionId = Str::uuid()->toString();

        $job = new ProcessEligibleRefundJob($payment);

        $this->refundService->method('refund')->willReturn(
            new RefundPaymentResultDto(
                isSuccess: true,
                status: PaymentStatusEnum::CREDITED,
                refundPaymentId: $payment->id,
                transactionId: $expectedTransactionId
            )
        );

        Log::shouldReceive('shareContext')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->with(__('messages.payment.eligible_refunds_processing.start_refund'));
        Log::shouldReceive('info')->once()->with(
            __('messages.payment.eligible_refunds_processing.refunded_successfully'),
            ['transaction_id' => $expectedTransactionId]
        );

        $now = now();
        Carbon::setTestNow($now);
        $this->paymentRepository->expects($this->once())->method('update')->with(
            $payment,
            [
                'pestroutes_refund_processed_at' => now(),
                'updated_by' => $this->getUrn()
            ]
        );

        $job->handle($this->refundService, $this->paymentRepository);
    }

    private function getUrn(): string
    {
        return (new Urn(
            prefix: PrefixEnum::URN,
            tenant: TenantEnum::Aptive,
            domain: DomainEnum::Organization,
            entity: EntityEnum::ApiAccount,
            identity: config(key: 'attribution.payment_service_api_account_id')
        ))->toString();
    }
}
