<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Repositories\DatabaseScheduledPaymentRepository;
use App\Entities\Subscription;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\ScheduledPaymentTriggerEnum;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;
use Tests\Stubs\CRM\SubscriptionResponses;

class PostScheduledPaymentTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/scheduled-payments';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    #[Test]
    public function it_returns_201_created_response_with_valid_request(): void
    {
        $account = Account::factory()->create();

        $this->mockSubscriptionService(subscription: Subscription::fromObject(SubscriptionResponses::getSingle()));

        $response = $this->makeRequest(data: [
            'account_id' => $account->id,
            'amount' => 100,
            'method_id' => PaymentMethod::factory()->for($account)->ach()->create()->id,
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'metadata' => ['subscription_id' => '123'],
        ]);

        $response->assertStatus(status: HttpStatus::CREATED);
        $this->assertSuccessResponseStructure($response);
    }

    #[Test]
    public function it_returns_400_bad_request_if_amount_is_missing(): void
    {
        $response = $this->makeRequest(data: [
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'metadata' => ['subscription_id' => '123'],
        ]);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure($response);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_if_api_key_is_not_found(): void
    {
        $response = $this->makeRequest(headers: []);

        $response->assertStatus(HttpStatus::UNAUTHORIZED);
    }

    #[Test]
    public function it_returns_500_server_error_if_db_throws_exception(): void
    {
        $account = Account::factory()->create();
        $repository = $this->getMockBuilder(className: DatabaseScheduledPaymentRepository::class)->getMock();
        $repository->method('create')->willThrowException(exception: new ConnectionException(message: 'Connection issues'));
        $this->app->instance(abstract: DatabaseScheduledPaymentRepository::class, instance: $repository);

        $this->mockSubscriptionService(subscription: Subscription::fromObject(SubscriptionResponses::getSingle()));

        $response = $this->makeRequest(data: [
            'account_id' => $account->id,
            'amount' => 100,
            'method_id' => PaymentMethod::factory()->for($account)->ach()->create()->id,
            'trigger_id' => ScheduledPaymentTriggerEnum::InitialServiceCompleted->value,
            'metadata' => ['subscription_id' => '123'],
        ]);

        $response->assertStatus(status: HttpStatus::INTERNAL_SERVER_ERROR);
        $this->assertErrorResponseStructure(response: $response, hasError: false);
    }

    private function mockSubscriptionService(Subscription $subscription): void
    {
        $subscriptionService = $this->createMock(SubscriptionServiceInterface::class);
        $subscriptionService->method('getSubscription')->willReturn($subscription);

        $this->app->instance(abstract: SubscriptionServiceInterface::class, instance: $subscriptionService);
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => [
                'message',
                'scheduled_payment_id',
            ],
        ], $response->json());
    }

    private function assertErrorResponseStructure(TestResponse $response, bool $hasError = true): void
    {
        $response->assertValid();

        $structure = [
            '_metadata' => ['success'],
            'result' => [
                'message',
            ],
        ];

        if ($hasError) {
            $structure['result']['errors'] = ['*' => ['detail']];
        }

        $response->assertJsonStructure($structure, $response->json());
    }

    private function makeRequest(array $data = [], array|null $headers = null): TestResponse
    {
        $defaultHeaders = [
            'Api-Key' => config('auth.api_keys.payment_processing'),
        ];

        return $this->post(
            uri: url(path: self::ENDPOINT_URI),
            data: $data,
            headers: $headers ?? $defaultHeaders
        );
    }
}
