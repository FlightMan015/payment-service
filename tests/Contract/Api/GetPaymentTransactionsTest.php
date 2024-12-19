<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\Payment;
use App\Models\Transaction;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class GetPaymentTransactionsTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/payments/%s/transactions';

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTransactionsInDatabase();
    }

    private function createTransactionsInDatabase(int $count = 10): void
    {
        $this->payment = Payment::factory()->create();

        Transaction::factory()->for($this->payment)->count(count: $count)->create();
    }

    #[Test]
    #[DataProvider('validInputProvider')]
    public function it_returns_200_success_response_with_expected_structure(int|null $customPaymentId, array $input, array $expected): void
    {
        $response = $this->makeRequest(paymentId: $customPaymentId ?? $this->payment->id, data: $input);

        $response->assertStatus(status: HttpStatus::OK);

        $response->assertJsonStructure([
            '_metadata' => [
                'success',
                'links' => ['self'],
            ],
            'result' => [
                '*' => [
                    'id' =>
                    'payment_id',
                    'transaction_type' => ['id', 'name', 'description'],
                    'gateway_transaction_id',
                    'gateway_response_code',
                    'created_at',
                ],
            ],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', true);
        $response->assertJsonPath('_metadata.current_page', $expected['current_page']);
        $response->assertJsonPath('_metadata.per_page', $expected['per_page']);
        $response->assertJsonPath('_metadata.total_pages', $expected['total_pages']);
        $response->assertJsonPath('_metadata.total_results', $expected['total_results']);
        $this->assertCount($expected['count_current_result'], $response->json()['result']);

    }

    public static function validInputProvider(): \Iterator
    {
        yield 'empty input' => [
            'customPaymentId' => null,
            'input' => [],
            'expected' => [
                'current_page' => 1,
                'per_page' => 100,
                'total_pages' => 1,
                'total_results' => 10,
                'count_current_result' => 10,
            ],
        ];
        yield 'empty page' => [
            'customPaymentId' => null,
            'input' => [
                'per_page' => 2,
            ],
            'expected' => [
                'current_page' => 1,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 2,
            ],
        ];
        yield 'has page' => [
            'customPaymentId' => null,
            'input' => [
                'per_page' => 2,
                'page' => 4,
            ],
            'expected' => [
                'current_page' => 4,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 2,
            ],
        ];
        yield 'has page, no data' => [
            'customPaymentId' => null,
            'input' => [
                'per_page' => 2,
                'page' => 6,
            ],
            'expected' => [
                'current_page' => 6,
                'per_page' => 2,
                'total_pages' => 5,
                'total_results' => 10,
                'count_current_result' => 0,
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidInputProvider')]
    public function it_returns_400_bad_request_response_for_invalid_input(mixed $customPaymentId, array $input): void
    {
        $response = $this->makeRequest(paymentId: $customPaymentId ?? $this->payment->id, data: $input);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath(
            'result.message',
            $customPaymentId
            ? __('validation.parameter_invalid_uuid', ['parameter' => 'payment id'])
            : __('messages.invalid_input')
        );
    }

    public static function invalidInputProvider(): \Iterator
    {
        yield 'invalid page' => ['customPaymentId' => null, 'input' => ['page' => 'asd']];
        yield 'invalid per page' => ['customPaymentId' => null, 'input' => ['per_page' => 'asd']];
        yield 'invalid payment id' => ['customPaymentId' => 'aaa', 'input' => []];
    }

    #[Test]
    public function it_returns_404_not_found_response_for_non_existing_payment_id(): void
    {
        $nonExistingPaymentId = Str::uuid()->toString();
        $response = $this->makeRequest(paymentId: $nonExistingPaymentId);

        $response->assertStatus(status: HttpStatus::NOT_FOUND);

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'],
        ], $response->json());

        $response->assertValid();
        $response->assertJsonPath('_metadata.success', false);
        $response->assertJsonPath('result.message', __('messages.payment.not_found', ['id' => $nonExistingPaymentId]));
    }

    private function makeRequest(mixed $paymentId, array $data = []): TestResponse
    {
        return $this->get(
            uri: url(path: sprintf(self::ENDPOINT_URI, $paymentId)) . '?' . http_build_query($data),
            headers: ['Api-Key' => config('auth.api_keys.payment_processing')]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->payment);
    }
}
