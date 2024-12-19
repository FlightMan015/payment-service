<?php

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Operations\AuthCapture;
use App\PaymentProcessor\Operations\Validators\AuthCaptureValidator;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class AuthCaptureValidatorTest extends UnitTestCase
{
    #[Test]
    public function validate_method_returns_true_if_provide_all_necessary_fields_with_correct_format(): void
    {
        $this->assertTrue((new AuthCaptureValidator(operation: self::mockOperation()))->validate());
    }

    #[Test]
    #[DataProvider('emptyEmailProvider')]
    public function validate_method_returns_true_if_email_is_empty(string|null $email): void
    {
        $this->assertTrue((new AuthCaptureValidator(operation: self::mockOperation(emailAddress: $email)))->validate());
    }

    #[Test]
    #[DataProvider('getInvalidOperationData')]
    public function validate_method_returns_false_if_something_wrong(AuthCapture $operation): void
    {
        $validator = new AuthCaptureValidator(operation: $operation);

        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->getErrors());
    }

    public static function getInvalidOperationData(): \Iterator
    {
        yield 'missing reference_id field' => [self::mockOperation(referenceId: null)];
        yield 'missing name_on_account field' => [self::mockOperation(nameOnAccount: null)];
        yield 'missing amount field' => [self::mockOperation(amount: null)];
        yield 'missing charge_description field' => [self::mockOperation(chargeDescription: null)];
        yield 'missing address_line_1 field' => [self::mockOperation(addressLine1: null)];
        yield 'missing city field' => [self::mockOperation(city: null)];
        yield 'missing province field' => [self::mockOperation(province: null)];
        yield 'missing postal_code field' => [self::mockOperation(postalCode: null)];

        yield 'missing СС and ACH fields' => [self::mockOperation(achAccountNumber: null, achRoutingNumber: null)];
        yield 'both CC and ACH fields presenting' => [self::mockOperation(token: 'some token', ccExpYear: 2024, ccExpMonth: 04)];

        yield 'reference_id field does not meet required format' => [self::mockOperation(referenceId: 'string')];
        yield 'name_on_account field does not meet required format' => [self::mockOperation(nameOnAccount: 'John`')];
        yield 'amount field is negative' => [self::mockOperation(amount: new Money(amount: -1, currency: new Currency('USD')))];
        yield 'address_line_1 field does not meet required format' => [self::mockOperation(addressLine1: 'Address ` test')];
        yield 'city field does not meet required format' => [self::mockOperation(city: 'City with backtick `')];
        yield 'province field does not meet required format' => [self::mockOperation(province: 'Florida')];
        yield 'postal_code field does not meet required format' => [self::mockOperation(postalCode: 'not postal code')];
        yield 'email_address field does not meet required format' => [self::mockOperation(emailAddress: 'not.email.com')];
    }

    private static function mockOperation(
        string|null $referenceId = 'e3771188-6a76-41b5-99e4-f61b866b1479',
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
    ): AuthCapture|MockObject {
        $operationMock = \Mockery::mock(AuthCapture::class);

        $operationMock->allows('getReferenceId')->andReturns($referenceId);
        $operationMock->allows('getNameOnAccount')->andReturn($nameOnAccount);
        $operationMock->allows('getAmount')->andReturn($amount);
        $operationMock->allows('getChargeDescription')->andReturn($chargeDescription);
        $operationMock->allows('getAddressLine1')->andReturn($addressLine1);
        $operationMock->allows('getCity')->andReturn($city);
        $operationMock->allows('getProvince')->andReturn($province);
        $operationMock->allows('getPostalCode')->andReturn($postalCode);
        $operationMock->allows('getEmailAddress')->andReturn($emailAddress);
        $operationMock->allows('getAchAccountNumber')->andReturn($achAccountNumber);
        $operationMock->allows('getAchRoutingNumber')->andReturn($achRoutingNumber);
        $operationMock->allows('getAchToken')->andReturn($achToken);

        $operationMock->allows('getToken')->andReturn($token);
        $operationMock->allows('getCcExpYear')->andReturn($ccExpYear);
        $operationMock->allows('getCcExpMonth')->andReturn($ccExpMonth);

        return $operationMock;
    }

    #[Test]
    #[DataProvider('oneOfTheseDataProvider')]
    public function validate_method_returns_true_if_provide_at_least_one_of_required_set_of_fields(
        AuthCapture $operation
    ): void {
        $this->assertTrue((new AuthCaptureValidator(operation: $operation))->validate());
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

    public static function emptyEmailProvider(): iterable
    {
        yield 'Null email' => ['email' => null];
        yield 'Empty email' => ['email' => ''];
    }

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }
}
