<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ScheduledPaymentTriggerInvalidMetadataException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class ScheduledPaymentTriggerInvalidMetadataExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_creates_exception_with_correct_message(): void
    {
        $exception = new ScheduledPaymentTriggerInvalidMetadataException('test message');

        $this->assertSame(__('messages.scheduled_payment.metadata_validation_error', ['message' => 'test message']), $exception->getMessage());
    }
}
