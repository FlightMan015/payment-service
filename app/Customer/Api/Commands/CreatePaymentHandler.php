<?php

declare(strict_types=1);

namespace Customer\Api\Commands;

use App\Models\OldPayment;
use Aptive\Component\Http\Exceptions\InternalServerErrorHttpException;
use Aptive\Component\Http\Exceptions\NotFoundHttpException;
use Aptive\PestRoutesSDK\Client;
use Aptive\PestRoutesSDK\Exceptions\PestRoutesApiException;
use Aptive\PestRoutesSDK\Exceptions\ResourceNotFoundException;
use Aptive\PestRoutesSDK\Filters\NumberFilter;
use Aptive\PestRoutesSDK\Resources\Appointments\Appointment;
use Aptive\PestRoutesSDK\Resources\Customers\Customer;
use Aptive\PestRoutesSDK\Resources\Customers\CustomerAutoPay;
use Aptive\PestRoutesSDK\Resources\Customers\Params\FindCustomersParams;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfile;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfileStatus;
use Aptive\PestRoutesSDK\Resources\Payments\Params\CreatePaymentsParams;
use Aptive\PestRoutesSDK\Resources\Payments\PaymentMethod;
use Aptive\PestRoutesSDK\Resources\Tickets\Params\SearchTicketsParams;
use Aptive\PestRoutesSDK\Resources\Tickets\Ticket;
use Aptive\PestRoutesSDK\Resources\Tickets\TicketActiveStatus;
use Customer\Api\Exceptions\AutoPayStatusException;
use Customer\Api\Exceptions\InvalidParametersException;
use Customer\Api\Exceptions\InvalidPaymentHoldDateException;
use Customer\Api\Exceptions\PaymentFailedException;
use Customer\Api\Exceptions\PestroutesAPIException as ApplicationPestroutesAPIException;
use Customer\DataSources\PestRoutesAPIDataSource;
use Illuminate\Support\Facades\Log;

class CreatePaymentHandler
{
    private CreatePaymentCommand|null $command = null;
    private Client|null $client = null;
    private PaymentProfile|null $paymentProfile = null;
    private Appointment|null $appointmentDetails = null;
    private OldPayment|null $payment = null;
    private Customer|null $customer = null;

    /**
     * @param PestRoutesAPIDataSource $dataSource
     */
    public function __construct(private PestRoutesAPIDataSource $dataSource)
    {
    }

    /**
     * @param CreatePaymentCommand $command
     *
     * @throws InvalidParametersException
     * @throws InvalidPaymentHoldDateException
     * @throws NotFoundHttpException
     * @throws PaymentFailedException
     * @throws InternalServerErrorHttpException
     * @throws AutoPayStatusException
     *
     * @return void
     */
    public function handle(CreatePaymentCommand $command): void
    {
        $this->command = $command;

        if ($this->payoffOutstandingBalance() && $this->command->amount !== null) {
            throw new InvalidParametersException("Given not expected variable 'amount'");
        }

        if (!$this->payoffOutstandingBalance() && empty($this->command->appointmentId)) {
            throw new InvalidParametersException('Appointment ID is required');
        }

        $this->client = $this->dataSource->getAPIClient();

        $this->validateCustomerAutoPayStatus();
        $this->getAutoPayPaymentProfile();
        $this->validatePaymentHoldDate();

        if (!$this->payoffOutstandingBalance()) {
            $this->getAppointmentTicketDetails();
        }

        $this->createPayment(
            $this->getPaymentMethodFromInt($this->paymentProfile->paymentMethod->value),
            $this->command->customerId,
            $this->getPaymentAmount(),
            $this->appointmentDetails?->ticketId
        );
    }

    private function payoffOutstandingBalance(): bool
    {
        return $this->command->payoffOutstandingBalance === true;
    }

    private function getPaymentAmount(): float
    {
        return $this->payoffOutstandingBalance()
            ? $this->getTotalDebt()
            : (is_null($this->command->amount) ? 0.0 : $this->command->amount);
    }

    private function logPaymentDetails(float $paymentAmount): void
    {
        $message = $this->payoffOutstandingBalance()
            ? 'Attempting payoff outstanding balance'
            : 'Attempting process appointment payment';

        Log::info($message, [
            'customer_id' => $this->command->customerId,
            'appointment_id' => $this->command->appointmentId,
            'amount' => $paymentAmount,
            'payment_method' => $this->paymentProfile->paymentMethod->name,
        ]);
    }

    private function validateCustomerAutoPayStatus(): void
    {
        try {
            $this->customer = $this->client->office($this->command->officeId)
                ->customers()->find($this->command->customerId, new FindCustomersParams());
        } catch (ResourceNotFoundException) {
            throw new NotFoundHttpException(sprintf(
                'Customer with id [%d] was not found.',
                $this->command->customerId
            ));
        }

        if ($this->customer->autoPay === CustomerAutoPay::NotOnAutoPay) {
            throw new AutoPayStatusException('Unable to process payment for customer_id: ' . $this->customer->id .  '. Auto pay status disallows payment', $this->customer->autoPay->numericValue());
        }
    }

    /**
     * @throws NotFoundHttpException
     * @throws InternalServerErrorHttpException
     */
    private function getAutoPayPaymentProfile(): void
    {
        if (is_null($this->customer->autoPayPaymentProfileId)) {
            throw new NotFoundHttpException(ApplicationPestroutesAPIException::PAYMENT_PROFILE_NOT_FOUND);
        }

        try {
            $this->paymentProfile = $this->client
                ->office($this->command->officeId)
                ->paymentProfiles()
                ->find($this->customer->autoPayPaymentProfileId);
        } catch (ResourceNotFoundException $ex) {
            throw new NotFoundHttpException(ApplicationPestroutesAPIException::PAYMENT_PROFILE_NOT_FOUND);
        }

        if ($this->paymentProfile->status !== PaymentProfileStatus::Valid) {
            throw new NotFoundHttpException('Payment Profile with active status was not found.');
        }
    }

    private function getAppointmentTicketDetails(): void
    {
        try {
            $this->appointmentDetails = $this->client->office($this->command->officeId)
                ->appointments()->find(id: $this->command->appointmentId);
        } catch (ResourceNotFoundException) {
            throw new NotFoundHttpException(sprintf(
                'Appointment with id [%d] was not found.',
                $this->command->appointmentId
            ));
        }

        if (
            !empty($this->appointmentDetails->ticketId)
            && !empty($this->getTicketById($this->appointmentDetails->ticketId))
        ) {
            return;
        }

        throw new NotFoundHttpException(ApplicationPestroutesAPIException::INVOICE_FOR_THIS_APPOINTMENT_NOT_FOUND);
    }

    private function validatePaymentHoldDate(): void
    {
        $paymentHoldDate = $this->paymentProfile->paymentHoldDate?->format('Y-m-d');
        $currentDate = strtotime('today UTC');

        if (
            $paymentHoldDate
            && strtotime($paymentHoldDate) >= strtotime('today UTC')
        ) {
            throw new InvalidPaymentHoldDateException(sprintf(
                'Payment hold date [%s] must be less than current date [%s]. Payment profile ID: [%d]. Payment method: [%s]',
                $paymentHoldDate,
                date('Y-m-d', $currentDate),
                $this->paymentProfile->id,
                $this->paymentProfile->paymentMethod->name
            ));
        }
    }

    private function getTotalDebt(): float
    {
        $status = TicketActiveStatus::ACTIVE;
        $balance = NumberFilter::greaterThan(0);
        $params = new SearchTicketsParams(status: $status, customerIds: [$this->command->customerId], balance: $balance);
        $tickets = $this->client->office($this->command->officeId)->tickets()->search($params)->all()->items;

        $totalAmount = 0;
        foreach ($tickets as $ticket) {
            $totalAmount += $ticket->balance;
        }

        return $totalAmount;
    }

    private function getPaymentMethodFromInt(int $profilePaymentMethod): PaymentMethod
    {
        // $paymentMethod 1 - cc, $paymentMethod 2 - ach
        if ($profilePaymentMethod === 1) {
            return PaymentMethod::CreditCard;
        }

        return PaymentMethod::ACH;
    }

    private function createPayment(PaymentMethod $paymentMethod, int $customerId, float $amount, int|null $ticketId = null): void
    {
        $this->logPaymentDetails($amount);
        $this->savePaymentData($amount);

        if ($amount <= 0.0) {
            return;
        }

        $params = new CreatePaymentsParams($paymentMethod, $customerId, $amount, true, $ticketId);

        try {
            $this->client->office($this->command->officeId)->payments()->create($params);
            $this->paymentSucceeded();
        } catch (PestRoutesApiException $exception) {
            $this->paymentFailed($exception->getMessage());
            throw new PaymentFailedException($exception->getMessage());
        }
    }

    private function savePaymentData(float $amount): void
    {
        $this->payment = new OldPayment([
            'appointment_id' => $this->command->appointmentId,
            'ticket_id' => $this->appointmentDetails?->ticketId,
            'amount' => $amount,
            'request_origin' => $this->command->requestOrigin,
        ]);
        $this->payment->save();
    }

    private function paymentFailed(string $message): void
    {
        $this->payment->update([
            'service_response' => $message,
        ]);
    }

    private function paymentSucceeded(): void
    {
        $this->payment->update([
            'success' => true,
        ]);
    }

    private function getTicketById(int $ticketId): Ticket|null
    {
        $status = TicketActiveStatus::ACTIVE;
        $params = new SearchTicketsParams(status: $status, ids: [$ticketId]);
        $tickets = $this->client->office($this->command->officeId)->tickets()->search($params)->all()->items;

        return empty($tickets) ? null : end($tickets);
    }
}
