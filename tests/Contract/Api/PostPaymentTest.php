<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Repositories\DatabasePaymentRepository;
use App\Models\CRM\Customer\Account;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostPaymentTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments';

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_returns_201_created_response_with_valid_request_for_payment_of_type_check(): void
    {
        $response = $this->makeRequest(data: [
            'account_id' => Account::factory()->create()->id,
            'amount' => 100,
            'type' => PaymentTypeEnum::CHECK->name,
            'check_date' => '2021-01-01',
            'notes' => 'some notes',
        ]);

        $response->assertStatus(status: HttpStatus::CREATED);
        $this->assertSuccessResponseStructure($response);
    }

    #[Test]
    public function it_returns_400_bad_request_if_check_date_is_missing_for_payment_of_type_check(): void
    {
        $response = $this->makeRequest(data: [
            'account_id' => Account::factory()->create()->id,
            'amount' => 100,
            'type' => PaymentTypeEnum::CHECK->name,
            'notes' => 'some notes',
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
        $repository = $this->getMockBuilder(className: DatabasePaymentRepository::class)->getMock();
        $repository->method('create')->willThrowException(exception: new ConnectionException(message: 'Connection issues'));
        $this->app->instance(abstract: DatabasePaymentRepository::class, instance: $repository);

        $response = $this->makeRequest(data: [
            'account_id' => Account::factory()->create()->id,
            'amount' => 100,
            'type' => PaymentTypeEnum::CHECK->name,
            'check_date' => '2021-01-01',
        ]);

        $response->assertStatus(status: HttpStatus::INTERNAL_SERVER_ERROR);
        $this->assertErrorResponseStructure(response: $response, hasError: false);
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => [
                'message',
                'payment_id',
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
            'Origin' => 'some_service_name',
        ];

        return $this->post(
            uri: url(path: self::ENDPOINT_URI),
            data: $data,
            headers: $headers ?? $defaultHeaders
        );
    }
}
