<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\InvalidPreferredBillingDateException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class InvalidPreferredBillingDateExceptionTest extends UnitTestCase
{
    #[Test]
    public function in_creates_exception_with_correct_error_message_and_context(): void
    {
        $preferredDay = 1;
        $exception = new InvalidPreferredBillingDateException($preferredDay);
        $this->assertEquals(
            expected: __('messages.payment.batch_processing.invalid_preferred_billing_date'),
            actual: $exception->getMessage()
        );
        $this->assertEquals(
            expected: ['preferred_day' => $preferredDay],
            actual: $exception->context
        );
    }
}
