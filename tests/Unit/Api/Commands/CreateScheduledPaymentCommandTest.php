<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CreateScheduledPaymentCommand;
use App\Api\Requests\PostScheduledPaymentRequest;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentStatusEnum;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

final class CreateScheduledPaymentCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data, array $expected): void
    {
        $request = new PostScheduledPaymentRequest($data);

        $command = CreateScheduledPaymentCommand::fromRequest($request);

        $this->assertInstanceOf(CreateScheduledPaymentCommand::class, $command);
        $this->assertEquals($expected, $command->toArray());
    }

    /**
     * @return array
     */
    public static function commandTestData(): array
    {
        $uuid = Str::uuid()->toString();
        $initialDataSet = [
            'account_id' => $uuid,
            'amount' => 100,
            'method_id' => $uuid,
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'metadata' => ['subscription_id' => $uuid],
        ];
        $initialExpectation = [
            'account_id' => $uuid,
            'amount' => 100,
            'payment_method_id' => $uuid,
            'status_id' => ScheduledPaymentStatusEnum::PENDING->value,
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'metadata' => ['subscription_id' => $uuid],
        ];

        return [
            'valid data' => [
                $initialDataSet,
                $initialExpectation
            ],
            'null metadata field value' => [
                array_replace($initialDataSet, ['metadata' => null]),
                array_replace($initialExpectation, ['metadata' => null]),
            ],
        ];
    }
}
