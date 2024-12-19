<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\CRM\Customer\Account;
use App\Models\Ledger;
use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\PaymentGatewayEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PatchAccountAutopayStatusTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/accounts/%s/autopay-status';

    #[Test]
    #[DataProvider('paymentMethodsProvider')]
    public function it_returns_200_response_when_set_autopay_payment_method(array $input): void
    {
        $account = Account::factory()->create();
        $paymentMethod = $this->createPaymentMethod(account: $account, attributes: $input['paymentMethodAttributes']);

        $response = $this->makeRequest(accountId: $account->id, input: ['autopay_method_id' => $paymentMethod->id]);

        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.account.autopay.update_success'));
        $this->assertSuccessResponseStructure($response);

        $this->assertDatabaseHas(
            table: Ledger::class,
            data: [
                'account_id' => $account->id,
                'autopay_payment_method_id' => $paymentMethod->id,
            ]
        );
    }

    #[Test]
    public function it_returns_200_response_when_unset_autopay_status(): void
    {
        $account = Account::factory()->create();

        $response = $this->makeRequest(accountId: $account->id, input: ['autopay_method_id' => null]);

        $response->assertStatus(status: HttpStatus::OK);
        $response->assertJsonPath('result.message', __('messages.account.autopay.update_success'));
        $this->assertSuccessResponseStructure($response);

        $this->assertDatabaseHas(
            table: Ledger::class,
            data: [
                'account_id' => $account->id,
                'autopay_payment_method_id' => null,
            ]
        );
    }

    #[Test]
    public function it_returns_400_response_when_account_is_not_found(): void
    {
        $response = $this->makeRequest(accountId: Str::uuid()->toString());

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $response->assertJsonPath(path: 'result.errors.0.detail', expect: __('messages.account.not_found_in_db'));
        $this->assertErrorResponseStructure(response: $response);
    }

    public static function paymentMethodsProvider(): \Iterator
    {
        yield 'CC' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::CC->value,
                    'cc_token' => Str::uuid()->toString(),
                    'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                ],
            ],
        ];
        yield 'ACH' => [
            'input' => [
                'paymentMethodAttributes' => [
                    'payment_type_id' => PaymentTypeEnum::ACH->value,
                    'payment_gateway_id' => PaymentGatewayEnum::WORLDPAY->value,
                ],
            ],
        ];
    }

    private function makeRequest(string $accountId, array $input = [], array|null $headers = null): TestResponse
    {
        $defaultHeaders = ['Api-Key' => config('auth.api_keys.payment_processing')];

        return $this->patch(
            uri: url(path: sprintf(self::ENDPOINT_URI, $accountId)),
            data: $input,
            headers: $headers ?? $defaultHeaders
        );
    }

    private function createPaymentMethod(Account $account, array $attributes = []): PaymentMethod
    {
        return match($attributes['payment_type_id']) {
            PaymentTypeEnum::CC->value => PaymentMethod::factory()->for($account)->cc()->create(attributes: $attributes),
            PaymentTypeEnum::ACH->value => PaymentMethod::factory()->for($account)->ach()->create(attributes: $attributes),
            default => PaymentMethod::factory()->for($account)->create(attributes: $attributes)
        };
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
