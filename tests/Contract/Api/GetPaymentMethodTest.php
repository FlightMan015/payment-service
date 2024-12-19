<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\PaymentMethod;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetPaymentMethodTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payment-methods/%s';
    private array $paymentMethodAch = [];
    private array $paymentMethodNonAch = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodAch = $this->createAchPaymentMethodInDatabase();
        $this->paymentMethodNonAch = $this->createNonAchPaymentMethodInDatabase();
    }

    #[Test]
    public function it_returns_200_response_for_retrieving_payment_method_successfully_ach(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, $this->paymentMethodAch['id']),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => [
                'payment_method_id',
                'account_id',
                'type',
                'date_added',
                'is_primary',
                'is_autopay',
                'description',
                'gateway' => ['id', 'name'],
                'ach_account_last_four',
                'ach_routing_number',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);

        $response->assertJsonPath('result.payment_method_id', $this->paymentMethodAch['id']);
    }

    #[Test]
    public function it_returns_200_response_for_retrieving_payment_method_successfully_non_ach(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, $this->paymentMethodNonAch['id']),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => [
                'payment_method_id',
                'account_id',
                'type',
                'date_added',
                'is_primary',
                'is_autopay',
                'description',
                'gateway' => ['id', 'name'],
                'cc_last_four',
                'cc_expiration_month',
                'cc_expiration_year'
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);

        $response->assertJsonPath('result.payment_method_id', $this->paymentMethodNonAch['id']);
    }

    #[Test]
    public function it_returns_400_bad_request_for_invalid_id(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, 'test-id'),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('validation.parameter_invalid_uuid', ['parameter' => 'payment method id']));
    }

    #[Test]
    public function it_return_401_response_for_non_api_key_api(): void
    {
        $response = $this->get(uri: sprintf(self::ENDPOINT_URI, $this->paymentMethodNonAch['id']));
        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('auth.api_key_not_found'));
    }

    #[Test]
    public function it_returns_404_not_found_response_for_request_with_non_existing_uuid(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, $paymentMethodId = Str::uuid()->toString()),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.payment_method.not_found', ['id' => $paymentMethodId]));
    }

    private function createNonAchPaymentMethodInDatabase(): array
    {
        return PaymentMethod::factory()->cc()->create()->toArray();
    }

    private function createAchPaymentMethodInDatabase(): array
    {
        return PaymentMethod::factory()->ach()->create()->toArray();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentMethodAch, $this->paymentMethodNonAch);
    }
}
