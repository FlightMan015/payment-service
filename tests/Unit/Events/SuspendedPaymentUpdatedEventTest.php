<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\SuspendedPaymentUpdatedEvent;
use App\Models\CRM\Customer\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\PaymentResolutionEnum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class SuspendedPaymentUpdatedEventTest extends UnitTestCase
{
    #[Test]
    public function it_creates_a_timestamp_when_creating_the_event(): void
    {
        $event = new SuspendedPaymentUpdatedEvent(
            account: Account::factory()->withoutRelationships()->make(),
            resolution: PaymentResolutionEnum::TERMINATED,
            paymentMethod: PaymentMethod::factory()->withoutRelationships()->make(),
            payment: Payment::factory()->withoutRelationships()->make(),
        );

        $this->assertNotNull($event->timestamp);
    }
}
