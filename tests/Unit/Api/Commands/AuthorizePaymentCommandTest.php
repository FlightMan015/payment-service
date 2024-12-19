<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\AuthorizePaymentCommand;
use App\Api\Requests\PostAuthorizePaymentRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

final class AuthorizePaymentCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data): void
    {
        $request = new PostAuthorizePaymentRequest($data);

        $command = AuthorizePaymentCommand::fromRequest($request);

        $this->assertInstanceOf(AuthorizePaymentCommand::class, $command);
        $this->assertEquals($data, $command->toArray());
    }

    /**
     * @return array
     */
    public static function commandTestData(): array
    {
        $uuid = Str::uuid()->toString();
        $initialDataSet = [
            'amount' => 100,
            'account_id' => $uuid,
            'method_id' => $uuid,
            'notes' => 'some note',
        ];

        return [
            'valid data' => [
                $initialDataSet,
            ],
            'null payment method id field value' => [
                array_replace($initialDataSet, ['method_id' => null]),
            ],
            'null notes field value' => [
                array_replace($initialDataSet, ['notes' => null]),
            ],
        ];
    }
}
