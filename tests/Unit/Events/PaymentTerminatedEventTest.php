<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\PaymentTerminatedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PaymentTerminatedEventTest extends UnitTestCase
{
    #[Test]
    public function it_creates_a_timestamp_when_creating_the_event(): void
    {
        Carbon::setTestNow(now());

        $payment = Payment::factory()->makeWithRelationships(
            attributes: ['processed_at' => now()],
            relationships: [
                'account' => Account::factory()->withoutRelationships()->make(),
                'paymentMethod' => PaymentMethod::factory()->withoutRelationships()->make(),
                'originalPayment' => Payment::factory()->withoutRelationships()->make(),
            ]
        );

        $event = new PaymentTerminatedEvent(
            account: $payment->account,
            paymentMethod: $payment->paymentMethod,
            terminatedPayment: $payment,
            originalPayment: $payment->originalPayment
        );

        $this->assertNotNull($event->timestamp);
        $this->assertEquals($event->timestamp, now()->getTimestampMs());

        Carbon::setTestNow(null);
    }
}
