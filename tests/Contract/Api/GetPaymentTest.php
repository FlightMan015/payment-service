<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\Payment;
use Aptive\Component\Http\HttpStatus;
use Aptive\Component\Money\MoneyHandler;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetPaymentTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/%s';

    private array $payment = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->payment = $this->createPaymentInDatabase();
    }

    private function createPaymentInDatabase(): array
    {
        if ($payment = Payment::inRandomOrder()->first()) {
            return $payment->toArray();
        }

        return Payment::factory()->create()->toArray();
    }

    #[Test]
    public function it_return_401_response_for_non_api_key_api(): void
    {
        $response = $this->get(uri: sprintf(self::ENDPOINT_URI, $this->payment['id']));
        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('auth.api_key_not_found'));
    }

    #[Test]
    public function it_returns_json_get_single_response_for_retrieving_detail_successfully(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, $this->payment['id']),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
                'links' => [
                    'self' => [],
                ],
            ],
            'result' => [],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('result.payment_id', $this->payment['id']);

        $amount = (float) (new MoneyHandler())->formatFloat(
            money: new Money(amount: $this->payment['amount'], currency: new Currency('USD'))
        );
        $this->assertEquals($response->json()['result']['amount'], $amount);
    }

    #[Test]
    public function it_returns_404_not_found_response_for_unsuccessful_request(): void
    {
        $response = $this->get(
            uri: sprintf(self::ENDPOINT_URI, $paymentId = Str::uuid()->toString()),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.payment.not_found', ['id' => $paymentId]));
    }

    #[Test]
    public function it_returns_400_invalid_response_for_invalid_id(): void
    {
        $response = $this->get(
            uri: url('/api/v1/payments/asd'),
            headers: [
                'Api-Key' => config('auth.api_keys.payment_processing'),
            ]
        );

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure([
            '_metadata' => [
                'success' => [],
            ],
            'result' => [
                'message' => [],
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('validation.parameter_invalid_uuid', ['parameter' => 'payment id']));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->payment);
    }
}
