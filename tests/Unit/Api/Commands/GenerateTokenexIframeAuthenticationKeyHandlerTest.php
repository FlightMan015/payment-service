<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Api\Commands;

use App\Api\Commands\GenerateTokenexIframeAuthenticationKeyCommand;
use App\Api\Commands\GenerateTokenexIframeAuthenticationKeyHandler;
use Aptive\Component\Http\Exceptions\InternalServerErrorHttpException;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class GenerateTokenexIframeAuthenticationKeyHandlerTest extends UnitTestCase
{
    private GenerateTokenexIframeAuthenticationKeyCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new GenerateTokenexIframeAuthenticationKeyCommand(
            tokenScheme: 'PCI',
            origins: ['https://sample.com'],
            timestamp: '20180109161437'
        );
    }

    #[Test]
    public function it_returns_a_string_when_input_and_config_valid(): void
    {
        if (!config('tokenex.iframe_tokenex_id')) {
            Config::set('tokenex.iframe_tokenex_id', 1123);
        }
        if (!config('tokenex.iframe_client_secret_key')) {
            Config::set('tokenex.iframe_client_secret_key', 'ASDASd13123');
        }
        $result = $this->handler()->handle(command: $this->command);

        $this->assertIsString($result);
    }

    #[Test]
    public function it_throws_internal_server_error_when_tokenex_config_is_not_set(): void
    {
        Config::set('tokenex.iframe_tokenex_id', null);
        Config::set('tokenex.iframe_client_secret_key', null);
        $this->expectException(exception: InternalServerErrorHttpException::class);
        $this->handler()->handle(command: $this->command);
    }

    private function handler(): GenerateTokenexIframeAuthenticationKeyHandler
    {
        return new GenerateTokenexIframeAuthenticationKeyHandler();
    }
}
