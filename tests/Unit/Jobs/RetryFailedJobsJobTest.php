<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\RetryFailedJobsJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class RetryFailedJobsJobTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    #[DataProvider('tryJobProvider')]
    public function retry_jobs_calls_artisan_queue_with_expected_times(array $input, int $expected): void
    {
        Artisan::shouldReceive('call')
            ->times($expected);

        $job = new RetryFailedJobsJob(jobIds: collect($input));
        $job->handle();
    }

    public static function tryJobProvider(): \Iterator
    {
        yield 'empty input' => [
            'input' => [],
            'expected' => 0,
        ];
        yield 'run 1 time' => [
            'input' => [1, 2, 3],
            'expected' => 1,
        ];
        yield 'run 1 time (2)' => [
            'input' => range(1, 100),
            'expected' => 1,
        ];
        yield 'run 2 times' => [
            'input' => range(1, 101),
            'expected' => 2,
        ];
        yield '10 times' => [
            'input' => range(1, 999),
            'expected' => 10,
        ];
    }

    #[Test]
    public function retry_jobs_calls_artisan_queue_with_expected_command_and_parameters(): void
    {
        $ids = [Str::uuid()->toString(), Str::uuid()->toString(), Str::uuid()->toString()];

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => $ids]);

        $job = new RetryFailedJobsJob(jobIds: new Collection(items: $ids));
        $job->handle();
    }
}
