<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Api\Exceptions\UnsupportedValueException;
use App\Factories\PaymentGatewayFactory;
use App\PaymentProcessor\Gateways\GatewayInterface;
use Illuminate\Contracts\Container\BindingResolutionException;

trait RetrieveGatewayForPaymentMethodTrait
{
    private GatewayInterface|null $gateway = null;

    /**
     * @throws UnsupportedValueException
     * @throws BindingResolutionException
     */
    private function getGatewayInstanceBasedOnPaymentMethod(): void
    {
        $this->gateway = PaymentGatewayFactory::makeForPaymentMethod(paymentMethod: $this->paymentMethod);
    }
}
