<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Repositories\Interface\PaymentProcessorRepository;
use App\Models\Payment;
use App\PaymentProcessor\PaymentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckAchPaymentStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param Payment $payment
     */
    public function __construct(
        private readonly Payment $payment,
    ) {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
    }

    /**
     * Execute the job.
     *
     * @param PaymentProcessorRepository $paymentProcessorRepository
     * @param PaymentProcessor $paymentProcessor
     *
     * @return void
     */
    public function handle(
        PaymentProcessorRepository $paymentProcessorRepository,
        PaymentProcessor $paymentProcessor,
    ): void {
        $result = $paymentProcessorRepository->status(
            payment: $this->payment,
            paymentProcessor: $paymentProcessor,
        );

        if ($result->isSuccess) {
            Log::info(__('messages.payment.ach_status_checking.success', [
                'id' => $this->payment->id,
            ]));
            return;
        }

        Log::error(__('messages.payment.ach_status_checking.failed', [
            'id' => $this->payment->id,
            'error_message' => $result->message,
        ]));
    }
}
