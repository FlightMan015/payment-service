<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ScheduledPaymentDuplicateException;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class ScheduledPaymentDuplicateExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_creates_exception_with_correct_message(): void
    {
        $duplicatePaymentId = Str::uuid()->toString();

        $exception = new ScheduledPaymentDuplicateException(duplicatePaymentId: $duplicatePaymentId);

        $this->assertSame(__('messages.scheduled_payment.duplicate', ['id' => $duplicatePaymentId]), $exception->getMessage());
    }
}
