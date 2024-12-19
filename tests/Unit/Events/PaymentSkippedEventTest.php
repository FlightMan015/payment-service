<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\PaymentSkippedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class PaymentSkippedEventTest extends UnitTestCase
{
    #[Test]
    public function it_creates_a_timestamp_when_creating_the_event(): void
    {
        $event = new PaymentSkippedEvent(
            account: Account::factory()->withoutRelationships()->make(),
            reason: 'Reason to skip',
            paymentMethod: PaymentMethod::factory()->withoutRelationships()->make(),
            payment: Payment::factory()->withoutRelationships()->make(),
        );

        $this->assertNotNull($event->timestamp);
    }
}
