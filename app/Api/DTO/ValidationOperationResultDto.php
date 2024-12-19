<?php

declare(strict_types=1);

namespace App\Api\DTO;

class ValidationOperationResultDto
{
    /**
     * @param bool $isValid
     * @param string|null $errorMessage
     */
    public function __construct(public readonly bool $isValid, public readonly string|null $errorMessage = null)
    {
    }

    /**
     * @return array{is_valid: bool, message: string}
     */
    public function toArray(): array
    {
        // by using array_filter, we won't return a message in the array if there is no error
        return array_filter(array: [
            'is_valid' => $this->isValid,
            'message' => $this->isValid && is_null($this->errorMessage) ? null : __('messages.operation.gateway_response', ['message' => $this->errorMessage]),
        ], callback: static fn (mixed $value) => !is_null($value));
    }
}
