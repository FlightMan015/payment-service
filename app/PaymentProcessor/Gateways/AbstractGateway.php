<?php

declare(strict_types=1);

namespace App\PaymentProcessor\Gateways;

use App\Helpers\JsonDecoder;
use App\PaymentProcessor\Enums\Database\PaymentStatusEnum;
use App\PaymentProcessor\Enums\PaymentTypeEnum;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractGateway
{
    use LoggerAwareTrait;

    public const int HTTP_200 = 200;
    protected string|null $request = null;
    private string|null $response = null;
    private string|null $errorMessage = null;
    private int $httpCode;
    private PaymentTypeEnum|null $paymentType = null;
    private PaymentStatusEnum|null $paymentStatus = null;

    /**
     * @return string|null
     */
    public function getResponse(): string|null
    {
        return $this->response;
    }

    /**
     * @return string|null
     */
    public function getRequest(): string|null
    {
        return $this->request;
    }

    /**
     * @param string $response
     *
     * @throws \JsonException
     *
     * @return AbstractGateway
     */
    public function addResponse(string $response): AbstractGateway
    {
        $currentResponse = JsonDecoder::decode(json: $this->response);
        $newResponse = [$currentResponse, $response];

        $this->response = JsonDecoder::encode(value: $newResponse);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getErrorMessage(): string|null
    {
        return $this->errorMessage;
    }

    /**
     * @param string $errorMessage
     *
     * @return AbstractGateway
     */
    public function setErrorMessage(string $errorMessage): AbstractGateway
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @param int $httpCode
     *
     * @return AbstractGateway
     */
    public function setHttpCode(int $httpCode): AbstractGateway
    {
        $this->httpCode = $httpCode;

        return $this;
    }

    /**
     * @return PaymentTypeEnum|null
     */
    public function getPaymentType(): PaymentTypeEnum|null
    {
        return $this->paymentType;
    }

    /**
     * @param PaymentTypeEnum $paymentType
     *
     * @return static
     */
    public function setPaymentType(PaymentTypeEnum $paymentType): static
    {
        $this->paymentType = $paymentType;

        return $this;
    }

    /**
     * @param PaymentStatusEnum $paymentStatus
     *
     * @return static
     */
    public function setPaymentStatus(PaymentStatusEnum $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    /**
     * @return PaymentStatusEnum|null
     */
    public function getPaymentStatus(): PaymentStatusEnum|null
    {
        return $this->paymentStatus;
    }
}
