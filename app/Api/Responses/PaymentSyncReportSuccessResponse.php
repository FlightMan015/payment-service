<?php

declare(strict_types=1);

namespace App\Api\Responses;

use Aptive\Component\Http\HttpStatus;
use Illuminate\Http\JsonResponse;

final class PaymentSyncReportSuccessResponse extends JsonResponse
{
    /**
     * @param string $message
     *
     * @return self
     */
    public static function create(string $message): self
    {
        return new self(
            data: [
                '_metadata' => [
                    'success' => true,
                ],
                'result' => [
                    'message' => $message,
                ]
            ],
            status: HttpStatus::OK,
        );
    }
}
