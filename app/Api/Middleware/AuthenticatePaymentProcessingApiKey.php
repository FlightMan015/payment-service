<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use Aptive\Component\Http\HttpStatus;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePaymentProcessingApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure(Request): (Response) $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasHeader(key: 'Api-Key')) {
            return new JsonResponse(data: [
                '_metadata' => ['success' => false],
                'result' => ['message' => __('auth.api_key_not_found')]
            ], status: HttpStatus::UNAUTHORIZED);
        }

        if ($request->header(key: 'Api-Key') !== config(key: 'auth.api_keys.payment_processing')) {
            return new JsonResponse(data: [
                '_metadata' => ['success' => false],
                'result' => ['message' => __('auth.invalid_api_key')]
            ], status: HttpStatus::UNAUTHORIZED);
        }

        return $next($request);
    }
}
