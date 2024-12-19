<?php

declare(strict_types=1);

namespace App\Infrastructure\CRM;

use App\Entities\Subscription;
use App\Infrastructure\Interfaces\SubscriptionServiceInterface;
use GuzzleHttp\Exception\GuzzleException;

class CrmSubscriptionService extends AbstractCrmService implements SubscriptionServiceInterface
{
    /**
     * @param string|int $id
     *
     * @throws GuzzleException
     * @throws \JsonException
     * @throws \Exception
     *
     * @return Subscription
     */
    public function getSubscription(int|string $id): Subscription
    {
        $response = $this->sendRequest(uri: sprintf(config(key: 'crm.endpoints.get_subscription'), $id), method: 'GET');

        return Subscription::fromObject($response);
    }
}
