<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Exceptions;

use App\Api\Exceptions\PaymentValidationException;
use App\Api\Exceptions\ServerErrorException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class ServerErrorExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_sets_errors_from_payment_validation_exception(): void
    {
        $paymentValidationException = new PaymentValidationException(errors: ['Validation error']);

        $exception = new ServerErrorException(exception: $paymentValidationException);

        $this->assertSame(expected: $paymentValidationException->errors, actual: $exception->errors);
    }

    #[Test]
    public function it_sets_message_from_the_exception(): void
    {
        $originalException = new \RuntimeException(message: 'RunTime exception message');

        $exception = new ServerErrorException(exception: $originalException);

        $this->assertSame(expected: $originalException->getMessage(), actual: $exception->getMessage());
    }

    #[Test]
    public function it_sets_default_message_if_original_exception_does_not_contain_any_message(): void
    {
        $originalException = new \RuntimeException();

        $exception = new ServerErrorException(exception: $originalException);

        $this->assertSame(expected: __('messages.operation.something_went_wrong'), actual: $exception->getMessage());
    }
}
