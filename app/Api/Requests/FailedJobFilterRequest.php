<?php

declare(strict_types=1);

namespace App\Api\Requests;

use Illuminate\Validation\Rule;

/**
 * @property int $queue
 */
class FailedJobFilterRequest extends FilterRequest
{
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'job_ids' => ['nullable', 'array', 'min:1'],
            'job_ids.*' => ['bail', 'string', 'uuid'],
            'queue' => [
                'nullable',
                'string',
                Rule::in(values: config('queue.connections.sqs.queues')),
            ],
        ]);
    }
}
