<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\RefundPaymentCommand;
use App\Api\Requests\PostRefundPaymentRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class RefundPaymentCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $requestData, string $paymentId): void
    {
        $request = new PostRefundPaymentRequest($requestData);

        $command = RefundPaymentCommand::fromRequest($request, $paymentId);

        $this->assertInstanceOf(RefundPaymentCommand::class, $command);
        $this->assertEquals($requestData['amount'], $command->amount);
        $this->assertEquals($paymentId, $command->paymentId);
    }

    /**
     * @return array[]
     */
    public static function commandTestData(): array
    {
        $uuid = Str::uuid()->toString();
        $initialDataSet = [
            'amount' => 100,
        ];

        return [
            'filled data' => [
                'requestData' => $initialDataSet,
                'paymentId' => $uuid,
            ],
            'filled data string amount' => [
                'requestData' => array_replace($initialDataSet, [
                    'amount' => '100',
                ]),
                'paymentId' => $uuid,
            ],
            'filled data null amount' => [
                'requestData' => array_replace($initialDataSet, [
                    'amount' => '100',
                ]),
                'paymentId' => $uuid,
            ],
        ];
    }

    #[Test]
    public function to_array_method_returns_correct_array(): void
    {
        $paymentId = Str::uuid()->toString();
        $amount = 12399;
        $command = new RefundPaymentCommand(paymentId: $paymentId, amount: $amount);

        $expectedArray = [
            'payment_id' => $paymentId,
            'amount' => $amount
        ];

        $this->assertEquals($expectedArray, $command->toArray());
    }
}
