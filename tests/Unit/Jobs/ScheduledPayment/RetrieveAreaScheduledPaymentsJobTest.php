<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs\ScheduledPayment;

use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Jobs\ScheduledPayment\RetrieveAreaScheduledPaymentsJob;
use App\Jobs\ScheduledPayment\Triggers\InitialServiceCompletedScheduledPaymentTriggerJob;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class RetrieveAreaScheduledPaymentsJobTest extends UnitTestCase
{
    /** @var ScheduledPaymentRepository&MockObject */
    private ScheduledPaymentRepository $scheduledPaymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduledPaymentRepository = $this->createMock(ScheduledPaymentRepository::class);

        Queue::fake();
    }

    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        $areaId = 1;

        RetrieveAreaScheduledPaymentsJob::dispatch($areaId);

        Queue::assertPushedOn(
            config(key: 'queue.connections.sqs.queues.process_payments'),
            RetrieveAreaScheduledPaymentsJob::class
        );
    }

    #[Test]
    public function it_dispatches_jobs_for_every_scheduled_payment(): void
    {
        /** @var Collection<int, ScheduledPayment> $initialServiceScheduledPayments */
        $initialServiceScheduledPayments = ScheduledPayment::factory()
            ->count(5)
            ->withoutRelationships()
            ->make(attributes: ['trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value]);

        $scheduledPayments = collect($initialServiceScheduledPayments);

        $this->scheduledPaymentRepository
            ->method('getPendingScheduledPaymentsForArea')
            ->willReturn(
                new LengthAwarePaginator(items: $scheduledPayments, total: $scheduledPayments->count(), perPage: 10)
            );

        Log::shouldReceive('info')->once()->with('DISPATCHED - Process Scheduled Payment Jobs', ['number_of_jobs' => count($scheduledPayments)]);

        $job = new RetrieveAreaScheduledPaymentsJob(1);
        $job->handle($this->scheduledPaymentRepository);

        Queue::assertPushed(InitialServiceCompletedScheduledPaymentTriggerJob::class, count($initialServiceScheduledPayments));
    }

    #[Test]
    public function it_retrieves_scheduled_payments_with_pagination_and_dispatches_jobs(): void
    {
        $this->scheduledPaymentRepository
            ->method('getPendingScheduledPaymentsForArea')
            ->willReturnCallback(static fn ($officeId, $page, $perPage) => match ($page) {
                1 => new LengthAwarePaginator(
                    items: ScheduledPayment::factory()
                        ->count(20)
                        ->withoutRelationships()
                        ->make(attributes: ['trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value]),
                    total: 21,
                    perPage: RetrieveAreaScheduledPaymentsJob::SCHEDULED_PAYMENTS_BATCH_SIZE_PER_REQUEST,
                    currentPage: 1
                ),
                2 => new LengthAwarePaginator(
                    items: ScheduledPayment::factory()
                        ->count(1)
                        ->withoutRelationships()
                        ->make(attributes: ['trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value]),
                    total: 21,
                    perPage: RetrieveAreaScheduledPaymentsJob::SCHEDULED_PAYMENTS_BATCH_SIZE_PER_REQUEST,
                    currentPage: 2
                ),
                default => throw new \RuntimeException('Unexpected page number')
            });

        Log::shouldReceive('info')->once()->with('DISPATCHED - Process Scheduled Payment Jobs', ['number_of_jobs' => 21]);

        $job = new RetrieveAreaScheduledPaymentsJob(1);
        $job->handle($this->scheduledPaymentRepository);

        Queue::assertPushed(InitialServiceCompletedScheduledPaymentTriggerJob::class, 21);
    }
}
