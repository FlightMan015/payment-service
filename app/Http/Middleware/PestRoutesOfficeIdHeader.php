<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Constants\HttpHeader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PestRoutesOfficeIdHeader
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $errorMessage = '';
        $officeId = $request->header(HttpHeader::APTIVE_PESTROUTES_OFFICE_ID);

        if (empty($officeId)) {
            $errorMessage = 'Office id is required';
        } elseif (!is_numeric($officeId)) {
            $errorMessage = 'Office id is not integer';
        }

        if (!empty($errorMessage)) {
            return response(['errors' => ['status' => Response::HTTP_BAD_REQUEST, 'title' => $errorMessage]], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
