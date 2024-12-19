<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Jobs\CheckAchPaymentStatusJob;
use App\Jobs\RetrieveUnsettledPaymentsJob;
use App\Models\CRM\Customer\Account;
use App\Models\CRM\FieldOperations\Area;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class RetrieveUnsettledPaymentsJobTest extends UnitTestCase
{
    /** @var MockObject&PaymentRepository $paymentRepository */
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = $this->createMock(originalClassName: PaymentRepository::class);

        Queue::fake();
    }

    #[Test]
    public function job_is_queued_with_correct_data_in_the_correct_queue(): void
    {
        RetrieveUnsettledPaymentsJob::dispatch(
            processedAtFrom: now()->subDays(1),
            processedAtTo: now(),
            areaId: 1,
        );

        Queue::assertPushedOn(queue: config(key: 'queue.connections.sqs.queues.process_payments'), job: RetrieveUnsettledPaymentsJob::class);
    }

    #[Test]
    public function it_retrieve_unsettled_payments_and_dispatches_check_ach_returned_job_with_pagination(): void
    {
        // Arrange
        $totalPaymentsCount = 13;

        $area = Area::factory()->make([
            'id' => 1,
        ]);
        $account = Account::factory()->makeWithRelationships(relationships: ['area' => $area]);
        $paymentMethod = PaymentMethod::factory()->makeWithRelationships(relationships: ['account' => $account]);

        $firstPagePayments = Payment::factory()->count(10)->makeWithRelationships(
            attributes: ['processed_at' => now()],
            relationships: ['paymentMethod' => $paymentMethod]
        );
        $secondPagePayments = Payment::factory()->count(3)->makeWithRelationships(
            attributes: ['processed_at' => now()],
            relationships: ['paymentMethod' => $paymentMethod]
        );
        $this->paymentRepository
            ->expects($this->exactly(2))
            ->method('getNotFullySettledAchPayments')
            ->willReturnCallback(static fn ($processedAtFrom, $processedAtTo, $page, $perPage) => match ($page) {
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
        // Assert
        Log::shouldReceive('info')
            ->with(__('messages.payment.ach_status_checking.dispatched'), ['number_of_jobs' => $totalPaymentsCount])
            ->once();

        // Act
        $job = new RetrieveUnsettledPaymentsJob(
            processedAtFrom: now()->subDays(1),
            processedAtTo: now(),
            areaId: $area->id,
        );
        $job->handle(
            paymentRepository: $this->paymentRepository,
        );

        // Assert
        Queue::assertPushed(CheckAchPaymentStatusJob::class, 13);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentRepository);
    }
}
