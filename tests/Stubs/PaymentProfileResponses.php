<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Aptive\PestRoutesSDK\Collection;
use Aptive\PestRoutesSDK\Resources\PaymentProfiles\PaymentProfile;

class PaymentProfileResponses
{
    /**
     * @param string $paymentHoldDate
     * @param int $paymentMethod
     * @param int $status
     * @param int $id
     *
     * @return Collection
     */
    public static function getProfile(
        string $paymentHoldDate = '0000-00-00 00:00:00',
        int $paymentMethod = 1,
        int $status = 1,
        int $id = 1309258,
    ): Collection {
        $paymentProfiles = [];
        $paymentProfiles[] =
            PaymentProfile::fromApiObject(
                (object)[
                    'paymentProfileID' => (string)$id,
                    'customerID' => '8332',
                    'officeID' => '39',
                    'createdBy' => '0',
                    'description' => '',
                    'dateCreated' => '2018-05-04 00:00:00',
                    'dateUpdated' => '2018-05-04 00:00:00',
                    'status' => $status,
                    'statusNotes' => 'Success! Approved',
                    'billingName' => 'Jonathan Ostroff',
                    'billingAddress1' => '112 Chatham Rd',
                    'billingAddress2' => '',
                    'billingCountryID' => 'US',
                    'billingCity' => 'Stamford',
                    'billingState' => 'CT',
                    'billingZip' => '06903',
                    'billingPhone' => '2038323763',
                    'billingEmail' => 'jbostroff@gmail.com',
                    'paymentMethod' => $paymentMethod,
                    'gateway' => 'element',
                    'merchantID' => '',
                    'merchantToken' => '',
                    'lastFour' => '3008',
                    'expMonth' => '12',
                    'expYear' => '21',
                    'cardType' => 'Amex',
                    'bankName' => '',
                    'accountNumber' => '',
                    'routingNumber' => '',
                    'checkType' => '0',
                    'accountType' => '0',
                    'failedAttempts' => '0',
                    'sentFailureDate' => '0000-00-00 00:00:00',
                    'lastAttemptDate' => '2018-05-09 17:12:15',
                    'paymentHoldDate' => $paymentHoldDate,
                    'retryPoints' => '0',
                    'initialTransactionID' => 'NONE',
                    'lastDeclineType' => 'NONE',
                ]
            );

        return new Collection(items: $paymentProfiles, total: count($paymentProfiles));
    }

    public static function getEmptyProfileCollection(): Collection
    {
        return new Collection(items: [], total: 0);
    }

    /**
     * @param array $ids
     *
     * @return Collection
     */
    public static function getCollection(array $ids): Collection
    {
        $paymentProfiles = [];

        foreach ($ids as $id) {
            $paymentProfiles[] = self::getProfile(id: $id)->getItems()[0];
        }

        return new Collection(items: $paymentProfiles, total: count($paymentProfiles));
    }
}
