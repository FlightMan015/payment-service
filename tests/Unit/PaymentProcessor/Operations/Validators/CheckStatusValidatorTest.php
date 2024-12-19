<?php

declare(strict_types=1);

namespace Tests\Unit\PaymentProcessor\Operations\Validators;

use App\PaymentProcessor\Operations\CheckStatus;
use App\PaymentProcessor\Operations\Validators\CheckStatusValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\UnitTestCase;

class CheckStatusValidatorTest extends UnitTestCase
{
    #[Test]
    public function validate_method_returns_true_if_provide_all_necessary_fields_with_correct_format(): void
    {
        $this->assertTrue((new CheckStatusValidator(operation: self::mockOperation()))->validate());
    }

    #[Test]
    #[DataProvider('getInvalidOperationData')]
    public function validate_method_returns_false_if_something_wrong(CheckStatus $operation): void
    {
        $validator = new CheckStatusValidator(operation: $operation);

        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->getErrors());
    }

    public static function getInvalidOperationData(): \Iterator
    {
        yield 'missing reference_id field' => [self::mockOperation(referenceId: null)];
        yield 'missing reference_transaction_id field' => [self::mockOperation(referenceTransactionId: null)];

        yield 'reference_id field does not meet required format' => [self::mockOperation(referenceId: 'string')];
        yield 'reference_transaction_id field does not meet required format' => [self::mockOperation(referenceId: '_$-+=')];
    }

    private static function mockOperation(
        string|null $referenceId = 'e5763a76-cb12-4a48-a5af-4ef76aa42e39',
        string|null $referenceTransactionId = 'abcde-12345-qwerty',
    ): CheckStatus|MockObject {
        $operation = \Mockery::mock(CheckStatus::class);

        $operation->allows('getReferenceId')->andReturn($referenceId);
        $operation->allows('getReferenceTransactionId')->andReturn($referenceTransactionId);

        return $operation;
    }
}
