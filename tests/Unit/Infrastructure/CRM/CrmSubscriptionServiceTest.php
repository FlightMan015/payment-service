<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\CRM;

use App\Infrastructure\CRM\CrmSubscriptionService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Stubs\CRM\SubscriptionResponses;
use Tests\Unit\UnitTestCase;

class CrmSubscriptionServiceTest extends UnitTestCase
{
    /** @var Client&MockObject $guzzle */
    private Client $guzzle;
    private CrmSubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guzzle = $this->createMock(Client::class);
        $this->service = new CrmSubscriptionService($this->guzzle);
    }

    #[Test]
    public function it_returns_subscription_from_api_response(): void
    {
        $expectedResponse = SubscriptionResponses::getSingle();

        $this->guzzle->expects($this->once())
            ->method('get')
            ->willReturn(new Response(
                status: 200,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode($expectedResponse, JSON_THROW_ON_ERROR)
            ));

        $this->mockTokenRetrieving();

        $subscription = $this->service->getSubscription($expectedResponse->id);

        $this->assertEquals($expectedResponse->id, $subscription->id);
    }

    private function mockTokenRetrieving(): void
    {
        Cache::forget('payment-service:crm_access_token');

        $this->guzzle->expects($this->once())
            ->method('post')
            ->willReturn(
                new Response(
                    status: 200,
                    headers: ['Content-Type' => 'application/json'],
                    body: json_encode(['access_token' => Str::random(length: 128)], JSON_THROW_ON_ERROR)
                )
            );
    }
}
