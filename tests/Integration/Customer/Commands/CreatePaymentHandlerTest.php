<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Customer\Commands;

use Aptive\Component\Http\Exceptions\NotFoundHttpException;
use Aptive\PestRoutesSDK\Client;
use Aptive\PestRoutesSDK\Collection;
use Aptive\PestRoutesSDK\Exceptions\ResourceNotFoundException;
use Aptive\PestRoutesSDK\Resources\Appointments\Appointment;
use Aptive\PestRoutesSDK\Resources\Appointments\AppointmentsResource;
use Aptive\PestRoutesSDK\Resources\Customers\CustomerAutoPay;
use Aptive\PestRoutesSDK\Resources\Customers\CustomersResource;
use Aptive\PestRoutesSDK\Resources\Offices\OfficesResource;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfilesResource;
use Aptive\PestRoutesSDK\Resources\Payments\Params\CreatePaymentsParams;
use Aptive\PestRoutesSDK\Resources\Payments\PaymentMethod;
use Aptive\PestRoutesSDK\Resources\Payments\PaymentsResource;
use Aptive\PestRoutesSDK\Resources\Tickets\Ticket;
use Aptive\PestRoutesSDK\Resources\Tickets\TicketsResource;
use Customer\Api\Commands\CreatePaymentCommand;
use Customer\Api\Commands\CreatePaymentHandler;
use Customer\Api\Exceptions\AutoPayStatusException;
use Customer\Api\Exceptions\InvalidParametersException;
use Customer\Api\Exceptions\InvalidPaymentHoldDateException;
use Customer\Api\Exceptions\PaymentFailedException;
use Customer\Api\Exceptions\PestroutesAPIException;
use Customer\DataSources\PestRoutesAPIDataSource;
use DateTime;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\AppointmentResponses;
use Tests\Stubs\CustomerResponses;
use Tests\Stubs\PaymentProfileResponses;
use Tests\Stubs\TicketResponses;
use Tests\TestCase;

class CreatePaymentHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private CreatePaymentHandler|null $handler;
    /** @var MockInterface&OfficesResource $mockOfficesResource */
    private OfficesResource $mockOfficesResource;
    /** @var MockInterface&Client $mockPestRoutesClient */
    private Client $mockPestRoutesClient;
    /** @var MockInterface&PestRoutesAPIDataSource $mockDataSource */
    private PestRoutesAPIDataSource $mockDataSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockOfficesResource = Mockery::mock(OfficesResource::class);

        $this->mockPestRoutesClient = Mockery::mock(Client::class);
        $this->mockPestRoutesClient->allows('office')->andReturns($this->mockOfficesResource);

        $this->mockDataSource = Mockery::mock(PestRoutesAPIDataSource::class);
        $this->mockDataSource->allows('getAPIClient')->andReturns($this->mockPestRoutesClient);

        $this->handler = new CreatePaymentHandler($this->mockDataSource);
    }

    #[Test]
    public function it_throws_not_found_exception_when_payment_profile_status_invalid_(): void
    {
        $paymentProfilesResource = Mockery::mock(PaymentProfilesResource::class);
        $pestRoutesProfiles = PaymentProfileResponses::getProfile(
            paymentHoldDate: (new DateTime())->modify('+1 year')->format('Y-m-d H:i:s'),
            status: -1
        );
        $paymentProfilesResource->expects('find')
            ->withArgs(static fn (int $autoPayPaymentProfileId) => $autoPayPaymentProfileId === $pestRoutesProfiles->getItems()[0]->id)
            ->andReturns($pestRoutesProfiles->getItems()[0]);
        $this->mockOfficesResource
            ->expects('paymentProfiles')
            ->andReturns($paymentProfilesResource);

        $this->mockCustomerResource(autoPayPaymentProfileID: $pestRoutesProfiles->getItems()[0]->id);

        $this->expectException(NotFoundHttpException::class);

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    #[Test]
    public function it_selects_correct_autopay_payment_method_and_throws_invalid_hold_date_exception(): void
    {
        $paymentProfilesResource = Mockery::mock(PaymentProfilesResource::class);
        $pestRoutesProfiles = PaymentProfileResponses::getProfile(
            (new DateTime())->modify('+1 year')->format('Y-m-d H:i:s'),
        );
        $paymentProfilesResource->expects('find')
            ->withArgs(static fn (int $autoPayPaymentProfileId) => $autoPayPaymentProfileId === $pestRoutesProfiles->getItems()[0]->id)
            ->andReturns($pestRoutesProfiles->getItems()[0]);
        $this->mockOfficesResource
            ->expects('paymentProfiles')
            ->andReturns($paymentProfilesResource);

        $this->mockCustomerResource(autoPayPaymentProfileID: $pestRoutesProfiles->getItems()[0]->id);

        $this->expectException(InvalidPaymentHoldDateException::class);

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    #[Test]
    public function it_throws_exception_if_payment_hold_date_is_not_less_than_current_date(): void
    {
        $this->mockCustomerResource();
        $paymentProfilesResource = Mockery::mock(PaymentProfilesResource::class);
        $paymentProfilesResource->allows('find')
            ->andReturns(
                PaymentProfileResponses::getProfile(
                    (new DateTime())->modify('+1 year')->format('Y-m-d H:i:s')
                )->getItems()[0]
            );

        $this->mockOfficesResource
            ->allows('paymentProfiles')
            ->andReturns($paymentProfilesResource);

        $this->expectException(InvalidPaymentHoldDateException::class);

        $this->handler->handle($this->getPayoffOutstandingBalanceCommand());
    }

    #[Test]
    public function it_throws_exception_if_auto_pay_is_not_enabled_for_the_customer(): void
    {
        $this->mockCustomerResource(CustomerAutoPay::NotOnAutoPay);

        $this->expectException(AutoPayStatusException::class);

        $this->handler->handle($this->getPayoffOutstandingBalanceCommand());
    }

    #[Test]
    public function it_throws_exception_if_payoff_outstanding_balance_true_and_amount_exist(): void
    {
        $command = new CreatePaymentCommand(
            1,
            8332,
            true,
            22400029,
            0.4
        );

        $this->expectException(InvalidParametersException::class);
        $this->expectExceptionMessage("Given not expected variable 'amount'");

        $this->handler->handle($command);
    }

    #[Test]
    public function it_throws_exception_if_appointment_id_was_not_provided(): void
    {
        $command = new CreatePaymentCommand(
            1,
            8332,
            false,
            null,
            10.0
        );

        $this->expectException(InvalidParametersException::class);
        $this->expectExceptionMessage('Appointment ID is required');

        $this->handler->handle($command);
    }

    #[Test]
    public function it_throws_exception_if_there_no_payment_profile(): void
    {
        $this->mockCustomerResource();
        $paymentProfilesResource = Mockery::mock(PaymentProfilesResource::class);
        $paymentProfilesResource->allows('find')
            ->andThrow(ResourceNotFoundException::class);

        $this->mockOfficesResource->allows('paymentProfiles')
            ->andReturns($paymentProfilesResource);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(PestroutesAPIException::PAYMENT_PROFILE_NOT_FOUND);

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    #[Test]
    public function it_throws_exception_if_customer_auto_pay_payment_profile_id_is_null(): void
    {
        $this->mockCustomerResource(autoPayPaymentProfileID: null);
        $paymentProfilesResource = Mockery::mock(PaymentProfilesResource::class);
        $paymentProfilesResource->allows('find')
            ->andThrow(ResourceNotFoundException::class);

        $this->mockOfficesResource->allows('paymentProfiles')
            ->andReturns($paymentProfilesResource);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(PestroutesAPIException::PAYMENT_PROFILE_NOT_FOUND);

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    #[Test]
    public function it_throws_exception_when_payment_create_failed(): void
    {
        $this->mockPaymentProfileResource();
        $this->mockCustomerResource();

        $ticketResource = Mockery::mock(TicketsResource::class);
        $ticketResource->allows('search')->andReturnSelf();
        $ticketResource->expects('all')
            ->andReturns(TicketResponses::getTicketCollection());
        $this->mockOfficesResource
            ->expects('tickets')
            ->andReturns($ticketResource);

        $paymentResource = Mockery::mock(PaymentsResource::class);
        $paymentResource->allows('create')
            ->andThrow(new \Aptive\PestRoutesSDK\Exceptions\PestRoutesApiException('Payment failed'));

        $this->mockOfficesResource
            ->expects('payments')
            ->andReturns($paymentResource);

        Log::shouldReceive('info')->once();
        $this->expectException(PaymentFailedException::class);

        $this->handler->handle($this->getPayoffOutstandingBalanceCommand());
    }

    #[Test]
    public function it_processes_total_debt_billing(): void
    {
        $this->mockPaymentProfileResource();
        $this->mockCustomerResource();

        $ticketResource = Mockery::mock(TicketsResource::class);
        $ticketResource->allows('search')->andReturnSelf();
        $ticketResource->expects('all')
            ->andReturns(TicketResponses::getTicketCollection());
        $this->mockOfficesResource
            ->expects('tickets')
            ->andReturns($ticketResource);

        $paymentResource = Mockery::mock(PaymentsResource::class);
        $paymentResource->expects('create')
            ->withArgs(static function (CreatePaymentsParams $params) {
                return
                    $params->toArray()['amount'] === 13.0;
            });

        $this->mockOfficesResource
            ->expects('payments')
            ->andReturns($paymentResource);

        Log::shouldReceive('info')->once();

        $this->handler->handle($this->getPayoffOutstandingBalanceCommand());
    }

    #[Test]
    public function it_does_not_processes_payment_when_total_amount_is_zero(): void
    {
        $this->mockPaymentProfileResource();
        $this->mockCustomerResource();

        $ticket = Ticket::fromApiObject(TicketResponses::getTicketsArray()[0]);
        $ticketCollection = new Collection(items: [$ticket], total: 1);
        $ticketResource = Mockery::mock(TicketsResource::class);
        $ticketResource->allows('search')->andReturnSelf();
        $ticketResource->expects('all')
            ->andReturns($ticketCollection);
        $this->mockOfficesResource
            ->expects('tickets')
            ->andReturns($ticketResource);

        $this->mockOfficesResource
            ->allows('payments')
            ->never();

        Log::shouldReceive('info')->once();

        $this->handler->handle($this->getPayoffOutstandingBalanceCommand());
    }

    #[Test]
    public function it_handles_credit_card_payment_method(): void
    {
        $this->mockPaymentProfileResource();
        $this->mockCustomerResource();
        $this->mockAppointmentResource();
        $this->mockTicketResource();

        $paymentResource = Mockery::mock(PaymentsResource::class);
        $paymentResource->expects('create')
            ->withArgs(static fn (CreatePaymentsParams $params) => $params->toArray()['paymentMethod'] === (PaymentMethod::CreditCard)->value);

        $this->mockOfficesResource
            ->expects('payments')
            ->andReturns($paymentResource);

        Log::shouldReceive('info')->once();

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    #[Test]
    public function it_handles_ach_payment_method(): void
    {
        $this->mockPaymentProfileResource(2);
        $this->mockCustomerResource();
        $this->mockAppointmentResource();
        $this->mockTicketResource();

        $paymentResource = Mockery::mock(PaymentsResource::class);
        $paymentResource->expects('create')
            ->withArgs(static fn (CreatePaymentsParams $params) => $params->toArray()['paymentMethod'] === (PaymentMethod::ACH)->value);

        $this->mockOfficesResource
            ->expects('payments')
            ->andReturns($paymentResource);

        Log::shouldReceive('info')->once();

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    #[Test]
    public function it_throws_exception_if_there_no_payment_ticket_for_appointment(): void
    {
        $this->mockPaymentProfileResource();
        $this->mockCustomerResource();
        $this->mockAppointmentResource();

        $ticketResource = Mockery::mock(TicketsResource::class);
        $ticketResource->allows('search')->andReturnSelf();
        $ticketResource->expects('all')
            ->andReturns(TicketResponses::getEmptyTicketCollection());

        $this->mockOfficesResource
            ->expects('tickets')
            ->andReturns($ticketResource);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(PestroutesAPIException::INVOICE_FOR_THIS_APPOINTMENT_NOT_FOUND);

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    #[Test]
    public function it_throws_exception_if_there_no_customer(): void
    {
        $customerResource = Mockery::mock(CustomersResource::class);
        $customerResource->expects('find')->andThrow(ResourceNotFoundException::class);

        $this->mockOfficesResource->expects('customers')->andReturns($customerResource);

        $this->expectException(NotFoundHttpException::class);

        $this->handler->handle($this->getAppointmentPaymentCommand());
    }

    private function mockPaymentProfileResource(int $paymentMethod = 1): void
    {
        $paymentProfilesResource = Mockery::mock(PaymentProfilesResource::class);
        $paymentProfilesResource->expects('find')
            ->andReturns(PaymentProfileResponses::getProfile(paymentMethod: $paymentMethod)->getItems()[0]);

        $this->mockOfficesResource
            ->expects('paymentProfiles')
            ->andReturns($paymentProfilesResource);
    }

    private function mockCustomerResource(CustomerAutoPay $apay = CustomerAutoPay::AutoPayCC, int|null $autoPayPaymentProfileID = 5052262): void
    {
        $customerResource = Mockery::mock(CustomersResource::class);
        $pestRoutesCustomer = CustomerResponses::getCustomer($apay, $autoPayPaymentProfileID);
        $customerResource->expects('find')->andReturns($pestRoutesCustomer);

        $this->mockOfficesResource->expects('customers')->andReturns($customerResource);
    }

    private function mockAppointmentResource(): void
    {
        $appointmentResource = Mockery::mock(AppointmentsResource::class);
        $appointmentObject = AppointmentResponses::getAppointment();
        $appointmentObject->officeTimeZone = 'America/Los_Angeles';
        $appointment = Appointment::fromApiObject($appointmentObject);
        $appointmentResource->expects('find')
            ->andReturns($appointment);

        $this->mockOfficesResource
            ->expects('appointments')
            ->andReturns($appointmentResource);
    }

    private function mockTicketResource(): void
    {
        $ticketResource = Mockery::mock(TicketsResource::class);
        $ticketResource->allows('search')->andReturnSelf();
        $ticketResource->expects('all')
            ->andReturns(TicketResponses::getTicketCollection());

        $this->mockOfficesResource
            ->expects('tickets')
            ->andReturns($ticketResource);
    }

    private function getPayoffOutstandingBalanceCommand(): CreatePaymentCommand
    {
        return new CreatePaymentCommand(
            1,
            8853,
            true,
            null,
            null
        );
    }

    private function getAppointmentPaymentCommand(): CreatePaymentCommand
    {
        return new CreatePaymentCommand(
            1,
            8853,
            false,
            16384,
            10.0
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->handler, $this->mockDataSource, $this->mockOfficesResource, $this->mockPestRoutesClient);
    }
}
