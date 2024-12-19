<?php

declare(strict_types=1);

namespace App\Infrastructure\CRM;

use App\Helpers\JsonDecoder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class AbstractCrmService
{
    /**
     * @param Client $client
     */
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array $data
     *
     * @throws GuzzleException
     * @throws \JsonException
     *
     * @return object
     */
    protected function sendRequest(string $uri, string $method, array $data = []): object
    {
        Log::info(message: 'Sending request to CRM', context: ['uri' => $uri, 'method' => $method, 'data' => $data]);

        $response = match ($method) {
            'GET' => $this->sendGetRequest($uri),
            'POST' => $this->sendPostRequest($uri, $data),
            default => throw new \InvalidArgumentException(sprintf('Given method %s is not supported', $method)),
        };

        $response = $response->getBody()->getContents();

        Log::info(message: 'Received response from CRM', context: ['response' => $response]);

        return JsonDecoder::decode(json: $response, toArray: false);
    }

    /**
     * @param string $uri
     * @param array $params
     *
     * @throws GuzzleException
     *
     * @return object
     */
    private function sendGetRequest(string $uri, array $params = []): object
    {
        return $this->client->get(uri: config('crm.base_url') . $uri, options: [
            'query' => $params,
            'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken(), 'Accept' => 'application/json']
        ]);
    }

    /**
     * @param string $uri
     * @param array $data
     *
     * @throws GuzzleException
     *
     * @return object
     */
    private function sendPostRequest(string $uri, array $data = []): object
    {
        return $this->client->post(uri: $uri, options: [
            'json' => $data,
            'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken(), 'Accept' => 'application/json']
        ]);
    }

    private function getAccessToken(): string
    {
        return Cache::remember(key: 'payment-service:crm_access_token', ttl: 3600, callback: function (): string {
            $response = $this->client->post(uri: config('crm.auth_url'), options: [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'scope' => sprintf('target-entity:%s:api_access', config('crm.auth_target_entity')),
                    'client_id' => config('crm.client_id'),
                    'client_secret' => config('crm.client_secret'),
                ]
            ]);

            /** @var object $parsedResponse */
            $parsedResponse = JsonDecoder::decode(json: $response->getBody()->getContents(), toArray: false);

            return $parsedResponse->access_token;
        });
    }
}
