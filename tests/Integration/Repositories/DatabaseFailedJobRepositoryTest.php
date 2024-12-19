<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Api\Repositories\DatabaseFailedJobRepository;
use App\Models\FailedJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\RepositoryFilterMethodTestTrait;
use Tests\TestCase;

class DatabaseFailedJobRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    use RepositoryFilterMethodTestTrait;

    private DatabaseFailedJobRepository $repository;
    /** @var Collection<int, FailedJob>|null */
    private Collection|null $failedJobs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app()->make(DatabaseFailedJobRepository::class);

        $this->failedJobs = FailedJob::factory(count: 100)->create();
    }

    #[Test]
    public function get_retry_failed_jobs_return_collection_as_expected(): void
    {
        $jobIds = $this->failedJobs->pluck('uuid')->toArray();
        $actual = $this->repository->getRetryFailedJobs(filter: [
            'job_ids' => $jobIds,
        ]);

        $this->assertEqualsCanonicalizing($this->failedJobs->map(static fn ($job) => ['uuid' => $job->uuid])->toArray(), $actual->toArray());
    }

    protected function getEntity(): Model
    {
        return new FailedJob();
    }

    protected function getRepository(): mixed
    {
        return $this->repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->repository, $this->failedJobs);
    }
}
