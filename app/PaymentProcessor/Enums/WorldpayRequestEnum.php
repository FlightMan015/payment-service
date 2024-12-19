<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Enums;

enum WorldpayRequestEnum: string
{
    /**
     * AKA AuthCapture
     */
    case CREDIT_CARD_SALE = 'CreditCardSale';
    /**
     * AKA authorization
     */
    case CREDIT_CARD_AUTHORIZATION = 'CreditCardAuthorization';
    /**
     * AKA capture
     */
    case CREDIT_CARD_AUTHORIZATION_COMPLETION = 'CreditCardAuthorizationCompletion';
    /**
     * AKA credit
     */
    case CREDIT_CARD_RETURN = 'CreditCardReturn';
    /**
     * AKA void
     */
    case CREDIT_CARD_REVERSAL = 'CreditCardReversal';
    case TRANSACTION_QUERY = 'TransactionQuery';
    case CHECK_VERIFICATION = 'CheckVerification';
    case CHECK_SALE = 'CheckSale';
    case CHECK_RETURN = 'CheckReturn';
    case CHECK_VOID = 'CheckVoid';
    case CHECK_QUERY = 'CheckQuery';
    case PAYMENT_ACCOUNT_QUERY = 'PaymentAccountQuery';
    case PAYMENT_ACCOUNT_UPDATE = 'PaymentAccountUpdate';
    case TRANSACTION_SETUP = 'TransactionSetup';
    case TRANSACTION_HOSTED_PAYMENTS = 'TransactionHostedPayments';
    private const string TRANSACTION_ENDPOINT = 'https://transaction.elementexpress.com';
    private const string REPORTING_ENDPOINT = 'https://reporting.elementexpress.com';
    private const string SERVICES_ENDPOINT = 'https://services.elementexpress.com';
    private const string TRANSACTION_HOSTED_PAYMENTS_ENDPOINT = 'https://transaction.hostedpayments.com';
    private const string CERT_TRANSACTION_ENDPOINT = 'https://certtransaction.elementexpress.com';
    private const string CERT_REPORTING_ENDPOINT = 'https://certreporting.elementexpress.com';
    private const string CERT_SERVICES_ENDPOINT = 'https://certservices.elementexpress.com';
    private const string CERT_TRANSACTION_HOSTED_PAYMENTS_ENDPOINT = 'https://certtransaction.hostedpayments.com';

    /**
     * @param bool $isProduction
     *
     * @return string
     */
    public function endpoint(bool $isProduction = false): string
    {
        return self::getEndpoint($this, $isProduction);
    }

    /**
     * @return string
     */
    public function targetNamespace(): string
    {
        return self::getTargetNamespace($this);
    }

    /**
     * @param WorldpayRequestEnum $value
     * @param bool $isProduction
     *
     * @return string
     */
    public static function getEndpoint(self $value, bool $isProduction = false): string
    {
        return match ($value) {
            self::TRANSACTION_SETUP,
            self::CREDIT_CARD_SALE,
            self::CREDIT_CARD_AUTHORIZATION,
            self::CREDIT_CARD_AUTHORIZATION_COMPLETION,
            self::CREDIT_CARD_RETURN,
            self::CREDIT_CARD_REVERSAL,
            self::CHECK_VERIFICATION,
            self::CHECK_SALE,
            self::CHECK_RETURN,
            self::CHECK_VOID => $isProduction ? self::TRANSACTION_ENDPOINT : self::CERT_TRANSACTION_ENDPOINT,
            self::TRANSACTION_QUERY,
            self::CHECK_QUERY => $isProduction ? self::REPORTING_ENDPOINT : self::CERT_REPORTING_ENDPOINT,
            self::PAYMENT_ACCOUNT_QUERY,
            self::PAYMENT_ACCOUNT_UPDATE => $isProduction ? self::SERVICES_ENDPOINT : self::CERT_SERVICES_ENDPOINT,
            self::TRANSACTION_HOSTED_PAYMENTS => $isProduction ?
                self::TRANSACTION_HOSTED_PAYMENTS_ENDPOINT :
                self::CERT_TRANSACTION_HOSTED_PAYMENTS_ENDPOINT,
        };
    }

    /**
     * @param WorldpayRequestEnum $value
     *
     * @return string
     */
    public static function getTargetNamespace(self $value): string
    {
        return match ($value) {
            self::TRANSACTION_SETUP,
            self::CREDIT_CARD_SALE,
            self::CREDIT_CARD_AUTHORIZATION,
            self::CREDIT_CARD_AUTHORIZATION_COMPLETION,
            self::CREDIT_CARD_RETURN,
            self::CREDIT_CARD_REVERSAL,
            self::CHECK_VERIFICATION,
            self::CHECK_SALE,
            self::CHECK_RETURN,
            self::CHECK_VOID => self::TRANSACTION_ENDPOINT,
            self::TRANSACTION_QUERY,
            self::CHECK_QUERY => self::REPORTING_ENDPOINT,
            self::PAYMENT_ACCOUNT_QUERY,
            self::PAYMENT_ACCOUNT_UPDATE => self::SERVICES_ENDPOINT,
            self::TRANSACTION_HOSTED_PAYMENTS => self::TRANSACTION_HOSTED_PAYMENTS_ENDPOINT,
        };
    }
}
