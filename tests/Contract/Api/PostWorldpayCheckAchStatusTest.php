<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Models\CRM\FieldOperations\Area;
use Aptive\Component\Http\HttpStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostWorldpayCheckAchStatusTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/gateways/worldpay/check-ach-status';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function it_returns_202_accepted_response(): void
    {
        $response = $this->makeRequest();

        $response->assertStatus(status: HttpStatus::ACCEPTED);
        $this->assertSuccessResponseStructure($response);
    }

    #[Test]
    public function it_returns_400_bad_request(): void
    {
        $response = $this->makeRequest(data: []);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure($response);
    }

    #[Test]
    public function it_returns_401_unauthorized_response(): void
    {
        $response = $this->makeRequest(headers: ['Api-Key' => 'incorrect key']);

        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure($response);
    }

    private function makeRequest(array|null $headers = null, array|null $data = null): TestResponse
    {
        $defaultHeaders = ['Api-Key' => config('auth.api_keys.payment_processing')];

        if (is_null($data)) {
            $data = [
                'processed_at_from' => Carbon::now()->subMonth()->format('Y-m-d H:i:s'),
                'processed_at_to' => Carbon::now()->format('Y-m-d H:i:s'),
                'area_ids' => [Area::factory()->create(attributes: ['is_active' => true])->id],
            ];
        }

        return $this->post(
            uri: url(path: self::ENDPOINT_URI),
            headers: $headers ?? $defaultHeaders,
            data: $data,
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

        $response->assertJsonStructure(['_metadata' => ['success'], 'result' => ['message']], $response->json());
    }
}
