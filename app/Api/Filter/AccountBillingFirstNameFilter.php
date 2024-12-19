<?php

declare(strict_types=1);

namespace App\Api\Filter;

use App\Models\Payment;
use Aptive\Illuminate\Filter\CommonFilters\FilterInterface;
use Aptive\Illuminate\Filter\CommonFilters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

class AccountBillingFirstNameFilter extends QueryFilter implements FilterInterface
{
    /**
     * Filter key to put in model's $filters array
     */
    public const string FILTER_KEY = 'account_billing_first_name';

    /**
     * @param Builder<Payment> $query
     * @param array $filterValues
     *
     * @return void
     */
    public function buildQuery(Builder $query, array $filterValues): void
    {
        $query->whereHas(
            'account.billingContact',
            static function ($query) use ($filterValues) {
                $query->where(
                    column: 'first_name',
                    operator: 'ilike',
                    value: '%' . $filterValues[0] . '%'
                );
            }
        );
    }
}
