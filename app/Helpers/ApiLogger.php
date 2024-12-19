<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiLogger
{
    /**
     * @param string $apiMethod
     * @param Request $request
     *
     * @return void
     */
    public static function logRequest(string $apiMethod, Request $request): void
    {
        Log::info("payment-service.request $apiMethod", [
            'HTTP Method' => $request->method(),
            'Headers' => $request->header(),
            'URI' => $request->url(),
            'Body' => $request->post(),
            'Query params' => $request->query(),
        ]);
    }

    /**
     * @param string $apiMethod
     * @param iterable $headers
     * @param mixed $body
     * @param int $statusCode
     *
     * @return void
     */
    public static function logResponse(string $apiMethod, iterable $headers, mixed $body, int $statusCode): void
    {
        Log::log(
            self::getLogLevelFromStatusCode($statusCode),
            "payment-service.response $apiMethod",
            [
                'Headers' => $headers,
                'Body' => $body,
                'HTTP Status Code' => $statusCode,
            ]
        );
    }

    private static function getLogLevelFromStatusCode(int $statusCode): string
    {
        if ($statusCode >= 400 && $statusCode < 500) {
            return 'notice';
        }

        if ($statusCode >= 500) {
            return 'error';
        }

        return 'info';
    }
}
