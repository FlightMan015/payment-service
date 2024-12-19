<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Factories\ScheduledPaymentTriggerMetadataValidatorFactory;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use App\Validators\InitialServiceCompletedScheduledPaymentTriggerValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class ScheduledPaymentTriggerMetadataValidatorFactoryTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('triggerProvider')]
    public function it_creates_correct_validator_instance_based_on_trigger(
        ScheduledPaymentTriggerEnum $trigger,
        string $expectedValidator
    ): void {
        $validator = ScheduledPaymentTriggerMetadataValidatorFactory::make($trigger);

        $this->assertInstanceOf($expectedValidator, $validator);
    }

    public static function triggerProvider(): iterable
    {
        yield 'InitialServiceCompleted' => [
            'trigger' => ScheduledPaymentTriggerEnum::InitialServiceCompleted,
            'expectedValidator' => InitialServiceCompletedScheduledPaymentTriggerValidator::class
        ];
    }
}
