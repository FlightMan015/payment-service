<?php

declare(strict_types=1);

namespace Tests\Stubs;

class AppointmentResponses
{
    public static function getAppointment(): object
    {
        return (object)[
            'appointmentID' => '21898362',
            'officeID' => '1',
            'customerID' => '2504324',
            'subscriptionID' => '-1',
            'subscriptionRegionID' => '-1',
            'routeID' => '3475584',
            'spotID' => '67774611',
            'date' => '2022-03-01',
            'start' => '13:00:00',
            'end' => '17:00:00',
            'duration' => '30',
            'type' => '2309',
            'dateAdded' => '2022-02-28 09:50:07',
            'employeeID' => '403353',
            'status' => '-1',
            'statusText' => 'Cancelled',
            'callAhead' => '30',
            'isInitial' => '0',
            'subscriptionPreferredTech' => '-1',
            'completedBy' => null,
            'servicedBy' => null,
            'dateCompleted' => null,
            'notes' => null,
            'officeNotes' => null,
            'timeIn' => '2022-03-01 13:00:00',
            'timeOut' => '2022-03-01 17:00:00',
            'checkIn' => '2022-03-01 13:00:00',
            'checkOut' => '2022-03-01 17:00:00',
            'windSpeed' => null,
            'windDirection' => null,
            'temperature' => null,
            'amountCollected' => null,
            'paymentMethod' => null,
            'servicedInterior' => null,
            'ticketID' => '21553970',
            'dateCancelled' => '2022-03-01 09:19:22',
            'additionalTechs' => null,
            'appointmentNotes' => '',
            'doInterior' => '2',
            'dateUpdated' => '2022-03-02 23:52:17',
            'cancelledBy' => '272806',
            'assignedTech' => '0',
            'latIn' => null,
            'latOut' => null,
            'longIn' => null,
            'longOut' => null,
            'sequence' => '15',
            'lockedBy' => '0',
            'unitIDs' => [],
        ];
    }
}
