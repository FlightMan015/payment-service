<?php

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Operations\Authorize;
use App\PaymentProcessor\Operations\Validators\AuthorizeValidator;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class AuthorizeValidatorTest extends UnitTestCase
{
    #[Test]
    public function validate_method_returns_true_if_provide_all_necessary_fields_with_correct_format(): void
    {
        $this->assertTrue((new AuthorizeValidator(operation: self::mockOperation()))->validate());
    }

    #[Test]
    public function validate_method_returns_true_if_provide_only_cc_token_information(): void
    {
        $this->assertTrue(
            (new AuthorizeValidator(operation: self::mockOperation(
                referenceId: null,
                nameOnAccount: null,
                amount: new Money(amount: 0, currency: new Currency(code: 'USD')),
                chargeDescription: 'Credit Card Validation',
                addressLine1: null,
                city: null,
                province: null,
                postalCode: null,
                emailAddress: null,
                achAccountNumber: null,
                achRoutingNumber: null,
                achToken: null,
                token: 'd8a5bbd7-f0fc-42e7-b432-5fc813446315',
                ccExpYear: 2028,
                ccExpMonth: 12,
            )))->validate()
        );
    }

    #[Test]
    #[DataProvider('getInvalidOperationData')]
    public function validate_method_returns_false_if_something_wrong(Authorize $operation): void
    {
        $validator = new AuthorizeValidator(operation: $operation);

        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->getErrors());
    }

    public static function getInvalidOperationData(): \Iterator
    {
        yield 'missing amount field' => [self::mockOperation(amount: null)];
        yield 'missing charge_description field' => [self::mockOperation(chargeDescription: null)];
        yield 'missing CC and ACH fields' => [self::mockOperation(achAccountNumber: null, achRoutingNumber: null)];
        yield 'both CC and ACH fields presenting' => [self::mockOperation(token: 'some token', ccExpYear: 2024, ccExpMonth: 04)];
        yield 'amount field is negative' => [self::mockOperation(amount: new Money(amount: -1, currency: new Currency('USD')))];
    }

    #[Test]
    #[DataProvider('oneOfTheseDataProvider')]
    public function validate_method_returns_true_if_provide_at_least_one_of_required_set_of_fields(
        Authorize $operation
    ): void {
        $this->assertTrue((new AuthorizeValidator(operation: $operation))->validate());
    }

    public static function oneOfTheseDataProvider(): iterable
    {
        yield 'Credit Card: token' => [self::mockOperation(
            achAccountNumber: null,
            achRoutingNumber: null,
            achToken: null,
            token: 'some token',
            ccExpYear: null,
            ccExpMonth: null,
        )];

        yield 'ACH: account number and routing number' => [self::mockOperation(
            achAccountNumber: '123456789',
            achRoutingNumber: '987654321',
            achToken: null,
            token: null,
            ccExpYear: null,
            ccExpMonth: null,
        )];

        yield 'ACH: token' => [self::mockOperation(
            achAccountNumber: null,
            achRoutingNumber: null,
            achToken: 'some token',
            token: null,
            ccExpYear: null,
            ccExpMonth: null,
        )];
    }

    private static function mockOperation(
        string|null $referenceId = 'd494bda0-a4e1-4dd0-9e4b-66d2a9b1601e',
        string|null $nameOnAccount = 'Some name',
        Money|null $amount = new Money(amount: 2390, currency: new Currency(code: 'USD')),
        string|null $chargeDescription = 'Description',
        string|null $addressLine1 = 'Address Line 1',
        string|null $city = 'Any City',
        string|null $province = 'FL',
        string|null $postalCode = '01103',
        string|null $emailAddress = 'someemail@goaptive.com',
        string|null $achAccountNumber = '123456789',
        string|null $achRoutingNumber = '111000025',
        string|null $achToken = null,
        string|null $token = null,
        int|null $ccExpYear = null,
        int|null $ccExpMonth = null,
    ): Authorize|MockObject {

        $operation = \Mockery::mock(Authorize::class);

        $operation->allows('getReferenceId')->andReturn($referenceId);
        $operation->allows('getNameOnAccount')->andReturn($nameOnAccount);
        $operation->allows('getAmount')->andReturn($amount);
        $operation->allows('getChargeDescription')->andReturn($chargeDescription);
        $operation->allows('getAddressLine1')->andReturn($addressLine1);
        $operation->allows('getCity')->andReturn($city);
        $operation->allows('getProvince')->andReturn($province);
        $operation->allows('getPostalCode')->andReturn($postalCode);
        $operation->allows('getEmailAddress')->andReturn($emailAddress);

        $operation->allows('getAchAccountNumber')->andReturn($achAccountNumber);
        $operation->allows('getAchRoutingNumber')->andReturn($achRoutingNumber);
        $operation->allows('getAchToken')->andReturn($achToken);

        $operation->allows('getToken')->andReturn($token);
        $operation->allows('getCcExpYear')->andReturn($ccExpYear);
        $operation->allows('getCcExpMonth')->andReturn($ccExpMonth);

        return $operation;
    }
}
