<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\AuthorizeAndCapturePaymentCommand;
use App\Api\Requests\PostAuthorizeAndCapturePaymentRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class AuthorizeAndCapturePaymentCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data): void
    {
        $request = new PostAuthorizeAndCapturePaymentRequest($data);

        $command = AuthorizeAndCapturePaymentCommand::fromRequest($request);

        $this->assertInstanceOf(AuthorizeAndCapturePaymentCommand::class, $command);
        $this->assertEquals($data, $command->toArray());
    }

    #[Test]
    public function from_request_sets_payment_id_to_null(): void
    {
        $uuid = Str::uuid()->toString();
        $request = new PostAuthorizeAndCapturePaymentRequest([
            'amount' => 100,
            'account_id' => $uuid,
            'method_id' => $uuid,
            'notes' => 'some note',
            'payment_id' => $uuid
        ]);

        $command = AuthorizeAndCapturePaymentCommand::fromRequest($request);

        $this->assertNull($command->paymentId);
    }

    /**
     * @return array[]
     */
    public static function commandTestData(): array
    {
        $uuid = Str::uuid()->toString();
        $initialDataSet = [
            'amount' => 100,
            'account_id' => $uuid,
            'method_id' => $uuid,
            'notes' => 'some note',
            'payment_id' => null,
        ];

        return [
            'valid data' => [
                $initialDataSet
            ],
            'null payment method id field value' => [
                array_replace($initialDataSet, ['method_id' => null]),
            ],
            'null note field value' => [
                array_replace($initialDataSet, ['notes' => null])
            ],
        ];
    }
}
