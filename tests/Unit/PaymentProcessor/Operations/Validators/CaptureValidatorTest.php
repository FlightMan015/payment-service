<?php

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Operations\Capture;
use App\PaymentProcessor\Operations\Validators\CaptureValidator;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class CaptureValidatorTest extends UnitTestCase
{
    #[Test]
    public function validate_method_returns_true_if_provide_all_necessary_fields_with_correct_format(): void
    {
        $this->assertTrue((new CaptureValidator(operation: self::mockOperation()))->validate());
    }

    #[Test]
    #[DataProvider('getInvalidOperationData')]
    public function validate_method_returns_false_if_something_wrong(Capture $operation): void
    {
        $validator = new CaptureValidator(operation: $operation);

        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->getErrors());
    }

    public static function getInvalidOperationData(): \Iterator
    {
        yield 'missing reference_id field' => [self::mockOperation(referenceId: null)];
        yield 'missing reference_transaction_id field' => [self::mockOperation(referenceTransactionId: null)];
        yield 'missing amount field' => [self::mockOperation(amount: null)];

        yield 'reference_id field does not meet required format' => [self::mockOperation(referenceId: 'string')];
        yield 'reference_transaction_id field does not meet required format' => [self::mockOperation(referenceId: '_$-+=')];
        yield 'amount field is negative' => [self::mockOperation(amount: new Money(amount: -1, currency: new Currency('USD')))];
    }

    private static function mockOperation(
        string|null $referenceId = 'c5841dc9-d436-4bed-91fb-bb93acfea1c9',
        string|null $referenceTransactionId = 'abcde-12345-qwerty',
        Money|null $amount = new Money(amount: 2390, currency: new Currency(code: 'USD'))
    ): Capture|MockObject {

        $operation = \Mockery::mock(Capture::class);

        $operation->allows('getReferenceId')->andReturns($referenceId);
        $operation->allows('getReferenceTransactionId')->andReturns($referenceTransactionId);
        $operation->allows('getAmount')->andReturns($amount);

        return $operation;
    }
}
