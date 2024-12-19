<?php

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Operations\Credit;
use App\PaymentProcessor\Operations\Validators\CreditValidator;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class CreditValidatorTest extends UnitTestCase
{
    #[Test]
    public function validate_method_returns_true_if_provide_all_necessary_fields_with_correct_format(): void
    {
        $this->assertTrue((new CreditValidator(operation: self::mockOperation()))->validate());
    }

    #[Test]
    #[DataProvider('getInvalidOperationData')]
    public function validate_method_returns_false_if_something_wrong(Credit $operation): void
    {
        $validator = new CreditValidator(operation: $operation);

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
        string|null $referenceId = '186c9b34-f728-499f-a710-f3e1f1b1b430',
        string|null $referenceTransactionId = 'abcde-12345-qwerty',
        Money|null $amount = new Money(amount: 2390, currency: new Currency(code: 'USD'))
    ): Credit|MockObject {

        $operation = \Mockery::mock(Credit::class);

        $operation->allows('getReferenceId')->andReturn($referenceId);
        $operation->allows('getReferenceTransactionId')->andReturn($referenceTransactionId);
        $operation->allows('getAmount')->andReturn($amount);

        return $operation;
    }
}
