<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\Repositories\Interface\FailedJobRepository;
use App\Api\Requests\FailedJobFilterRequest;
use App\Api\Requests\RetryFailedJobRequest;
use App\Api\Responses\AcceptedSuccessResponse;
use App\Api\Responses\GetMultipleSuccessResponse;
use App\Api\Responses\SuccessResponse;
use App\Jobs\RetryFailedJobsJob;
use App\Jobs\TestLogsJob;
use App\Models\FailedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobController
{
    /**
     * @param Request $request
     *
     * return SuccessResponse
     */
    public function availableQueues(Request $request): SuccessResponse
    {
        return SuccessResponse::create(
            message: 'Success',
            selfLink: $request->fullUrl(),
            additionalData: [
                'items' => array_values(config('queue.connections.sqs.queues')),
            ]
        );
    }

    /**
     * @param FailedJobFilterRequest $request
     * @param FailedJobRepository $repository
     *
     * @return GetMultipleSuccessResponse
     */
    public function get(
        FailedJobFilterRequest $request,
        FailedJobRepository $repository
    ): GetMultipleSuccessResponse {
        $paginator = $repository->filter(
            filter: $request->validated(),
            columns: ['uuid', 'queue', 'exception', 'failed_at'],
        );
        $paginator->through(fn (FailedJob $failedJob) => $this->exposeFailedJob(failedJob: $failedJob));

        return GetMultipleSuccessResponse::create(paginator: $paginator, request: $request);
    }

    private function exposeFailedJob(FailedJob $failedJob): array
    {
        return [
            'uuid' => $failedJob->uuid,
            'queue' => Str::afterLast(subject: $failedJob->queue, search: '/'),
            'failure_reason' => Str::before(subject: $failedJob->exception, search: "\n"),
            'failed_at' => $failedJob->failed_at,
        ];
    }

    /**
     * @param RetryFailedJobRequest $request
     * @param FailedJobRepository $repository
     *
     * @return AcceptedSuccessResponse
     */
    public function retryFailedJob(RetryFailedJobRequest $request, FailedJobRepository $repository): AcceptedSuccessResponse
    {
        RetryFailedJobsJob::dispatch(
            $repository->getRetryFailedJobs(filter: $request->validated())->pluck(value: 'uuid')
        );

        return AcceptedSuccessResponse::create(message: 'Accepted', selfLink: $request->fullUrl());
    }

    /**
     * TODO: remove this after jobs logs issues are investigated
     *
     * @param int $jobsQuantity
     *
     * @return JsonResponse
     */
    public function testLogs(int $jobsQuantity = 1): JsonResponse
    {
        for ($i = 0; $i < $jobsQuantity; $i++) {
            TestLogsJob::dispatch();
        }

        return response()->json(
            data: ['message' => 'Test logs job has been executed successfully.', 'jobs_quantity' => $jobsQuantity],
            status: 202
        );
    }
}
