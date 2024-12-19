<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\ValidateCreditCardTokenCommand;
use App\Api\Requests\PostValidateCreditCardTokenRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class ValidateCreditCardTokenCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data): void
    {
        $request = new PostValidateCreditCardTokenRequest($data);

        $command = ValidateCreditCardTokenCommand::fromRequest($request);

        $this->assertInstanceOf(ValidateCreditCardTokenCommand::class, $command);
        $this->assertEquals($data, $command->toArray());
    }

    /**
     * @return array
     */
    public static function commandTestData(): array
    {
        $initialDataSet = [
            'gateway_id' => 1,
            'office_id' => 1,
            'cc_token' => 'xxx-xxx-xx-xxxxx',
            'cc_expiration_month' => 12,
            'cc_expiration_year' => date('Y', strtotime('+20 years')),
        ];

        return [
            'full data' => [
                $initialDataSet,
            ],
            'string exp month' => [
                array_replace($initialDataSet, [
                    'cc_expiration_month' => '12',
                ]),
            ],
        ];
    }
}
