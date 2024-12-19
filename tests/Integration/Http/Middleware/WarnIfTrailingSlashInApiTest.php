<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Integration\Http\Middleware;

use App\Api\Middleware\AuthenticatePaymentProcessingApiKey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\AllowTrailingSlashInHttpRequestsTrait;
use Tests\TestCase;

class WarnIfTrailingSlashInApiTest extends TestCase
{
    use AllowTrailingSlashInHttpRequestsTrait;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // disable API keys authorizing
        $this->withoutMiddleware(middleware: [AuthenticatePaymentProcessingApiKey::class]);
    }

    #[Test]
    #[DataProvider('apiUrlsWithSlash')]
    public function it_returns_400_bad_request_response_if_call_the_api_url_with_trailing_slash(
        string $method,
        string $url
    ): void {
        $response = $this->call(method: $method, uri: $url);

        $response->assertBadRequest()->assertJsonFragment(data: [__('messages.error.trailing_slash_not_allowed')]);
    }

    public static function apiUrlsWithSlash(): iterable
    {
        yield 'payments' => ['method' => 'GET', 'url' => 'api/v1/payments/'];
        yield 'payment methods' => ['method' => 'GET', 'url' => 'api/v1/payment-methods/'];
        yield 'process payments' => ['method' => 'POST', 'url' => 'api/v1/process-payments/'];
    }
}
