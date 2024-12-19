<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetrieveAreaEligibleRefundsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const int REFUNDS_BATCH_SIZE_PER_REQUEST = 500;
    public const string REFUND_DATE_THRESHOLD = '2024-07-01'; // do not process refunds that are older than this date

    public int $timeout = 2 * CarbonInterface::SECONDS_PER_MINUTE;

    private PaymentRepository $paymentRepository;

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
     * @param PaymentRepository $paymentRepository
     *
     * @throws \Exception
     *
     * @return void
     */
    public function handle(PaymentRepository $paymentRepository): void
    {
        Log::shareContext(['area_id' => $this->areaId]);

        $this->paymentRepository = $paymentRepository;

        /** @var Payment[] $refunds */
        $refunds = $this->filterRefundsByDate($this->retrieveEligibleRefundsFromDatabase());

        foreach ($refunds as $refund) {
            ProcessEligibleRefundJob::dispatch($refund);
        }

        Log::info(
            message: 'DISPATCHED - Process Eligible Refund Jobs',
            context: ['number_of_jobs' => count($refunds)]
        );
    }

    private function retrieveEligibleRefundsFromDatabase(): array
    {
        $refunds = [];
        $page = 1;

        do {
            $paginatedRefunds = $this->paymentRepository->getExternalRefundsWithoutTransactionsForArea(
                areaId: $this->areaId,
                page: $page,
                quantity: self::REFUNDS_BATCH_SIZE_PER_REQUEST
            );

            array_push($refunds, ...$paginatedRefunds->items());

            $page++;
        } while (count($refunds) < $paginatedRefunds->total());

        return $refunds;
    }

    private function filterRefundsByDate(array $refunds): array
    {
        $thresholdDate = Carbon::parse(self::REFUND_DATE_THRESHOLD);

        Log::info(
            message: __('messages.payment.eligible_refunds_processing.retrieved_from_database'),
            context: [
                'total' => count($refunds),
                'identifiers' => array_map(static fn (Payment $refund): string => $refund->id, $refunds)
            ]
        );

        $invalidRefunds = array_filter(
            array: $refunds,
            callback: static fn (Payment $refund): bool => Carbon::parse($refund->processed_at)->isBefore($thresholdDate)
        );

        if (count($invalidRefunds) > 0) {
            Log::info(
                message: __('messages.payment.eligible_refunds_processing.filtered_by_date', ['date' => $thresholdDate->format('Y-m-d')]),
                context: [
                    'count' => count($invalidRefunds),
                    'identifiers' => array_values(array_map(static fn (Payment $refund): string => $refund->id, $invalidRefunds))
                ]
            );
        }

        return array_diff_key($refunds, $invalidRefunds);
    }
}
