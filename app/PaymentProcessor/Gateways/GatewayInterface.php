<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Gateways;

use App\Models\PaymentMethod;
use App\PaymentProcessor\Enums\Database\DeclineReasonEnum;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;

interface GatewayInterface
{
    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function authCapture(array $inputData): bool;

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function authorize(array $inputData): bool;

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function capture(array $inputData): bool;

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function cancel(array $inputData): bool;

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function status(array $inputData): bool;

    /**
     * @param array $inputData
     *
     * @return bool
     */
    public function credit(array $inputData): bool;

    /**
     * @param int|string|null $paymentAccountId
     * @param string|null $paymentAccountReferenceNumber
     *
     * @return object|null
     */
    public function getPaymentAccount(
        int|string|null $paymentAccountId = null,
        string|null $paymentAccountReferenceNumber = null
    ): object|null;

    /**
     * @param string $paymentAccountId
     * @param PaymentMethod $paymentMethod
     *
     * @return bool
     */
    public function updatePaymentAccount(string $paymentAccountId, PaymentMethod $paymentMethod): bool;

    /**
     * @param int|string $referenceId
     * @param string $callbackUrl
     * @param string|null $name
     * @param string|null $address1
     * @param string|null $address2
     * @param string|null $city
     * @param string|null $province
     * @param string|null $postalCode
     * @param string|null $email
     * @param string|null $phone
     *
     * @return array|null
     */
    public function createTransactionSetup(
        int|string $referenceId,
        string $callbackUrl,
        string|null $name,
        string|null $address1,
        string|null $address2,
        string|null $city,
        string|null $province,
        string|null $postalCode,
        string|null $email,
        string|null $phone
    ): array|null;

    /**
     * @param int|string $transactionSetupId
     *
     * @return string|null
     */
    public function generateTransactionSetupUrl(int|string $transactionSetupId): string|null;

    /**
     * @return string|null
     */
    public function getTransactionId(): string|null;

    /**
     * @return string
     */
    public function getTransactionStatus(): string;

    /**
     * @return DeclineReasonEnum|null
     */
    public function getDeclineReason(): DeclineReasonEnum|null;

    /**
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * @return string|null
     */
    public function getResponse(): string|null;

    /**
     * @return string|null
     */
    public function getRequest(): string|null;

    /**
     * @return string|null
     */
    public function getErrorMessage(): string|null;

    /**
     * @param PaymentTypeEnum $paymentType
     *
     * @return static
     */
    public function setPaymentType(PaymentTypeEnum $paymentType): static;

    /**
     * @return PaymentStatusEnum|null
     */
    public function getPaymentStatus(): PaymentStatusEnum|null;
}
