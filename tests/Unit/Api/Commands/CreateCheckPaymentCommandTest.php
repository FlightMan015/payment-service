<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\CreateCheckPaymentCommand;
use App\Api\Requests\PostPaymentRequest;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

final class CreateCheckPaymentCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data, array $expected): void
    {
        $request = new PostPaymentRequest($data);

        $command = CreateCheckPaymentCommand::fromRequest($request);

        $this->assertInstanceOf(CreateCheckPaymentCommand::class, $command);
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
            'type' => PaymentTypeEnum::CHECK->name,
            'check_date' => '2021-01-01',
            'notes' => 'some note',
        ];
        $initialExpectation = [
            'account_id' => $uuid,
            'amount' => 100,
            'payment_type_id' => PaymentTypeEnum::CHECK->value,
            'processed_at' => Carbon::createFromFormat('Y-m-d', '2021-01-01')->startOfDay(),
            'payment_status_id' => PaymentStatusEnum::CAPTURED->value,
            'payment_gateway_id' => PaymentGatewayEnum::CHECK->value,
            'applied_amount' => 0,
            'notes' => 'some note',
        ];

        return [
            'valid data' => [
                $initialDataSet,
                $initialExpectation
            ],
            'null note field value' => [
                array_replace($initialDataSet, ['notes' => null]),
                array_replace($initialExpectation, ['notes' => null]),
            ],
        ];
    }
}
