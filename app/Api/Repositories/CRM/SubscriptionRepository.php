<?php

declare(strict_types=1);

namespace App\Api\Repositories\CRM;

use App\Api\Exceptions\PaymentMethodDoesNotBelongToAccountException;
use App\Models\CRM\Customer\Subscription;
use App\Models\PaymentMethod;

class SubscriptionRepository
{
    /**
     * @param string $id
     *
     * @return Subscription|null
     */
    public function find(string $id): Subscription|null
    {
        return Subscription::whereIsActive(true)->find($id);
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function exists(string $id): bool
    {
        return Subscription::whereIsActive(true)->whereId($id)->exists();
    }

    /**
     * @param Subscription $subscription
     * @param PaymentMethod|null $autopayPaymentMethod
     *
     * @throws PaymentMethodDoesNotBelongToAccountException
     *
     * @return void
     */
    public function setAutoPayPaymentMethod(Subscription $subscription, PaymentMethod|null $autopayPaymentMethod): void
    {
        $subscription->setAutoPayPaymentMethod(autopayPaymentMethod: $autopayPaymentMethod);
    }
}
