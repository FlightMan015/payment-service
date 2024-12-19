<?php

declare(strict_types=1);

namespace App\Api\Responses;

use App\Api\Requests\FilterRequest;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

final class GetMultipleSuccessResponse extends JsonResponse
{
    /**
     * @param LengthAwarePaginator<mixed> $paginator
     * @param FilterRequest $request
     *
     * @return self
     */
    public static function create(LengthAwarePaginator $paginator, FilterRequest $request): self
    {
        return new self(
            data: [
                '_metadata' => [
                    'success' => true,
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_results' => $paginator->total(),
                    'links' => self::getLinks(paginator: $paginator, request: $request),
                ],
                'result' => $paginator->items(),
            ],
            status: HttpStatus::OK,
        );
    }

    /**
     * Method to get the paginated links from pagination and request
     *
     * For example:
     *  - $paginator = Model::select()->blah blah->paginate(100);
     *  - GetMultipleSuccessResponse::getLinks($paginator, $request);
     *
     * @param LengthAwarePaginator<Model> $paginator
     * @param FilterRequest $request
     *
     * @return array
     */
    public static function getLinks(LengthAwarePaginator $paginator, FilterRequest $request): array
    {
        return [
            'self' => $request->fullUrl(),
            'first' => $paginator->withQueryString()->url(1),
            'previous' => $paginator->withQueryString()->previousPageUrl(),
            'next' => $paginator->withQueryString()->nextPageUrl(),
            'last' => $paginator->withQueryString()->url($paginator->lastPage()),
        ];
    }
}
