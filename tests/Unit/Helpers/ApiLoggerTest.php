<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ApiLogger;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\UnitTestCase;

class ApiLoggerTest extends UnitTestCase
{
    #[Test]
    public function logging_response_expected_info_level(): void
    {
        $this->assertLoggingResponse(200, 'info');
    }

    #[Test]
    public function logging_response_expected_notice_level(): void
    {
        $this->assertLoggingResponse(404, 'notice');
    }

    #[Test]
    public function logging_response_expected_error_level(): void
    {
        $this->assertLoggingResponse(500, 'error');
    }

    #[Test]
    public function logging_request(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(static fn ($message) => $message === 'payment-service.request companies');

        ApiLogger::logRequest('companies', request());
    }

    private function assertLoggingResponse(int $statusCode, string $expectedLogLevel): void
    {
        $apiMethod = 'companies';

        Log::shouldReceive('log')
            ->once()
            ->withArgs(static fn ($level, $message) => $level === $expectedLogLevel && stripos($message, $apiMethod) !== false);

        ApiLogger::logResponse($apiMethod, [], 'body', $statusCode);
    }
}
