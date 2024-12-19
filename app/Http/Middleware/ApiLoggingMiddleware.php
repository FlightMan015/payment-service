<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Helpers\ApiLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiLoggingMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        ApiLogger::logRequest($request->path(), $request);

        return $next($request);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        ApiLogger::logResponse(
            $request->path(),
            $response->headers->all(),
            $response->getContent(),
            $response->getStatusCode()
        );
    }
}
