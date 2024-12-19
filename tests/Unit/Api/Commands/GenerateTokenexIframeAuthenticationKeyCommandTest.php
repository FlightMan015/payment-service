<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\GenerateTokenexIframeAuthenticationKeyCommand;
use App\Api\Requests\PostTokenexGenerateAuthenticationKeyRequest;
use App\PaymentProcessor\Enums\TokenexTokenSchemeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

final class GenerateTokenexIframeAuthenticationKeyCommandTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('commandTestData')]
    public function from_request_sets_properties_correctly(array $data): void
    {
        $request = new PostTokenexGenerateAuthenticationKeyRequest($data);

        $command = GenerateTokenexIframeAuthenticationKeyCommand::fromRequest($request);

        $this->assertInstanceOf(GenerateTokenexIframeAuthenticationKeyCommand::class, $command);
        $this->assertEquals($data['token_scheme'], $command->tokenScheme);
        $this->assertEquals($data['origins'], $command->origins);
        $this->assertSame((string)$data['timestamp'], $command->timestamp);
    }

    /**
     * @return array
     */
    public static function commandTestData(): array
    {
        $initialDataSet = [
            'token_scheme' => TokenexTokenSchemeEnum::PCI->value,
            'origins' => [
                'https://mysite.com',
                'http://someothersite.com',
            ],
            'timestamp' => '20180109161437',
        ];

        return [
            'filled data' => [
                $initialDataSet,
            ],
            'filled data int timestamp' => [
                array_replace($initialDataSet, [
                    'timestamp' => 20180109161437
                ]),
            ],
        ];
    }
}
