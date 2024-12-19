<?php

declare(strict_types=1);

namespace Tests\Stubs\PaymentProcessor;

use App\PaymentProcessor\Enums\AchAccountTypeEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Gateways\NullGateway;
use App\PaymentProcessor\Operations\AbstractOperation;
use App\PaymentProcessor\Operations\AuthCapture;
use App\PaymentProcessor\Operations\Authorize;
use App\PaymentProcessor\Operations\Cancel;
use App\PaymentProcessor\Operations\Capture;
use App\PaymentProcessor\Operations\CheckStatus;
use App\PaymentProcessor\Operations\Credit;
use Money\Currency;
use Money\Money;

class OperationStub
{
    /**
     * @return AuthCapture
     */
    public static function authCapture(): AbstractOperation
    {
        $authCapture = new AuthCapture(gateway: new NullGateway());

        $authCapture->setUp(paymentType: PaymentTypeEnum::CC)
            ->setAmount(amount: new Money(amount: 1000, currency: new Currency(code: 'USD')))
            ->setChargeDescription(chargeDescription: 'Test Charge')
            ->setAchAccountNumber(achAccountNumber: '123456789')->setAchRoutingNumber(achRoutingNumber: '111000025')
            ->setAchAccountType(achAccountType: AchAccountTypeEnum::BUSINESS_CHECKING)->setNameOnAccount(nameOnAccount: 'Test User')
            ->setAddress(addressLine1: '123 Main St', addressLine2: 'Apt 1', city: 'Anytown', province: 'CA', postalCode: '12345', countryCode: 'USA')
            ->setEmailAddress(emailAddress: 'test@example.com')->setReferenceId(referenceId: 'Test Ref ID')
            ->setReferenceTransactionId(referenceTransactionId: '123');

        return $authCapture;
    }

    /**
     * @return Authorize
     */
    public static function authorize(): Authorize
    {
        $authorize = new Authorize(gateway: new NullGateway());

        $authorize->setUp(paymentType: PaymentTypeEnum::CC)
            ->setAmount(amount: new Money(amount: 1000, currency: new Currency(code: 'USD')))
            ->setChargeDescription(chargeDescription: 'Test Charge')
            ->setToken(token: 'Test Token')->setCcExpYear(ccExpYear: 2025)->setCcExpMonth(ccExpMonth: 12)
            ->setAchAccountType(achAccountType: AchAccountTypeEnum::BUSINESS_CHECKING)->setNameOnAccount(nameOnAccount: 'Test User')
            ->setAddress(addressLine1: '123 Main St', addressLine2: 'Apt 1', city: 'Anytown', province: 'CA', postalCode: '12345', countryCode: 'USA')
            ->setEmailAddress(emailAddress: 'test@example.com')->setReferenceId(referenceId: 'Test Ref ID');

        return $authorize;
    }

    /**
     * @return Cancel
     */
    public static function cancel(): Cancel
    {
        $cancel = new Cancel(gateway: new NullGateway());

        $cancel->setUp()
            ->setReferenceTransactionId(referenceTransactionId: 'transaction-id')
            ->setAmount(amount: new Money(amount: 1000, currency: new Currency(code: 'USD')))
            ->setReferenceId(referenceId: 'reference-id');

        return $cancel;
    }

    /**
     * @return Capture
     */
    public static function capture(): Capture
    {
        $capture = new Capture(gateway: new NullGateway());

        $capture->setUp()
            ->setReferenceTransactionId(referenceTransactionId: '1234567')
            ->setAmount(amount: new Money(amount: 1042, currency: new Currency(code: 'USD')))
            ->setReferenceId(referenceId: 'QWERTY');

        return $capture;
    }

    /**
     * @return CheckStatus
     */
    public static function checkStatus(): CheckStatus
    {
        $checkStatus = new CheckStatus(gateway: new NullGateway());

        $checkStatus->setUp()
            ->setReferenceTransactionId(referenceTransactionId: '1234567')
            ->setReferenceId(referenceId: 'e7d3a5f1-9f3a-4d7b-9a6c-2f4b9a3475af');

        return $checkStatus;
    }

    /**
     * @return Credit
     */
    public static function credit(): Credit
    {
        $credit = new Credit(gateway: new NullGateway());

        $credit->setUp()
            ->setReferenceTransactionId(referenceTransactionId: '1234567')
            ->setAmount(amount: new Money(amount: 1042, currency: new Currency(code: 'USD')))
            ->setReferenceId(referenceId: 'e7d3a5f1-9f3a-4d7b-9a6c-2f4b9a3475af');

        return $credit;
    }
}
