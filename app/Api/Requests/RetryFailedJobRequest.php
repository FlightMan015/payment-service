<?php

declare(strict_types=1);

namespace App\Api\Requests;

use App\Models\FailedJob;
use Illuminate\Validation\Rule;

/**
 * @property int $job_ids
 */
class RetryFailedJobRequest extends AbstractRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_ids' => ['nullable', 'array', 'min:1'],
            'job_ids.*' => ['bail', 'string', 'uuid', Rule::exists(table: FailedJob::class, column: 'uuid')],
            'queue' => [
                'sometimes',
                'string',
                Rule::in(values: config('queue.connections.sqs.queues')),
            ],
        ];
    }
}
