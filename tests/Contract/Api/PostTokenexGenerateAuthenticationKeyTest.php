<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Contract\Api;

use Aptive\Component\Http\HttpStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\AbstractContractTest;

class PostTokenexGenerateAuthenticationKeyTest extends AbstractContractTest
{
    private const string ENDPOINT_URI = '/api/v1/gateways/tokenex/authentication-keys';

    #[Test]
    public function it_returns_200_response_when_input_valid(): void
    {
        $response = $this->makeRequest(input: [
            'token_scheme' => 'ASCII',
            'origins' => ['https://sample.com'],
            'timestamp' => '20180109161437'
        ]);

        $response->assertStatus(status: HttpStatus::OK);
        $this->assertSuccessResponseStructure($response);
    }

    #[Test]
    public function it_returns_expected_400_response_structure(): void
    {
        $response = $this->makeRequest(input: ['token_scheme' => 'asdasd', 'origins' => [], 'timestamp' => '20180109161437']);

        $response->assertStatus(status: HttpStatus::BAD_REQUEST);
        $this->assertErrorResponseStructure($response);
    }

    #[Test]
    public function it_returns_expected_500_response_structure(): void
    {
        $response = $this->makeRequest(
            input: [
                'token_scheme' => 'ASCII',
                'origins' => ['https://sample.com'],
                'timestamp' => '20180109161437'
            ],
            setConfig: false
        );

        $response->assertStatus(status: HttpStatus::INTERNAL_SERVER_ERROR);
        $this->assertErrorResponseStructure($response);
    }

    private function makeRequest(array $input = [], array|null $headers = null, bool $setConfig = true): TestResponse
    {
        if ($setConfig) {
            Config::set('tokenex.iframe_tokenex_id', 1123);
            Config::set('tokenex.iframe_client_secret_key', 'ASDASd13123');
        } else {
            Config::set('tokenex.iframe_tokenex_id', null);
            Config::set('tokenex.iframe_client_secret_key', null);
        }
        $defaultHeaders = ['Api-Key' => config('auth.api_keys.payment_processing')];

        return $this->post(
            uri: url(path: self::ENDPOINT_URI),
            data: $input,
            headers: $headers ?? $defaultHeaders
        );
    }

    private function assertSuccessResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success', 'links' => ['self']],
            'result' => ['message', 'authentication_key'],
        ], $response->json());
    }

    private function assertErrorResponseStructure(TestResponse $response): void
    {
        $response->assertValid();

        $response->assertJsonStructure([
            '_metadata' => ['success'],
            'result' => ['message'], // TODO: Split this to test 500 and 400 response structures separatly
        ], $response->json());
    }
}
