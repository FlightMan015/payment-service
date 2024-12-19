<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Jobs\ProcessEligibleRefundJob;
use App\Jobs\RetrieveAreaEligibleRefundsJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class RetrieveAreaEligibleRefundsJobTest extends UnitTestCase
{
    /** @var PaymentRepository&MockObject $paymentRepository */
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = $this->createMock(PaymentRepository::class);

        Queue::fake();
    }

    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        $areaId = 123;

        RetrieveAreaEligibleRefundsJob::dispatch($areaId);

        Queue::assertPushedOn(
            queue: config(key: 'queue.connections.sqs.queues.process_payments'),
            job: RetrieveAreaEligibleRefundsJob::class
        );
    }

    #[Test]
    public function it_retrieves_refunds_and_dispatches_process_eligible_refund_jobs(): void
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);

        /** @var Collection<int, Payment> $payments */
        $payments = Payment::factory()->count(3)->makeWithRelationships(attributes: [
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'pestroutes_created_by_crm' => false,
            'processed_at' => now(),
        ], relationships: [
            'paymentMethod' => $paymentMethod
        ]);

        $this->paymentRepository->method('getExternalRefundsWithoutTransactionsForArea')->willReturn(
            new LengthAwarePaginator(items: $payments, total: 3, perPage: 10)
        );

        $job = new RetrieveAreaEligibleRefundsJob(123);

        Log::shouldReceive('shareContext')->once()->with(['area_id' => 123])->andReturnSelf();
        Log::shouldReceive('info')->once()->with(
            'Total eligible refunds retrieved from database',
            ['total' => 3, 'identifiers' => $payments->pluck('id')->toArray()]
        );
        Log::shouldReceive('info')->once()->with('DISPATCHED - Process Eligible Refund Jobs', ['number_of_jobs' => 3]);
        $job->handle($this->paymentRepository);

        Queue::assertPushed(ProcessEligibleRefundJob::class, 3);
    }

    #[Test]
    public function it_retrieves_refunds_and_dispatches_process_eligible_refund_jobs_with_pagination(): void
    {
        $totalPaymentsCount = 13;

        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);

        /** @var Collection<int, Payment> $firstPagePayments */
        $firstPagePayments = Payment::factory()->count(10)->makeWithRelationships(
            attributes: ['processed_at' => now()],
            relationships: ['paymentMethod' => $paymentMethod]
        );
        /** @var Collection<int, Payment> $secondPagePayments */
        $secondPagePayments = Payment::factory()->count(3)->makeWithRelationships(
            attributes: ['processed_at' => now()],
            relationships: ['paymentMethod' => $paymentMethod]
        );

        $this->paymentRepository
            ->expects($this->exactly(2))
            ->method('getExternalRefundsWithoutTransactionsForArea')
            ->willReturnCallback(static fn ($officeId, $page, $perPage) => match ($page) {
                1 => new LengthAwarePaginator(
                    items: $firstPagePayments,
                    total: $totalPaymentsCount,
                    perPage: 10,
                    currentPage: 1
                ),
                2 => new LengthAwarePaginator(
                    items: $secondPagePayments,
                    total: $totalPaymentsCount,
                    perPage: 10,
                    currentPage: 2
                ),
                default => throw new \RuntimeException('Unexpected page number')
            });

        $job = new RetrieveAreaEligibleRefundsJob(123);

        Log::shouldReceive('shareContext')->once()->with(['area_id' => 123])->andReturnSelf();
        Log::shouldReceive('info')->once()->with(
            'Total eligible refunds retrieved from database',
            ['total' => $totalPaymentsCount, 'identifiers' => array_merge($firstPagePayments->pluck('id')->toArray(), $secondPagePayments->pluck('id')->toArray())]
        );
        Log::shouldReceive('info')->once()->with('DISPATCHED - Process Eligible Refund Jobs', ['number_of_jobs' => $totalPaymentsCount]);

        $job->handle($this->paymentRepository);

        Queue::assertPushed(ProcessEligibleRefundJob::class, $totalPaymentsCount);
    }

    #[Test]
    public function it_retrieves_refunds_filters_them_by_date_and_dispatches_process_eligible_refund_jobs(): void
    {
        $area = Area::factory()->make();
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);

        $paymentsWithValidDateQuantity = 3;
        $paymentsWithInvalidDateQuantity = 4;
        $totalPaymentsCount = $paymentsWithValidDateQuantity + $paymentsWithInvalidDateQuantity;

        /** @var Collection<int, Payment> $paymentsWithValidDate */
        $paymentsWithValidDate = Payment::factory()->count($paymentsWithValidDateQuantity)->makeWithRelationships(attributes: [
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'pestroutes_created_by_crm' => false,
            'processed_at' => Carbon::parse('2024-07-01 01:00:00'),
        ], relationships: [
            'paymentMethod' => $paymentMethod
        ]);

        /** @var Collection<int, Payment> $paymentsWithInvalidDate */
        $paymentsWithInvalidDate = Payment::factory()->count($paymentsWithInvalidDateQuantity)->makeWithRelationships(attributes: [
            'payment_status_id' => PaymentStatusEnum::CREDITED->value,
            'pestroutes_created_by_crm' => false,
            'processed_at' => Carbon::parse('2024-06-30 23:59:59'),
        ], relationships: [
            'paymentMethod' => $paymentMethod
        ]);

        $payments = collect($paymentsWithValidDate)->merge($paymentsWithInvalidDate);

        $this->paymentRepository->method('getExternalRefundsWithoutTransactionsForArea')->willReturn(
            new LengthAwarePaginator(
                items: $payments,
                total: $totalPaymentsCount,
                perPage: 10
            )
        );

        $job = new RetrieveAreaEligibleRefundsJob(123);

        Log::shouldReceive('shareContext')->once()->with(['area_id' => 123])->andReturnSelf();
        Log::shouldReceive('info')->once()->with(
            __('messages.payment.eligible_refunds_processing.retrieved_from_database'),
            ['total' => $totalPaymentsCount, 'identifiers' => array_merge($paymentsWithValidDate->pluck('id')->toArray(), $paymentsWithInvalidDate->pluck('id')->toArray())]
        );
        Log::shouldReceive('info')->once()->with(
            __('messages.payment.eligible_refunds_processing.filtered_by_date', ['date' => RetrieveAreaEligibleRefundsJob::REFUND_DATE_THRESHOLD]),
            ['count' => $paymentsWithInvalidDateQuantity, 'identifiers' => $paymentsWithInvalidDate->pluck('id')->toArray()]
        );
        Log::shouldReceive('info')->once()->with('DISPATCHED - Process Eligible Refund Jobs', ['number_of_jobs' => $paymentsWithValidDateQuantity]);

        $job->handle($this->paymentRepository);

        Queue::assertPushed(ProcessEligibleRefundJob::class, $paymentsWithValidDateQuantity);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentRepository);
    }
}
