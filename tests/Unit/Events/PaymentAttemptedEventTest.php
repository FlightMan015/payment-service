<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Enums\PaymentProcessingInitiator;
use App\Events\PaymentAttemptedEvent;
use App\Models\Payment;
use App\PaymentProcessor\Enums\OperationEnum;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PaymentAttemptedEventTest extends UnitTestCase
{
    #[Test]
    public function it_creates_a_timestamp_when_creating_the_event(): void
    {
        Carbon::setTestNow(testNow: Carbon::now());

        $event = new PaymentAttemptedEvent(
            payment: Payment::factory()->withoutRelationships()->make(),
            initiated_by: PaymentProcessingInitiator::BATCH_PROCESSING,
            operation: OperationEnum::AUTHORIZE
        );

        $this->assertNotNull($event->timestamp);
        $this->assertEquals($event->timestamp, now()->getTimestampMs());

        Carbon::setTestNow(testNow: null);
    }
}
