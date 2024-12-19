<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\PestRoutes;

use App\Constants\PestroutesOfficeIds;
use App\Infrastructure\PestRoutes\PestRoutesDataRetrieverService;
use Aptive\PestRoutesSDK\Client;
use Aptive\PestRoutesSDK\Filters\NumberFilter;
use Aptive\PestRoutesSDK\Resources\Customers\CustomerAutoPay;
use Aptive\PestRoutesSDK\Resources\Customers\CustomersResource;
use Aptive\PestRoutesSDK\Resources\Customers\Params\SearchCustomersParams;
use Aptive\PestRoutesSDK\Resources\Offices\OfficesResource;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfilesResource;
use Aptive\PestRoutesSDK\Resources\Tickets\Params\SearchTicketsParams;
use Aptive\PestRoutesSDK\Resources\Tickets\TicketActiveStatus;
use Aptive\PestRoutesSDK\Resources\Tickets\TicketsResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\CustomerResponses;
use Tests\Stubs\PaymentProfileResponses;
use Tests\Stubs\TicketResponses;
use Tests\Unit\UnitTestCase;

class PestRoutesDataRetrieverServiceTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('paginatorDataProvider')]
    public function it_retrieves_customers_as_expected_and_returns_collection(
        int|null $page,
        int|null $perPage,
        int $expectedPage,
        int $expectedPerPage,
    ): void {
        $officeId = 1;

        $customers = CustomerResponses::getCollection(quantity: $perPage, totalQuantity: $perPage * $page);

        $expectedParams = new SearchCustomersParams(
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

        $pestRoutesClient = $this->createMock(Client::class);
        $officesResource = $this->createMock(OfficesResource::class);

        $customersResource = $this->createMock(CustomersResource::class);
        $officesResource->method('customers')->willReturn($customersResource);

        $pestRoutesClient->method('office')->willReturn($officesResource);

        $customersResource->expects($this->once())->method('search')->with($expectedParams)->willReturnSelf();
        $customersResource->expects($this->once())->method('paginate')->with($expectedPage, $expectedPerPage)->willReturn($customers);

        $dataRetriever = new PestRoutesDataRetrieverService($pestRoutesClient);

        $result = is_null($page) && is_null($perPage)
            ? $dataRetriever->getCustomersWithUnpaidBalance(officeId: $officeId)
            : $dataRetriever->getCustomersWithUnpaidBalance(officeId: $officeId, page: $page, quantity: $perPage);

        $this->assertEquals($customers, $result);
    }

    #[Test]
    public function it_retrieves_payment_profiles_by_their_ids_as_expected(): void
    {
        $paymentProfileIds = [1, 2, 3];
        $paymentProfiles = PaymentProfileResponses::getCollection($paymentProfileIds);

        $pestRoutesClient = $this->createMock(Client::class);
        $officesResource = $this->createMock(OfficesResource::class);

        $paymentProfilesResource = $this->createMock(PaymentProfilesResource::class);
        $officesResource->method('paymentProfiles')->willReturn($paymentProfilesResource);
        $paymentProfilesResource->expects($this->once())->method('getByIds')->with($paymentProfileIds)->willReturn($paymentProfiles);

        $pestRoutesClient->method('office')->willReturn($officesResource);

        $dataRetriever = new PestRoutesDataRetrieverService($pestRoutesClient);
        $result = $dataRetriever->getPaymentProfiles($paymentProfileIds);

        $this->assertEquals($paymentProfiles, $result);
    }

    #[Test]
    public function it_retrieves_tickets_by_customer_ids_as_expected(): void
    {
        // Given
        $tickets = TicketResponses::getTicketCollection(3);
        $customerIds = [];
        foreach ($tickets as $ticket) {
            $customerIds[] = $ticket->customerId;
        }
        $searchParams = new SearchTicketsParams(
            status: TicketActiveStatus::ACTIVE,
            customerIds: $customerIds,
            officeIds: PestroutesOfficeIds::ALL_OFFICES_ID,
        );
        $pestRoutesClient = $this->createMock(Client::class);
        $officesResource = $this->createMock(OfficesResource::class);
        $ticketsResource = $this->createMock(TicketsResource::class);

        $ticketsResource->expects($this->once())
            ->method('search')
            ->with($searchParams)
            ->willReturnSelf();
        $ticketsResource->expects($this->once())->method('paginate')->with($this->anything(), $this->anything())->willReturn($tickets);
        $officesResource->method('tickets')->willReturn($ticketsResource);
        $pestRoutesClient->method('office')->willReturn($officesResource);

        // When
        $dataRetriever = new PestRoutesDataRetrieverService($pestRoutesClient);
        $result = $dataRetriever->getTicketsByCustomerIds($customerIds);

        // Then
        $this->assertEquals($tickets, $result);
    }

    public static function paginatorDataProvider(): iterable
    {
        yield 'page 2, perPage 20' => [2, 20, 2, 20];
        yield 'default page and per Page' => [null, null, 1, 100];
    }
}
