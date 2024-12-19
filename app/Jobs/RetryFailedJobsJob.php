<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class RetryFailedJobsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int LIMIT_IDS_PER_JOB = 100;

    /**
     * Create a new job instance.
     *
     * @param Collection<int, string> $jobIds
     */
    public function __construct(private readonly Collection $jobIds)
    {
        $this->onQueue(queue: config(key: 'queue.connections.sqs.queues.process_failed_jobs'));
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->jobIds->chunk(size: self::LIMIT_IDS_PER_JOB)->each(static function (Collection $ids) {
            Artisan::call(command: 'queue:retry', parameters: [
                'id' => $ids->toArray(),
            ]);
        });
    }
}
