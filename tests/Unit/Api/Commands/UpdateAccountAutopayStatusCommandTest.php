<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\UpdateAccountAutopayStatusCommand;
use App\Api\Requests\PatchAccountAutopayRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class UpdateAccountAutopayStatusCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data, array $expected): void
    {
        $request = new PatchAccountAutopayRequest($data);

        $command = UpdateAccountAutopayStatusCommand::fromRequest($request);

        $this->assertInstanceOf(UpdateAccountAutopayStatusCommand::class, $command);
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
            'autopay_method_id' => $uuid,
        ];

        $initialExpectation = [
            'account_id' => $uuid,
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
