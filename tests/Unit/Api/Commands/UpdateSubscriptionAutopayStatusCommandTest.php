<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\UpdateSubscriptionAutopayStatusCommand;
use App\Api\Requests\PatchSubscriptionAutopayStatusRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class UpdateSubscriptionAutopayStatusCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data, array $expected): void
    {
        $request = new PatchSubscriptionAutopayStatusRequest($data);

        $command = UpdateSubscriptionAutopayStatusCommand::fromRequest($request);

        $this->assertInstanceOf(UpdateSubscriptionAutopayStatusCommand::class, $command);
        $this->assertEquals($expected, $command->toArray());
    }

    /**
     * @return array
     */
    public static function commandTestData(): array
    {
        $uuid = Str::uuid()->toString();
        $initialDataSet = [
            'subscription_id' => $uuid,
            'autopay_method_id' => $uuid,
        ];

        $initialExpectation = [
            'subscription_id' => $uuid,
            'autopay_payment_method_id' => $uuid,
        ];

        return [
            'filled data' => [
                $initialDataSet,
                $initialExpectation
            ],
            'null autopay payment method id' => [
                array_replace($initialDataSet, [
                    'autopay_method_id' => null,
                ]),
                array_replace($initialExpectation, [
                    'autopay_payment_method_id' => null,
                ]),
            ],
        ];
    }
}
