<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Factories\ScheduledPaymentTriggerFactory;
use App\Jobs\ScheduledPayment\Triggers\InitialServiceCompletedScheduledPaymentTriggerJob;
use App\Models\ScheduledPayment;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class ScheduledPaymentTriggerFactoryTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('triggerProvider')]
    public function it_creates_correct_job_instance_based_on_trigger(
        ScheduledPaymentTriggerEnum $trigger,
        string $expectedJob
    ): void {
        $payment = ScheduledPayment::factory()->withoutRelationships()->make(['trigger_id' => $trigger->value]);

        $job = ScheduledPaymentTriggerFactory::make($payment);

        $this->assertInstanceOf($expectedJob, $job);
    }

    public static function triggerProvider(): iterable
    {
        yield 'InitialServiceCompleted' => [
            'trigger' => ScheduledPaymentTriggerEnum::InitialServiceCompleted,
            'expectedJob' => InitialServiceCompletedScheduledPaymentTriggerJob::class
        ];
    }

    #[Test]
    public function it_throws_exception_if_payment_trigger_is_not_corresponding_to_the_job(): void
    {
        $payment = ScheduledPayment::factory()->withoutRelationships()->make(['trigger_id' => ScheduledPaymentTriggerEnum::NextServiceCompleted->value]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trigger is not implemented yet.');

        ScheduledPaymentTriggerFactory::make($payment);
    }
}
