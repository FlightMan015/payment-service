<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Aptive\PestRoutesSDK\Collection;
use Aptive\PestRoutesSDK\Resources\Customers\Customer;
use Aptive\PestRoutesSDK\Resources\Customers\CustomerAutoPay;

class CustomerResponses
{
    /**
     * @param CustomerAutoPay $apay
     * @param int|null $autoPayPaymentProfileId
     * @param float $responsibleBalance
     * @param \DateTimeInterface|null $paymentHoldDate
     *
     * @throws \Exception
     *
     * @return Customer
     */
    public static function getCustomer(
        CustomerAutoPay $apay = CustomerAutoPay::AutoPayCC,
        int|null $autoPayPaymentProfileId = 5052262,
        float $responsibleBalance = 7.99,
        \DateTimeInterface|null $paymentHoldDate = new \DateTimeImmutable('2021-12-10 00:00:00')
    ): Customer {
        return Customer::fromApiObject(
            (object) [
                'customerID' => (string)random_int(100000, 999999),
                'billToAccountID' => '1234',
                'officeID' => '1',
                'fname' => 'Donna',
                'lname' => 'Cloudy',
                'companyName' => '',
                'spouse' => null,
                'commercialAccount' => '0',
                'status' => '1',
                'statusText' => 'Active',
                'email' => 'moodrtyuio7@hotmail.com',
                'phone1' => '1112223333',
                'ext1' => '',
                'phone2' => '',
                'ext2' => '',
                'address' => '340 Some Street',
                'city' => 'City',
                'state' => 'GA',
                'zip' => '12345',
                'billingCompanyName' => '',
                'billingFName' => 'Donna',
                'billingLName' => 'Moody',
                'billingCountryID' => 'US',
                'billingAddress' => '340 Some Street',
                'billingCity' => 'City',
                'billingState' => 'GA',
                'billingZip' => '12345',
                'billingPhone' => '1112223333',
                'billingEmail' => 'moodrtyuio7@hotmail.com',
                'lat' => '33.452549',
                'lng' => '-84.334717',
                'squareFeet' => '0',
                'addedByID' => '275',
                'dateAdded' => '2016-07-19 00:00:00',
                'dateCancelled' => '0000-00-00 00:00:00',
                'dateUpdated' => '2023-06-14 23:04:10',
                'sourceID' => '0',
                'source' => null,
                'aPay' => $apay->value,
                'preferredTechID' => '0',
                'paidInFull' => '0',
                'subscriptionIDs' => '8615,2955103',
                'balance' => '0.00',
                'balanceAge' => '0',
                'responsibleBalance' => (string)$responsibleBalance,
                'responsibleBalanceAge' => '0',
                'customerLink' => '109489|109525',
                'masterAccount' => '0',
                'preferredBillingDate' => '8',
                'paymentHoldDate' => $paymentHoldDate?->format('Y-m-d H:i:s'),
                'mostRecentCreditCardLastFour' => '9999',
                'mostRecentCreditCardExpirationDate' => '11/26',
                'regionID' => '0',
                'mapCode' => '',
                'mapPage' => '',
                'specialScheduling' => '',
                'taxRate' => '0.000000',
                'smsReminders' => '1',
                'phoneReminders' => '0',
                'emailReminders' => '1',
                'customerSource' => '*SALES REP',
                'customerSourceID' => '32',
                'maxMonthlyCharge' => '-1.00',
                'county' => 'Clayton',
                'useStructures' => '0',
                'isMultiUnit' => '0',
                'pendingCancel' => '0',
                'autoPayPaymentProfileID' => $autoPayPaymentProfileId,
                'divisionID' => '-1',
                'subPropertyTypeID' => '0',
                'agingDate' => '2023-06-14',
                'responsibleAgingDate' => '2023-06-14',
                'salesmanAPay' => '0',
                'purpleDragon' => '0',
                'termiteMonitoring' => '0',
                'appointmentIDs' => '7966825,8758012,18056960,22125046,24191948,26713380,29280366,2192369,2819894,4517527,6745320,10078696,13214154,17913890,26639069,16806,24987,33861,58949,2196546,2840351,3209818,3612883,4770597,5276124,5820328,6207035,6801128,8005497,8782105,9548514,10380632,11733649,12831188,15279443,16293041,18165401,19704989,20697310,21448688,22337396,23063706,24360409,25750465,27210543,28291293,29295623,3175318,5750431,9434864,15253119',
                'ticketIDs' => '33189,41361,50245,64342,79867,3172554,3699376,4025735,4420772,5725824,6211001,6716429,7031682,7652164,10784429,11499769,11918664,12666450,14527120,15355397,16146888,16843757,18584029,19989217,20681556,21310694,21895416,22756569,24266574,25224285,26349997,27199962,28310263',
                'paymentIDs' => '1589,1590,1591,40960,920717,1310425,1661698,2106616,2956569,3463050,3585075,4062568,4323553,4822845,5727858,5738853,6538567,7215776,7898310,8948544,10082496,11260617,12251727,13516798,14377895,14521801,14746408,15059111,15623310,16322745,16930558,17830326,18601487,20051425,21100628,21988283',
                'unitIDs' => []
            ]
        );
    }

    /**
     * @param int|null $quantity
     * @param int|null $totalQuantity
     *
     * @throws \Exception
     *
     * @return Collection
     */
    public static function getCollection(int|null $quantity = null, int|null $totalQuantity = null): Collection
    {
        $customers = [];

        for ($i = 0; $i < $quantity; $i++) {
            $customers[] = self::getCustomer();
        }

        if (is_null($quantity) || is_null($totalQuantity)) {
            return new Collection(items: $customers, total: count($customers));
        }

        return new Collection(items: $customers, total: $totalQuantity);
    }
}
