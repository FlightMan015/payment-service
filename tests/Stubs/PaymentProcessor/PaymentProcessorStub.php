<?php

declare(strict_types=1);

namespace Tests\Stubs\PaymentProcessor;

use App\PaymentProcessor\Enums\PaymentTypeEnum;
use App\PaymentProcessor\Gateways\GatewayInterface;
use App\PaymentProcessor\PaymentProcessor;
use Money\Currency;
use Money\Money;
use Psr\Log\LoggerInterface;

class PaymentProcessorStub
{
    public static function make(
        LoggerInterface|null $logger,
        GatewayInterface|null $gateway = null
    ): PaymentProcessor {
        $paymentProcessor = new PaymentProcessor(
            referenceId: '186c9b34-f728-499f-a710-f3e1f1b1b430',
            paymentType: PaymentTypeEnum::CC,
            token: 'Test',
            ccExpYear: 2023,
            ccExpMonth: 5,
            nameOnAccount: 'Test',
            addressLine1: 'Test',
            addressLine2: 'Test',
            city: 'Test',
            province: 'UT',
            postalCode: '08500',
            countryCode: 'Test',
            emailAddress: 'email@goaptive.com',
            amount: new Money(amount:1000, currency:new Currency(code:'USD')),
            referenceTransactionId: 'Test',
            chargeDescription: 'Initial Charge',
            logger: $logger
        );

        if ($gateway) {
            $paymentProcessor->setGateway(gateway: $gateway);
        }

        return $paymentProcessor;
    }
}
