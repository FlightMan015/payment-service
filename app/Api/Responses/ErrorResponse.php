<?php

declare(strict_types=1);

namespace App\Api\Responses;

use Illuminate\Http\JsonResponse;

class ErrorResponse extends JsonResponse
{
    /**
     * @param \Throwable $exception
     * @param int $status
     * @param string|null $errorMessage
     * @param array $errors
     *
     * @return self
     */
    public static function fromException(
        \Throwable $exception,
        int $status,
        string|null $errorMessage = null,
        array $errors = []
    ): self {
        $responseData = ['message' => $errorMessage ?? $exception->getMessage()];

        if (!empty($errors)) {
            $responseData['errors'] = $errors;
        }

        return new ErrorResponse(data: [
            '_metadata' => ['success' => false],
            'result' => $responseData,
        ], status: $status);
    }
}
