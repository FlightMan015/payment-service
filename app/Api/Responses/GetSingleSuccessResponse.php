<?php

declare(strict_types=1);

namespace App\Api\Responses;

use Aptive\Component\Http\HttpStatus;
use Illuminate\Http\JsonResponse;

final class GetSingleSuccessResponse extends JsonResponse
{
    /**
     * @param array|object $entity
     * @param string $selfLink
     *
     * @return self
     */
    public static function create(array|object $entity, string $selfLink): self
    {
        return new self(
            data: [
                '_metadata' => [
                    'success' => true,
                    'links' => [
                        'self' => $selfLink,
                    ],
                ],
                'result' => $entity,
            ],
            status: HttpStatus::OK,
        );
    }
}
