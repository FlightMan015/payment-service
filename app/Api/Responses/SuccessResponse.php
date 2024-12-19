<?php

declare(strict_types=1);

namespace App\Api\Responses;

use Aptive\Component\Http\HttpStatus;
use Illuminate\Http\JsonResponse;

final class SuccessResponse extends JsonResponse
{
    /**
     * @param string $message
     * @param string $selfLink
     * @param int $statusCode
     * @param array $additionalData
     *
     * @return self
     */
    public static function create(
        string $message,
        string $selfLink,
        int $statusCode = HttpStatus::OK,
        array $additionalData = []
    ): self {
        return new self(
            data: [
                '_metadata' => [
                    'success' => true,
                    'links' => [
                        'self' => $selfLink,
                    ],
                ],
                'result' => [
                    'message' => $message,
                    ...$additionalData,
                ]
            ],
            status: $statusCode,
        );
    }
}
