<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\CRM\Customer\Account;
use App\Models\PaymentMethod;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetAccountPrimaryPaymentMethodTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/accounts/%s/primary-payment-method';

    #[Test]
    public function it_returns_200_response_for_retrieving_account_primary_payment_method_successfully_for_ach(): void
    {
        $accountId = Str::uuid()->toString();
        $paymentMethod = $this->createPaymentMethodInDatabase(accountId: $accountId, isPrimary: true, isAch: true);
        $response = $this->sendRequest(accountId: $accountId);
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
        $response->assertJsonPath(path: '_metadata.success', expect: true);
        $response->assertJsonPath(path: 'result.payment_method_id', expect: $paymentMethod->id);
    }

    #[Test]
    public function it_returns_200_response_for_retrieving_account_primary_payment_method_successfully_for_non_ach(): void
    {
        $accountId = Str::uuid()->toString();
        $paymentMethod = $this->createPaymentMethodInDatabase(accountId: $accountId, isPrimary: true, isAch: false);

        $response = $this->sendRequest(accountId: $accountId);

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
                'cc_expiration_year',
            ],
        ], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: true);
        $response->assertJsonPath(path: 'result.payment_method_id', expect: $paymentMethod->id);
    }

    #[Test]
    public function it_returns_400_bad_request_for_invalid_account_id(): void
    {
        $response = $this->sendRequest(accountId: 'test-id');

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
        $response->assertJsonPath(
            path: 'result.message',
            expect: __('validation.parameter_invalid_uuid', ['parameter' => 'account id'])
        );
    }

    #[Test]
    public function it_return_401_response_for_non_api_key_api(): void
    {
        $response = $this->sendRequest(accountId: Str::uuid()->toString(), headers: ['Api-Key' => 'invalid']);
        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
        $response->assertJsonPath(path: 'result.message', expect: __('auth.invalid_api_key'));
    }

    #[Test]
    public function it_returns_404_not_found_response_when_primary_payment_method_is_not_found(): void
    {
        $account = Account::factory()->create(); // create account
        // do not create payment method
        $response = $this->sendRequest(accountId: $account->id);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
        $response->assertJsonPath(path: 'result.message', expect: __('messages.account.primary_payment_method_not_found'));
    }

    #[Test]
    public function it_returns_422_unprocessable_entity_response_when_account_is_not_found_in_database(): void
    {
        // do not create account
        $response = $this->sendRequest(accountId: Str::uuid()->toString());

        $response->assertStatus(status: HttpStatus::UNPROCESSABLE_ENTITY);

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
        $response->assertValid();
        $response->assertJsonPath(path: '_metadata.success', expect: false);
        $response->assertJsonPath(path: 'result.message', expect: __('messages.account.not_found'));
    }

    private function createPaymentMethodInDatabase(string $accountId, bool $isPrimary, bool $isAch): PaymentMethod
    {
        $account = Account::factory()->create(attributes: ['id' => $accountId]);

        return $isAch
            ? PaymentMethod::factory()->for($account)->ach()->create(['is_primary' => $isPrimary])
            : PaymentMethod::factory()->for($account)->cc()->create(['is_primary' => $isPrimary]);
    }

    private function sendRequest(mixed $accountId, array $headers = []): TestResponse
    {
        return $this->get(
            uri: sprintf(self::ENDPOINT_URI, $accountId),
            headers: array_merge(['Api-Key' => config(key: 'auth.api_keys.payment_processing')], $headers)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->paymentMethod);
    }
}
