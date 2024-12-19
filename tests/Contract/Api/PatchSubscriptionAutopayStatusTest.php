<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\CRM\Customer\Subscription;
use App\Models\PaymentMethod;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PatchSubscriptionAutopayStatusTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/subscriptions/%s/autopay-status';

    #[Test]
    public function it_returns_200_response_when_set_autopay_payment_method(): void
    {
        $subscription = Subscription::factory()->create(['is_active' => true]);
        $paymentMethod = PaymentMethod::factory()->create(attributes: ['account_id' => $subscription->account->id]);

        $response = $this->makeRequest(subscriptionId: $subscription->id, input: ['autopay_method_id' => $paymentMethod->id]);

        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.subscription.autopay.update_success'));
        $this->assertSuccessResponseStructure($response);

        $this->assertDatabaseHas(
            table: 'billing.subscription_autopay_payment_methods',
            data: [
                'subscription_id' => $subscription->id,
                'payment_method_id' => $paymentMethod->id,
            ]
        );
    }

    #[Test]
    public function it_returns_200_response_when_unset_autopay_status(): void
    {
        $subscription = Subscription::factory()->create(['is_active' => true]);

        $response = $this->makeRequest(
            subscriptionId: $subscription->id,
            input: [
                'autopay_method_id' => null,
            ]
        );

        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.subscription.autopay.update_success'));
        $this->assertSuccessResponseStructure($response);

        $this->assertDatabaseMissing(
            table: 'billing.subscription_autopay_payment_methods',
            data: [
                'subscription_id' => $subscription->id,
                'payment_method_id' => null,
            ]
        );
    }

    #[Test]
    public function it_returns_400_response_when_subscription_is_not_found(): void
    {
        $response = $this->makeRequest(subscriptionId: Str::uuid()->toString());

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $response->assertJsonPath(path: 'result.errors.0.detail', expect: __('messages.subscription.not_found_in_db'));
        $this->assertErrorResponseStructure(response: $response);
    }

    private function makeRequest(string $subscriptionId, array $input = [], array|null $headers = null): TestResponse
    {
        $defaultHeaders = ['Api-Key' => config('auth.api_keys.payment_processing')];

        return $this->patch(
            uri: url(path: sprintf(self::ENDPOINT_URI, $subscriptionId)),
            data: $input,
            headers: $headers ?? $defaultHeaders
        );
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => ['message'],
        ], $response->json());
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());
    }
}
