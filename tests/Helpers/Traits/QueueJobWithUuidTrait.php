<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Str;

trait QueueJobWithUuidTrait
{
    /**
     * @return Job
     */
    public function queueJobWithUuid(): Job
    {
        $mockJob = $this->createMock(Job::class);
        $mockJob->method('uuid')->willReturn(value: Str::uuid()->toString());

        return $mockJob;
    }
}
