<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Repositories\Interface\PaymentRepository;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetrieveUnsettledPaymentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const int BATCH_SIZE_PER_REQUEST = 500;

    private PaymentRepository $paymentRepository;

    /**
     * Create a new job instance.
     *
     * @param \DateTimeInterface $processedAtFrom
     * @param \DateTimeInterface $processedAtTo
     * @param int $areaId
     */
    public function __construct(
        private readonly \DateTimeInterface $processedAtFrom,
        private readonly \DateTimeInterface $processedAtTo,
        private readonly int $areaId,
    ) {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
    }

    /**
     * Execute the job.
     *
     * @param PaymentRepository $paymentRepository
     *
     * @return void
     */
    public function handle(
        PaymentRepository $paymentRepository,
    ): void {
        $this->paymentRepository = $paymentRepository;

        $payments = $this->retrieveNotFullySettledAchPaymentsFromDatabase();

        /* @var Payment $payment */
        foreach ($payments as $payment) {
            CheckAchPaymentStatusJob::dispatch($payment);
        }

        Log::info(__('messages.payment.ach_status_checking.dispatched'), ['number_of_jobs' => count($payments)]);
    }

    private function retrieveNotFullySettledAchPaymentsFromDatabase(): array
    {
        $payments = [];
        $page = 1;

        do {
            $paginatedPayments = $this->paymentRepository->getNotFullySettledAchPayments(
                processedAtFrom: $this->processedAtFrom,
                processedAtTo: $this->processedAtTo,
                page: $page,
                quantity: self::BATCH_SIZE_PER_REQUEST,
                areaId: $this->areaId,
            );

            array_push($payments, ...$paginatedPayments->items());

            $page++;
        } while (count($payments) < $paginatedPayments->total());

        return $payments;
    }
}
