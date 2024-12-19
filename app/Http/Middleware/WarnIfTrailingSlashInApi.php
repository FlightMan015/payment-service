<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Aptive\Component\Http\Exceptions\BadRequestHttpException;
use Illuminate\Http\Request;

class WarnIfTrailingSlashInApi
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     *
     * @throws BadRequestHttpException
     *
     * @return mixed
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        if (preg_match('/^\/api\/(.+\/)?$/i', $request->getPathInfo())) {
            throw new BadRequestHttpException(message: __('messages.error.trailing_slash_not_allowed'));
        }

        return $next($request);
    }
}
