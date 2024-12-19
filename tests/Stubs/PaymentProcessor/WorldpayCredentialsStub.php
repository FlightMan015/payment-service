<?php

declare(strict_types=1);

namespace Tests\Stubs\PaymentProcessor;

use Aptive\Worldpay\CredentialsRepository\Credentials\Credentials;

class WorldpayCredentialsStub
{
    /**
     * @return Credentials
     */
    public static function make(): Credentials
    {
        return new Credentials(
            validationAccountId: '123',
            validationAccountToken: 'abc',
            validationMerchantNumber: '12345',
            validationTerminalId: '01',
            tokenizationAccountId: '12345',
            tokenizationAccountToken: 'token',
            tokenizationAcceptorId: '12353445',
            tokenizationTerminalId: '01'
        );
    }
}
