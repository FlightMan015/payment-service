<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Traits;

use App\Api\Traits\SortableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class SortableTraitTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('sortDataProvider')]
    public function sort(
        array $filter,
        string|null $defaultSort,
        string|null $defaultSortDirection,
        string $expectedQuery
    ): void {
        $repository = new class ($defaultSort, $defaultSortDirection, $this->app) {
            use SortableTrait;

            protected array $allowedSorts = [
                'id',
                'name',
                'amount',
            ];

            public function __construct(
                private readonly string|null $defaultSort,
                private readonly string|null $defaultSortDirection,
                private Application $app
            ) {
            }

            /**
             * @param array $filter
             *
             * @return Builder<Model>
             */
            public function methodAppliesSorting(array $filter = []): Builder
            {
                $query = new Builder(new QueryBuilder($this->app['db']->connection(), $this->app['db']->getQueryGrammar(), $this->app['db']->getPostProcessor()));
                $query->select('id')->from('table');
                return $this->sort($query, $filter);
            }

        };

        $this->assertEquals($expectedQuery, $repository->methodAppliesSorting($filter)->toSql());
    }

    /**
     * @return \Iterator<array{filter: array, defaultSort: string|null, defaultSortDirection: string|null, expectedQuery: string}>
     */
    public static function sortDataProvider(): \Iterator
    {
        yield 'no sort provided default sort specified' => [
            'filter' => [],
            'defaultSort' => 'id',
            'defaultSortDirection' => 'asc',
            'expectedQuery' => 'select "id" from "table" order by ("id" is null) asc, "id" asc',
        ];

        yield 'no sort provided default sort not specified' => [
            'filter' => [],
            'defaultSort' => null,
            'defaultSortDirection' => null,
            'expectedQuery' => 'select "id" from "table" order by ("created_at" is null) asc, "created_at" asc',
        ];

        yield 'sort not in list default specified' => [
            'filter' => [
                'sort' => 'not_in_list',
            ],
            'defaultSort' => 'id',
            'defaultSortDirection' => 'asc',
            'expectedQuery' => 'select "id" from "table" order by ("id" is null) asc, "id" asc',
        ];

        yield 'sort not in list default not specified' => [
            'filter' => [
                'sort' => 'not_in_list',
            ],
            'defaultSort' => null,
            'defaultSortDirection' => null,
            'expectedQuery' => 'select "id" from "table" order by ("created_at" is null) asc, "created_at" asc',
        ];

        yield 'sort without direction' => [
            'filter' => [
                'sort' => 'id',
            ],
            'defaultSort' => null,
            'defaultSortDirection' => null,
            'expectedQuery' => 'select "id" from "table" order by ("id" is null) asc, "id" asc',
        ];

        yield 'sort with direction' => [
            'filter' => [
                'sort' => 'id',
                'direction' => 'desc',
            ],
            'defaultSort' => null,
            'defaultSortDirection' => null,
            'expectedQuery' => 'select "id" from "table" order by ("id" is null) asc, "id" desc',
        ];

        yield 'sort with direction uppercase' => [
            'filter' => [
                'sort' => 'id',
                'direction' => 'DESC',
            ],
            'defaultSort' => null,
            'defaultSortDirection' => null,
            'expectedQuery' => 'select "id" from "table" order by ("id" is null) asc, "id" desc',
        ];

        yield 'sort with table name included' => [
            'filter' => [
                'sort' => 'payments.amount',
                'direction' => 'DESC',
            ],
            'defaultSort' => null,
            'defaultSortDirection' => null,
            'expectedQuery' => 'select "id" from "table" order by ("payments"."amount" is null) asc, "payments"."amount" desc',
        ];
    }
}
