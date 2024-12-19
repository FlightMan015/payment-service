<?php

declare(strict_types=1);

namespace App\Infrastructure\Interfaces;

use App\Entities\Subscription;

interface SubscriptionServiceInterface
{
    /**
     * @param int|string $id
     *
     * @return Subscription
     */
    public function getSubscription(int|string $id): Subscription;
}
