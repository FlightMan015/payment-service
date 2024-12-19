<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Gateways;

use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;

class NullGateway extends AbstractGateway implements GatewayInterface
{
    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function authCapture(array $inputData): bool
    {
        return false;
    }

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function authorize(array $inputData): bool
    {
        return false;
    }

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function cancel(array $inputData): bool
    {
        return false;
    }

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function status(array $inputData): bool
    {
        return false;
    }

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function credit(array $inputData): bool
    {
        return false;
    }

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function capture(array $inputData): bool
    {
        return false;
    }

    /**
     * @return \stdClass
     */
    public function parseResponse(): object
    {
        return new \stdClass();
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getTransactionStatus(): string
    {
        return '';
    }

    /**
     * @return DeclineReasonEnum|null
     */
    public function getDeclineReason(): DeclineReasonEnum|null
    {
        return null;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return false;
    }

    /**
     * @param int|string|null $paymentAccountId
     * @param string|null $paymentAccountReferenceNumber
     *
     * @return object|null
     */
    public function getPaymentAccount(
        int|string|null $paymentAccountId = null,
        string|null $paymentAccountReferenceNumber = null
    ): object|null {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function updatePaymentAccount(
        string $paymentAccountId,
        PaymentMethod $paymentMethod
    ): bool {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function createTransactionSetup(
        int|string $referenceId,
        string $callbackUrl,
        string|null $name = null,
        string|null $address1 = null,
        string|null $address2 = null,
        string|null $city = null,
        string|null $province = null,
        string|null $postalCode = null,
        string|null $email = null,
        string|null $phone = null
    ): array|null {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function generateTransactionSetupUrl(int|string $transactionSetupId): string|null
    {
        return null;
    }
}
