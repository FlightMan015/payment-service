<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Random\RandomException;

// TODO: remove this after jobs logs issues are investigated
class TestLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_payments'));
    }

    /**
     * Execute the job.
     *
     * @param LoggerInterface $logger
     *
     * @throws RandomException
     *
     * @return void
     */
    public function handle(LoggerInterface $logger): void
    {
        $logsQuantity = random_int(2, 10);

        \Log::shareContext(context: [
            'total_logs_quantity' => $logsQuantity,
            'job_id' => $this->job->getJobId(),
        ]);

        $logger->info('Initiated fake logs for testing purposes', context: ['iteration' => 1]);

        // start from 2 on purpose because of the previous info log
        for ($i = 2; $i <= $logsQuantity; $i++) {
            $logLevel = match (random_int(1, 5)) {
                1 => 'debug',
                2 => 'info',
                3 => 'notice',
                4 => 'warning',
                5 => 'error',
            };

            $logger->$logLevel(
                message: sprintf('Test log message: %s', fake()->sentence()),
                context: [
                    'iteration' => $i,
                    'random_additional_context' => [
                        'name' => fake()->name(),
                        'email' => fake()->email(),
                        'phone' => fake()->phoneNumber(),
                        'address' => fake()->address(),
                    ],
                ]
            );
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error(message: 'TestLogsJob failed', context: [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTrace()
        ]);
    }
}
