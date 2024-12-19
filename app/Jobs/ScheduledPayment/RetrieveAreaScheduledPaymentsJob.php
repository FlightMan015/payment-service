<?php

declare(strict_types=1);

namespace App\Jobs\ScheduledPayment;

use App\Api\Repositories\Interface\ScheduledPaymentRepository;
use App\Factories\ScheduledPaymentTriggerFactory;
use App\Models\ScheduledPayment;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetrieveAreaScheduledPaymentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const int SCHEDULED_PAYMENTS_BATCH_SIZE_PER_REQUEST = 500;

    public int $timeout = 2 * CarbonInterface::SECONDS_PER_MINUTE;

    private ScheduledPaymentRepository $scheduledPaymentRepository;

    /**
     * Create a new job instance.
     *
     * @param int $areaId
     */
    public function __construct(private readonly int $areaId)
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
    }

    /**
     * Execute the job.
     *
     * @param ScheduledPaymentRepository $scheduledPaymentRepository
     *
     * @throws \Exception
     *
     * @return void
     */
    public function handle(ScheduledPaymentRepository $scheduledPaymentRepository): void
    {
        $this->scheduledPaymentRepository = $scheduledPaymentRepository;

        /** @var ScheduledPayment[] $scheduledPayments */
        $scheduledPayments = $this->retrieveScheduledPaymentsFromDatabase();

        foreach ($scheduledPayments as $scheduledPayment) {
            $job = ScheduledPaymentTriggerFactory::make($scheduledPayment);
            dispatch($job);
        }

        Log::info(
            message: 'DISPATCHED - Process Scheduled Payment Jobs',
            context: ['number_of_jobs' => count($scheduledPayments)]
        );
    }

    private function retrieveScheduledPaymentsFromDatabase(): array
    {
        $scheduledPayments = [];
        $page = 1;

        do {
            $paginatedScheduledPayments = $this->scheduledPaymentRepository->getPendingScheduledPaymentsForArea(
                areaId: $this->areaId,
                page: $page,
                quantity: self::SCHEDULED_PAYMENTS_BATCH_SIZE_PER_REQUEST
            );

            array_push($scheduledPayments, ...$paginatedScheduledPayments->items());

            $page++;
        } while (count($scheduledPayments) < $paginatedScheduledPayments->total());

        return $scheduledPayments;
    }
}
