<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use App\Api\Repositories\CRM\AreaRepository;
use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;
use Tests\Helpers\Traits\RepositoryMockingTrait;

class PostTokenexUpdateAccountsTest extends AbstractContractTest
{
    use RepositoryMockingTrait;

    private const string ENDPOINT_URI = '/api/v1/gateways/tokenex/update-accounts';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(disk: 's3'); // we do not want to store anything to s3 here
    }

    #[Test]
    public function it_returns_202_accepted_response(): void
    {
        Storage::fake(disk: 's3'); // we do not want to store anything to s3 here
        $this->mockAreasRetrieving(); // we don't want to actually get areas from Database

        $response = $this->makeRequest();

        $response->assertStatus(status: HttpStatus::ACCEPTED);
        $this->assertSuccessResponseStructure($response);
    }

    #[Test]
    public function it_returns_401_unauthorized_response(): void
    {
        $response = $this->makeRequest(headers: ['Api-Key' => 'incorrect key']);

        $response->assertStatus(status: HttpStatus::UNAUTHORIZED);
        $this->assertErrorResponseStructure($response);
    }

    private function makeRequest(array|null $headers = null): TestResponse
    {
        $defaultHeaders = ['Api-Key' => config('auth.api_keys.payment_processing')];

        return $this->post(uri: url(path: self::ENDPOINT_URI), headers: $headers ?? $defaultHeaders);
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

    private function mockAreasRetrieving(): void
    {
        $this->repositoryWillReturn(repositoryClass: AreaRepository::class, method: 'retrieveAllNames', value: []);
    }
}
