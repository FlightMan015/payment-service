<?php

declare(strict_types=1);

namespace App\Api\Responses;

use Aptive\Component\Http\HttpStatus;
use Illuminate\Http\JsonResponse;

final class AcceptedSuccessResponse extends JsonResponse
{
    /**
     * @param string $message
     * @param string $selfLink
     *
     * @return self
     */
    public static function create(string $message, string $selfLink): self
    {
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
                ]
            ],
            status: HttpStatus::ACCEPTED,
        );
    }
}
