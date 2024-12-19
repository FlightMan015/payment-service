<?php

declare(strict_types=1);

namespace App\Infrastructure\PestRoutes;

use App\Constants\PestroutesOfficeIds;
use Aptive\Component\Http\Exceptions\InternalServerErrorHttpException;
use Aptive\PestRoutesSDK\Client;
use Aptive\PestRoutesSDK\Collection;
use Aptive\PestRoutesSDK\Filters\NumberFilter;
use Aptive\PestRoutesSDK\Resources\Customers\Customer;
use Aptive\PestRoutesSDK\Resources\Customers\CustomerAutoPay;
use Aptive\PestRoutesSDK\Resources\Customers\Params\SearchCustomersParams;
use Aptive\PestRoutesSDK\Resources\Tickets\Params\SearchTicketsParams;
use Aptive\PestRoutesSDK\Resources\Tickets\Ticket;
use Aptive\PestRoutesSDK\Resources\Tickets\TicketActiveStatus;

class PestRoutesDataRetrieverService
{
    /**
     * @param Client $client
     */
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param int $officeId
     * @param int $page
     * @param int $quantity
     *
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     *
     * @return Collection<Customer>
     */
    public function getCustomersWithUnpaidBalance(int $officeId, int $page = 1, int $quantity = 100): Collection
    {
        $searchParams = new SearchCustomersParams(
            officeIds: $officeId,
            autoPay: NumberFilter::in(values: [
                CustomerAutoPay::AutoPayCC->numericValue(),
                CustomerAutoPay::AutoPayACH->numericValue()
            ]),
            includeCancellationReason: false,
            includeSubscriptions: false,
            includeCustomerFlag: false,
            includeAdditionalContacts: false,
            includePortalLogin: false,
            responsibleBalance: NumberFilter::greaterThan(value: 0)
        );

        return $this->client->office(officeId: $officeId)
            ->customers()
            ->search(params: $searchParams)
            ->paginate(page: $page, perPage: $quantity);
    }

    /**
     * @param array $ids
     *
     * @throws InternalServerErrorHttpException
     *
     * @return Collection
     */
    public function getPaymentProfiles(array $ids): Collection
    {
        return $this->client->office(PestroutesOfficeIds::ALL_OFFICES_ID)->paymentProfiles()->getByIds(ids: $ids);
    }

    /**
     * @param array $customerIds
     * @param int $page
     * @param int $quantity
     *
     * @throws InternalServerErrorHttpException
     * @throws \JsonException
     *
     * @return Collection<Ticket>
     */
    public function getTicketsByCustomerIds(
        array $customerIds,
        int $page = 1,
        int $quantity = 1000
    ): Collection {
        $searchParams = new SearchTicketsParams(
            status: TicketActiveStatus::ACTIVE,
            customerIds: $customerIds,
            officeIds: PestroutesOfficeIds::ALL_OFFICES_ID,
        );

        return $this->client->office(officeId: PestroutesOfficeIds::ALL_OFFICES_ID)
            ->tickets()
            ->search(params: $searchParams)
            ->paginate(page: $page, perPage: $quantity);
    }
}
